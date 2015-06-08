<?php
/**
 * @brief		Content Item Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		5 Jul 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Item Model
 */
abstract class _Item extends \IPS\Content
{
	/**
	 * @brief	[Content\Item]	Comment Class
	 */
	public static $commentClass = NULL;
		
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;

	/**
	 * @brief	[Content\Item]	Include the ability to search this content item in global site searches
	 */
	public static $includeInSiteSearch = TRUE;

	/**
	 * @brief	[Content\Item]	Sharelink HTML
	 */
	protected $sharelinks = array();

	/**
	 * Build form to create
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	\IPS\Helpers\Form
	 */
	public static function create( \IPS\Node\Model $container=NULL )
	{
		/* Perform permission checks */
		static::canCreate( \IPS\Member::loggedIn(), $container, TRUE );
		
		/* Build the form */
		$form = static::buildCreateForm( $container );
				
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			try
			{
				$obj = static::createFromForm( $values, $container );
				\IPS\Output::i()->redirect( $obj->url() );
			}
			catch ( \DomainException $e )
			{
				$form->error = $e->getMessage();
			}			
		}
		
		/* Return */
		return $form;
	}
	
	/**
	 * Build form to create
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	\IPS\Helpers\Form
	 */
	protected static function buildCreateForm( \IPS\Node\Model $container=NULL )
	{
		$form = new \IPS\Helpers\Form( 'form', \IPS\Member::loggedIn()->language()->checkKeyExists( static::$formLangPrefix . '_save' ) ? static::$formLangPrefix . '_save' : 'save' );
		$form->class = 'ipsForm_vertical';
		$formElements = static::formElements( NULL, $container );
		if ( isset( $formElements['poll'] ) )
		{
			$form->addTab( static::$formLangPrefix . 'mainTab' );
		}
		foreach ( $formElements as $key => $object )
		{
			if ( $key === 'poll' )
			{
				$form->addTab( static::$formLangPrefix . 'pollTab' );
			}
			
			if ( is_object( $object ) )
			{
				$form->add( $object );
			}
			else
			{
				$form->addMessage( $object, NULL, FALSE, $key );
			}
		}
		
		return $form;
	}
	
	/**
	 * Create generic object
	 *
	 * @param	\IPS\Member				$author		The author
	 * @param	string|NULL				$ipAddress	The IP address
	 * @param	\IPS\DateTime			$time		The time
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool|NULL				$hidden		Hidden? (NULL to work our automatically)
	 * @return	static
	 */
	public static function createItem( \IPS\Member $author, $ipAddress, \IPS\DateTime $time, \IPS\Node\Model $container = NULL, $hidden=NULL )
	{
		/* Create the object */
		$obj = new static;
		foreach ( array( 'date', 'updated', 'author', 'author_name', 'ip_address', 'last_comment', 'last_comment_by', 'last_comment_name', 'last_review', 'container', 'approved', 'hidden', 'locked', 'status', 'views', 'pinned', 'featured', 'is_future_entry' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$val = NULL;
				switch ( $k )
				{
					case 'container':
						$val = $container->_id;
						break;
					
					case 'last_comment':
					case 'last_review':
					case 'date':
					case 'updated':
						$val = $time->getTimestamp();
						break;
					
					case 'author':
					case 'last_comment_by':
						$val = (int) $author->member_id;
						break;
					
					case 'author_name':
					case 'last_comment_name':
						$val = ( $author->member_id ) ? $author->name : '';
						break;

					case 'ip_address':
						$val = $ipAddress;
						break;
						
					case 'approved':
						if ( $hidden === NULL )
						{
							$val = static::moderateNewItems( $author, $container ) ? 0 : 1;
						}
						else
						{
							$val = intval( !$hidden );
						}
						break;
					
					case 'hidden':
						if ( $hidden === NULL )
						{
							$val = static::moderateNewItems( $author, $container ) ? 1 : 0;
						}
						else
						{
							$val = intval( $hidden );
						}
						break;
						
					case 'locked':
						$val = FALSE;
						break;
						
					case 'status':
						$val = 'open';
						break;
					
					case 'views':
					case 'pinned':
					case 'featured':
						$val = 0;
						break;
					case 'is_future_entry':
						$val = ( $time->getTimestamp() > time() ) ? 1 : 0;
						break;
				}
				
				foreach ( is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$obj->$column = $val;
				}
			}
		}
		
		/* Update the container */
		if ( $container )
		{
			if ( $obj->isFutureDate() )
			{
				if ( $container->_futureItems !== NULL )
				{
					$container->_futureItems = ( $container->_futureItems + 1 );
				}
			}
			elseif ( !$obj->hidden() )
			{
				if ( $container->_items !== NULL )
				{
					$container->_items = ( $container->_items + 1 );
				}
			}
			elseif ( $container->_unapprovedItems !== NULL )
			{
				$container->_unapprovedItems = ( $container->_unapprovedItems + 1 );
			}
			$container->save();
		}
		
		/* Increment post count */
		if ( !$obj->hidden() and static::incrementPostCount( $container ) )
		{
			$obj->author()->member_posts++;
		}
		
		/* Update member's last post */
		if( $obj->author()->member_id )
		{
			$obj->author()->member_last_post = time();
			$obj->author()->save();
		}

		/* Return */
		return $obj;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array					$values				Values from form
	 * @param	\IPS\Node\Model|NULL	$container			Container (e.g. forum), if appropriate
	 * @param	bool					$sendNotification	TRUE to automatically send new content notifications (useful for items that may be uploaded in bulk)
	 * @return	static
	 */
	public static function createFromForm( $values, \IPS\Node\Model $container = NULL, $sendNotification = TRUE )
	{
		/* Some applications may include the container selection on the form itself. If $container is NULL, attempt to find it automatically. */
		if( $container === NULL )
		{
			if( isset( $values[ static::$formLangPrefix . 'container'] ) AND isset( static::$containerNodeClass ) AND static::$containerNodeClass AND $values[ static::$formLangPrefix . 'container'] instanceof static::$containerNodeClass )
			{
				$container	= $values[ static::$formLangPrefix . 'container'];
			}
		}

		$member	= \IPS\Member::loggedIn();

		if( isset( $values['guest_name'] ) AND isset( static::$databaseColumnMap['author_name'] ) )
		{
			$member->name	= $values['guest_name'];
		}
		
		/* Create the item */
		$time = ( static::canFuturePublish( NULL, $container ) and  isset( static::$databaseColumnMap['date'] ) and isset( $values[ static::$formLangPrefix . 'date' ] ) and $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime ) ? $values[ static::$formLangPrefix . 'date' ] : new \IPS\DateTime;

		$obj = static::createItem( $member, \IPS\Request::i()->ipAddress(), $time, $container );
		$obj->processBeforeCreate( $values );
		$obj->processForm( $values );
		$obj->save();

		/* Create the comment */
		$comment = NULL;
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$commentClass = static::$commentClass;
			
			$comment = $commentClass::create( $obj, $values[ static::$formLangPrefix . 'content' ], TRUE, ( !$member->name ) ? NULL : $member->name, NULL, $member );
			
			$idColumn = static::$databaseColumnId;
			$commentIdColumn = $commentClass::$databaseColumnId;
			call_user_func_array( array( 'IPS\File', 'claimAttachments' ), array_merge( array( 'newContentItem-' . static::$application . '/' . static::$module  . '-' . ( $container ? $container->_id : 0 ) ), $comment->attachmentIds() ) );
			
			if ( isset( static::$databaseColumnMap['first_comment_id'] ) )
			{
				$firstCommentIdColumn = static::$databaseColumnMap['first_comment_id'];
				$obj->$firstCommentIdColumn = $comment->$commentIdColumn;
				$obj->save();
			}
		}
		
		/* Do any processing */
		$obj->processAfterCreate( $comment, $values );

		/* Auto-follow */
		if( isset( $values[ static::$formLangPrefix . 'auto_follow'] ) AND $values[ static::$formLangPrefix . 'auto_follow'] )
		{
			$followArea = mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) );
			
			/* Insert */
			$idColumn = static::$databaseColumnId;
			$save = array(
				'follow_id'				=> md5( static::$application . ';' . $followArea . ';' . $obj->$idColumn . ';' .  \IPS\Member::loggedIn()->member_id ),
				'follow_app'			=> static::$application,
				'follow_area'			=> $followArea,
				'follow_rel_id'			=> $obj->$idColumn,
				'follow_member_id'		=> \IPS\Member::loggedIn()->member_id,
				'follow_is_anon'		=> 0,
				'follow_added'			=> time(),
				'follow_notify_do'		=> 1,
				'follow_notify_meta'	=> '',
				'follow_notify_freq'	=> \IPS\Member::loggedIn()->auto_follow['method'],
				'follow_notify_sent'	=> 0,
				'follow_visible'		=> 1
			);
			
			\IPS\Db::i()->insert( 'core_follow', $save );
		}
		
		/* Auto-share */
		if ( $obj->canShare() and !$obj->hidden() and !$obj->isFutureDate() )
		{
			foreach( \IPS\core\ShareLinks\Service::shareLinks() as $node )
			{
				if ( isset( $values[ "auto_share_{$node->key}" ] ) and $values[ "auto_share_{$node->key}" ] )
				{
					$className = "\\IPS\\Content\\ShareServices\\" . ucwords( $node->key );
					
					try
					{
						$className::publish( $obj->mapped('title'), $obj->url() );
					}
					catch( \InvalidArgumentException $e )
					{
						/* Anything we can do here? Can't and shouldn't stop the submission */
					}
				}
			}
		}

		/* Send notifications */
		if ( $sendNotification and !$obj->isFutureDate() )
		{
			if ( !$obj->hidden() )
			{
				$obj->sendNotifications();
			}
			else if( $obj instanceof \IPS\Content\Hideable and $obj->hidden() !== -1 )
			{
				$obj->sendUnapprovedNotification();
			}
		}

		/* Return */
		return $obj;
	}
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		/* General columns */
		foreach ( array( 'title', 'poll' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) and array_key_exists( static::$formLangPrefix . $k , $values ) )
			{
				$val = $values[ static::$formLangPrefix . $k ];
				if ( $k === 'poll' )
				{
					$val = $val ? $val->pid : NULL;
				}
				foreach ( is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$this->$column = $val;
				}
			}
		}
				
		/* Tags */
		if ( $this instanceof \IPS\Content\Tags and static::canTag( NULL, $this->container() ) and isset( $values[ static::$formLangPrefix . 'tags' ] ) )
		{
			$idColumn = static::$databaseColumnId;
			if ( !$this->$idColumn )
			{
				$this->save();
			}
			
			$this->setTags( $values[ static::$formLangPrefix . 'tags' ] ?: array() );
		}
	}
			
	/**
	 * Can a given member create this type of content?
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool		$showError	If TRUE, rather than returning a boolean value, will display an error
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member, \IPS\Node\Model $container=NULL, $showError=FALSE )
	{
		$return = TRUE;
		$error = 'no_module_permission';
				
		/* Are we restricted from posting completely? */
		if ( $member->restrict_post )
		{
			$return = FALSE;
			$error = 'restricted_cannot_comment';
			
			if ( $member->restrict_post > 0 )
			{
				$error = $member->language()->addToStack( $error ) . ' ' . $member->language()->addToStack( 'restriction_ends', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $member->restrict_post )->relative() ) ) ); 
			}
		}
		
		/* Or have an unacknowledged warning? */
		if ( $member->members_bitoptions['unacknowledged_warnings'] )
		{
			$return = FALSE;
			$error = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->createItemUnavailable( 'unacknowledged_warning_cannot_post', $member->warnings( 1, FALSE ) );
		}
		
		/* Do we have permission? */
		if ( $container !== NULL AND in_array( 'IPS\Content\Permissions', class_implements( get_called_class() ) ) )
		{
			if ( !$container->can('add') )
			{
				$return = FALSE;
			}
		}
		else if( $container === NULL AND in_array( 'IPS\Content\Permissions', class_implements( get_called_class() ) ) )
		{
			$containerClass	= static::$containerNodeClass;
			if ( !$containerClass::canOnAny('add') )
			{
				$return = FALSE;
			}
		}
		
		/* Can we access the module */
		if ( !static::_canAccessModule( $member ) )
		{
			$return = FALSE;
		}
		
		/* Return */
		if ( $showError and !$return )
		{
			\IPS\Output::i()->error( $error, '2C137/3', 403 );
		}
		return $return;
	}

	/**
	 * During canCreate() check, verify member can access the module too
	 *
	 * @param	\IPS\Member	$member		The member
	 * @note	The only reason this is abstracted at this time is because Pages creates dynamic 'modules' with its dynamic records class which do not exist
	 * @return	bool
	 */
	protected static function _canAccessModule( \IPS\Member $member )
	{
		/* Can we access the module */
		return $member->canAccessModule( \IPS\Application\Module::get( static::$application, static::$module, 'front' ) );
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$return = array();
		
		/* Title */
		if ( isset( static::$databaseColumnMap['title'] ) )
		{
			$return['title'] = new \IPS\Helpers\Form\Text( static::$formLangPrefix . 'title', $item ? $item->mapped('title') : ( isset( \IPS\Request::i()->title ) ? \IPS\Request::i()->title : NULL ), TRUE, array( 'maxLength' => 250 ) );
		}
		
		/* Container */
		if ( $container === NULL AND isset( static::$containerNodeClass ) AND static::$containerNodeClass )
		{
			$return['container'] = new \IPS\Helpers\Form\Node( static::$formLangPrefix . 'container', NULL, TRUE, array( 'class' => static::$containerNodeClass, 'permCheck' => 'add' ), NULL, NULL, NULL, static::$formLangPrefix . 'container' );
		}

		if ( !\IPS\Member::loggedIn()->member_id )
		{
			if ( isset( static::$databaseColumnMap['author_name'] ) )
			{
				$return['guest_name']	= new \IPS\Helpers\Form\Text( 'guest_name', NULL, FALSE, array( 'maxLength' => 255, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_name') ) );
			}
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$return['captcha']	= new \IPS\Helpers\Form\Captcha;
			}
		}

		/* Tags */
		if ( in_array( 'IPS\Content\Tags', class_implements( get_called_class() ) ) and static::canTag( NULL, $container ) )
		{
			$options = array( 'autocomplete' => array( 'unique' => TRUE, 'source' => static::definedTags( $container ), 'freeChoice' => \IPS\Settings::i()->tags_open_system ? TRUE : FALSE ) );

			if ( \IPS\Settings::i()->tags_force_lower )
			{
				$options['autocomplete']['forceLower'] = TRUE;
			}
			if ( \IPS\Settings::i()->tags_min )
			{
				$options['autocomplete']['minItems'] = \IPS\Settings::i()->tags_min;
			}
			if ( \IPS\Settings::i()->tags_max )
			{
				$options['autocomplete']['maxItems'] = \IPS\Settings::i()->tags_max;
			}
			if ( \IPS\Settings::i()->tags_len_min )
			{
				$options['autocomplete']['minLength'] = \IPS\Settings::i()->tags_len_min;
			}
			if ( \IPS\Settings::i()->tags_len_max )
			{
				$options['autocomplete']['maxLength'] = \IPS\Settings::i()->tags_len_max;
			}
			if ( \IPS\Settings::i()->tags_clean )
			{
				$options['autocomplete']['filterProfanity'] = TRUE;
			}
			
			$options['autocomplete']['prefix'] = static::canPrefix( NULL, $container );

			/* Language strings for tags description */
			if ( \IPS\Settings::i()->tags_open_system )
			{
				$extralang = array();

				if ( \IPS\Settings::i()->tags_min && \IPS\Settings::i()->tags_max )
				{
					$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_min_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_max ), 'pluralize' => array( \IPS\Settings::i()->tags_min ) ) );
				}
				else if( \IPS\Settings::i()->tags_min )
				{
					$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_min', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_min ) ) );
				}
				else if( \IPS\Settings::i()->tags_min )
				{
					$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_max', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_max ) ) );
				}

				if( \IPS\Settings::i()->tags_len_min && \IPS\Settings::i()->tags_len_max )
				{
					$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_min_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_len_min, \IPS\Settings::i()->tags_len_max ) ) );
				}
				else if( \IPS\Settings::i()->tags_len_min )
				{
					$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_min', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_len_min ) ) );
				}
				else if( \IPS\Settings::i()->tags_len_max )
				{
					$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_len_max ) ) );
				}

				$options['autocomplete']['desc'] = \IPS\Member::loggedIn()->language()->addToStack('tags_desc') . ( ( count( $extralang ) ) ? '<br>' . implode( $extralang, ' ' ) : '' );
			}

			$return['tags'] = new \IPS\Helpers\Form\Text( static::$formLangPrefix . 'tags', $item ? ( $item->prefix() ? array_merge( array( 'prefix' => $item->prefix() ), $item->tags() ) : $item->tags() ) : array(), \IPS\Settings::i()->tags_min and \IPS\Settings::i()->tags_min_req, $options );
		}
		
		/* Intitial Comment */
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$idColumn = static::$databaseColumnId;
			$commentClass = static::$commentClass;
			if ( $item )
			{
				$commentObj		= $commentClass::load( $item->mapped('first_comment_id') );
			}
			$commentIdColumn = $commentClass::$databaseColumnId;
			$return['content'] = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'content', $item ? $item->content() : NULL, TRUE, array(
				'app'			=> static::$application,
				'key'			=> ucfirst( static::$module ),
				'autoSaveKey'	=> ( $item === NULL ? ( 'newContentItem-' . static::$application . '/' . static::$module . '-' . ( $container ? $container->_id : 0 ) ) : ( 'contentEdit-' . static::$application . '/' . static::$module . '-' . $item->$idColumn ) ),
				'attachIds'		=> ( $item === NULL ? NULL : array( $item->$idColumn, $item->comments( 1, 0 )->$commentIdColumn ) )
			), '\IPS\Helpers\Form::floodCheck', NULL, NULL, static::$formLangPrefix . 'content_editor' );
			
			if ( $item AND in_array( 'IPS\Content\EditHistory', class_implements( $commentClass ) ) and \IPS\Settings::i()->edit_log )
			{
				if ( \IPS\Settings::i()->edit_log == 2 or isset( $commentClass::$databaseColumnMap['edit_reason'] ) )
				{
					$return['comment_edit_reason'] = new \IPS\Helpers\Form\Text( 'comment_edit_reason', ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) ) ? $commentObj->mapped('edit_reason') : NULL, FALSE, array( 'maxLength' => 255 ) );
				}
				if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
				{
					$return['comment_log_edit'] = new \IPS\Helpers\Form\Checkbox( 'comment_log_edit', \IPS\Member::loggedIn()->member_id == $item->author()->member_id );
				}
			}
		}
		
		/* Auto-follow */
		if ( $item === NULL and in_array( 'IPS\Content\Followable', class_implements( get_called_class() ) ) and \IPS\Member::loggedIn()->member_id )
		{
			$return['auto_follow']	= new \IPS\Helpers\Form\YesNo( static::$formLangPrefix . 'auto_follow', (bool) \IPS\Member::loggedIn()->auto_follow['content'], FALSE, array(), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack( static::$formLangPrefix . 'auto_follow_suffix' ) );
		}
		
		/* Share Links */
		if ( $item === NULL and in_array( 'IPS\Content\Shareable', class_implements( get_called_class() ) ) )
		{
			foreach( \IPS\core\ShareLinks\Service::roots() as $node )
			{
				if ( $node->enabled AND $node->autoshare )
				{
					/* Do guests have permission to see this? */
					if ( $container and ! $container->can( 'read', new \IPS\Member ) )
					{
						continue;
					}
					
					$className	= "\\IPS\\Content\\ShareServices\\" . ucwords( $node->key );
					
					if ( $className::canAutoshare() )
					{
						$return["auto_share_{$node->key}"] = new \IPS\Helpers\Form\Checkbox( "auto_share_{$node->key}" );
					}
				}
			}
		}
		
		/* Polls */
		if ( in_array( 'IPS\Content\Polls', class_implements( get_called_class() ) ) and static::canCreatePoll( NULL, $container ) )
		{
			/* Can we create a poll on this item? */
			$existingPoll = NULL;
			if ( $item )
			{
				$existingPoll = $item->getPoll();
				
				/* If there's already a poll, we can edit it... */
				if ( $existingPoll )
				{
					$canCreatePoll = TRUE;
				}
				/* Otherwise, it depends on the cutoff for adding a poll */
				else
				{
					$canCreatePoll = ( \IPS\Settings::i()->startpoll_cutoff == -1 or \IPS\DateTime::create()->sub( new \DateInterval( 'PT' . \IPS\Settings::i()->startpoll_cutoff . 'H' ) )->getTimestamp() < $item->mapped('date') );
				}
			}
			else
			{
				/* If this is a new item, we can create a poll */
				$canCreatePoll = TRUE;
			}
			
			/* Create form element */
			if ( $canCreatePoll )
			{
				$return['poll'] = new \IPS\Helpers\Form\Poll( static::$formLangPrefix . 'poll', $existingPoll, FALSE, array( 'allowPollOnly' => TRUE ) );
			}
		}

		/* Future date */
		if ( in_array( 'IPS\Content\FuturePublishing', class_implements( get_called_class() ) ) and static::canFuturePublish( NULL, $container ) and isset( static::$databaseColumnMap['date'] ) )
		{
			$column = static::$databaseColumnMap['date'];
			$return['date'] = new \IPS\Helpers\Form\Date( static::$formLangPrefix . 'date', ( $item and $item->$column ) ? \IPS\DateTime::ts( $item->$column ) : 0, FALSE, array( 'time' => TRUE, 'unlimited' => 0, 'unlimitedLang' => 'immediately'), NULL, NULL, NULL,  static::$formLangPrefix . 'date' );
		}
		
		return $return;
	}
	
	/**
	 * Process created object BEFORE the object has been created
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	protected function processBeforeCreate( $values ) {}
	
	/**
	 * Process created object AFTER the object has been created
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The first comment
	 * @param	array						$values		Values from form
	 * @return	void
	 */
	protected function processAfterCreate( $comment, $values )
	{
		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
	}
	
	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		/* Initial Comment */
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$commentClass	= static::$commentClass;
			$commentObj		= $commentClass::load( $this->mapped('first_comment_id') );
			$column			= $commentClass::$databaseColumnMap['content'];
			$idField		= $commentClass::$databaseColumnId;
			
			if ( $commentObj instanceof \IPS\Content\EditHistory )
			{
				$editIsPublic = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
				
				if ( \IPS\Settings::i()->edit_log == 2 )
				{
					\IPS\Db::i()->insert( 'core_edit_history', array(
						'class'			=> get_class( $commentObj ),
						'comment_id'	=> $commentObj->$idField,
						'member'		=> \IPS\Member::loggedIn()->member_id,
						'time'			=> time(),
						'old'			=> $commentObj->$column,
						'new'			=> $values[ static::$formLangPrefix . 'content' ],
						'public'		=> $editIsPublic,
						'reason'		=> isset( $values['comment_edit_reason'] ) ? $values['comment_edit_reason'] : NULL,
					) );
				}
				
				if ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) and isset( $values['comment_edit_reason'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_reason'];
					$commentObj->$field = $values['comment_edit_reason'];
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_time'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_time'];
					$commentObj->$field = time();
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_member_id'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_member_id'];
					$commentObj->$field = \IPS\Member::loggedIn()->member_id;
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_member_name'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_member_name'];
					$commentObj->$field = \IPS\Member::loggedIn()->name;
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_show'] ) and $editIsPublic )
				{
					$field = $commentClass::$databaseColumnMap['edit_show'];
					$commentObj->$field = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
				}
			}
			
			$oldValue = $commentObj->$column;
			$commentObj->$column	= $values[ static::$formLangPrefix . 'content' ];
			$commentObj->save();
			$commentObj->sendAfterEditNotifications( $oldValue );
			
			if ( $commentObj instanceof \IPS\Content\Searchable )
			{
				\IPS\Content\Search\Index::i()->index( $commentObj );
			}
		}

		if ( $this instanceof \IPS\Content\FuturePublishing AND isset( $values[ static::$formLangPrefix . 'date' ] ) )
		{
			$container = $this->containerWrapper();
			$column    = static::$databaseColumnMap['is_future_entry'];

			if ( $container AND $this->$column )
			{
				if ( ( ! ( $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime ) AND $values[ static::$formLangPrefix . 'date' ] == 0 ) OR ( $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime AND $values[ static::$formLangPrefix . 'date' ]->getTimestamp() <= time() ) )
				{
					/* Was future, now not */
					$this->publish();
				}
			}
			elseif ( $container AND ! $this->$column )
			{
				if ( $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime AND $values[ static::$formLangPrefix . 'date' ]->getTimestamp() > time() )
				{
					/* Was not future, but now is */
					$this->unPublish();
				}
			}
		}
	}
	
	/**
	 * @brief	Container
	 */
	protected $container;

	/**
	 * Wrapper to get container. May return NULL if there is no container (e.g. private messages)
	 *
	 * @param	bool	$allowOutOfRangeException	If TRUE, will return NULL if the container doesn't exist rather than throw OutOfRangeException
	 * @return	\IPS\Node\Model|NULL
	 * @note	This simply wraps container()
	 * @see		container()
	 */
	public function containerWrapper( $allowOutOfRangeException = FALSE )
	{
		/* Get container, if valid */
		$container = NULL;

		try
		{
			$container = $this->container();
		}
		catch( \OutOfRangeException $e )
		{
			if ( !$allowOutOfRangeException )
			{
				throw $e;
			}
		}
		catch( \BadMethodCallException $e ){}

		return $container;
	}

	/**
	 * Get container
	 *
	 * @return	\IPS\Node\Model
	 * @note	Certain functionality requires a valid container but some areas do not use this functionality (e.g. messenger)
	 * @throws	\OutOfRangeException|\BadMethodCallException
	 */
	public function container()
	{
		if ( $this->container === NULL )
		{
			if ( !isset( static::$containerNodeClass ) or !isset( static::$databaseColumnMap['container'] ) )
			{
				throw new \BadMethodCallException;
			}

			$this->container = call_user_func( array( static::$containerNodeClass, 'load' ), $this->mapped('container') );
		}
		
		return $this->container;
	}
	
	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	abstract public function url( $action=NULL );
	
	/**
	 * Returns the content
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function content()
	{
		if ( isset( static::$databaseColumnMap['content'] ) )
		{
			return parent::content();
		}
		elseif ( static::$commentClass )
		{
			if ( $comment = $this->comments( 1, 0, 'date', 'asc' ) )
			{
				return $comment->content();
			}
			else
			{
				throw new \BadMethodCallException;
			}
		}
		else
		{
			throw new \BadMethodCallException;
		}
	}
	
	/**
	 * Returns the meta description
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function metaDescription()
	{
		if( isset( $_SESSION['_findComment'] ) )
		{
			$commentId	= $_SESSION['_findComment'];
			unset( $_SESSION['_findComment'] );

			$commentClass	= static::$commentClass;
			
			if( $commentClass !== NULL )	
			{
				try
				{
					$comment = $commentClass::loadAndCheckPerms( $commentId );

					return $comment->content();
				}
				catch( \Exception $e ){}
			}
		}

		if ( isset( static::$databaseColumnMap['content'] ) )
		{
			return parent::content();
		}
		else
		{
			return $this->mapped('title');
		}
	}
	
	/**
	 * @brief	Hot stats
	 */
	public $hotStats = array();
	
	/**
	 * Stats for table view
	 *
	 * @param	bool	$includeFirstCommentInCommentCount	Determines whether the first comment should be inlcluded in the comment count (e.g. For "posts", use TRUE. For "replies", use FALSE)
	 * @return	array
	 */
	public function stats( $includeFirstCommentInCommentCount=TRUE )
	{
		$return = array();

		if ( static::$commentClass )
		{
			$return['comments'] = (int) $this->mapped('num_comments');
			if ( !$includeFirstCommentInCommentCount )
			{
				$return['comments']--;
			}

			if ( $return['comments'] < 0 )
			{
				$return['comments'] = 0;
			}
		}
		
		if ( $this instanceof \IPS\Content\Views )
		{
			$return['num_views'] = (int) $this->mapped('views');
		}
		
		return $return;
	}
	
	/**
	 * Move
	 *
	 * @param	\IPS\Node\Model	$container	Container to move to
	 * @param	bool			$keepLink	If TRUE, will keep a link in the source
	 * @return	void
	 */
	public function move( \IPS\Node\Model $container, $keepLink=FALSE )
	{
		/* Reduce the counts in the old node */
		$oldContainer = $this->container();

		if ( $this->isFutureDate() and $oldContainer->_futureItems !== NULL )
		{
			$oldContainer->_futureItems = intval( $oldContainer->_futureItems - 1 );
		}
		else if ( !$this->hidden() )
		{
			if ( $oldContainer->_items !== NULL )
			{
				$oldContainer->_items = intval( $oldContainer->_items - 1 );
			}
			if ( isset( static::$commentClass ) and $oldContainer->_comments !== NULL )
			{
				$oldContainer->_comments = intval( $oldContainer->_comments - $this->mapped('num_comments') );
			}
			if ( isset( static::$reviewClass ) and $oldContainer->_reviews !== NULL )
			{
				$oldContainer->_reviews = intval( $oldContainer->_reviews - $this->mapped('num_reviews') );
			}
		}
		elseif ( $this->hidden() === 1 and $oldContainer->_unapprovedItems !== NULL )
		{
			$oldContainer->_unapprovedItems = intval( $oldContainer->_unapprovedItems - 1 );
		}

		if ( isset( static::$commentClass ) and $oldContainer->_unapprovedComments !== NULL and isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$oldContainer->_unapprovedComments = intval( $oldContainer->_unapprovedComments - $this->mapped('unapproved_comments') );
		}
		if ( isset( static::$reviewClass ) and $oldContainer->_unapprovedReviews !== NULL and isset( static::$databaseColumnMap['unapproved_reviews'] ) )
		{
			$oldContainer->_unapprovedReviews = intval( $oldContainer->_unapprovedReviews - $this->mapped('unapproved_reviews') );
		}

		/* Make a link */
		if ( $keepLink )
		{
			$link = clone $this;
			$movedToColumn = static::$databaseColumnMap['moved_to'];
			$idColumn = static::$databaseColumnId;
			$link->$movedToColumn = $this->$idColumn . '&' . $container->_id;
			
			if ( isset( static::$databaseColumnMap['state'] ) )
			{
				$stateColumn = static::$databaseColumnMap['state'];
				$link->$stateColumn = 'link';
			}
			if ( isset( static::$databaseColumnMap['moved_on'] ) )
			{
				$movedOnColumn = static::$databaseColumnMap['moved_on'];
				$link->$movedOnColumn = time();
			}
			
			$link->save();
		}
		
		/* If this item is read, we need to re-mark it as such after moving */
		$unread = $this->unread();
		
		/* Change container */
		$column = static::$databaseColumnMap['container'];
		$this->$column = $container->_id;
		$this->save();
		$this->container = $container;
	
		/* Rebuild tags */
		$containerClass = static::$containerNodeClass;
		if ( $this instanceof \IPS\Content\Tags )
		{
			if( static::canTag( $this->author(), $container ) )
			{
				\IPS\Db::i()->update( 'core_tags', array(
					'tag_aap_lookup'		=> $this->tagAAPKey(),
					'tag_meta_parent_id'	=> $container->_id
				), array( 'tag_aai_lookup=?', $this->tagAAIKey() ) );

				if ( isset( $containerClass::$permissionMap['read'] ) )
				{
					\IPS\Db::i()->update( 'core_tags_perms', array(
						'tag_perm_aap_lookup'	=> $this->tagAAPKey(),
						'tag_perm_text'			=> \IPS\Db::i()->select( 'perm_' . $containerClass::$permissionMap['read'], 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', $containerClass::$permApp, $containerClass::$permType, $container->_id ) )->first()
					), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
				}
			}
			else
			{
				$this->setTags( array() );
			}
		}
		
		/* Update the counts in the new node */
		if ( $this->isFutureDate() and $container->_futureItems !== NULL )
		{
			$container->_futureItems = ( $container->_futureItems + 1 );
		}
		elseif ( !$this->hidden() )
		{
			if ( $container->_items !== NULL )
			{
				$container->_items = ( $container->_items + 1 );
			}
			if ( isset( static::$commentClass ) and $container->_comments !== NULL )
			{
				$container->_comments = ( $container->_comments + $this->mapped('num_comments') );
			}
			if ( isset( static::$reviewClass ) and $this->container()->_reviews !== NULL )
			{
				$container->_reviews = ( $container->_reviews + $this->mapped('num_reviews') );
			}
		}
		elseif ( $this->hidden() === 1 and $container->_unapprovedItems !== NULL )
		{
			$container->_unapprovedItems = ( $container->_unapprovedItems + 1 );
		}
		if ( isset( static::$commentClass ) and $container->_unapprovedComments !== NULL and isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$container->_unapprovedComments = ( $container->_unapprovedComments + $this->mapped('unapproved_comments') );
		}
		if ( isset( static::$reviewClass ) and $this->container()->_unapprovedReviews !== NULL and isset( static::$databaseColumnMap['unapproved_reviews'] ) )
		{
			$container->_unapprovedReviews = ( $container->_unapprovedReviews + $this->mapped('unapproved_reviews') );
		}
				
		/* Rebuild node data */
		$oldContainer->setLastComment();
		$oldContainer->setLastReview();
		$oldContainer->save();
		$container->setLastComment();
		$container->setLastReview();
		$container->save();

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		$idColumn = static::$databaseColumnId;
		foreach ( array( 'commentClass', 'reviewClass' ) as $class )
		{
			if ( isset( static::$$class ) )
			{
				$className = static::$$class;
				if ( in_array( 'IPS\Content\Searchable', class_implements( $className ) ) )
				{
					\IPS\Content\Search\Index::i()->massUpdate( $className, NULL, $this->$idColumn, $this->searchIndexPermissions(), NULL, $container->_id );
				}
			}
		}

		/* Update caches */
		$this->expireWidgetCaches();
		
		/* Mark it as read */
		if( $unread == 0 )
		{
			$this->markRead();
		}
		
		/* If we have a link, mark it read */
		if ( $keepLink )
		{
			$link->markRead();
		}

		\IPS\Session::i()->modLog( 'modlog__action_move', array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') ?: ( method_exists( $this, 'item' ) ? $this->item()->mapped('title') : NULL ) => FALSE ),  $this );
	}
	
	/**
	 * Moved to
	 *
	 * @return	static|NULL
	 */
	public function movedTo()
	{
		if ( isset( static::$databaseColumnMap['moved_to'] ) )
		{
			$exploded = explode( '&', $this->mapped('moved_to') );
			try
			{
				return static::load( $exploded[0] );
			}
			catch ( \Exception $e ) { }
		}
	}
	
	/**
	 * Get Next Item
	 *
	 * @return	static|NULL
	 */
	public function nextItem()
	{
		try
		{
			$column = $this->getDateColumn();
			$idColumn = static::$databaseColumnId;

			$item	= NULL;

			foreach( static::getItemsWithPermission( array(
				array( static::$databaseTable . '.' . static::$databasePrefix . $column . '>?', $this->$column ),
				array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id ),
				array( static::$databaseTable . '.' . static::$databasePrefix . $idColumn . '!=?', $this->$idColumn )
			), static::$databasePrefix . $column . ' ASC', 1 ) AS $item )
			{
				break;
			}

			return $item;
		}
		catch( \Exception $e ) { }
	}
	
	/**
	 * Get Previous Item
	 *
	 * @return	static|NULL
	 */
	public function prevItem()
	{
		try
		{
			$column = $this->getDateColumn();
			$idColumn = static::$databaseColumnId;

			$item	= NULL;
			foreach( static::getItemsWithPermission( array(
				array( static::$databaseTable . '.' . static::$databasePrefix . $column . '<?', $this->$column ),
				array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id ),
				array( static::$databaseTable . '.' . static::$databasePrefix . $idColumn . '!=?', $this->$idColumn )
			), static::$databasePrefix . $column . ' DESC', 1 ) AS $item )
			{
				break;
			}
			
			return $item;
		}
		catch( \Exception $e ) { }
	}

	/**
	 * Get date column for next/prev item
	 * Does not use last comment / last review as these will often be 0 and is not how items are generally ordered
	 *
	 * @return	string
	 */
	protected function getDateColumn()
	{
		if( isset( static::$databaseColumnMap['updated'] ) )
		{
			$column	= is_array( static::$databaseColumnMap['updated'] ) ? static::$databaseColumnMap['updated'][0] : static::$databaseColumnMap['updated'];
		}
		else if( isset( static::$databaseColumnMap['date'] ) )
		{
			$column	= is_array( static::$databaseColumnMap['date'] ) ? static::$databaseColumnMap['date'][0] : static::$databaseColumnMap['date'];
		}

		return $column;
	}
	
	/**
	 * Merge other items in (they will be deleted, this will be kept)
	 *
	 * @param	array	$items	Items to merge in
	 * @return	void
	 */
	public function mergeIn( array $items )
	{
		$idColumn = static::$databaseColumnId;
		
		foreach ( $items as $item )
		{
			if ( isset( static::$commentClass ) )
			{
				$commentClass = static::$commentClass;
				$commentUpdate = array();
				$commentUpdate[ $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] ] = $this->$idColumn;
				if ( isset( $commentClass::$databaseColumnMap['first'] ) )
				{
					/* This item is being merged into another, so any comments defined as "first" need to be reset */
					$commentUpdate[ $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['first'] ] = FALSE;
				}
				\IPS\Db::i()->update( $commentClass::$databaseTable, $commentUpdate, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $item->$idColumn ) );
			}
			if ( isset( static::$reviewClass ) )
			{
				$reviewClass = static::$reviewClass;
				$reviewUpdate = array();
				$reviewUpdate[ $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] ] = $this->$idColumn;
				if ( isset( $reviewClass::$databaseColumnMap['first'] ) )
				{
					/* This item is being merged into another, so any comments defined as "first" need to be reset */
					$reviewUpdate[ $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] ] = FALSE;
				}
				\IPS\Db::i()->update( $reviewClass::$databaseTable, array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] => $this->$idColumn ), array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $item->$idColumn ) );
			}

			/* Attachments */
			$locationKey = (string) $item::$application . '_' . ucfirst( $item::$module );
			\IPS\Db::i()->update( 'core_attachments_map', array( 'id1' => $this->$idColumn ), array( 'location_key=? and id1=?', $locationKey, $item->$idColumn ) );

            /* Update moderation history */
            \IPS\Db::i()->update( 'core_moderator_logs', array( 'item_id' => $this->$idColumn ), array( 'item_id=? AND class=?', $item->$idColumn, (string) get_class( $this ) ) );

			$item->delete();
		}

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		$this->rebuildFirstAndLastCommentData();
	}
	
	/**
	 * Rebuild meta data after splitting/merging
	 *
	 * @return	void
	 */
	public function rebuildFirstAndLastCommentData()
	{
		$firstComment = $this->comments( 1, 0, 'date', 'asc', NULL, FALSE );
		$lastComment = $this->comments( 1, 0, 'date', 'desc', NULL, FALSE );
		$idColumn = static::$databaseColumnId;
		$commentClass = static::$commentClass;
		$commentIdColumn = $commentClass::$databaseColumnId;
		
		/* First Comment stuff */
		if ( isset( static::$databaseColumnMap['author'] ) )
		{
			$authorField = static::$databaseColumnMap['author'];
			$this->$authorField = $firstComment->author()->member_id;
		}
		if ( isset( static::$databaseColumnMap['author_name'] ) )
		{
			$authorNameField = static::$databaseColumnMap['author_name'];
			$this->$authorNameField = $firstComment->mapped('author_name');
		}
		if ( isset( static::$databaseColumnMap['date'] ) )
		{
			$dateField = static::$databaseColumnMap['date'];
			$this->$dateField = $firstComment->mapped('date');
		}
		if ( isset( static::$databaseColumnMap['first_comment_id'] ) )
		{
			$firstCommentField = static::$databaseColumnMap['first_comment_id'];
			$this->$firstCommentField = $firstComment->$commentIdColumn;
		}
		
		/* Set first comments */
		if ( isset( $commentClass::$databaseColumnMap['first'] ) )
		{
			/* This can fail if we are, for example, splitting a post into a new topic, where a previous comment does not exist */
			$hasPrevious = TRUE;
			try
			{
				$previousFirstComment = $commentClass::constructFromData( \IPS\Db::i()->select( '*', $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=? AND ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['first'] . '=?', $this->$idColumn, TRUE ) )->first() );
			}
			catch( \UnderflowException $e )
			{
				$hasPrevious = FALSE;
			}
			
			if ( $hasPrevious )
			{
				if ( $previousFirstComment->$commentIdColumn !== $firstComment->$commentIdColumn )
				{
					$firstColumn = $commentClass::$databaseColumnMap['first'];
				
					$previousFirstComment->$firstColumn = FALSE;
					$previousFirstComment->save();
				
					$firstComment->$firstColumn = TRUE;
					$firstComment->save();
				}
			}
			else
			{
				$firstColumn = $commentClass::$databaseColumnMap['first'];
				
				$firstComment->$firstColumn = TRUE;
				$firstComment->save();
			}
		}
		
		/* Last comment stuff */
		if ( isset( static::$databaseColumnMap['last_comment'] ) )
		{
			$lastCommentField = static::$databaseColumnMap['last_comment'];
			if ( is_array( $lastCommentField ) )
			{
				foreach ( $lastCommentField as $column )
				{
					$this->$column = $lastComment->mapped('date');
				}
			}
			else
			{
				$this->$lastCommentField = $lastComment->mapped('date');
			}
		}
		if ( isset( static::$databaseColumnMap['last_comment_by'] ) )
		{
			$lastCommentByField = static::$databaseColumnMap['last_comment_by'];
			$this->$lastCommentByField = $lastComment->author()->member_id;
		}
		if ( isset( static::$databaseColumnMap['last_comment_name'] ) )
		{
			$lastCommentNameField = static::$databaseColumnMap['last_comment_name'];
			$this->$lastCommentNameField = $lastComment->author()->member_id ? $lastComment->author()->name : $lastComment->mapped('author_name');
		}
		
		/* Number of comments */
		if ( isset( static::$databaseColumnMap['num_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			if ( in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . ' IN( 0, 2 )' ); # 2 means the parent is hidden but the post itself is not
				}
			}
			
			$numCommentsField = static::$databaseColumnMap['num_comments'];
			$this->$numCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where )->first();
		}
		if ( isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			if ( in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 0 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 1 );
				}
			}
			
			$numUnapprovedCommentsField = static::$databaseColumnMap['unapproved_comments'];
			$this->$numUnapprovedCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where )->first();
		}

		$this->save();

		/* Update container */
		$containerWhere = array( array( static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id ) );
		if ( in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) )
		{
			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$containerWhere[] = array( static::$databasePrefix . static::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$containerWhere[] = array( static::$databasePrefix . static::$databaseColumnMap['hidden'] . '=?', 0 );
			}
		}
		if ( $this->container()->_items !== NULL )
		{
			$this->container()->_items = \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, $containerWhere )->first();
		}
		if ( $this->container()->_comments !== NULL )
		{
			$commentWhere = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . ' IN(?)', \IPS\Db::i()->select( $idColumn, static::$databaseTable, $containerWhere ) ) );
			if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
			{
				$commentWhere[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
			{
				$commentWhere[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 0 );
			}

			$this->container()->_comments = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $commentWhere )->first();
		}
		$this->container()->setLastComment();
		$this->container()->save();

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
	}

	/**
	 * Hide
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string				$reason	Reason
	 * @return	void
	 */
	public function hide( \IPS\Member $member = NULL, $reason = NULL )
	{
		parent::hide( $member, $reason );
		
		$idColumn = static::$databaseColumnId;
		foreach ( array( 'commentClass', 'reviewClass' ) as $class )
		{
			if ( isset( static::$$class ) )
			{
				$className = static::$$class;
				if ( in_array( 'IPS\Content\Hideable', class_implements( $className ) ) AND isset( $className::$databaseColumnMap['hidden'] ) )
				{
					\IPS\Db::i()->update( $className::$databaseTable, array( $className::$databasePrefix . $className::$databaseColumnMap['hidden'] => 2 ), array( $className::$databasePrefix . $className::$databaseColumnMap['item'] . '=? AND ' . $className::$databasePrefix . $className::$databaseColumnMap['hidden'] . '=?', $this->$idColumn, 0 ) );
				}
				
				if ( in_array( 'IPS\Content\Searchable', class_implements( $className ) ) )
				{
					\IPS\Content\Search\Index::i()->massUpdate( $className, NULL, $this->$idColumn, NULL, 2 );
					
					if ( static::$firstCommentRequired )
					{
						\IPS\Content\Search\Index::i()->index( $this->comments( 1, NULL, 'date', 'asc' ) );
					}
				}
			}
		}

		try
		{
			if ( $this->container()->_comments !== NULL )
			{
				$this->container()->setLastComment();
				$this->container()->save();
			}

			if ( $this->container()->_reviews !== NULL )
			{
				$this->container()->setLastReview();
				$this->container()->save();
			}
		} catch ( \BadMethodCallException $e ) {}
	}

	/**
	 * Item is moderator hidden by a moderator
	 *
	 * @return	boolean
	 * @throws	\RuntimeException
	 */
	public function approvedButHidden()
	{
		if ( $this instanceof \IPS\Content\Hideable )
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
				return ( $this->$column == 2 ) ? TRUE : FALSE;
			}
			elseif ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
				return $this->$column == -1 ? TRUE : FALSE;
			}
			else
			{
				throw new \RuntimeException;
			}
		}

		return FALSE;
	}

	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function unhide( $member=NULL )
	{
		parent::unhide( $member );
				
		$idColumn = static::$databaseColumnId;
		foreach ( array( 'commentClass', 'reviewClass' ) as $class )
		{
			if ( isset( static::$$class ) )
			{
				$className = static::$$class;
				if ( in_array( 'IPS\Content\Hideable', class_implements( $className ) ) AND isset( $className::$databaseColumnMap['hidden'] ) )
				{
					\IPS\Db::i()->update( $className::$databaseTable, array( $className::$databasePrefix . $className::$databaseColumnMap['hidden'] => 0 ), array( $className::$databasePrefix . $className::$databaseColumnMap['item'] . '=? AND ' . $className::$databasePrefix . $className::$databaseColumnMap['hidden'] . '=?', $this->$idColumn, 2 ) );
				}
				
				if ( isset( static::$commentClass ) and static::$firstCommentRequired )
				{
					$commentClass = static::$commentClass;
					\IPS\Content\Search\Index::i()->index( $commentClass::load( $this->mapped('first_comment_id') ) );
				}
				
				if ( in_array( 'IPS\Content\Searchable', class_implements( $className ) ) )
				{
					\IPS\Content\Search\Index::i()->massUpdate( $className, NULL, $this->$idColumn, NULL, 0 );
				}
			}
		}

		try
		{
			if ( $this->container()->_comments !== NULL )
			{
				$this->container()->setLastComment();
				$this->container()->save();
			}

			if ( $this->container()->_reviews !== NULL )
			{
				$this->container()->setLastReview();
				$this->container()->save();
			}
		} catch ( \BadMethodCallException $e ) {}
	}
		
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Delete it from the database */
		parent::delete();
		$idColumn = static::$databaseColumnId;
		
		/* Unclaim attachments */
		$this->unclaimAttachments();
		
		/* Remove from search index - we must do this before deleting comments so we know what to remove */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->removeFromSearchIndex( $this );
		}
		
		/* Update count */
		try
		{
			if ( $this->container()->_items !== NULL )
			{
				if ( $this->isFutureDate() and $this->container()->_futureItems !== NULL )
				{
					$this->container()->_futureItems = ( $this->container()->_futureItems - 1 );
				}
				elseif ( !$this->hidden() )
				{
					$this->container()->_items = ( $this->container()->_items - 1 );
				}
				elseif ( $this->hidden() === 1 )
				{
					$this->container()->_unapprovedItems = ( $this->container()->_unapprovedItems - 1 );
				}
			}
		} catch ( \BadMethodCallException $e ) {}
		
		/* Delete comments */
		if ( isset( static::$commentClass ) )
		{
			$commentClass = static::$commentClass;
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( method_exists( $commentClass, 'deleteWhereSql' ) )
			{
				$where = $commentClass::deleteWhereSql( $this->$idColumn );
			}

			\IPS\Db::i()->delete( $commentClass::$databaseTable, $where );
			
			try
			{
				if ( $this->container()->_comments !== NULL )
				{
					$this->container()->_comments = ( $this->container()->_comments - $this->mapped('num_comments') );
					$this->container()->setLastComment();
				}
				if ( $this->container()->_unapprovedComments !== NULL )
				{
					$this->container()->_unapprovedComments = ( $this->container()->_unapprovedComments - $this->mapped('unapproved_comments') );
				}
				$this->container()->save();
			} catch ( \BadMethodCallException $e ) {}
		}
		
		/* Delete reviews */
		if ( isset( static::$reviewClass ) )
		{
			$reviewClass = static::$reviewClass;
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( method_exists( $reviewClass, 'deleteWhereSql' ) )
			{
				$where = $reviewClass::deleteWhereSql( $this->$idColumn );
			}

			\IPS\Db::i()->delete( $reviewClass::$databaseTable, $where );
			
			try
			{
				if ( $this->container()->_reviews !== NULL )
				{
					$this->container()->_reviews = ( $this->container()->_reviews - $this->mapped('num_reviews') );
					$this->container()->setLastReview();
				}
				if ( $this->container()->_unapprovedReviews !== NULL )
				{
					$this->container()->_unapprovedReviews = ( $this->container()->_unapprovedReviews - $this->mapped('unapproved_reviews') );
				}
				$this->container()->save();
			} catch ( \BadMethodCallException $e ) {}
		}
		
		/* Delete tags */
		if ( $this instanceof \IPS\Content\Tags )
		{
			$aaiLookup = $this->tagAAIKey();
			\IPS\Db::i()->delete( 'core_tags', array( 'tag_aai_lookup=?', $aaiLookup ) );
			\IPS\Db::i()->delete( 'core_tags_cache', array( 'tag_cache_key=?', $aaiLookup ) );
			\IPS\Db::i()->delete( 'core_tags_perms', array( 'tag_perm_aai_lookup=?', $aaiLookup ) );
		}
		
		/* Delete follows */
		if ( $this instanceof \IPS\Content\Followable )
		{
			$followArea = mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) );
			\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', static::$application, $followArea, (int) $this->$idColumn ) );
		}
		
		/* Remove Notifications */
		$memberIds	= array();

		foreach( \IPS\DB::i()->select( 'member', 'core_notifications', array( 'item_class=? AND item_id=?', (string) get_class( $this ), (int) $this->$idColumn ) ) as $member )
		{
			$memberIds[ $member ]	= $member;
		}

		\IPS\Db::i()->delete( 'core_notifications', array( 'item_class=? AND item_id=?', (string) get_class( $this ), (int) $this->$idColumn ) );

		foreach( $memberIds as $member )
		{
			\IPS\Member::load( $member )->recountNotifications();
		}
	}
	
	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor )
	{
		$oldAuthor = $this->author();
		
		/* Update the row */
		parent::changeAuthor( $newAuthor );
		
		/* Adjust post counts */
		if ( static::incrementPostCount( $this->containerWrapper() ) )
		{
			$oldAuthor->member_posts--;
			$oldAuthor->save();
			
			$newAuthor->member_posts++;
			$newAuthor->save();
		}
		
		/* Last comment */
		if ( $container = $this->containerWrapper() )
		{
			$container->setLastComment();
			$container->setLastReview();
			$container->save();
		}
		
		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
	}
	
	/**
	 * Unclaim attachments
	 *
	 * @return	void
	 */
	protected function unclaimAttachments()
	{
		$idColumn = static::$databaseColumnId;
		\IPS\File::unclaimAttachments( static::$application . '_' . ucfirst( static::$module ), $this->$idColumn );
	}
	
	/**
	 * @brief Cached containers we can access
	 */
	protected static $permissionSelect	= array();

	/**
	 * Get items with permisison check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string|NULL	$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index or NULL to ignore permissions
	 * @param	bool|NULL	$includeHiddenItems	Include hidden files? Boolean or NULL to detect if currently logged member has permission
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly			If true will return the count
	 * @param	array|null	$joins				Additional arbitrary joins for the query
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=NULL, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL )
	{
		/* Work out the order */
		if ( $order === NULL )
		{
			$dateColumn = static::$databaseColumnMap['date'];
			if ( is_array( $dateColumn ) )
			{
				$dateColumn = array_pop( $dateColumn );
			}
			$order = static::$databaseTable . '.' . static::$databasePrefix . $dateColumn . ' DESC';
		}

		/* Exclude hidden items */
		$includeHiddenItems = ( $includeHiddenItems === NULL ) ? static::canViewHiddenItems( $member ) : $includeHiddenItems;
		if ( in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) and !$includeHiddenItems )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$authorCol = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['author'];
			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
				if ( $member->member_id )
				{
					$where[] = array( "( {$col}=1 OR ( {$col}=0 AND {$authorCol}={$member->member_id} ) )" );
				}
				else
				{
					$where[] = array( "{$col}=1" );
				}
			}
			elseif ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
				if ( $member->member_id )
				{
					$where[] = array( "( {$col}=0 OR ( {$col}=1 AND {$authorCol}={$member->member_id} ) )" );
				}
				else
				{
					$where[] = array( "{$col}=0" );
				}
			}
		}
        else
        {
            /* Legacy items pending deletion in 3.x at time of upgrade may still exist */
            $col	= null;

            if ( isset( static::$databaseColumnMap['approved'] ) )
            {
                $col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
            }
            else if( isset( static::$databaseColumnMap['hidden'] ) )
            {
                $col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
            }

            if( $col )
            {
            	$where[] = array( "{$col} < 2" );
            }
        }

		/* Future items? */
		if ( in_array( 'IPS\Content\FuturePublishing', class_implements( get_called_class() ) ) )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$authorCol = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['author'];

			if ( ! static::canViewFutureItems( $member ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['is_future_entry'];
				if ( $member->member_id )
				{
					$where[] = array( "( {$col}=0 OR ( {$col}=1 AND {$authorCol}={$member->member_id} ) )" );
				}
				else
				{
					$where[] = array( "{$col}=0" );
				}
			}
		}
		
		/* We always want to make this multidimensional */
		$queryFlags |= \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS;

		/* Build the select clause */
		if( $countOnly )
		{
			$select = \IPS\Db::i()->select( 'COUNT(*) as cnt', static::$databaseTable, $where, NULL, NULL, ( $joinComments ? static::$databasePrefix . static::$databaseColumnId : NULL ), NULL, $queryFlags );
			if ( $joinContainer )
			{
				$containerClass = static::$containerNodeClass;
				$select->join( $containerClass::$databaseTable, array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) );
			}
			if ( count( $joins ) )
			{
				foreach( $joins as $join )
				{
					$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), 'LEFT' );
				}
			}
			return $select->first();
		}
		else
		{
			$selectClause = static::$databaseTable . '.*';

            if( isset( static::$databaseColumnMap['author'] ) )
            {
                $selectClause .= ', author.*';
            }
            if( isset( static::$databaseColumnMap['last_comment_by'] ) )
            {
                $selectClause .= ', last_commenter.*';
            }

			/* Are we doing a pseudo-rand ordering? */
			if( $order == '_rand' )
			{
				$selectClause	.= ', SUBSTR( ' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['date'] . ', ' . rand( 1, 9 ) . ', 10 ) as _rand';
			}

			if ( count( $joins ) )
			{
				foreach( $joins as $join )
				{
					if( isset( $join['select']) AND $join['select'] )
					{
						$selectClause .= ', ' . $join['select'];
					}
				}
			}
			
			if ( in_array( 'IPS\Content\Tags', class_implements( get_called_class() ) ) )
			{
				$selectClause .= ', core_tags_cache.tag_cache_text';
			}

			if ( in_array( 'IPS\Content\Permissions', class_implements( get_called_class() ) ) AND $permissionKey !== NULL )
			{
				$containerClass = static::$containerNodeClass;

				$member = $member ?: \IPS\Member::loggedIn();
				//$where[] = array( '(' . \IPS\Db::i()->in( 'perm_' . $containerClass::$permissionMap[ $permissionKey ], $member->groups ) . ' OR ' . 'perm_' . $containerClass::$permissionMap[ $permissionKey ] . '=? )', '*' );

				$categories	= array();
				$lookupKey	= md5( $containerClass::$permApp . $containerClass::$permType . $permissionKey . json_encode( $member->groups ) );

				if( !isset( static::$permissionSelect[ $lookupKey ] ) )
				{
					static::$permissionSelect[ $lookupKey ] = array();
					foreach( \IPS\Db::i()->select( 'perm_type_id', 'core_permission_index', array( "core_permission_index.app='" . $containerClass::$permApp . "' AND core_permission_index.perm_type='" . $containerClass::$permType . "' AND (" . \IPS\Db::i()->findInSet( 'perm_' . $containerClass::$permissionMap[ $permissionKey ], $member->groups ) . ' OR ' . 'perm_' . $containerClass::$permissionMap[ $permissionKey ] . "='*' )" ) ) as $result )
					{
						static::$permissionSelect[ $lookupKey ][] = $result;
					}
				}

				$categories = static::$permissionSelect[ $lookupKey ];

				if( count( $categories ) )
				{
					$where[]	= array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['container'] . ' IN(' . implode( ',', $categories ) . ')' );
				}
				else
				{
					$where[]	= array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['container'] . '=0' );
				}

				$select = \IPS\Db::i()->select( $selectClause, static::$databaseTable, $where, $order, $limit, ( $joinComments ? static::$databasePrefix . static::$databaseColumnId : NULL ), NULL, $queryFlags );
			}
			else
			{
				$select = \IPS\Db::i()->select( $selectClause, static::$databaseTable, $where, $order, $limit, ( $joinComments ? static::$databasePrefix . static::$databaseColumnId : NULL ), NULL, $queryFlags );
			}
		}

		/* Join stuff */
		if ( $joinContainer AND isset( static::$containerNodeClass ) )
		{
			$containerClass = static::$containerNodeClass;
			$select->join( $containerClass::$databaseTable, array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) );
		}
		if ( $joinComments )
		{
			$commentClass = static::$commentClass;
			$select->join( $commentClass::$databaseTable, array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId ) );
		}
		if ( $joinReviews )
		{
			$reviewClass = static::$reviewClass;
			$select->join( $reviewClass::$databaseTable, array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId ) );
		}

		/* Join the tags cache, if applicable */
		if ( in_array( 'IPS\Content\Tags', class_implements( get_called_class() ) ) )
		{
			$itemClass = get_called_class();
			$idColumn = static::$databasePrefix . static::$databaseColumnId;
			$select->join( 'core_tags_cache', array( "tag_cache_key=MD5(CONCAT(?,{$itemClass::$databaseTable}.{$idColumn}))", static::$application . ';' . static::$module . ';' ) );
		}

        /* Join the members table */
        if( isset( static::$databaseColumnMap['author'] ) )
        {
            $authorColumn = static::$databaseColumnMap['author'];
            $select->join( array( 'core_members', 'author' ), array( 'author.member_id = ' . static::$databaseTable . '.' . static::$databasePrefix . $authorColumn ) );
        }
        if( isset( static::$databaseColumnMap['last_comment_by'] ) )
        {
	        $lastCommeneterColumn = static::$databaseColumnMap['last_comment_by'];
            $select->join( array( 'core_members', 'last_commenter' ), array( 'last_commenter.member_id = ' . static::$databaseTable . '.' . static::$databasePrefix . $lastCommeneterColumn ) );
        }

        if ( count( $joins ) )
		{
			foreach( $joins as $join )
			{
				$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), 'LEFT' );
			}
		}
		
		/* Return */
		return new \IPS\Patterns\ActiveRecordIterator( $select, get_called_class() );
	}
	
	/**
	 * Additional WHERE clauses for New Content view
	 *
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	array		$joins				Other joins
	 * @return	array
	 */
	public static function vncWhere( &$joinContainer, &$joins )
	{
		return array();
	}
	
	/**
	 * Get featured items
	 *
	 * @param	int						$limit		Number to get
	 * @param	string					$order		MySQL ORDER BY clause
	 * @param	\IPS\Node\Model|NULL	$container	Container to restrict to (or NULL for any)
	 * @return	\IPS\Patterns\AciveRecordIterator
	 * @throws	\BadMethodCallException
	 */
	public static function featured( $limit=10, $order='RAND()', $container = NULL )
	{
		if ( !in_array( 'IPS\Content\Featurable', class_implements( get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}
		
		$where = array( array( static::$databasePrefix . static::$databaseColumnMap['featured'] . '=?', 1 ) );
		if ( $container )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $container->_id );
		}

		if ( in_array( 'IPS\Content\FuturePublishing', class_implements( get_called_class() ) ) )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnMap['is_future_entry'] . '=?', 0 );
		}
		
		return static::getItemsWithPermission( $where, $order, $limit );
	}
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' ), 'rows' );
	}

	/**
	 * Get content table states
	 *
	 * @return string
	 */
	public function tableStates()
	{
		$return	= array();

		$return[]	= ( $this->unread() === -1 or $this->unread() === 1 ) ? "unread" : "read";

		if( $this->hidden() === -1 )
		{
			$return[]	= "hidden";
		}
		else if( $this->hidden() === 1 )
		{
			$return[]	= "unapproved";
		}

		if( $this->mapped('pinned') )
		{
			$return[]	= "pinned";
		}

		if( $this->mapped('featured') )
		{
			$return[]	= "featured";
		}

		try
		{
			if( $this->locked() )
			{
				$return[]	= "locked";
			}
		}
		catch( \BadMethodCallException $e ){}

		try
		{
			if( $this->isFutureDate() )
			{
				$return[]	= "future";
			}
		}
		catch( \BadMethodCallException $e ){}
		
		if ( $this->_followData )
		{
			$return[] = 'follow_freq_' . $this->_followData['follow_notify_freq'];
			$return[] = 'follow_privacy_' . intval( $this->_followData['follow_is_anon'] );
		}

		return implode( " ", $return );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function searchResultHtml()
	{
		return \IPS\Theme::i()->getTemplate( 'search', 'core', 'front' )->contentItem( $this );
	}
			
	/* !Comments & Reviews */

	/**
	 * @brief	[Content\Item]	Number of reviews to show per page
	 */
	public static $reviewsPerPage = 25;

	/**
	 * @brief	Review Page count
	 * @see		reviewPageCount()
	 */
	protected $reviewPageCount;

	/**
	 * @brief	Comment Page count
	 * @see		commentPageCount()
	 */
	protected $commentPageCount;

	/**
	 * Get number of comments to show per page
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		return 25;
	}

	/**
	 * Get comment page count
	 *
	 * @return	int
	 */
	public function commentPageCount( $recache=FALSE )
	{		
		if ( $this->commentPageCount === NULL or $recache )
		{
			$this->commentPageCount = ceil( $this->commentCount() / $this->getCommentsPerPage() );

			if( $this->commentPageCount < 1 )
			{
				$this->commentPageCount	= 1;
			}
		}
		return $this->commentPageCount;
	}
	
	/**
	 * Get comment count
	 *
	 * @return	int
	 */
	public function commentCount()
	{
		$count = $this->mapped('num_comments');
		$commentClass = static::$commentClass;

		if( $this->canViewHiddenComments() and ( isset( $commentClass::$databaseColumnMap['hidden'] ) or isset( $commentClass::$databaseColumnMap['approved'] ))  )
		{
			$idColumn = static::$databaseColumnId;

			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			if ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( '( ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=1 OR ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=-1 )' );
			}
			elseif ( isset( $commentClass::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( '( ' .  $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=0 OR ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=-1 )' );
			}

			$count += $commentClass::getItemsWithPermission( $where, NULL, NULL )->count();
		}

		return $count;
	}
	
	/**
	 * Get review page count
	 *
	 * @return	int
	 */
	public function reviewPageCount()
	{
		if ( $this->reviewPageCount === NULL )
		{
			$reviewClass = static::$reviewClass;
			$idColumn = static::$databaseColumnId;
			$count = $reviewClass::getItemsWithPermission( array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) ) )->count();
			$this->reviewPageCount = ceil( $count / static::$reviewsPerPage );

			if( $this->reviewPageCount < 1 )
			{
				$this->reviewPageCount	= 1;
			}
		}
		return $this->reviewPageCount;
	}
	
	/**
	 * Get comment pagination
	 *
	 * @param	array		$qs	Query string parameters to keep (for example sort options)
	 * @param	string		$template	Template to use
	 * @param	int|null	$pageCount	The number of pages, if known, or NULL to calculate automatically
	 * @return	string
	 */
	public function commentPagination( $qs=array(), $template='pagination', $pageCount = NULL )
	{
		return $this->_pagination( $qs, $pageCount ?: $this->commentPageCount(), $this->getCommentsPerPage(), $template );
	}
	
	/**
	 * Get review pagination
	 *
	 * @param	array		$qs			Query string parameters to keep (for example sort options)
	 * @param	string		$template	Template to use
	 * @param	int|null	$pageCount	The number of pages, if known, or NULL to calculate automatically
	 * @return	string
	 */
	public function reviewPagination( $qs=array(), $template='pagination', $pageCount = NULL )
	{
		return $this->_pagination( $qs, $pageCount ?: $this->reviewPageCount(), static::$reviewsPerPage, $template );
	}
	
	/**
	 * Get comment/review pagination
	 *
	 * @param	array	$qs			Query string parameters to keep (for example sort options)
	 * @param	int		$count		Page count
	 * @param	int		$perPage	Number per page
	 * @return	string
	 */
	protected function _pagination( $qs, $count, $perPage, $template )
	{
		$url = $this->url();
		foreach ( $qs as $key )
		{
			if ( isset( \IPS\Request::i()->$key ) )
			{
				$url = $url->setQueryString( $key, \IPS\Request::i()->$key );
			}
		}

		$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->$template( $url, $count, $page, $perPage );
	}

	/**
	 * Whether we're viewing the last page of reviews/comments on this item
	 *
	 * @param	string	$type		"reviews" or "comments"
	 * @return	boolean
	 */
	public function isLastPage( $type='comments' )
	{
		/* If this class does not have any comments or reviews, return true */
		if ( !isset( static::$commentClass ) AND !isset( static::$reviewClass ) )
		{
			return TRUE;
		}
		
		$pageCount = ( $type == 'reviews' ) ? $this->reviewPageCount() : $this->commentPageCount();

		if( $pageCount !== NULL && ( ( \IPS\Request::i()->page && \IPS\Request::i()->page == $pageCount ) || !isset( \IPS\Request::i()->page ) && in_array( $pageCount, array( 0, 1 ) ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Get comments
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by
	 * @param	string				$orderDirection			"asc" or "desc"
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenComments	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function comments( $limit=NULL, $offset=NULL, $order='date', $orderDirection='asc', $member=NULL, $includeHiddenComments=NULL, $cutoff=NULL, $extraWhereClause=NULL )
	{
		static $comments	= array();
		$idField			= static::$databaseColumnId;
		$_hash				= md5( $this->$idField . json_encode( func_get_args() ) );

		if( isset( $comments[ $_hash ] ) )
		{
			return $comments[ $_hash ];
		}

		$class = static::$commentClass;
				
		$comments[ $_hash ]	= $this->_comments( $class, $limit ?: $this->getCommentsPerPage(), $offset, ( isset( $class::$databaseColumnMap[ $order ] ) ? ( $class::$databasePrefix . $class::$databaseColumnMap[ $order ] ) : $order ) . ' ' . $orderDirection, $member, $includeHiddenComments, $cutoff, NULL, $extraWhereClause );
		return $comments[ $_hash ];
	}

	/**
	 * @brief	Cached review pulls
	 */
	protected $cachedReviews	= array();

	/**
	 * Get reviews
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by (NULL to examine \IPS\Request::i()->sort)
	 * @param	string				$orderDirection			"asc" or "desc" (NULL to examine \IPS\Request::i()->sort)
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenReviews	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function reviews( $limit=NULL, $offset=NULL, $order=NULL, $orderDirection='desc', $member=NULL, $includeHiddenReviews=NULL, $cutoff=NULL, $extraWhereClause=NULL )
	{
		$cacheKey	= md5( json_encode( func_get_args() ) );

		if( isset( $this->cachedReviews[ $cacheKey ] ) )
		{
			return $this->cachedReviews[ $cacheKey ];
		}

		$class = static::$reviewClass;
	
		if ( $order === NULL )
		{
			if ( isset( \IPS\Request::i()->sort ) and \IPS\Request::i()->sort === 'newest' )
			{
				$order = $class::$databasePrefix . $class::$databaseColumnMap['date'] . ' DESC';
			}
			else
			{
				$order = "({$class::$databasePrefix}{$class::$databaseColumnMap['votes_helpful']}/{$class::$databasePrefix}{$class::$databaseColumnMap['votes_total']}) DESC, {$class::$databasePrefix}{$class::$databaseColumnMap['date']} DESC";
			}
		}
		else
		{
			$order = ( isset( $class::$databaseColumnMap[ $order ] ) ? ( $class::$databasePrefix . $class::$databaseColumnMap[ $order ] ) : $order ) .  ' ' . $orderDirection;
		}
		
		$this->cachedReviews[ $cacheKey ]	= $this->_comments( $class, $limit ?: static::$reviewsPerPage, $offset, $order, $member, $includeHiddenReviews, $cutoff, NULL, $extraWhereClause );
		return $this->cachedReviews[ $cacheKey ];
	}
	
	/**
	 * Get comments/reviews
	 *
	 * @param	string				$class 					The class
	 * @param	int|NULL			$limit					The number to get (NULL to use $perPage)
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The ORDER BY clause
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHidden			Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	protected function _comments( $class, $limit, $offset=NULL, $order='date DESC', $member=NULL, $includeHidden=NULL, $cutoff=NULL, $canViewWarn=NULL, $extraWhereClause=NULL )
	{
		/* Initial WHERE clause */
		$idColumn = static::$databaseColumnId;
		$where = array( array( $class::$databasePrefix . $class::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
		if ( $member !== NULL )
		{
			$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['author'] . '=?', $member->member_id );
		}
		if ( $cutoff !== NULL )
		{
			$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['date'] . '>?', $cutoff->getTimestamp() );
		}
		
		/* Exclude hidden comments? */
		if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			/* If $includeHidden is not a bool, work it out from the member's permissions */
			$includeHiddenByMember = FALSE;
			if ( $includeHidden === NULL )
			{
				if ( isset( static::$commentClass ) and $class == static::$commentClass )
				{
					$includeHidden = $this->canViewHiddenComments();
				}
				else if ( isset( static::$reviewClass ) and $class == static::$reviewClass )
				{
					$includeHidden = $this->canViewHiddenReviews();
				}

				$includeHiddenByMember = TRUE;
			}
			
			/* If we can't view hidden comments, exclude them with the WHERE clause */
			if ( !$includeHidden )
			{
				$authorCol = $class::$databasePrefix . $class::$databaseColumnMap['author'];
				if ( isset( $class::$databaseColumnMap['approved'] ) )
				{
					$col = $class::$databasePrefix . $class::$databaseColumnMap['approved'];
					if ( $includeHiddenByMember and \IPS\Member::loggedIn()->member_id )
					{
						$where[] = array( "({$col}=1 OR ( {$col}=0 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id . '))' );
					}
					else
					{
						$where[] = array( "{$col}=1" );
					}
				}
				elseif( isset( $class::$databaseColumnMap['hidden'] ) )
				{
					$col = $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
					if ( $includeHiddenByMember and \IPS\Member::loggedIn()->member_id )
					{
						$where[] = array( "({$col}=0 OR ( {$col}=1 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id . '))' );
					}
					else
					{
						$where[] = array( "{$col}=0" );
					}
				}
			}
		}
		
		/* Additional where clause */
		if( $extraWhereClause !== NULL )
		{
			if ( !is_array( $extraWhereClause ) or !is_array( $extraWhereClause[0] ) )
			{
				$extraWhereClause = array( $extraWhereClause );
			}
			$where = array_merge( $where, $extraWhereClause );
		}
		
		/* Get the joins */
		$selectClause = $class::$databaseTable . '.*';		
		$joins = $class::joins( $this );
		if ( is_array( $joins ) )
		{
			foreach ( $joins as $join )
			{
				if ( isset( $join['select'] ) )
				{
					$selectClause .= ', ' . $join['select'];
				}
			}
		}

		/* Bad offset values can create an SQL error with a negative limit */
		$_pageValue = ( \IPS\Request::i()->page ? intval( \IPS\Request::i()->page ) : 1 );

		if( $_pageValue < 1 )
		{
			$_pageValue = 1;
		}

		/* Construct the query */
		$results = array();
		$query = $class::db()->select( $selectClause, $class::$databaseTable, $where, $order, array( ( $offset !== NULL ? $offset : ( ( $_pageValue - 1 ) * $limit ) ), $limit ), NULL, NULL, \IPS\DB::SELECT_SQL_CALC_FOUND_ROWS + \IPS\DB::SELECT_MULTIDIMENSIONAL_JOINS );
		if ( is_array( $joins ) )
		{
			foreach ( $joins as $join )
			{
				$query->join( $join['from'], $join['where'] );
			}
		}

		/* Get the results */
		$commentIdColumn = $class::$databaseColumnId;
		foreach ( $query as $row )
		{
			$result = $class::constructFromData( $row );
			if ( $limit === 1 )
			{
				return $result;
			}
			else
			{
				if ( in_array( 'IPS\Content\Reputation', class_implements( $class ) ) )
				{
					$result->reputation = array();
				}
				$results[ $result->$commentIdColumn ] = $result;
			}
		}
		
		/* Get the reputation stuff now so we don 't have to do lots of queries later */
		if ( in_array( 'IPS\Content\Reputation', class_implements( $class ) ) AND count( $results ) )
		{
			/* Work out the query */
			$reputationWhere = array( array( 'app=? AND type=?', $class::$application, $class::$reputationType ) );
			$reputationWhere[] = array( \IPS\Db::i()->in( 'type_id', array_keys( $results ) ) );
			switch( \IPS\Settings::i()->reputation_point_types )
			{
				case 'positive':
				case 'like':
					$reputationWhere[] = array( 'rep_rating=?', "1" );
					break;					
				case 'negative':
					$reputationWhere[] = array( 'rep_rating=?', "-1" );
					break;
			}
			
			/* If we need to display the "like blurb", we need the names of the people who have liked */
			if ( \IPS\Settings::i()->reputation_point_types == 'like' and \IPS\Member::loggedIn()->group['gbw_view_reps'] )
			{
				$names = array();
				$select = \IPS\Db::i()->select( 'core_reputation_index.type_id, core_reputation_index.member_id, core_reputation_index.rep_rating, core_members.name, core_members.members_seo_name', 'core_reputation_index', $reputationWhere, 'RAND()' )->join( 'core_members', 'core_members.member_id=core_reputation_index.member_id' );
			}
			/* Otherwise we just need the data */
			else
			{
				$select = \IPS\Db::i()->select( 'type_id, member_id, rep_rating', 'core_reputation_index', $reputationWhere );
			}
			
			/* Populate */
			foreach ( $select as $reputation )
			{
				$results[ $reputation['type_id'] ]->reputation[ $reputation['member_id'] ] = $reputation['rep_rating'];
				if ( \IPS\Settings::i()->reputation_point_types == 'like' and \IPS\Member::loggedIn()->group['gbw_view_reps'] )
				{
					if ( $reputation['member_id'] === \IPS\Member::loggedIn()->member_id )
					{
						if( isset( $names[ $reputation['type_id'] ] ) )
						{
							array_unshift( $names[ $reputation['type_id'] ], '' );
						}
						else
						{
							$names[ $reputation['type_id'] ][0] = '';
						}
					}
					elseif ( !isset( $names[ $reputation['type_id'] ] ) or count( $names[ $reputation['type_id'] ] ) < 3 )
					{
						$names[ $reputation['type_id'] ][ $reputation['member_id'] ] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $reputation['member_id'], $reputation['name'], $reputation['members_seo_name'] );
					}
				}
			}
						
			/* If we need to display the "like blurb", compile that now */
			if ( \IPS\Settings::i()->reputation_point_types == 'like' and \IPS\Member::loggedIn()->group['gbw_view_reps'] )
			{
				foreach ( $names as $commentId => $people )
				{
					if ( isset( $people[0] ) )
					{						
						if ( count( $names[ $commentId ] ) === 1 )
						{
							$results[ $commentId ]->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'like_blurb_just_you' );
							continue;
						}
						
						$names[ $commentId ][0] = \IPS\Member::loggedIn()->language()->addToStack('like_blurb_you_and_others');
												
						while ( count( $names[ $commentId ] ) > 3 )
						{
							array_pop( $names[ $commentId ] );
						}
						
						$people = $names[ $commentId ];
					}
					
					$totalRep = array_sum( $results[ $commentId ]->reputation );
					if ( $totalRep > count( $people ) )
					{
						$people[] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reputationOthers( $results[ $commentId ], \IPS\Member::loggedIn()->language()->addToStack( empty( $people ) ? 'like_blurb_generic' : 'like_blurb_others', FALSE, array( 'pluralize' => array( $totalRep - count( $people ) ) ) ) );
					}
					
					$results[ $commentId ]->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'like_blurb', FALSE, array( 'pluralize' => array( $totalRep ), 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $people ) ) ) );
				}
			}
		}
		
		/* Get the warning stuff now so we don 't have to do lots of queries later */
		$canViewWarn = is_null( $canViewWarn ) ? \IPS\Member::loggedIn()->modPermission('mod_see_warn') : $canViewWarn;
		if ( $canViewWarn and count( $results ) )
		{
			$module = static::$module;
			
			if ( isset( static::$commentClass ) and $class == static::$commentClass )
			{
				$module .= '-comment';
			}
			if ( isset( static::$reviewClass ) and $class == static::$reviewClass )
			{
				$module .= '-review';
			}
			
			$where = array( array( 'wl_content_app=? AND wl_content_module=? AND wl_content_id1=?', static::$application, $module, $this->$idColumn ) );
			$where[] = array( \IPS\Db::i()->in( 'wl_content_id2', array_keys( $results ) ) );
			
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members_warn_logs', $where ), 'IPS\core\Warnings\Warning' ) as $warning )
			{
				$results[ $warning->content_id2 ]->warning = $warning;
			}
		}
		
		/* Return */
		return ( $limit === 1 ) ? NULL : $results;
	}
	
	/**
	 * @brief	Comment form output cached
	 */
	protected $_commentFormHtml	= NULL;
	
	/**
	 * Build comment form
	 *
	 * @return	string
	 */
	public function commentForm( $lastSeenId = NULL )
	{
		/* Have we built it already? */
		if( $this->_commentFormHtml !== NULL )
		{
			return $this->_commentFormHtml;
		}

		/* Can we comment? */
		if ( $this->canComment() )
		{
			$commentClass = static::$commentClass;
			$idColumn = static::$databaseColumnId;
			$commentIdColumn = $commentClass::$databaseColumnId;
			$commentDateColumn = $commentClass::$databaseColumnMap['date'];
			
			$form = new \IPS\Helpers\Form( 'commentform' . '_' . $this->$idColumn, static::$formLangPrefix . 'submit_comment' );
			$form->class = 'ipsForm_vertical';
			$form->hiddenValues['_contentReply'] = TRUE;

			$elements = $this->commentFormElements();
			
			foreach( $elements as $element )
			{
				$form->add( $element );
			}
						
			if ( $values = $form->values() )
			{
				$currentPageCount = \IPS\Request::i()->currentPage;
				
				$comment = $this->processCommentForm( $values );
				
				unset( $this->commentPageCount );
				if ( \IPS\Request::i()->isAjax() )
				{
					$this->markRead();

					$newPageCount = $this->commentPageCount();
					if ( $currentPageCount != $newPageCount )
					{
						\IPS\Output::i()->json( array( 'type' => 'redirect', 'page' => $newPageCount, 'total' => $this->mapped('num_comments'), 'content' => $comment->html(), 'url' => (string) $comment->url('find') ) );
					}
					else
					{
						$output = '';
						/* This comes from a form field and has an underscore, see the form definition above */
						if ( isset( \IPS\Request::i()->_lastSeenID ) and \IPS\Request::i()->_lastSeenID )
						{
							$lastComment = $commentClass::load( \IPS\Request::i()->_lastSeenID );
							foreach ( $this->comments( NULL, 0, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $lastComment->$commentDateColumn ) ) as $newComment )
							{
								if ( $newComment->$commentIdColumn != $comment->$commentIdColumn )
								{
									$output .= $newComment->html();
								}
							}
						}
						$output .= $comment->html();
						
						\IPS\Output::i()->json( array( 'type' => 'add', 'id' => $comment->$commentIdColumn, 'page' => $newPageCount, 'total' => $this->mapped('num_comments'), 'content' => $output ) );
					}
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $this->lastCommentPageUrl()->setFragment( 'comment-' . $comment->$commentIdColumn ) );
				}
			}
			elseif ( \IPS\Request::i()->isAjax() )
			{
				foreach ( $elements as $input )
				{
					if ( $input->error )
					{
						\IPS\Output::i()->json( array( 'type' => 'error', 'message' => \IPS\Member::loggedIn()->language()->addToStack($input->error ) ) );
					}
				}
			}
			
			/* Mod Queue? */
			$return = '';
			if ( static::moderateNewComments( \IPS\Member::loggedIn() ) )
			{
				$return = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts );
			}
			
			$this->_commentFormHtml	= $return . $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), $commentClass::$formTemplate[0] ), $commentClass::$formTemplate[1] ) );
			return $this->_commentFormHtml;
		}

		/* Hang on, are we a guest, but if logged in, could comment? */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$testUser = new \IPS\Member;
			$testUser->member_group_id = \IPS\Settings::i()->member_group;
			if ( $this->canComment( $testUser ) )
			{
				$this->_commentFormHtml	= $this->guestTeaser();
				return $this->_commentFormHtml;
			}
		}
		
		/* Nope, just display nothing */
		$this->_commentFormHtml	= '';

		return $this->_commentFormHtml;
	}
	
	/**
	 * Add the comment form elements
	 *
	 * @return	array
	 */
	public function commentFormElements()
	{
		$commentClass = static::$commentClass;
		$idColumn = static::$databaseColumnId;
		$return   = array();
		
		$editorField = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'comment' . '_' . $this->$idColumn, NULL, TRUE, array(
			'app'			=> static::$application,
			'key'			=> ucfirst( static::$module ),
			'autoSaveKey' 	=> 'reply-' . static::$application . '/' . static::$module . '-' . $this->$idColumn,
			'minimize'		=> static::$formLangPrefix . '_comment_placeholder'
		), '\IPS\Helpers\Form::floodCheck' );
		$return['editor'] = $editorField;
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			if ( isset( $commentClass::$databaseColumnMap['author_name'] ) )
			{
				$return['guest_name'] = new \IPS\Helpers\Form\Text( 'guest_name', NULL, FALSE, array( 'maxLength' => 255, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_name') ) );
			}
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$return['captcha'] = new \IPS\Helpers\Form\Captcha;
			}
		}
		
		$followArea = mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) );
	
		/* Add in the "automatically follow" option */
		if ( in_array( 'IPS\Content\Followable', class_implements( get_called_class() ) ) and \IPS\Member::loggedIn()->member_id )
		{
			$return['follow'] = new \IPS\Helpers\Form\YesNo( static::$formLangPrefix . 'auto_follow', (bool) ( \IPS\Member::loggedIn()->auto_follow['comments'] or \IPS\Member::loggedIn()->following( static::$application, $followArea, $this->$idColumn ) ), FALSE, array( 'label' => static::$formLangPrefix . 'auto_follow_suffix' ) );
		}
		
		return $return;
	}
	
	/**
	 * Process the comment form
	 *
	 * @param	array	$values		Array of $form values
	 * @return  \IPS\Content\Comment
	 */
	public function processCommentForm( $values )
	{
		$commentClass = static::$commentClass;
		$idColumn = static::$databaseColumnId;
		$commentIdColumn = $commentClass::$databaseColumnId;
		$followArea = mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) );	

		$comment = $commentClass::create( $this, $values[ static::$formLangPrefix . 'comment' . '_' . $this->$idColumn ], FALSE, isset( $values['guest_name'] ) ? $values['guest_name'] : NULL );
		call_user_func_array( array( 'IPS\File', 'claimAttachments' ), array_merge( array( 'reply-' . static::$application . '/' . static::$module  . '-' . $this->$idColumn ), $comment->attachmentIds() ) );
		
		/* Auto-follow */
		if( isset( $values[ static::$formLangPrefix . 'auto_follow' ] ) )
		{
			if ( $values[ static::$formLangPrefix . 'auto_follow' ] and !\IPS\Member::loggedIn()->following( static::$application, $followArea, $this->$idColumn ) )
			{
				/* Insert */
				$save = array(
					'follow_id'				=> md5( static::$application . ';' . $followArea . ';' . $this->$idColumn . ';' .  \IPS\Member::loggedIn()->member_id ),
					'follow_app'			=> static::$application,
					'follow_area'			=> $followArea,
					'follow_rel_id'			=> $this->$idColumn,
					'follow_member_id'		=> \IPS\Member::loggedIn()->member_id,
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> \IPS\Member::loggedIn()->auto_follow['method'],
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1
				);
			
				\IPS\Db::i()->insert( 'core_follow', $save );
			}
			else if ( $values[ static::$formLangPrefix . 'auto_follow' ] === false AND \IPS\Member::loggedIn()->following( static::$application, $followArea, $this->$idColumn ) )
			{
				\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=?', (string) md5( static::$application . ';' . $followArea . ';' . $this->$idColumn . ';' . \IPS\Member::loggedIn()->member_id ) ) );
			}
		}

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
		if ( $comment instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $comment );
		}

		return $comment;
	}
	
	/**
	 * Build review form
	 *
	 * @return	string
	 */
	public function reviewForm()
	{
		/* Can we review? */
		if ( $this->canReview() )
		{
			$reviewClass = static::$reviewClass;
			$idColumn = static::$databaseColumnId;
			$reviewIdColumn = static::$databaseColumnId;
			
			$form = new \IPS\Helpers\Form( 'review', 'add_review' );
			$form->class  = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Rating( static::$formLangPrefix . 'rating_value', NULL, TRUE, array( 'max' => \IPS\Settings::i()->reviews_rating_out_of ) ) );
			$editorField = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'review_text', NULL, TRUE, array(
				'app'			=> static::$application,
				'key'			=> ucfirst( static::$module ),
				'autoSaveKey' 	=> 'review-' . static::$application . '/' . static::$module . '-' . $this->$idColumn,
				'minimize'		=> static::$formLangPrefix . '_review_placeholder'
			), '\IPS\Helpers\Form::floodCheck' );
			$form->add( $editorField );
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				if ( isset( $reviewClass::$databaseColumnMap['author_name'] ) )
				{
					$form->add( new \IPS\Helpers\Form\Text( 'guest_name', NULL, FALSE, array( 'maxLength' => 255, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_name') ) ) );
				}
				if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
				{
					$form->add( new \IPS\Helpers\Form\Captcha );
				}
			}
			
			if ( $values = $form->values() )
			{
				$currentPageCount = \IPS\Request::i()->currentPage;
				
				unset( $this->reviewpageCount );
								
				$review = $this->processReviewForm( $values );
								
				\IPS\Output::i()->redirect( $this->url(), 'thanks_for_your_review' );
			}
			elseif ( \IPS\Request::i()->isAjax() and $editorField->error )
			{
				\IPS\Output::i()->json( array( 'type' => 'error', 'message' => \IPS\Member::loggedIn()->language()->addToStack( $editorField->error ) ) );
			}
						
			return $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'reviewTemplate' ) );
		}
		
		/* Hang on, are we a guest, but if logged in, could comment? */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$testUser = new \IPS\Member;
			$testUser->member_group_id = \IPS\Settings::i()->member_group;
			if ( $this->canComment( $testUser ) )
			{
				return $this->guestTeaser();
			}
		}
		
		/* Nope, just display nothing */
		return '';
	}
	
	/**
	 * Process the review form
	 *
	 * @param	array	$values		Array of $form values
	 * @return  \IPS\Content\Comment
	 */
	public function processReviewForm( $values )
	{
		$reviewClass = static::$reviewClass;
		$idColumn = static::$databaseColumnId;
		$reviewIdColumn = $reviewClass::$databaseColumnId;
		
		$review = $reviewClass::create( $this, $values[ static::$formLangPrefix . 'review_text' ], FALSE, $values[ static::$formLangPrefix . 'rating_value' ], isset( $values['guest_name'] ) ? $values['guest_name'] : NULL );
		call_user_func_array( array( 'IPS\File', 'claimAttachments' ), array_merge( array( 'review-' . static::$application . '/' . static::$module  . '-' . $this->$idColumn ), $review->attachmentIds() ) );
		
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
		
		return $review;
	}
	
	/**
	 * Message explaining to guests that if they log in they can comment
	 *
	 * @return	string
	 */
	public function guestTeaser()
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core' )->guestCommentTeaser( $this, new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login', NULL, \IPS\Settings::i()->logins_over_https ) ) );
	}
	
	/**
	 * Get URL for last comment page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastCommentPageUrl()
	{
		$url = $this->url();
		$lastPage = $this->commentPageCount();
		if ( $lastPage != 1 )
		{
			$url = $url->setQueryString( 'page', $lastPage );
		}
		return $url;
	}
	
	/**
	 * Get URL for last review page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastReviewPageUrl()
	{
		$url = $this->url();
		$lastPage = $this->reviewPageCount();
		if ( $lastPage != 1 )
		{
			$url = $url->setQueryString( 'page', $lastPage );
		}
		return $url;
	}
	
	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canComment( $member=NULL )
	{
		return $this->canCommentReview( 'reply', $member );
	}
	
	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canReview( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id or $member->restrict_post or ( $this instanceof \IPS\Content\Lockable and $this->locked() and ( !$member->member_id or !static::modPermission( 'reply_to_locked', $member, $this->container() ) ) ) )
		{
			return FALSE;
		}
							
		return $this->canCommentReview( 'review', $member ) and !$this->hasReviewed( $member );
	}

	/**
 	 * @brief	Cache if we have already reviewed this item
 	 */
	protected $_hasReviewed	= NULL;

	/**
	 * Already reviewed?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function hasReviewed( $member=NULL )
	{
		/* Check cache */
		if( $this->_hasReviewed !== NULL )
		{
			return $this->_hasReviewed;
		}

		$member = $member ?: \IPS\Member::loggedIn();
	
		$reviewClass = static::$reviewClass;
		$idColumn = static::$databaseColumnId;
	
		$this->_hasReviewed	= \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, array(
				array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=?', $member->member_id ),
				array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn )
		) )->first();
		return $this->_hasReviewed;
	}
	
	/**
	 * Can Comment/Review
	 *
	 * @param	string				$type	Type
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	protected function canCommentReview( $type, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Are we restricted from posting completely? */
		if ( $member->restrict_post )
		{
			return FALSE;
		}

		/* Or have an unacknowledged warning? */
		if ( $member->members_bitoptions['unacknowledged_warnings'] )
		{
			return FALSE;
		}

		/* Is this locked? */
		if ( ( $this instanceof \IPS\Content\Lockable and $this->locked() ) or ( $this instanceof \IPS\Content\Polls and $this->getPoll() and $this->getPoll()->poll_only ) )
		{
			if ( !$member->member_id )
			{
				return FALSE;
			}

			$container = NULL;
			if ( method_exists( $this, 'container' ) )
			{
				try
				{
					$container = $this->container();
				}
				catch ( \BadMethodCallException $e ) { }
			}

			return ( static::modPermission( 'reply_to_locked', $member, $container ) and $this->can( $type, $member ) );
		}

		/* Check permissions as normal */
		return $this->can( $type, $member );
	}
	
	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member		The member posting
	 * @param	\IPS\Node\Model	$container	The container
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL )
	{
		return in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) and $member->moderateNewContent();
	}
	
	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member	The member posting
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member )
	{
		return in_array( 'IPS\Content\Hideable', class_implements( static::$commentClass ) ) and $member->moderateNewContent();
	}
	
	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member	The member posting
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member )
	{
		return in_array( 'IPS\Content\Hideable', class_implements( static::$reviewClass ) ) and $member->moderateNewContent();
	}
	
	/**
	 * @brief	Cached calculated average review rating
	 */
	protected $_averageReviewRating = NULL;

	/**
	 * Get average review rating
	 *
	 * @return	int
	 */
	public function averageReviewRating()
	{
		if( $this->_averageReviewRating !== NULL )
		{
			return $this->_averageReviewRating;
		}

		$reviewClass = static::$reviewClass;
		$idColumn = static::$databaseColumnId;
		
		$where = array();
		$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn );
		if ( in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
		{
			if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 0 );
			}
		}
		
		$this->_averageReviewRating = round( \IPS\Db::i()->select( 'AVG(' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['rating'] . ')', $reviewClass::$databaseTable, $where )->first() );
		
		return $this->_averageReviewRating;
	}
	
	/**
	 * @brief	Cached last commenter
	 */
	protected $_lastCommenter	= NULL;

	/**
	 * Get last comment author
	 *
	 * @return	\IPS\Member
	 * @throws	\BadMethodCallException
	 */
	public function lastCommenter()
	{
		if ( !isset( static::$commentClass ) )
		{
			throw new \BadMethodCallException;
		}

		if( $this->_lastCommenter === NULL )
		{
			if ( isset( static::$databaseColumnMap['last_comment_by'] ) )
			{
				$this->_lastCommenter	= \IPS\Member::load( $this->mapped('last_comment_by') );
			}
			else
			{
				$_lastComment = $this->comments( 1, 0, 'date', 'desc' );

				if( $_lastComment !== NULL )
				{
					$this->_lastCommenter	= $this->comments( 1, 0, 'date', 'desc' )->author();
				}
				else
				{
					$this->_lastCommenter	= new \IPS\Member;
				}
			}
		}

		return $this->_lastCommenter;
	}

	/**
	 * Resync the comments/unapproved comment counts
	 *
	 * @return void
	 */
	public function resyncCommentCounts()
	{
		$idColumn     = static::$databaseColumnId;
		$commentClass = static::$commentClass;

		/* Number of comments */
		if ( isset( static::$databaseColumnMap['num_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			
			if ( in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . ' IN( 0, 2 )' ); # 2 means the parent is hidden but the post itself is not
				}
			}

			if ( $commentClass::commentWhere() !== NULL )
			{
				$where[] = $commentClass::commentWhere();
			}

			$numCommentsField        = static::$databaseColumnMap['num_comments'];
			$this->$numCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where )->first();
		}
		if ( isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 0 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 1 );
				}
			}

			if ( $commentClass::commentWhere() !== NULL )
			{
				$where[] = $commentClass::commentWhere();
			}

			$numUnapprovedCommentsField        = static::$databaseColumnMap['unapproved_comments'];
			$this->$numUnapprovedCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where )->first();
		}

		$this->save();

	}
		
	/**
	 * Resync last comment
	 *
	 * @return	void
	 */
	public function resyncLastComment()
	{
		$columns = array( 'last_comment', 'last_comment_by', 'last_comment_name' );
		$resync = FALSE;
		foreach ( $columns as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$resync = TRUE;
			}
		}
		
		if ( $resync )
		{
			try
			{
				$comment = $this->comments( 1, 0, 'date', 'desc', NULL, FALSE );
				if ( !$comment )
				{
					throw new \UnderflowException;
				}
				
				if ( isset( static::$databaseColumnMap['last_comment'] ) )
				{
					$lastCommentField = static::$databaseColumnMap['last_comment'];
					if ( is_array( $lastCommentField ) )
					{
						foreach ( $lastCommentField as $column )
						{
							$this->$column = $comment->mapped('date');
						}
					}
					else
					{
						if ( !is_null( $comment ) )
						{
							$this->$lastCommentField = $comment->mapped('date');
						}
						else
						{
							$this->$lastCommentField = $this->date;
						}
					}
				}
				if ( isset( static::$databaseColumnMap['last_comment_by'] ) )
				{
					$lastCommentByField = static::$databaseColumnMap['last_comment_by'];
					$this->$lastCommentByField = (int) $comment->author()->member_id;
				}
				if ( isset( static::$databaseColumnMap['last_comment_name'] ) )
				{
					$lastCommentNameField = static::$databaseColumnMap['last_comment_name'];
					$this->$lastCommentNameField = ( !$comment->author()->member_id and isset( $comment::$databaseColumnMap['author_name'] ) ) ? $comment->mapped('author_name') : $comment->author()->name;
				}
			}
			catch ( \UnderflowException $e )
			{
				foreach ( $columns as $c )
				{
					if ( $c === 'last_comment' and isset( static::$databaseColumnMap['last_comment'] ) and is_array( static::$databaseColumnMap['last_comment'] ) )
					{
						$lastCommentField = static::$databaseColumnMap['last_comment'];
						if ( is_array( $lastCommentField ) )
						{
							foreach ( $lastCommentField as $col )
							{
								$this->$col = 0;
							}
						}
					}
					else if( $c === 'last_comment_by' )
					{
						$field        = static::$databaseColumnMap[$c];
						$this->$field = 0;
					}
					else
					{
						if ( isset( static::$databaseColumnMap[$c] ) )
						{
							$field        = static::$databaseColumnMap[$c];
							$this->$field = NULL;
						}
					}
				}
			}

			$this->save();
		}
	}
	
	/**
	 * Resync last review
	 *
	 * @return	void
	 */
	public function resyncLastReview()
	{
		$columns = array( 'last_review', 'last_review_by', 'last_review_name' );
		$resync = FALSE;
		foreach ( $columns as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$resync = TRUE;
			}
		}
		
		if ( $resync )
		{
			try
			{
				$review = $this->reviews( 1, 0, 'date', 'desc', NULL, FALSE );
				
				if ( isset( static::$databaseColumnMap['last_review'] ) )
				{
					$lastReviewField = static::$databaseColumnMap['last_review'];
					if ( is_array( $lastReviewField ) )
					{
						foreach ( $lastReviewField as $column )
						{
							$this->$column = $review->mapped('date');
						}
					}
					else
					{
						if ( !is_null( $review ) )
						{
							$this->$lastReviewField = $review->mapped('date');
						}
						else
						{
							$this->$lastReviewField = $this->date;
						}
					}
				}
				if ( isset( static::$databaseColumnMap['last_review_by'] ) )
				{
					$lastReviewByField = static::$databaseColumnMap['last_review_by'];
					$this->$lastReviewByField = ( is_null( $review ) ? NULL : $review->author()->member_id );
				}
				if ( isset( static::$databaseColumnMap['last_review_name'] ) )
				{
					$lastReviewNameField = static::$databaseColumnMap['last_review_name'];
					$this->$lastReviewNameField = ( is_null( $review ) ? NULL : ( ( !$review->author()->member_id and isset( $review::$databaseColumnMap['author_name'] ) ) ? $review->mapped('author_name') : $review->author()->name ) );
				}
			}
			catch ( \UnderflowException $e )
			{
				if ( is_array( $columns ) )
				{
					foreach ( $columns as $c )
					{
						if ( isset( static::$databaseColumnMap[ $c ] ) )
						{
							$field = static::$databaseColumnMap[ $c ];
							$this->$field = NULL;
						}
					}
				}
				else
				{
					if ( isset( static::$databaseColumnMap[ $column ] ) )
					{
						$field = static::$databaseColumnMap[ $column ];
						$this->$field = NULL;
					}
				}
			}

			$this->save();
		}
	}
	
	/**
	 * @brief	Item counts
	 */
	protected static $itemCounts = array();
	
	/**
	 * @brief	Comment counts
	 */
	protected static $commentCounts = array();
	
	/**
	 * @brief	Review counts
	 */
	protected static $reviewCounts = array();
	
	/**
	 * Total item count (including children)
	 *
	 * @param	\IPS\Node\Model	$container			The container
	 * @param	bool			$includeItems		If TRUE, items will be included (this should usually be true)
	 * @param	bool			$includeComments	If TRUE, comments will be included
	 * @param	bool			$includeReviews		If TRUE, reviews will be included
	 * @param	int				$depth				Used to keep track of current depth to avoid going too deep
	 * @return	int|NULL|string	When depth exceeds 10, will return "NULL" and initial call will return something like "100+"
	 * @note	This method may return something like "100+" if it has lots of children to avoid exahusting memory. It is intended only for display use
	 * @note	This method includes counts of hidden and unapproved content items as well
	 */
	public static function contentCount( \IPS\Node\Model $container, $includeItems=TRUE, $includeComments=FALSE, $includeReviews=FALSE, $depth=0 )
	{
		/* Are we in too deep? */
		if ( $depth > 3 )
		{
			return '+';
		}

		/* Generate a key */
		$_key	= md5( get_class( $container ) . $container->_id );
		
		/* Count items */
		$count = 0;
		if( $includeItems )
		{
			if ( $container->_items === NULL )
			{
				if ( !isset( static::$itemCounts[ $_key ] ) )
				{
					$_count = static::getItemsWithPermission( array( array( static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $container->_id ) ), NULL, 1, 'read', NULL, 0, NULL, FALSE, FALSE, FALSE, TRUE );

					$_key = md5( get_class( $container ) . $container->_id );
					static::$itemCounts[ $_key ][ $container->_id ] = $_count['cnt'];
				}

				if ( isset( static::$itemCounts[ $_key ][ $container->_id ] ) )
				{
					$count += static::$itemCounts[ $_key ][ $container->_id ];
				}
			}
			else
			{
				$count += $container->_items;
			}
		}

		/* Count comments */
		if ( $includeComments )
		{
			if ( $container->_comments === NULL )
			{
				if ( !isset( static::$commentCounts ) )
				{
					$commentClass = static::$commentClass;
					static::$commentCounts[ $_key ] = iterator_to_array( \IPS\Db::i()->select(
						'COUNT(*) AS count, ' . static::$databasePrefix . static::$databaseColumnMap['container'],
						$commentClass::$databaseTable,
						NULL,
						NULL,
						NULL,
						static::$databasePrefix . static::$databaseColumnMap['container']
					)->join( static::$databaseTable, $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . static::$databasePrefix . static::$databaseColumnId )
					->setKeyField( static::$databasePrefix . static::$databaseColumnMap['container'] )
					->setValueField('count') );
				}
				
				if ( isset( static::$commentCounts[ $_key ][ $container->_id ] ) )
				{
					$count += static::$commentCounts[ $_key ][ $container->_id ];
				}
			}
			else
			{
				$count += $container->_comments;
			}
		}
		
		/* Count Reviews */
		if ( $includeReviews )
		{
			if ( $container->_reviews === NULL )
			{
				if ( !isset( static::$reviewCounts ) )
				{
					$reviewClass = static::$commentClass;
					static::$reviewCounts[ $_key ] = iterator_to_array( \IPS\Db::i()->select(
						'COUNT(*) AS count, ' . static::$databasePrefix . static::$databaseColumnMap['container'],
						$reviewClass::$databaseTable,
						NULL,
						NULL,
						NULL,
						static::$databasePrefix . static::$databaseColumnMap['container']
					)->join( static::$databaseTable, $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=' . static::$databasePrefix . static::$databaseColumnId )
					->setKeyField( static::$databasePrefix . static::$databaseColumnMap['container'] )
					->setValueField('count') );
				}

				if ( isset( static::$reviewCounts[ $_key ][ $container->_id ] ) )
				{
					$count += static::$reviewCounts[ $_key ][ $container->_id ];
				}
			}
			else
			{
				$count += $container->_reviews;
			}
		}
		
		/* Add Children */
		$childDepth	= $depth++;
		foreach ( $container->children() as $child )
		{
			$toAdd = static::contentCount( $child, $includeItems, $includeComments, $includeReviews, $childDepth );
			if ( is_string( $toAdd ) )
			{
				return $count . '+';
			}
			else
			{
				$count += $toAdd;
			}
			
		}
		return $count;
	}
	
	/**
	 * @brief	Actions to show in comment multi-mod
	 * @see		\IPS\Content\Item::commentMultimodActions()
	 */
	protected $_commentMultiModActions;
	
	/**
	 * @brief	Actions to show in review multi-mod
	 * @see		\IPS\Content\Item::reviewMultimodActions()
	 */
	protected $_reviewMultiModActions;
	
	/**
	 * Actions to show in comment multi-mod
	 *
	 * @param	\IPS\Member	$member	Member (NULL for currently logged in member)
	 * @return	array
	 */
	public function commentMultimodActions( \IPS\Member $member = NULL )
	{
		if ( $this->_commentMultiModActions === NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$this->_commentMultiModActions = array();
			if ( isset( static::$commentClass ) )
			{
				$this->_commentMultiModActions = static::_commentReviewMultimodActions( static::$commentClass, $member );
			}
		}
		
		return $this->_commentMultiModActions;
	}
	
	/**
	 * Actions to show in review multi-mod
	 *
	 * @param	\IPS\Member	$member	Member (NULL for currently logged in member)
	 * @return	array
	 */
	public function reviewMultimodActions( \IPS\Member $member = NULL )
	{
		if ( $this->_reviewMultiModActions === NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$this->_reviewMultiModActions = array();
			if ( isset( static::$reviewClass ) )
			{
				$this->_reviewMultiModActions = static::_commentReviewMultimodActions( static::$reviewClass, $member );
			}
		}
		
		return $this->_reviewMultiModActions;
	}
	
	/**
	 * Actions to show in comment/review multi-mod
	 *
	 * @param	string		$class 	The class
	 * @param	\IPS\Member	$member	Member (NULL for currently logged in member)
	 * @return	array
	 */
	protected static function _commentReviewMultimodActions( $class, \IPS\Member $member )
	{
		$return = array();
		$check = array();
		$check[] = 'split_merge';
		if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			$check[] = 'hide';
			$check[] = 'unhide';
		}
		$check[] = 'delete';
		
		foreach ( $check as $k )
		{
			if ( $class::modPermission( $k, $member ) )	
			{
				$return[] = $k;
			}		
		}
		
		return $return;
	}
	
	/**
	 * Get table showing moderation actions
	 *
	 * @return	\IPS\Helpers\Table\Db
	 * @throws	\DomainException
	 */
	public function moderationTable()
	{
		if( !\IPS\Member::loggedIn()->modPermission('can_view_moderation_log') )
		{
			throw new \DomainException;
		}
		
		$idColumn = static::$databaseColumnId;
		$where = array( 'class=? AND item_id=?', get_class( $this ), $this->$idColumn );
	
		$table = new \IPS\Helpers\Table\Db( 'core_moderator_logs', $this->url( 'modLog' ), $where );
		$table->langPrefix = 'modlogs_';
		$table->include = array( 'member_id', 'action', 'ip_address', 'ctime' );
		$table->mainColumn = 'action';
		
		$table->tableTemplate	= array( \IPS\Theme::i()->getTemplate( 'moderationLog', 'core' ), 'table' );
		
		$table->parsers = array(
				'action'	=> function( $val, $row )
				{
					if ( $row['lang_key'] )
					{
						$langKey = $row['lang_key'];
						$params = array();
						foreach ( json_decode( $row['note'], TRUE ) as $k => $v )
						{
							$params[] = $v ? \IPS\Member::loggedIn()->language()->addToStack( $k ) : $k;
						}

						return \IPS\Member::loggedIn()->language()->addToStack( $langKey, FALSE, array( 'sprintf' => $params ) );
					}
					else
					{
						return $row['note'];
					}
				}
		);
		$table->sortBy = $table->sortBy ?: 'ctime';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		return $table;
	}
			
	/* !Permissions */
	
	/**
	 * Get permission index ID
	 *
	 * @return	int|NULL
	 */
	public function permId()
	{
		if ( $this instanceof \IPS\Content\Permissions )
		{
			$permissions = $this->container()->permissions();
			return $permissions['perm_id'];
		}
		
		return NULL;
	}
	
	/**
	 * Check permissions
	 *
	 * @param	mixed								$permission		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member			The member or group to check (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in map
	 */
	public function can( $permission, $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* If the member is banned they can't do antyhing? */
		if ( !$member->group['g_view_board'] )
		{
			return FALSE;
		}

		/* Node-related permissions... */
		if ( $this instanceof \IPS\Content\Permissions )
		{
			/* If we can find the node... */
			try
			{
				/* Check with the node if we can do what we're trying to do */
				if( !$this->container()->can( $permission, $member ) )
				{
					return FALSE;
				}
				
				/* If we're trying to *read* a content item (or in fact anything, but we only check read since if we managed to access it we don't need to check this again for other permissions),
				   check if we can *view* (i.e. access) all of the parents. This is so if an admin, for example, removes a group's permission to view (i.e. access) a node, they will not be able
				   to access content within it. Though this is not in line with conventional ACL practices, it is how the suite has always worked and we don't want to mess up permissions for upgrades  */
				if ( $permission === 'read' )
				{
					foreach( $this->container()->parents() as $parent )
					{
						if( !$parent->can( 'view', $member ) )
						{
							return FALSE;
						}
					}
				}
			}
			/* If the node has been lost, assume we can do nothing */
			catch ( \OutOfRangeException $e )
			{
				return FALSE;
			}
		}
		
		/* Still here? It must be okay */
		return TRUE;
	}
	
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		if ( $this instanceof \IPS\Content\Hideable and $this->hidden() and !static::canViewHiddenItems( $member, $this->containerWrapper() ) and ( $this->hidden() !== 1 or $this->author() !== $member ) )
		{
			return FALSE;
		}
		
		if ( $this instanceof \IPS\Content\FuturePublishing )
		{
			$future = static::$databaseColumnMap['is_future_entry'];
			if ( $this->$future == 1 AND !static::canViewFutureItems( $member, $this->containerWrapper() ) )
			{
				return FALSE;
			}
		}

		return $this->can( 'read', $member );
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		try
		{
			return $this->container()->searchIndexPermissions();
		}
		catch ( \BadMethodCallException $e )
		{
			return '*';
		}
	}
	
	/**
	 * Online List Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function onlineListPermissions()
	{
		if ( $this->hidden() )
		{
			return '0';
		}
		return $this->searchIndexPermissions();
	}
	
	/* !Moderation */
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $member->member_id )
		{
			/* Do we have moderator permission to edit stuff in the container? */
			if ( static::modPermission( 'edit', $member, $this->containerWrapper() ) )
			{
				return TRUE;
			}
			
			/* Can the member edit their own content? */
			if ( $member->member_id == $this->author()->member_id and $member->group['g_edit_posts'] )
			{
				if ( !$member->group['g_edit_cutoff'] )
				{
					return TRUE;
				}
				else
				{
					if( \IPS\DateTime::ts( $this->mapped('date') )->add( new \DateInterval( "PT{$member->group['g_edit_cutoff']}M" ) ) > \IPS\DateTime::create() )
					{
						return TRUE;
					}
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Can pin?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canPin( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Pinnable ) or $this->mapped('pinned') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'pin', $member, $this->container() ) );
	}
	
	/**
	 * Can unpin?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnpin( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Pinnable ) or !$this->mapped('pinned') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'unpin', $member, $this->container() ) );
	}
	
	/**
	 * Can feature?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canFeature( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Featurable ) or $this->mapped('featured') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'feature', $member, $this->container() ) );
	}
	
	/**
	 * Can unfeature?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnfeature( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Featurable ) or !$this->mapped('featured') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'unfeature', $member, $this->container() ) );
	}
	
	/**
	 * Is locked?
	 *
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function locked()
	{
		if ( $this instanceof \IPS\Content\Lockable )
		{
			if ( isset( static::$databaseColumnMap['locked'] ) )
			{
				return $this->mapped('locked');
			}
			else
			{
				return ( $this->mapped('status') == 'closed' );
			}
		}
		else
		{
			throw new \BadMethodCallException;
		}
	}
	
	/**
	 * Can lock?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canLock( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Lockable ) or $this->locked() )
		{
			return FALSE;
		}
		
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();
			}
			catch ( \BadMethodCallException $e ) { }
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'lock', $member, $container ) );
	}
	
	/**
	 * Can unlock?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnlock( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Lockable ) or !$this->locked() )
		{
			return FALSE;
		}
		
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();
			}
			catch ( \BadMethodCallException $e ) { }
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'unlock', $member, $container ) );
	}
	
	/**
	 * Can hide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canHide( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) or $this->hidden() )
		{
			return FALSE;
		}
		
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();
			}
			catch ( \BadMethodCallException $e ) { }
		}
				
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and ( static::modPermission( 'hide', $member, $container ) or ( $member->member_id == $this->author()->member_id and $member->group['gbw_soft_delete_own'] ) ) );
	}
	
	/**
	 * Can unhide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnhide( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) or !$this->hidden() )
		{
			return FALSE;
		}

		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();
			}
			catch ( \BadMethodCallException $e ) { }
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and ( static::modPermission( 'unhide', $member, $container ) ) );
	}
	
	/**
	 * Can view hidden items?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 * @note	If called without passing $container, this method falls back to global "can view hidden content" moderator permission which isn't always what you want - pass $container if in doubt
	 */
	public static function canViewHiddenItems( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $container ? static::modPermission( 'view_hidden', $member, $container ) : $member->modPermission( "can_view_hidden_content" );
	}

	/**
	 * Can view hidden comments on this item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canViewHiddenComments( $member=NULL )
	{		
		$commentClass = static::$commentClass;

		return $commentClass::modPermission( 'view_hidden', $member, $this->containerWrapper() );
	}
	
	/**
	 * Can view hidden reviews on this item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canViewHiddenReviews( $member=NULL )
	{
		$reviewClass = static::$reviewClass;

		return $reviewClass::modPermission( 'view_hidden', $member, $this->containerWrapper() );
	}

	/**
	 * Can move?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMove( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		try
		{
			return ( $member->member_id and $this->container() and ( static::modPermission( 'move', $member, $this->container() ) ) );
		}
		catch( \BadMethodCallException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * Can Merge?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMerge( $member=NULL )
	{
		if ( static::$firstCommentRequired )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			return ( $member->member_id and ( static::modPermission( 'split_merge', $member, $this->container() ) ) );
		}
		return FALSE;
	}
	
	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Guests can never delete */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Can we delete our own content? */
		if ( $member->member_id == $this->author()->member_id and $member->group['g_delete_own_posts'] )
		{
			return TRUE;
		}
		
		/* What about this? */
		try
		{
			return static::modPermission( 'delete', $member, $this->container() );
		}
		catch ( \BadMethodCallException $e )
		{
			return $member->modPermission( "can_delete_content" );
		}
		
		return FALSE;
	}
	
	/**
	 * Syncing to run when hiding
	 *
	 * @return	void
	 */
	public function onHide()
	{
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();

				if ( ! $this->isFutureDate() )
				{
					$container->_items = $container->_items - 1;
				}

				if ( isset( static::$commentClass ) )
				{
					$container->_comments = $container->_comments - $this->mapped('num_comments');
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					$container->_reviews = $container->_reviews - $this->mapped('num_reviews');
					$container->setLastReview();
				}
				
				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}
	
	/**
	 * Syncing to run when unhiding
	 *
	 * @param	bool	$approving	If true, is being approved for the first time
	 * @return	void
	 */
	public function onUnhide( $approving )
	{
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();

				if ( ! $this->isFutureDate() )
				{
					if ( $approving and $container->_unapprovedItems !== NULL )
					{
						$container->_unapprovedItems = $container->_unapprovedItems - 1;
					}

					$container->_items = $container->_items + 1;
				}

				if ( isset( static::$commentClass ) )
				{
					$container->_comments = $container->_comments + $this->mapped('num_comments');
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					$container->_reviews = $container->_reviews + $this->mapped('num_reviews');
					$container->setLastReview();
				}
				
				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}


	/**
	 * Warning Reference Key
	 *
	 * @return	string|NULL
	 */
	public function warningRef()
	{
		/* If the member cannot warn, return NULL so we're not adding ugly parameters to the profile URL unnecessarily */
		if ( !\IPS\Member::loggedIn()->modPermission('mod_can_warn') )
		{
			return NULL;
		}
		
		$idColumn = static::$databaseColumnId;
		return base64_encode( json_encode( array( 'app' => static::$application, 'module' => static::$module, 'id_1' => $this->$idColumn ) ) );
	}
	
	/* !Sharelinks */
	
	/**
	 * Can share
	 *
	 * @return boolean
	 */
	public function canShare()
	{
		if ( !( $this instanceof \IPS\Content\Shareable ) )
		{
			return FALSE;
		}
		
		if ( !$this->canView( \IPS\Member::load( 0 ) ) )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	 
	/**
	 * Return sharelinks for this item
	 *
	 * @return array
	 */
	public function sharelinks()
	{
		if( !count( $this->sharelinks ) )
		{
			if ( $this->canShare() )
			{
				if ( $this instanceof Shareable )
				{
					$shareService	= 'IPS\core\ShareLinks\Service';
	
					foreach( $shareService::shareLinks() as $node )
					{
						if( $node->enabled and ( $node->groups === "*" or \IPS\Member::loggedIn()->inGroup( explode( ',', $node->groups ) ) ) )
						{
							if( file_exists( \IPS\ROOT_PATH . '/system/Content/ShareServices/' . ucwords( $node->key ) . '.php' ) )
							{
								$className	= "IPS\\Content\\ShareServices\\" . ucwords( $node->key );
								$this->sharelinks[ $node->key ]	= new $className( $this->url(), $this->mapped('title') );
							}
						}
					}
				}
			}
			else
			{
				$this->sharelinks = array();
			}
		}
		
		return $this->sharelinks;
	}
	
	/* !ReadMarkers */
	
	/**
	 * Read Marker cache
	 */
	protected $unread = NULL;
	
	/**
	 * Does a container contain unread items?
	 *
	 * @param	\IPS\Node\Model		$container	The container
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool|NULL
	 */
	public static function containerUnread( \IPS\Node\Model $container, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* We only do this if the thing is tracking markers */
		if ( !in_array( 'IPS\Content\ReadMarkers', class_implements( get_called_class() ) ) or !$member->member_id )
		{
			return NULL;
		}
		
		/* What was the last time something was posted in here? */
		$lastCommentTime = $container->getLastCommentTime();

		if ( $lastCommentTime === NULL )
		{
			/* Do we have any children to be concerned about? */
			foreach( $container->children( 'view', $member ) AS $child )
			{
				if ( static::containerUnread( $child, $member ) )
				{
					return TRUE;
				}
			}
			
			return FALSE;
		}
		
		/* Was that after the last time we marked this forum read? */
		$markers = $member->markersResetTimes( static::$application );

		if ( isset( $markers[ $container->_id ] ) )
		{
			if ( $markers[ $container->_id ] < $lastCommentTime->getTimestamp() )
			{
				return TRUE;
			}
		}
		else if ( $member->marked_site_read >= $lastCommentTime->getTimestamp() )
		{
			return FALSE;
		}
		else
		{
			if( $container->_items !== 0 or $container->_comments !== 0 )
			{
				return TRUE;
			}
		}
		
		/* Check children */
		foreach ( $container->children( 'view', $member ) as $child )
		{
			if ( static::containerUnread( $child, $member ) )
			{
				return TRUE;
			}
		}
		
		/* Still here? It's read */
		return FALSE;
	}
	
	/**
	 * Is unread?
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	int|NULL	0 = read. -1 = never read. 1 = updated since last read. NULL = unsupported
	 * @note	When a node is marked read, we stop noting which individual content items have been read. Therefore, -1 vs 1 is not always accurate but rather goes on if the item was created
	 */
	public function unread( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* We only do this if the thing is tracking markers */
		if ( !( $this instanceof ReadMarkers ) or !$member->member_id )
		{
			return NULL;
		}
						
		/* Is it read? */
		if ( $this->unread === NULL )
		{
			$latestThing = 0;
			foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
			{
				if ( isset( static::$databaseColumnMap[ $k ] ) and ( $this->mapped( $k ) < time() AND $this->mapped( $k ) > $latestThing ) )
				{
					$latestThing = $this->mapped( $k );
				}
			}
						
			$resetTimes = $member->markersResetTimes( static::$application );
			$markers = $member->markersItems( static::$application, static::makeMarkerKey( $this->containerWrapper() ) );
			
			$idColumn = static::$databaseColumnId;
			if( !isset( $markers[ $this->$idColumn ] ) )
			{
				if( $this->containerWrapper( TRUE ) )
				{
					$resetTime = ( isset( $resetTimes[ $this->container()->id ] ) AND $resetTimes[ $this->container()->id ] > $member->marked_site_read ) ? $resetTimes[ $this->container()->id ] : $member->marked_site_read;
				}
				else
				{
					$resetTime = ( $resetTimes > $member->marked_site_read ) ? $resetTimes : $member->marked_site_read;
				}

				if ( !is_null( $resetTime ) and $resetTime >= $latestThing )
				{
					$this->unread = 0;
				}
				else
				{
					if ( !is_null( $resetTime ) and $resetTime > $this->mapped('date') )
					{
						$this->unread = 1;
					}
					else
					{
						$this->unread = -1;
					}
				}
			}
			elseif( isset( $markers[ $this->$idColumn ] ) AND $markers[ $this->$idColumn ] < $latestThing )
			{
				$this->unread = 1;
			}
			else
			{
				$this->unread = 0;
			}
		}
		return $this->unread;
	}
	
	/**
	 * @brief	Time last read cache
	 */
	protected $timeLastRead = array();
	
	/**
	 * Time last read
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	\IPS\DateTime|NULL
	 * @throws	\BadMethodCallException
	 */
	public function timeLastRead( \IPS\Member $member = NULL )
	{
		/* We only do this if the thing is tracking markers */
		if ( !( $this instanceof ReadMarkers ) )
		{
			throw new \BadMethodCallException;
		}
		
		/* Work out the member */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return NULL;
		}
		
		/* Get it */
		if ( !isset( $this->timeLastRead[ $member->member_id ] ) )
		{
			/* Check the time the entire site was marked read */
			$times = array();
			$times[] =  $member->marked_site_read;
			
			/* Check the reset time */
			if ( $container = $this->containerWrapper() )
			{
				$resetTimes = $member->markersResetTimes( static::$application );
				if ( isset( $resetTimes[ $container->_id ] ) )
				{
					$times[] = $resetTimes[ $container->_id ];
				}
			}
	
			/* Check the actual item */
			$markers = $member->markersItems( static::$application, static::makeMarkerKey( $container ) );
			$idColumn = static::$databaseColumnId;
			if ( isset( $markers[ $this->$idColumn ] ) )
			{
				$times[] = ( is_array( $markers[ $this->$idColumn ] ) ) ? max( $markers[ $this->$idColumn ] ) : $markers[ $this->$idColumn ];
			}
			
			/* Set the highest of those */
			$this->timeLastRead[ $member->member_id ] = ( count( $times ) ? max( $times ) : NULL );
		}
		
		/* Return */
		return $this->timeLastRead[ $member->member_id ] ? \IPS\DateTime::ts( $this->timeLastRead[ $member->member_id ] ) : NULL;
	}
	
	/**
	 * Mark as read
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @param	int|NULL			$time	The timestamp to set (or NULL for current time)
     * @param	mixed				$extraContainerWhere	Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	void
	 */
	public function markRead( \IPS\Member $member = NULL, $time = NULL, $extraContainerWhere = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$time	= $time ?: time();

		if ( $this instanceof ReadMarkers and $member->member_id )
		{
			/* Mark this one read */
			$idColumn	= static::$databaseColumnId;
			$key		= static::makeMarkerKey( $this->containerWrapper() );
			$readArray	= $member->markersItems( static::$application, $key );

			if ( isset( $member->markers[ static::$application ][ $key ] ) )
			{
				$marker = $member->markers[ static::$application ][ $key ];

				/* We've already read this topic more recently */
				if( isset( $readArray[ $this->$idColumn ] ) AND $readArray[ $this->$idColumn ] >= $time )
				{
					return;
				}

				$readArray[ $this->$idColumn ] = $time;

				$readArray = array_slice( $readArray, ( count( $readArray ) > static::STORAGE_CUTOFF ) ? (int) '-' . static::STORAGE_CUTOFF : 0, ( count( $readArray ) > static::STORAGE_CUTOFF ) ? NULL : static::STORAGE_CUTOFF, TRUE );

				$toStore	= array( 'update', array( 'item_read_array' => json_encode( $readArray ) ), array( 'item_key=? AND item_member_id=? AND item_app=?', $key, $member->member_id, static::$application ) );
            }
			else
			{
				$readArray = array( $this->$idColumn => $time );
				$marker = array(
					'item_key'			=> $key,
					'item_member_id'	=> $member->member_id,
					'item_app'			=> static::$application,
					'item_read_array'	=> json_encode( $readArray ),
					'item_global_reset'	=> $member->marked_site_read,
					'item_app_key_1'	=> $this->mapped('container') ?: 0,
					'item_app_key_2'	=> static::getItemMarkerKey( 2 ),
					'item_app_key_3'	=> static::getItemMarkerKey( 3 ),
				);

				$toStore	= array( 'insert', $marker );
			}

			/* Reset cached markers in the member object */
			$member->markers[ static::$application ][ $key ] = $marker;
			
			/* Have we now read the whole node? */
			$whereClause = array();

			if ( count( $readArray ) > 0 )
			{
				$whereClause[] = array( static::$databasePrefix . $idColumn . ' NOT IN(' . implode( ',', array_keys( $readArray ) ) . ')' );
			}

			if( $this->containerWrapper() )
			{
				$whereClause[]	= array( static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id );
			}

			if ( in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) )
			{
				if ( !static::canViewHiddenItems( $member, $this->containerWrapper() ) )
				{
					if ( isset( static::$databaseColumnMap['approved'] ) )
					{
						$whereClause[] = array( static::$databasePrefix . static::$databaseColumnMap['approved'] . '=?', 1 );
					}
					elseif ( isset( static::$databaseColumnMap['hidden'] ) )
					{
						$whereClause[] = array( static::$databasePrefix . static::$databaseColumnMap['approved'] . '=?', 0 );
					}
				}
			}

            if( $extraContainerWhere !== NULL )
            {
                if ( !is_array( $extraContainerWhere ) or !is_array( $extraContainerWhere[0] ) )
                {
                    $extraContainerWhere = array( $extraContainerWhere );
                }
                $whereClause = array_merge( $whereClause, $extraContainerWhere );
            }

			if ( $marker )
			{
				$subWhere = array();
				foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
				{
					if ( isset( static::$databaseColumnMap[ $k ] ) )
					{
						if ( is_array( static::$databaseColumnMap[ $k ] ) )
						{
							$subWhere[] = static::$databasePrefix . static::$databaseColumnMap[ $k ][0] . '>' . $marker['item_global_reset'];
						}
						else
						{
							$subWhere[] = static::$databasePrefix . static::$databaseColumnMap[ $k ] . '>' . $marker['item_global_reset'];
						}
					}
				}

				if( count( $subWhere ) )
				{
					$whereClause[]	= array( '(' . implode( ' OR ', $subWhere ) . ')' );
				}
			}

			$unreadCount = \IPS\Db::i()->select(
				'COUNT(*) as count',
				static::$databaseTable,
				$whereClause
			)->first();

			if ( !$unreadCount AND $this->containerWrapper() )
			{
				static::markContainerRead( $this->containerWrapper(), NULL, FALSE );
			}
			elseif( $toStore !== NULL )
			{
				if( $toStore[0] == 'update' )
				{
					\IPS\Db::i()->update( 'core_item_markers', $toStore[1], $toStore[2] );
				}
				else
				{
					\IPS\Db::i()->replace( 'core_item_markers', $toStore[1] );
				}
			}
		}
	}
	
	/**
	 * Mark container as read
	 *
	 * @param	\IPS\Node\Model		$container	The container
	 * @param	\IPS\Member|NULL	$member		The member (NULL for currently logged in member)
	 * @param	bool				$children	Whether to mark children as read (default) or not as well
	 * @param	array|null			$marker		Marker data if already queried
	 * @return	void
	 */
	public static function markContainerRead( \IPS\Node\Model $container, \IPS\Member $member = NULL, $children = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( in_array( 'IPS\Content\ReadMarkers', class_implements( get_called_class() ) ) and $member->member_id )
		{		
			$key = static::makeMarkerKey( $container );

			\IPS\Db::i()->replace( 'core_item_markers', array(
					'item_key'			=> $key,
					'item_member_id'	=> $member->member_id,
					'item_app'			=> static::$application,
					'item_read_array'	=> json_encode( array() ),
					'item_global_reset'	=> time(),
					'item_app_key_1'	=> $container->id,
					'item_app_key_2'	=> static::getItemMarkerKey( 2 ),
					'item_app_key_3'	=> static::getItemMarkerKey( 3 ),
			) );
			
			if( $children )
			{
				foreach( $container->children( 'view', $member, false ) as $child )
				{
					static::markContainerRead( $child, $member );
				}
			}
		}
	}
	
	/**
	 * Make key
	 *
	 * @param	\IPS\Node\Model	$container	The cotainer
	 * @return	string
	 * @note	We use serialize here which is usually not allowed, however, the value is encoded and never unserialized so there is no security issue.
	 */
	public static function makeMarkerKey( \IPS\Node\Model $container = NULL )
	{
		$keyData = array();
		if ( $container )
		{
			$keyData['item_app_key_1'] = $container->_id;
		}
		
		return md5( \serialize( $keyData ) );
	}

	/**
	 * Find the next unread item in the same container
	 *
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public function nextUnread()
	{
		if ( static::containerUnread( $this->container() ) === TRUE )
		{
			$field			= $this->getDateColumn();
			$idField		= static::$databaseColumnId;

			$resetTimes		= \IPS\Member::loggedIn()->markersResetTimes( static::$application );
			$oldestTime		= time();
			$markers		= array_keys( \IPS\Member::loggedIn()->markersItems( static::$application, static::makeMarkerKey( $this->container() ) ) );

			if( isset( $resetTimes[ $this->mapped('container') ] ) )
			{
				$oldestTime	= $resetTimes[ $this->mapped('container') ];
			}
			/* Global board mark as read */
			else if( is_int( $resetTimes ) )
			{
				$oldestTime = $resetTimes;
			}

			if( count( $markers ) )
			{
				$filters	= array( '( (' . static::$databaseTable . '.' . static::$databasePrefix . $field . ">" . $oldestTime . " AND " . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId . ' NOT IN(' . implode( ',', $markers ) . ') ) OR ' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId . ' IN(' . implode( ',', $markers ) . ') )' );
			}
			else
			{
				$filters	= array( static::$databaseTable . '.' . static::$databasePrefix . $field . ">" . $oldestTime );
			}

			/* Loop through to find an unread topic */
			$limit	= array( 0, 25 );

			while( 1 )
			{
				$increment	= 0;

				foreach( static::getItemsWithPermission( array( 
						$filters,
						array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . "=" . $this->mapped('container') ),
						array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId . " <> " . $this->$idField )
					), static::$databaseTable . '.' . $field . ' DESC', $limit ) as $next )
				{

					if ( $next->unread() )
					{
						return $next;
					}

					$increment++;
				}

				$limit[0]	= $limit[0] + $limit[1];

				if( !$increment )
				{
					break;
				}
			}
		}

		throw new \OutOfRangeException;
	}
	
	/* !\IPS\Helpers\Table */
	
	public $tableHoverUrl = FALSE;
	
	/* !Tags */
	
	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return in_array( 'IPS\Content\Tags', class_implements( get_called_class() ) ) and \IPS\Settings::i()->tags_enabled and !( $member->group['gbw_disable_tagging'] ) and !( $member->members_bitoptions['bw_disable_tagging'] );
	}
	
	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return in_array( 'IPS\Content\Tags', class_implements( get_called_class() ) ) and \IPS\Settings::i()->tags_enabled and \IPS\Settings::i()->tags_can_prefix and !( $member->group['gbw_disable_tagging'] ) and !( $member->group['gbw_disable_prefixes'] ) and !( $member->members_bitoptions['bw_disable_tagging'] ) and !( $member->members_bitoptions['bw_disable_prefixes'] );
	}
	
	/**
	 * Defined Tags
	 *
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	array
	 */
	public static function definedTags( \IPS\Node\Model $container = NULL )
	{
		return \IPS\Settings::i()->tags_predefined ? explode( ',', \IPS\Settings::i()->tags_predefined ) : array();
	}
	
	/**
	 * @brief	Tags cache
	 */
	protected $tags = NULL;
	
	/**
	 * Get prefix
	 *
	 * @param	bool|NULL		Encode returned value
	 * @return	string|NULL
	 */
	public function prefix( $encode=FALSE )
	{
		if ( $this instanceof \IPS\Content\Tags )
		{
			if ( $this->tags === NULL )
			{
				$this->tags();
			}
									
			return isset( $this->tags['prefix'] ) ? ( $encode ) ? rawurlencode( $this->tags['prefix'] ) : $this->tags['prefix'] : NULL;
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Get tags
	 *
	 * @return	array
	 */
	public function tags()
	{
		if ( $this instanceof \IPS\Content\Tags )
		{
			if ( $this->tags === NULL )
			{
				$idColumn = static::$databaseColumnId;
				$this->tags = array( 'tags' => array(), 'prefix' => NULL );
				foreach ( \IPS\Db::i()->select( '*', 'core_tags', array( 'tag_meta_app=? AND tag_meta_area=? AND tag_meta_id=?', static::$application, static::$module, $this->$idColumn ) ) as $tag )
				{
					if ( $tag['tag_prefix'] )
					{
						$this->tags['prefix'] = $tag['tag_text'];
					}
					else
					{
						$this->tags['tags'][] = $tag['tag_text'];
					}
				}
			}

			return ( isset ( $this->tags['tags'] ) ? $this->tags['tags'] : NULL);
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Set tags
	 *
	 * @param	array	$set	The tags (if one has the key "prefix", it will be set as the prefix)
	 * @return	void
	 */
	public function setTags( $set )
	{
		$aaiLookup = $this->tagAAIKey();
		$aapLookup = $this->tagAAPKey();
		$tags = array();
		$prefix = NULL;
		$idColumn = static::$databaseColumnId;
		$this->tags = NULL;
		
		\IPS\Db::i()->delete( 'core_tags', array( 'tag_aai_lookup=?', $aaiLookup ) );
		
		if ( !is_array( $set ) )
		{
			$set = array( $set );
		}
		
		foreach ( $set as $key => $tag )
		{
			\IPS\Db::i()->insert( 'core_tags', array(
				'tag_aai_lookup'		=> $aaiLookup,
				'tag_aap_lookup'		=> $aapLookup,
				'tag_meta_app'			=> static::$application,
				'tag_meta_area'			=> static::$module,
				'tag_meta_id'			=> $this->$idColumn,
				'tag_meta_parent_id'	=> $this->container()->_id,
				'tag_member_id'			=> \IPS\Member::loggedIn()->member_id,
				'tag_added'				=> time(),
				'tag_prefix'			=> $key === 'prefix',
				'tag_text'				=> $tag
			), TRUE );
			
			if ( $key === 'prefix' )
			{
				$prefix = $tag;
			}
			else
			{
				$tags[] = $tag;
			}
		}
					
		\IPS\Db::i()->insert( 'core_tags_cache', array(
			'tag_cache_key'		=> $aaiLookup,
			'tag_cache_text'	=> json_encode( array( 'tags' => $tags, 'prefix' => $prefix ) ),
			'tag_cache_date'	=> time()
		), TRUE );
		
		$containerClass = static::$containerNodeClass;
		if ( isset( $containerClass::$permissionMap['read'] ) )
		{
			$permissions = $containerClass::load( $this->container()->_id )->permissions();
			
			if ( isset( $permissions[ 'perm_' . $containerClass::$permissionMap['read'] ] ) )
			{
				\IPS\Db::i()->insert( 'core_tags_perms', array(
					'tag_perm_aai_lookup'		=> $aaiLookup,
					'tag_perm_aap_lookup'		=> $aapLookup,
					'tag_perm_text'				=> $permissions[ 'perm_' . $containerClass::$permissionMap['read'] ],
					'tag_perm_visible'			=> ( $this->hidden() OR $this->isFutureDate() ) ? 0 : 1,
				), TRUE );
			}
		}

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
	}
	
	/**
	 * Get tag AAI key
	 *
	 * @return	string
	 */
	public function tagAAIKey()
	{
		$idColumn = static::$databaseColumnId;
		return md5( static::$application . ';' . static::$module . ';' . $this->$idColumn );
	}
	
	/**
	 * Get tag AAP key
	 *
	 * @return	string
	 */
	public function tagAAPKey()
	{
		$containerClass = static::$containerNodeClass;
		return md5( $containerClass::$permApp . ';' . $containerClass::$permType . ';' . $this->container()->_id );
	}
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		
		if ( isset( $data[ static::$databaseTable ] ) and is_array( $data[ static::$databaseTable ] ) )
		{
			if ( isset( $data['core_tags_cache'] ) )
			{
				$obj->tags = ! empty( $data['core_tags_cache']['tag_cache_text'] ) ? json_decode( $data['core_tags_cache']['tag_cache_text'], TRUE ) : array( 'tags' => array(), 'prefix' => NULL );
			}
			if ( isset( $data['last_commenter'] ) )
			{
				\IPS\Member::constructFromData( $data['last_commenter'], FALSE );
			}
		}
		
		return $obj;
	}
	
	/* !Follow */
	
	/**
	 * @brief	Cache for current follow data, used on "My Followed Content" screen
	 */
	public $_followData;
	
	/**
	 * Followers
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @param	int|null				$flags			SQL flags to pass to \IPS\Db
	 * @param	int
	 * @return	\IPS\Db\Select
	 * @throws	\BadMethodCallException
	 */
	public function followers( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL, $limit=array( 0, 25 ), $order=NULL, $flags=\IPS\Db::SELECT_SQL_CALC_FOUND_ROWS )
	{
		/* Check this class is followable */
		if ( !( $this instanceof \IPS\Content\Followable ) )
		{
			throw new \BadMethodCallException;
		}
		
		$idColumn = static::$databaseColumnId;

		return static::_followers( mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) ), $this->$idColumn, $privacy, $frequencyTypes, $date, $limit, $order, $flags );
	}
	
	/**
	 * Followers Count
	 */
	protected $followersCount;
	
	/**
	 * Followers Count
	 *
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public function followersCount()
	{
		/* Check this class is followable */
		if ( !( $this instanceof \IPS\Content\Followable ) )
		{
			throw new \BadMethodCallException;
		}
		
		/* Do it and store it in memory */
		if ( $this->followersCount === NULL )
		{
			$idColumn = static::$databaseColumnId;
			$this->followersCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', static::$application, mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) ), $this->$idColumn ) )->first();
		}
		return $this->followersCount;
	}
	
	/**
	 * Container Followers
	 *
	 * @param	\IPS\Node\Model			$container		The container
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @param	int|null				$flags			SQL flags to pass to \IPS\Db
	 * @return	\IPS\Db\Select
	 */
	public static function containerFollowers( \IPS\Node\Model $container, $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL, $limit=array( 0, 25 ), $order=NULL, $flags=\IPS\Db::SELECT_SQL_CALC_FOUND_ROWS )
	{
		/* Check this class is followable */
		if ( !in_array( 'IPS\Content\Followable', class_implements( get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}

		return static::_followers( mb_strtolower( mb_substr( get_class( $container ), mb_strrpos( get_class( $container ), '\\' ) + 1 ) ), $container->_id, $privacy, $frequencyTypes, $date, $limit, $order, $flags );
	}
	
	/**
	 * Container Follower Count
	 *
	 * @param	\IPS\Node\Model	$container		The container
	 * @return	int
	 */
	public static function containerFollowerCount( \IPS\Node\Model $container )
	{
		/* Check this class is followable */
		if ( !in_array( 'IPS\Content\Followable', class_implements( get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}
		
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', static::$application, mb_strtolower( mb_substr( get_class( $container ), mb_strrpos( get_class( $container ), '\\' ) + 1 ) ), $container->_id ) )->first();
	}
	
	/**
	 * Users to receive immediate notifications
	 *
	 * @param	int|array		$limit	LIMIT clause
	 * @param	string|NULL		$extra		Additional data
	 * @return \IPS\Db\Select
	 */
	public function notificationRecipients( $limit=array( 0, 25 ), $extra=NULL )
	{
		$memberFollowers = $this->author()->followers( 3, array( 'immediate' ), $this->mapped('date'), NULL, NULL, NULL );
		
		if( count( $memberFollowers ) )
		{
			$unions	= array( 
				static::containerFollowers( $this->container(), 3, array( 'immediate' ), $this->mapped('date'), NULL, NULL, 0 ),
				$memberFollowers
			);
		
			return \IPS\Db::i()->union( $unions, 'follow_added', $limit, 'follow_member_id', FALSE, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		}
		else
		{
			return static::containerFollowers( $this->container(), 3, array( 'immediate' ), $this->mapped('date'), $limit, 'follow_added', \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		}
	}
	
	/**
	 * Create Notification
	 *
	 * @param	string|NULL		$extra		Additional data
	 * @return	\IPS\Notification
	 */
	protected function createNotification( $extra=NULL )
	{
		// New content is sent with itself as the item as we deliberately do not group notifications about new content items. Unlike comments where you're going to read them all - you might scan the notifications list for topic titles you're interested in
		return new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_content', $this, array( $this ) );
	}
	
	/* !Polls */
	
	/**
	 * Can create polls?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if polls can be used in, if applicable
	 * @return	bool
	 */
	public static function canCreatePoll( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->group['g_post_polls'];
	}
	
	/**
	 * Get poll
	 *
	 * @return	\IPS\Poll|NULL
	 * @throws	\BadMethodCallException
	 */
	public function getPoll()
	{
		if ( !in_array( 'IPS\Content\Polls', class_implements( get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}
		
		try
		{
			return $this->mapped('poll') ? \IPS\Poll::load( $this->mapped('poll') ) : NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}

	/* !Future Publishing */
	/**
	 * Can view future publishing items?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 * @note	If called without passing $container, this method falls back to global "can view hidden content" moderator permission which isn't always what you want - pass $container if in doubt
	 */
	public static function canViewFutureItems( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $container ? static::modPermission( 'view_future', $member, $container ) : $member->modPermission( "can_view_future_content" );
	}

	/**
	 * Can set items to be published in the future?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 */
	public static function canFuturePublish( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $container ? static::modPermission( 'future_publish', $member, $container ) : $member->modPermission( "can_future_publish_content" );
	}

	/**
	 * Can publish future items?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 */
	public function canPublish( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		return static::canFuturePublish( $member, $container );
	}

	/**
	 * "Unpublishes" an item.
	 * @note    This will not change the item's date. This should be done via the form methods if required
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function unpublish( $member=NULL )
	{
		/* Now do the actual stuff */
		if ( isset( static::$databaseColumnMap['is_future_entry'] ) AND isset( static::$databaseColumnMap['date'] ) )
		{
			$future = static::$databaseColumnMap['is_future_entry'];

			$this->$future = 1;
		}

		$this->save();
		$this->onUnpublish();

		/* And update the tags perm cache */
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 0 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->removeFromSearchIndex( $this );
		}

		$this->expireWidgetCaches();
	}

	/**
	 * Publishes a 'future' entry now
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function publish( $member=NULL )
	{
		/* Now do the actual stuff */
		if ( isset( static::$databaseColumnMap['is_future_entry'] ) AND isset( static::$databaseColumnMap['date'] ) )
		{
			$date   = static::$databaseColumnMap['date'];
			$future = static::$databaseColumnMap['is_future_entry'];

			if ( $this->$future )
			{
				$this->$date = time();
			}

			$this->$future = 0;
		}

		$this->save();
		$this->onPublish();

		/* And update the tags perm cache */
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 1 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		/* Send notifications if necessary */
		$this->sendNotifications();
	}

	/**
	 * Syncing to run when publishing something previously pending publishing
	 *
	 * @return	void
	 */
	public function onPublish()
	{
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();

				if ( $container->_futureItems !== NULL )
				{
					$container->_futureItems = ( $container->_futureItems > 0 ) ? $container->_futureItems - 1 : 0;
				}

				$container->_items = $container->_items + 1;

				if ( isset( static::$commentClass ) )
				{
					$container->_comments = $container->_comments + $this->mapped('num_comments');
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					$container->_reviews = $container->_reviews + $this->mapped('num_reviews');
					$container->setLastReview();
				}

				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}

	/**
	 * Syncing to run when unpublishing an item (making it a future dated entry when it was already published)
	 *
	 * @return	void
	 */
	public function onUnpublish()
	{
		$container = NULL;
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();

				if ( $container->_futureItems !== NULL )
				{
					$container->_futureItems = $container->_futureItems + 1;
				}

				$container->_items = $container->_items - 1;

				if ( isset( static::$commentClass ) )
				{
					$container->_comments = $container->_comments - $this->mapped('num_comments');
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					$container->_reviews = $container->_reviews - $this->mapped('num_reviews');
					$container->setLastReview();
				}

				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}

	/* !Ratings */
	
	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		switch ( $member->group['g_topic_rate_setting'] )
		{
			case 2:
				return TRUE;
			case 1:
				try
				{
					$idColumn = static::$databaseColumnId;
					\IPS\Db::i()->select( '*', 'core_ratings', array( 'class=? AND item_id=? AND member=?', get_called_class(), $this->$idColumn, $member->member_id ) )->first();
					return FALSE;
				}
				catch ( \UnderflowException $e )
				{
					return TRUE;
				}
				break;
			default:
				return FALSE;
		}
	}
	
	/**
	 * Get average rating
	 *
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public function averageRating()
	{
		if ( !( $this instanceof \IPS\Content\Ratings ) )
		{
			throw new \BadMethodCallException;
		}
				
		if ( isset( static::$databaseColumnMap['rating_average'] ) )
		{
			return $this->mapped('rating_average');
		}
		elseif ( isset( static::$databaseColumnMap['rating_total'] ) and isset( static::$databaseColumnMap['rating_hits'] ) )
		{
			return $this->mapped('rating_hits') ? round( $this->mapped('rating_total') / $this->mapped('rating_hits') ) : 0;
		}
		else
		{
			$idColumn = static::$databaseColumnId;
			return (int) \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', get_called_class(), $this->$idColumn ) )->first();
		}
	}
		
	/**
	 * Display rating (will just display stars if member cannot rate)
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function rating()
	{
		if ( !( $this instanceof \IPS\Content\Ratings ) )
		{
			throw new \BadMethodCallException;
		}

		if ( $this->canRate() )
		{
			$idColumn = static::$databaseColumnId;
						
			$form = new \IPS\Helpers\Form('rating');
			$form->add( new \IPS\Helpers\Form\Rating( 'rating', $this->averageRating() ) );
			
			if ( $values = $form->values() )
			{
				\IPS\Db::i()->insert( 'core_ratings', array(
					'class'		=> get_called_class(),
					'item_id'	=> $this->$idColumn,
					'member'	=> (int) \IPS\Member::loggedIn()->member_id,
					'rating'	=> $values['rating'],
					'ip'		=> \IPS\Request::i()->ipAddress()
				), TRUE );
				
				if ( isset( static::$databaseColumnMap['rating_average'] ) )
				{
					$column = static::$databaseColumnMap['rating_average'];
					$this->$column = \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', get_called_class(), $this->$idColumn ) )->first();
				}
				if ( isset( static::$databaseColumnMap['rating_total'] ) )
				{
					$column = static::$databaseColumnMap['rating_total'];
					$this->$column = \IPS\Db::i()->select( 'SUM(rating)', 'core_ratings', array( 'class=? AND item_id=?', get_called_class(), $this->$idColumn ) )->first();
				}
				if ( isset( static::$databaseColumnMap['rating_hits'] ) )
				{
					$column = static::$databaseColumnMap['rating_hits'];
					$this->$column = \IPS\Db::i()->select( 'COUNT(*)', 'core_ratings', array( 'class=? AND item_id=?', get_called_class(), $this->$idColumn ) )->first();
				}

				$this->save();

				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( 'OK' );
				}
			}
			
			return $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'ratingTemplate' ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'core' )->rating( 'veryLarge', $this->averageRating() );
		}
	}
	
	/* !Sitemap */
	
	/**
	 * WHERE clause for getting items for sitemap (permissions are already accounted for)
	 *
	 * @return	array
	 */
	public static function sitemapWhere()
	{
		return array();
	}
	
	/**
	 * Sitemap Priority
	 *
	 * @return	int|NULL	NULL to use default
	 */
	public function sitemapPriority()
	{
		return NULL;
	}

	/**
	 * Retrieve any custom item_app_key_x values for item marking
	 *
	 * @param	int	$key	2 or 3 for respective column
	 * @return	void
	 * @note	This is abstracted to make it easier for apps to override
	 */
	public static function getItemMarkerKey( $key )
	{
		return 0;
	}
}