<?php
/**
 * @brief		Bulkmail central library
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		25 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\BulkMail;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Bulk mail central library
 */
class _Bulkmailer extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_bulk_mail';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'mail_';

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	The number of bulk mails to send per cycle.  You can override this by defining a constant BULK_MAILS_PER_CYCLE in constants.php, but setting too high may cause timeouts.
	 */
	public $perCycle	= 50;

	/**
	 * Constructor - Create a blank object with default values
	 *
	 * @return	void
	 */
	public function __construct()
	{
		/* Do we want to send a different number of bulk emails per cycle? */
		if( defined( '\IPS\BULK_MAILS_PER_CYCLE' ) AND (int) \IPS\BULK_MAILS_PER_CYCLE > 0 )
		{
			$this->perCycle	= (int) \IPS\BULK_MAILS_PER_CYCLE;
		}

		return parent::__construct();
	}

	/**
	 * Enable or disable the bulk mail task
	 *
	 * @param	int		$force	0 or 1 to specify the task state or NULL to determine automatically
	 * @return	void
	 */
	public static function updateTask( $force=NULL )
	{
		/* Using Mandrill? */
		if( ( \IPS\Settings::i()->mandrill_api_key && \IPS\Settings::i()->mandrill_use_for ) AND $force === NULL )
		{
			$force	= 0;
		}

		/* Are we forcing the task enabled or disabled? */
		if( $force !== NULL )
		{
			\IPS\DB::i()->update( 'core_tasks', array( 'enabled' => (int) $force ), "`key`='bulkmail'" );
			return;
		}

		/* Figure out if we have any bulk mails to send and update task appropriately */
		if( \IPS\DB::i()->select( 'count(*)', 'core_bulk_mail', 'mail_active=1' )->first() > 0 )
		{
			/* Enable the task */
			\IPS\DB::i()->update( 'core_tasks', array( 'enabled' => 1 ), "`key`='bulkmail'" );
		}
		else
		{
			/* Disable the task */
			\IPS\DB::i()->update( 'core_tasks', array( 'enabled' => 0 ), "`key`='bulkmail'" );
		}
	}

	/**
	 * Get the mail options
	 *
	 * @return	array
	 */
	public function get__options()
	{
		if ( !isset( $this->_data['opts'] ) )
		{
			return array();
		}
		
		return json_decode( $this->_data['opts'], TRUE );
	}

	/**
	 * Set the mail options
	 *
	 * @param	array	$value	Mail options
	 * @return	vpid
	 */
	public function set__options( $value )
	{
		$this->opts	= json_encode( $value );
	}

	/**
	 * Send the bulk mail.  Determines if bulk mail should be sent via Mandrill or internally and dispatches appropriately.
	 *
	 * @return	NULL|int
	 * @throws	\Exception
	 */
	public function send()
	{
		/* Mandrill can handle 2000 users in one cycle */
		if( \IPS\Settings::i()->mandrill_api_key AND \IPS\Settings::i()->mandrill_use_for )
		{
			$limit	= array( $this->_data['sentto'], 2000 );
		}
		else
		{
			$limit	= array( $this->_data['sentto'], $this->perCycle );
		}

		/* Get recipients */
		$results	= $this->getQuery( $limit );

		/* If there are no recipients we must be done */
		if( !count( $results ) )
		{
			self::updateTask( 0 );

			$this->active	= 0;
			$this->save();

			return NULL;
		}

		/* Dispatch */
		if( \IPS\Settings::i()->mandrill_api_key AND \IPS\Settings::i()->mandrill_use_for )
		{
			$sent	= $this->_sendMandrill( $results );
		}
		else
		{
			$sent	= $this->_sendInternal( $results );
		}

		/* Update bulk mail record */
		if( $sent === 0 )
		{
			$this->active	= 0;
			$this->updated	= time();
			$this->save();

			self::updateTask( 0 );
		}
		else
		{
			$this->updated	= time();
			$this->sentto	= ( $this->_data['sentto'] + $sent );
			$this->save();
		}

		/* Return the number of users the email was sent to */
		return $sent;
	}

	/**
	 * Send the bulk mail using our own internal mailer
	 *
	 * @param	\IPS\Db\Select 	$recipients	Result set for recipients for the bulk mail
	 * @return	int		Number of users the bulk mail was sent to
	 */
	protected function _sendInternal( $recipients=array() )
	{
		$sent	= 0;

		/* Loop over recipients and send the email */
		foreach( $recipients as $recipient )
		{
			$_recipient	= \IPS\Member::constructFromData( $recipient );
			$htmlBody	= $this->formatBody( $_recipient );

			$email	= \IPS\Email::buildFromContent( $this->_data['subject'], $htmlBody );
			$email->headers['Precedence']	= 'bulk';
			$email->buildUnsubscribe( 'bulk', $_recipient );
			$email->send( $_recipient );

			$sent++;
		}

		return $sent;
	}

	/**
	 * Send the bulk mail using Mandrill
	 *
	 * @param	\IPS\Db\Select 	$recipients	Result set for recipients for the bulk mail
	 * @return	int				Number of users the bulk mail was sent to
	 * @throws	\RuntimeException
	 */
	protected function _sendMandrill( $recipients=array() )
	{
		$sent	= 0;

		/* Get the email we want to send in the HTML template - we intentionally do not swap out tags as Mandrill does this for us. */
		$email	= \IPS\Email::buildFromContent( $this->_data['subject'], $this->_data['content'] );
		$email->unsubscribe	= array( 'link' => \IPS\Http\Url::internal( '' ), 'text' => '*|unsubscribe|*' );
		$body	= $email->body['html'];

		/* Determine the variables to use */
		$usedVars	= array( 'unsubscribe' );

		foreach( array_keys( self::getTags() ) as $k )
		{
			if ( mb_strpos( $body, $k ) !== FALSE )
			{
				$usedVars[]	= $k;

				/* While we're not actually parsing the tags, we do need to put into Mandrill format */
				$body	= str_replace( $k, '*|' . str_replace( array( '{', '}' ), '', $k ) . '|*', $body );
			}
		}

		/* Loop over recipients and collect information */
		$recipientsTo		= array();
		$recipientsMerge	= array();

		foreach( $recipients as $recipient )
		{
			$_recipient	= \IPS\Member::constructFromData( $recipient );

			/* While registration and other similar routines should validate the email, we need to filter out any bad ones that may have crept through else it causes Mandrill to ignore the batch */
			if ( !$_recipient->email OR !$_recipient->name OR filter_var( $_recipient->email, FILTER_VALIDATE_EMAIL ) === FALSE )
			{
				continue;
			}

			/* Put our recipients into an array */
			$recipientsTo[] = array( 'email' => $_recipient->email, 'name' => $_recipient->name );

			/* Format variables */
			$vars	= array();

			foreach( $this->returnTagValues( 2, $_recipient ) as $k => $v )
			{
				if ( in_array( $k, $usedVars ) )
				{
					\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $v );
					$vars[]	= array( 'name' => str_replace( array( '{', '}' ), '', $k ), 'content' => $v );
				}
			}

			$email->buildUnsubscribe( 'bulk', $_recipient );
			$langKey	= $email->unsubscribe['text'];

			$vars[]	= array( 'name' => 'unsubscribe', 'content' => "<a href='" . $email->unsubscribe['link'] . "'>" . $_recipient->language()->get( $langKey ) . "</a>" );

			if ( !empty( $vars ) )
			{
				$recipientsMerge[] = array( 'rcpt' => $recipient['email'], 'vars' => $vars );
			}

			$sent++;
		}

		/* Sort out global vars */
		$globalMergeVars = array();

		foreach ( $this->returnTagValues( 1 ) as $k => $v )
		{
			if ( in_array( $k, $usedVars ) )
			{
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $v );

				$globalMergeVars[]	= array( 'name' => str_replace( array( '{', '}' ), '', $k ), 'content' => $v );
			}
		}

		/* Fix language strings */
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $body );
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $this->_data['subject'] );

		$response = \IPS\Email::mandrill( 'messages_send', \IPS\Settings::i()->mandrill_api_key, array(
			'message'	=> array(
				'html'					=> $body,
				'subject'				=> $this->_data['subject'],
				'from_email'			=> \IPS\Settings::i()->email_out,
				'from_name'				=> \IPS\Settings::i()->board_name,
				'to'					=> $recipientsTo,
				'auto_text'				=> TRUE,
				'url_strip_qs'			=> FALSE,
				'preserve_recipients'	=> FALSE,
				'merge'					=> TRUE,
				'global_merge_vars'		=> $globalMergeVars,
				'merge_vars'			=> $recipientsMerge,
				'tags'					=> array_merge( array( 'ips' ), array_filter( $this->_options['mandrill_tags'], function( $v ) { return (bool) $v; } ) )
				),
			'async'		=> TRUE
			) );

		if ( isset( $response->status ) and $response->status == 'error' )
		{
			throw new \UnexpectedValueException( \IPS\Member::loggedIn()->language()->addToStack('mandrill_error', FALSE, array( 'sprintf' => array( $response->message ) ) ) );
		}

		return $sent;
	}

	/**
	 * Retrieve the query to fetch members based on our filters
	 *
	 * @param	array	$limit	The limit to apply to the query
	 * @return	\IPS\Db\Select
	 * @note	Returns a default limit of $this->perCycle which should be overridden typically.  The default limit is simply to ensure we don't accidentally run a query against the members table without any limit if someone forgets.
	 */
	public function getQuery( $limit=array() )
	{
		/* Compile where */
		$where = array();
		$where[] = array( "core_members.allow_admin_mails=1" );
		$where[] = array( "core_members.temp_ban=0" );

		foreach ( \IPS\Application::allExtensions( 'core', 'BulkMail', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getQueryWhereClause' ) )
			{
				/* Grab our fields and add to the form */
				if( !empty( $this->_options[ $key ] ) )
				{
					if( $_where = $extension->getQueryWhereClause( $this->_options[ $key ] ) )
					{
						if ( is_string( $_where ) )
						{
							$_where = array( $_where );
						}
						
						$where	= array_merge( $where, $_where );
					}
				}
			}
		}
		
		/* Compile query */
		$query = \IPS\Db::i()->select( 'core_members.member_id AS my_member_id, core_members.*', 'core_members', $where, 'core_members.member_id', $limit, 'member_id', NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		
		/* Run callbacks */
		foreach ( \IPS\Application::allExtensions( 'core', 'BulkMail', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'queryCallback' ) )
			{
				/* Grab our fields and add to the form */
				if( !empty( $this->_options[ $key ] ) )
				{
					$extension->queryCallback( $this->_options[ $key ], $query );
				}
			}
		}
		
		return $query;
	}

	/**
	 * Format a bulk mail by replacing out the tags with the proper values
	 *
	 * @param	\IPS\Member 	$member	Member data
	 * @return	string
	 */
	public function formatBody( $member )
	{
		if( empty($this->_data['content']) )
		{
			return '';
		}

		/* Default tags */
		$tags	= $this->returnTagValues( 0, $member );

		/* Work on a copy rather than the original template */
		$body	= $this->_data['content'];

		/* Loop over the tags and swap out as appropriate */
		foreach( $tags as $key => $value )
		{
			$body	= str_replace( $key, $value, $body );
		}

		return $body;
	}

	/**
	 * Return tag values
	 *
	 * @param	int					$type	0=All, 1=Global, 2=Member-specific
	 * @param	NULL|\IPS\Member	$member	Member object if $type is 0 or 2
	 * @return	array
	 */
	protected function returnTagValues( $type, $member=NULL )
	{
		$tags	= array();

		/* Do we want global tags? */
		if( $type === 0 OR $type === 1 )
		{
			$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );

			$tags['{suite_name}']		= \IPS\Settings::i()->board_name;
			$tags['{suite_url}']		= \IPS\Settings::i()->base_url;
			$tags['{busy_time}']		= \IPS\DateTime::ts( ( $mostOnline['time'] ) ? $mostOnline['time'] : time() )->localeDate();
			$tags['{busy_count}']		= $mostOnline['count'];
			
			/* Only bother querying if we need the value */
			if( mb_strpos( $this->_data['content'], '{reg_total}' ) !== FALSE )
			{
				$tags['{reg_total}']		= \IPS\DB::i()->select( 'count(*)', 'core_members', 'member_id > 0' )->first();
			}
		}

		/* Do we want member tags? */
		if( $type === 0 OR $type === 2 )
		{
			$tags['{member_id}']			= $member->member_id;
			$tags['{member_name}']			= $member->name;
			$tags['{member_joined}']		= $member->joined->localeDate();
			$tags['{member_last_visit}']	= \IPS\DateTime::ts( $member->last_visit )->localeDate();
			$tags['{member_posts}']			= $member->member_posts;
		}

		/* Now retrieve tags via any bulk mail extensions.  We only want them to return an array of tags to perform formatting, but
			$body is passed in case a particular tag is computationally expensive so that the extension may "sniff" for it and elect
			not to perform the computation if it is not used. */
		foreach ( \IPS\Application::allExtensions( 'core', 'BulkMail', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'returnTagValues' ) )
			{
				$tags	= array_merge( $tags, $extension->returnTagValues( $this->_data['content'], $type, $member ) );
			}
		}

		return $tags;
	}

	/**
	 * Retrieve the tags that can be used in bulk mails
	 *
	 * @return	array 	An array of tags in foramt of 'tag' => 'explanation text'
	 */
	public static function getTags()
	{
		/* Default tags */
		$tags	= array(
			'{member_id}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_id'),
			'{member_name}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_name'),
			'{member_joined}'		=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_joined'),
			'{member_last_visit}'	=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_last_visit'),
			'{member_posts}'		=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_posts'),
			'{reg_total}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_reg_total'),
			'{suite_name}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_suite_name'),
			'{suite_url}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_suite_url'),
			'{busy_count}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_busy_count'),
			'{busy_time}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_busy_time'),
		);

		/* Now grab tags from any bulk mail extensions */
		foreach ( \IPS\Application::allExtensions( 'core', 'BulkMail', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getTags' ) )
			{
				$tags	= array_merge( $tags, $extension->getTags() );
			}
		}

		return $tags;
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\File::unclaimAttachments( 'core_Admin', $this->id, NULL, 'bulkmail' );
		parent::delete();
	}
}