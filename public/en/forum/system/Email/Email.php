<?php
/**
 * @brief		Outgoing Email Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Email Class
 */
abstract class _Email
{
	/**
	 * @brief	Temporary store needed in IN_DEV to rememver what parameters a template has
	 */
	protected static $matchesStore = '';

	/**
	 * @brief	Headers
	 */
	public $headers			= array(
		'MIME-Version'		=> '1.0',
		'Precedence'		=> 'list',			/* This is to try to prevent auto-responders and delivery failure notifications from responding */
		'Auto-Submitted'	=> 'auto-generated'	/* This is to try to prevent auto-responders and delivery failure notifications from responding */
	);

	/**
	 * @brief	From address
	 */
	public $from			= '';

	/**
	 * @brief	From display name
	 */
	public $fromName		= '';

	/**
	 * @brief	"To" addresses not encoded, primarily used for unsubscribe and debug purposes
	 */
	public $to				= array();

	/**
	 * @brief	All Recipients
	 */
	protected $recipients 	= array();
	
	/**
	 * @brief	Subject
	 */
	public $subject			= '';
	
	/**
	 * @brief	Message (this is the full compiled message complete with content-type and boundary markers)
	 */
	protected $message		= '';

	/**
	 * @brief	Unsubscribe link and text
	 */
	public $unsubscribe		= array( 'blurb' => '', 'link' => '', 'text' => '' );

	/**
	 * @brief	Wrap in our main template wrapper
	 */
	public $useWrapper		= TRUE;

	/**
	 * @brief	Email template information
	 */
	public $emailTemplate	= array();

	/**
	 * @brief	Raw email body
	 */
	public $emailBody		= array( 'html' => '', 'plain' => '' );

	/**
	 * @brief	Language object use
	 */
	public $language		= NULL;
	
	/**
	 * Build an unsubscribe link
	 *
	 * @param	string				$type	Type of unsubscribe link to automatically build.  Valid values are 'bulk' and 'notification'.
	 * @param	string|\IPS\Member	$data	Data necessary for unsubscribe link.  For 'bulk' type this is a member object.  For 'notification' type this is the notification key.
	 * @return	\IPS\Email
	 */
	public function buildUnsubscribe( $type, $data )
	{
		if( $type == 'bulk' AND $data instanceof \IPS\Member )
		{
			$this->unsubscribe['text']	= 'unsubscribe';
			$this->unsubscribe['link']	= \IPS\Http\Url::internal( 'app=core&module=system&controller=unsubscribe&email=' . $data->email . '&key=' . md5( $data->email . ':' . $data->members_pass_hash ), 'front', 'unsubscribe' );
		}
		else if( $type == 'notification' )
		{
			$this->unsubscribe['text']	= 'adjust_notification_prefs';
			$this->unsubscribe['link']	= \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=' . (string) $data , 'front', 'notifications_options' );
		}
		elseif ( $type == 'follow' )
		{
			$this->unsubscribe['blurb']	= '*|unsubscribe_blurb|*';
			$this->unsubscribe['text']	= 'unfollow';
			$this->unsubscribe['link']	= '*|unfollow_link|*';
			$this->unsubscribe['ortext']= 'adjust_notification_prefs';
			$this->unsubscribe['orlink']= \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=' . (string) $data , 'front', 'notifications_options' );
		}

		return $this;
	}

	/**
	 * Set the email body
	 *
	 * @param	string	$name	Name of the property
	 * @param	array 	$value	Value of the property (should be an array with keys 'html' and 'plain')
	 * @return	array ('html' and 'plain')
	 */
	public function __set( $name, $value )
	{
		if( $name == 'body' )
		{
			$this->emailBody	= $value;
		}
	}

	/**
	 * Retrieve the email body - factors in template and wrapper as needed
	 *
	 * @param	string	$name	Name of the property
	 * @return	array ('html' and 'plain')
	 */
	public function __get( $name )
	{
		if( $name == 'body' )
		{
			/* Do we have to build the email from a template? */
			if( count($this->emailTemplate) AND !$this->emailBody['html'] AND !$this->emailBody['plain'] )
			{
				$this->parseTemplate();
			}

			/* Do we have to wrap the email in our wrapper? */
			if( $this->useWrapper )
			{
				return $this->wrapEmail();
			}
			else
			{
				return $this->emailBody;
			}
		}
	}

	/**
	 * Determine if we have a specific email template
	 *
	 * @param	string		$app	Application key
	 * @param	string		$key	Email template key
	 * @return	bool
	 */
	public static function hasTemplate( $app, $key )
	{
		if( \IPS\IN_DEV )
		{
			foreach ( array( 'phtml', 'txt' ) as $type )
			{
				if( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/dev/email/{$key}.{$type}" ) )
				{
					return TRUE;
				}
			}

			return FALSE;
		}
		else
		{
			/* See if we found anything from the store */
			$key = md5( $app . ';' . $key ) . '_email_html';
			$htmlTemplate	= ( isset( \IPS\Data\Store::i()->$key ) ) ? \IPS\Data\Store::i()->$key : NULL;

			if( $htmlTemplate )
			{
				return TRUE;
			}

			$key = md5( $app . ';' . $key ) . '_email_plaintext';
			$plaintextTemplate	= ( isset( \IPS\Data\Store::i()->$key ) ) ? \IPS\Data\Store::i()->$key : NULL;

			if( $plaintextTemplate )
			{
				return TRUE;
			}

			return FALSE;
		}
	}

	/**
	 * Initiate a new custom email based on raw email content
	 *
	 * @note	While both body parameters are optional, you should really specify at least one, otherwise there will be no email body unless you manually set the body later.
	 * @param	string		$subject	Subject
	 * @param	string		$html		HTML Version
	 * @param	string		$plain		Plaintext version
	 * @return	\IPS\Email
	 */
	public static function buildFromContent( $subject, $htmlContent='', $plaintextContent='' )
	{
		if( !$plaintextContent AND $htmlContent )
		{
			$plaintextContent	= static::buildPlaintextBody( $htmlContent );
		}

		$obj	= static::i( $htmlContent, $plaintextContent );
		$obj->subject	= $subject;

		return $obj;
	}

	/**
	 * Turn an HTML email into a plaintext email
	 *
	 * @param	string	$html 	HTML email
	 * @return	string
	 * @note	We might find that using HTML Purifier to retain links in parenthesis is useful.
	 */
	public static function buildPlaintextBody( $html )
	{		
		/* Add newlines as needed */
		$html	= str_replace( "</p>", "</p>\n", $html );
		$html	= str_replace( array( "<br>", "<br />" ), "\n", $html );

		/* Strip HTML and return */
		return strip_tags( $html );
	}

	/**
	 * Initiate new email using a template
	 *
	 * @param	string		$app					Application key
	 * @param	string		$key					Email template key
	 * @param	array 		$parameters				Parameters for the template
	 * @param	bool		$placeholderRecipient	If TRUE, will use placeholders where member data will be rather than an actual member. Use if you will send using mergeAndSend
	 * @return	\IPS\Email
	 */
	public static function buildFromTemplate( $app, $key, $parameters=array(), $placeholderRecipient=FALSE )
	{
		$email	= static::i();

		$email->emailTemplate = array(
			'app'					=> $app,
			'key'					=> $key,
			'params'				=> $parameters,
			'placeholderRecipient'	=> $placeholderRecipient,
		);

		return $email;
	}

	/**
	 * Initiate new email from headers and body as an array
	 *
	 * @param	array		$headers		Email headers
	 * @param	array		$body			Email body array
	 * @return	\IPS\Email
	 */
	public static function buildFromRaw( $headers, $body )
	{
		$email	= static::i();

		foreach( $headers as $k => $v )
		{
			$email->headers[ $k ]	= $v;
		}

		foreach( $body as $k => $v )
		{
			$email->emailBody[ $k ]	= $v;
		}

		return $email;
	}

	/**
	 * Factory Method
	 *
	 * @param	string		$html		HTML Version
	 * @param	string		$plain		Plaintext version
	 * @return	\IPS\Email
	 */
	protected static function i( $html='', $plain='' )
	{
		/* What email handler are we using? */
		if( defined( '\IPS\EMAIL_DEBUG_PATH' ) AND \IPS\EMAIL_DEBUG_PATH )
		{
			$email 				= new \IPS\Email\Outgoing\Debug;
			$email->debugPath	= \IPS\EMAIL_DEBUG_PATH;
		}
		else if ( \IPS\Settings::i()->mail_method === 'smtp' )
		{
			$email	= new \IPS\Email\Outgoing\Smtp;
		}
		else
		{
			$email	= new \IPS\Email\Outgoing\Php;
		}

		/* Set a default language - this is overridden by the language of the recipient in the send() method if an instance of \IPS\Member is passed */
		$email->language	= \IPS\Lang::load( \IPS\Lang::defaultLanguage() );

		/* Set a common default headers */
		$email->headers['Date'] = date('r');
		$email->from			= \IPS\Settings::i()->email_out;
		$email->fromName		= \IPS\Settings::i()->board_name;
		
		/* Store the body */
		$email->emailBody	= array( 'html' => $html ?: $plain, 'plain' => $plain ?: static::buildPlaintextBody( $html ) );

		/* Return */
		return $email;
	}

	/**
	 * Compile Headers
	 *
	 * @see		<a href='http://www.ietf.org/rfc/rfc2822.txt'>RFC 2822</a>
	 * @see		<a href='http://www.ietf.org/rfc/rfc2045.txt'>RFC 2045</a>
	 * @param	bool	$isPhp	If using the PHP handler we need to skip the to and subject values
	 * @return	string
	 */
	protected function _compileHeaders( $isPhp=FALSE )
	{
		$this->headers['From']		= ( $this->fromName ) ? ( mb_encode_mimeheader( $this->fromName ) . " <{$this->from}>" ) : "<{$this->from}>";

		if( !$isPhp )
		{
			$this->headers['Subject']	= mb_encode_mimeheader( $this->subject );
		}

		/* Set the headers and return */
		$return	= array();

		foreach ( $this->headers as $k => $v )
		{
			if( \strtolower( $k ) == 'to' AND $isPhp )
			{
				continue;
			}

			/* We have already encoded the email address fields, as they have to be encoded differently */
			if( !in_array( \strtolower( $k ), array( 'from', 'to', 'cc', 'bcc', 'subject' ) ) )
			{
				/* If there are non-ASCII characters we need to encode them */
				if( preg_match('#([\x80-\xFF]){1}#', $v ) )
				{
					$v	= mb_encode_mimeheader( $v );
				}

				/* Otherwise we may still need to perform case-folding */
				else if( \strlen( "{$k}: {$v}" ) > 78 )
				{
					$v	= \substr( wordwrap( "{$k}: {$v}", 78, "\r\n " ), \strlen( $k ) + 2 );
				}
			}

			$return[]	= "{$k}: {$v}";
		}

		return implode( "\r\n", $return );
	}

	/**
	 * Build the email body and subject from a template
	 *
	 * @return	void
	 * @throws	OutOfRangeException
	 * @note	This method can throw an exception if you call an invalid template
	 */
	public function parseTemplate()
	{
		if( !count( $this->emailTemplate ) )
		{
			return;
		}

		$_typeMap	= array( 'phtml' => 'html', 'txt' => 'plain' );
		$params = array_merge( $this->emailTemplate['params'], array( $this ) );

		if( \IPS\IN_DEV )
		{
			/* Parse templates */
			foreach ( array( 'phtml', 'txt' ) as $type )
			{
				$this->emailBody[ $_typeMap[ $type ] ] = static::devProcessTemplate( "email__{$this->emailTemplate['app']}_{$this->emailTemplate['key']}_{$type}", file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->emailTemplate['app']}/dev/email/{$this->emailTemplate['key']}.{$type}" ), $params );
			}
		}
		else
		{
			/* Get built copy */
			if( empty( $this->emailBody['html'] ) )
			{
				$key		= md5( $this->emailTemplate['app'] . ';' . $this->emailTemplate['key'] ) . '_email_html';
				
				if ( \IPS\Data\Store::i()->exists( $key ) === FALSE )
				{
					$templateData = \IPS\Db::i()->select( '*', 'core_email_templates', array( "template_app=? AND template_name=?", $this->emailTemplate['app'], $this->emailTemplate['key'] ) )->first();
					\IPS\Data\Store::i()->$key = "namespace IPS\Theme;\n" . \IPS\Theme::compileTemplate( $templateData['template_content_html'], "email_html_{$templateData['template_app']}_{$templateData['template_name']}", $templateData['template_data'] );
				}
				$template	= \IPS\Data\Store::i()->$key;
 
				if( !function_exists( 'IPS\\Theme\\email_html_' . $this->emailTemplate['app'] . '_' . $this->emailTemplate['key'] ) )
				{
					eval( $template );
				}

				$this->emailBody['html']	= call_user_func_array( 'IPS\\Theme\\email_html_' . $this->emailTemplate['app'] . '_' . $this->emailTemplate['key'], $params );
			}

			if( empty( $this->emailBody['plain'] ) )
			{
				$key		= md5( $this->emailTemplate['app'] . ';' . $this->emailTemplate['key'] ) . '_email_plaintext';
				
				if ( \IPS\Data\Store::i()->exists( $key ) === FALSE )
				{
					$templateData = \IPS\Db::i()->select( '*', 'core_email_templates', array( "template_app=? AND template_name=?", $this->emailTemplate['app'], $this->emailTemplate['key'] ) )->first();
					\IPS\Data\Store::i()->$key = "namespace IPS\\Theme;\n" . \IPS\Theme::compileTemplate( $templateData['template_content_plaintext'], "email_plaintext_{$templateData['template_app']}_{$templateData['template_name']}", $templateData['template_data'] );
				}
				$template	= \IPS\Data\Store::i()->$key;

				if( !function_exists( 'IPS\\Theme\\email_plaintext_' . $this->emailTemplate['app'] . '_' . $this->emailTemplate['key'] ) )
				{
					eval( $template );
				}

				$this->emailBody['plain']	= call_user_func_array( 'IPS\\Theme\\email_plaintext_' . $this->emailTemplate['app'] . '_' . $this->emailTemplate['key'], $params );
			}
		}

		/* Get subject */
		$subjectKey		= "mailsub__{$this->emailTemplate['app']}_{$this->emailTemplate['key']}";
		$this->subject	= trim( static::devProcessTemplate( "email__{$this->emailTemplate['app']}_{$this->emailTemplate['key']}_subject", $this->language->get( $subjectKey ), $params ) );
	}

	/**
	 * Wrap the email body in the wrapper template if necessary
	 *
	 * @return	array 	Array containing the new wrapped html and plaintext emails
	 * @throws	OutOfRangeException
	 * @note	This method can throw an exception if the wrapper template is not compiled
	 */
	public function wrapEmail()
	{
		/* We need the member we are sending to */
		$member	= NULL;

		if( !empty( $this->to ) )
		{
			try
			{
				$member	= \IPS\Member::load( $this->to[0], 'email' );
			}
			catch( \OutOfRangeException $e )
			{
				$member	= NULL;
			}
		}

		if( $member === NULL )
		{
			$member	= new \IPS\Member;
		}

		/* Wrap as appropriate */
		if( \IPS\IN_DEV )
		{
			$body	= array();

			/* Wrap the HTML version in the wrapper */
			if( !empty( $this->emailBody['html'] ) )
			{
				$body['html']	= static::devProcessTemplate( "email_html_core_emailWrapper", file_get_contents( \IPS\ROOT_PATH . "/applications/core/dev/email/emailWrapper.phtml" ), array( $this->subject, $member, $this->emailBody['html'], $this->unsubscribe, isset( $this->emailTemplate['placeholderRecipient'] ) ? $this->emailTemplate['placeholderRecipient'] : FALSE, '', $this ) );
			}

			/* Wrap the Plaintext version in the wrapper */
			if( !empty( $this->emailBody['plain'] ) )
			{
				$body['plain']	= static::devProcessTemplate( "email_plaintext_core_emailWrapper", file_get_contents( \IPS\ROOT_PATH . "/applications/core/dev/email/emailWrapper.txt" ), array( $this->subject, $member, $this->emailBody['plain'], $this->unsubscribe, isset( $this->emailTemplate['placeholderRecipient'] ) ? $this->emailTemplate['placeholderRecipient'] : FALSE, '', $this ) );
			}
		}
		else
		{
			/* Get built copy */
			if( !empty( $this->emailBody['html'] ) )
			{
				$key		= md5( 'core;emailWrapper' ) . '_email_html';
				
				if ( \IPS\Data\Store::i()->exists( $key ) === FALSE )
				{
					$templateData = \IPS\Db::i()->select( '*', 'core_email_templates', array( "template_app=? AND template_name=?", 'core', 'emailWrapper' ) )->first();
					\IPS\Data\Store::i()->$key = "namespace IPS\Theme;\n" . \IPS\Theme::compileTemplate( $templateData['template_content_html'], "email_html_core_emailWrapper", $templateData['template_data'] );
				}
				
				$template	= \IPS\Data\Store::i()->$key;

				if( !function_exists( 'IPS\\Theme\\email_html_core_emailWrapper' ) )
				{
					eval( $template );
				}
								
				$body['html']	= call_user_func_array( 'IPS\\Theme\\email_html_core_emailWrapper', array( $this->subject, $member, $this->emailBody['html'], $this->unsubscribe, isset( $this->emailTemplate['placeholderRecipient'] ) ? $this->emailTemplate['placeholderRecipient'] : FALSE, '', $this ) );
			}

			if( !empty( $this->emailBody['plain'] ) )
			{
				$key		= md5( 'core;emailWrapper' ) . '_email_plaintext';
				
				if ( \IPS\Data\Store::i()->exists( $key ) === FALSE )
				{
					$templateData = \IPS\Db::i()->select( '*', 'core_email_templates', array( "template_app=? AND template_name=?", 'core', 'emailWrapper' ) )->first();
					\IPS\Data\Store::i()->$key = "namespace IPS\Theme;\n" . \IPS\Theme::compileTemplate( $templateData['template_content_plaintext'], "email_plaintext_core_emailWrapper", $templateData['template_data'] );
				}
				
				$template	= \IPS\Data\Store::i()->$key;

				if( !function_exists( 'IPS\\Theme\\email_plaintext_core_emailWrapper' ) )
				{
					eval( $template );
				}

				$body['plain']	= call_user_func_array( 'IPS\\Theme\\email_plaintext_core_emailWrapper', array( $this->subject, $member, $this->emailBody['plain'], $this->unsubscribe, isset( $this->emailTemplate['placeholderRecipient'] ) ? $this->emailTemplate['placeholderRecipient'] : FALSE, '', $this ) );
			}
		}

		return $body;
	}
	
	/**
	 * Send
	 *
	 * @param	mixed	$to		The member or email address, or array of members or email addresses, to send to
	 * @param	mixed	$cc		Addresses to CC (can also be email, member or array of either)
	 * @param	mixed	$bcc	Addresses to BCC (can also be email, member or array of either)
	 * @return	bool
	 * @throws	\IPS\Email\Outgoing\Exception
	 * @see		<a href='http://www.faqs.org/rfcs/rfc2369.html'>RFC 2369 describes the List-Unsubscribe header</a>
	 */
	public function send( $to, $cc=array(), $bcc=array() )
	{
		/* Parse the recipients - do this first in order to extract the language key in case we need it */
		$this->recipients = array();
				
		$this->headers['To'] = $this->_parseRecipients( $to, TRUE );
				
		if ( !empty( $cc ) )
		{
			$this->headers['Cc'] = $this->_parseRecipients( $cc );
		}
		elseif ( isset( $this->headers['Cc'] ) )
		{
			unset( $this->headers['Cc'] );
		}
		
		if ( !empty( $bcc ) )
		{
			$this->headers['Bcc'] = $this->_parseRecipients( $bcc );
		}
		elseif ( isset( $this->headers['Bcc'] ) )
		{
			unset( $this->headers['Bcc'] );
		}

		/* If we are appending an unsubscribe link, add the List-Unsubscribe header */
		if( !empty( $this->unsubscribe['link'] ) )
		{
			$this->headers['List-Unsubscribe']	= $this->unsubscribe['link'];
		}

		/* Do we have to build the email from a template? */
		if( count($this->emailTemplate) AND !$this->emailBody['html'] AND !$this->emailBody['plain'] )
		{
			$this->parseTemplate();
		}

		/* Do we have to wrap the email in our wrapper? */
		if( $this->useWrapper )
		{
			$this->emailBody = $this->wrapEmail();
		}

		/* Replace language stack keys with actual content */
		$this->language->parseOutputForDisplay( $this->emailBody['plain'] );
		$this->language->parseOutputForDisplay( $this->emailBody['html'] );
		$this->language->parseOutputForDisplay( $this->subject );

		/* Outlook can't handle protocol-relative URLs, so we need to switch them out */
		foreach( $this->emailBody as $type => $content )
		{
			$this->emailBody[ $type ] = preg_replace_callback( "/\s+?src=(['\"])\/\/([^'\"]+?)(['\"])/ims", function( $matches ){
				$baseUrl	= parse_url( \IPS\Settings::i()->base_url );

				/* Try to preserve http vs https */
				if( isset( $baseUrl['scheme'] ) )
				{
					$url = $baseUrl['scheme'] . '://' . $matches[2];
				}
				else
				{
					$url = 'http://' . $matches[2];
				}

				return " src={$matches[1]}{$url}{$matches[1]}";
			}, $content );
		}
		
		/* Now build the actual email body and send */
		$boundary	= "--==_mimepart_" . md5( uniqid() );

		$this->headers['Content-Type']				= "multipart/alternative; boundary=\"{$boundary}\"; charset=UTF-8";
		$this->headers['Content-Transfer-Encoding']	= "8bit";

		$this->_buildMessage( $boundary );
		
		/* Send the email - log any errors to the email error log */
		try
		{
			$this->_send();
		}
		catch( \IPS\Email\Outgoing\Exception $e )
		{
			\IPS\Db::i()->insert( 'core_mail_error_logs', array(
				'mlog_date'			=> time(),
				'mlog_to'			=> implode( ', ', $this->to ),
				'mlog_from'			=> $this->from,
				'mlog_subject'		=> $this->subject,
				'mlog_content'		=> $this->emailBody['html'] ?: $this->emailBody['plain'],
				'mlog_resend_data'	=> json_encode( array( 'headers' => $this->headers, 'body' => $this->emailBody, 'boundary' => $boundary ) ),
				'mlog_msg'			=> $e->getMessage(),
				'mlog_smtp_log'		=> $this->getLog(),
			) );
			
			return $e;
		}

		return TRUE;
	}
	
	/**
	 * Merge and Send
	 *
	 * @param	array	$recipients		Array where the keys are the email addresses to send to and the values are an array of variables to replace
	 * @return	void
	 */
	public function mergeAndSend( $recipients )
	{
		if ( \IPS\Settings::i()->mandrill_api_key && \IPS\Settings::i()->mandrill_use_for )
		{
			$toArray = array();
			$recipientsMerge = array();
			foreach ( $recipients as $address => $_vars )
			{
				$vars = array();
				$mergeVars = array();
				foreach ( $_vars as $k => $v )
				{
					$vars[] = array( 'name' => $k, 'content' => $v );
				}
				
				$toArray[] = array( 'email' => $address );
				$recipientsMerge[] = array( 'rcpt' => $address, 'vars'	=> $vars );
			}
			
			/* Replace language stack keys with actual content */
			$body = $this->body['html'];
			$subject = $this->subject;
			
			$this->language->parseOutputForDisplay( $body );
			$this->language->parseOutputForDisplay( $subject );
						
			$response = \IPS\Email::mandrill( 'messages_send', \IPS\Settings::i()->mandrill_api_key, array(
				'message'	=> array(
					'html'					=> $body,
					'subject'				=> $subject,
					'from_email'			=> $this->from,
					'from_name'				=> $this->fromName,
					'to'					=> $toArray,
					'auto_text'				=> TRUE,
					'url_strip_qs'			=> FALSE,
					'preserve_recipients'	=> FALSE,
					'merge'					=> TRUE,
					'global_merge_vars'		=> array(),
					'merge_vars'			=> $recipientsMerge,
					'tags'					=> array_merge( array( 'ips' ) )
					),
				'async'		=> TRUE
				) );
		}
		else
		{
			foreach ( $recipients as $address => $vars )
			{
				$content = $this->body;
				$subject = $this->subject;
				
				/* Replace language stack keys with actual content */
				$this->language->parseOutputForDisplay( $content['html'] );
				$this->language->parseOutputForDisplay( $content['plain'] );
				$this->language->parseOutputForDisplay( $subject );
				
				foreach ( $vars as $k => $v )
				{
					$content['html'] = str_replace( "*|{$k}|*", $v, $content['html'] );
					$content['plain'] = str_replace( "*|{$k}|*", $v, $content['plain'] );
					$subject = str_replace( "*|{$k}|*", $v, $subject );
				}
				
				$email = static::buildFromContent( $subject, $content['html'], $content['plain'] );
				$email->useWrapper = FALSE;
				$email->send( $address );

				unset( $email );
			}
		}
	}

	/**
	 * This method allows you to quickly send an email, bypassing the automatic formatting and email error logging.
	 * This is primarily used by the "resend failed emails".  To use this method you must manually populate $this->headers and $this->emailBody.
	 *
	 * @param	string	$boundary	The boundary used in the Content-Type header
	 * @return	bool
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	public function forceSend( $boundary )
	{
		$this->_buildMessage( $boundary );
		$this->_send();
	}

	/**
	 * Build the email message
	 *
	 * @param	string	$boundary	The boundary used in the Content-Type header
	 * @return	bool
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	protected function _buildMessage( $boundary )
	{
		foreach ( array( 'text/plain' => $this->emailBody['plain'], 'text/html' => $this->emailBody['html'] ) as $contentType => $content )
		{
			$this->message	.= "--{$boundary}\n";
			$this->message	.= "Content-Type: {$contentType}; charset=UTF-8\n\n";
			$this->message	.= $content;
			$this->message	.= "\n\n";
		}
	}

	/**
	 * Parse Recipients
	 *
	 * @param	string|array|\IPS\Member	$data		The member or email address, or array of members or email addresses, to send to
	 * @param	bool						$emailOnly	If TRUE, will use email only rather than names too. Set to TRUE for the "To" header
	 * @return	string
	 * @see		<a href='http://www.faqs.org/rfcs/rfc2822.html'>RFC 2822</a>
	 * @throws	\InvalidArgumentException
	 *	@li BAD_RECIPIENT
	 */
	protected function _parseRecipients( $data, $emailOnly=FALSE )
	{
		$return = array();
		
		if ( !is_array( $data ) )
		{
			$data = array( $data );
		}
		
		foreach ( $data as $recipient )
		{
			if ( is_string( $recipient ) )
			{
				$this->to[]	= $recipient;
				$return[]	= $emailOnly ? $recipient : "<{$recipient}>";
			}
			elseif ( $recipient instanceof \IPS\Member )
			{
				$this->to[]		= $recipient->email;
				$return[]		= $emailOnly ? $recipient->email : mb_encode_mimeheader($recipient->name) . " <{$recipient->email}>";
				$this->language	= $recipient->language();
			}
			else
			{
				throw new \InvalidArgumentException;
			}
		}
		
		$this->recipients = array_merge( $this->recipients, $return );
		return implode( ', ', $return );
	}

	/**
	 * IN_DEV - load and run template
	 *
	 * @param	string	$functionName		Function name to use
	 * @param	string	$templateContents	Content to parse
	 * @param	array	$params				Params
	 * @return	string
	 */
	protected static function devProcessTemplate( $functionName, $templateContents, $params )
	{
		if( !function_exists( 'IPS\\Theme\\' . $functionName ) )
		{
			preg_match( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', $templateContents, $matches );
			if ( isset( $matches[0] ) )
			{
				static::$matchesStore = isset( $matches[1] ) ? $matches[1] : '';
				$templateContents = preg_replace( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', '', $templateContents );
			}
			else
			{
				/* Subjects do not contain the ips:template header, so we need a little magic */
				if ( $params !== NULL and is_array( $params ) and count( $params ) )
				{
					/* Extract app and key from "email__{app}_{key}_subject" */
					list( $app, $key ) = explode( '_', mb_substr( $functionName, 7, -8 ), 2 );
					
					if ( $app and $key )
					{
						 /* Doesn't matter if it's HTML or TXT here, we just want the param list */
						$key	  = md5( $app . ';' . $key ) . '_email_html';
						$template = isset( \IPS\Data\Store::i()->$key ) ? \IPS\Data\Store::i()->$key : NULL;
						
						if ( $template )
						{
							preg_match( "#function\s+?([^\(]+?)\(([^\)]+?)\)#", $template, $matches );
							
							if ( isset( $matches[2] ) )
							{
								static::$matchesStore = trim( $matches[2] );
							}
						}
						else
						{
							/* Grab the param list from the database */
							try
							{
								$template = \IPS\Db::i()->select( 'template_name, template_data', 'core_email_templates', array( 'template_app=? AND template_name=?', $app, $key ) )->first();
								
								if ( isset( $template['template_name'] ) )
								{
									static::$matchesStore = $template['template_data'];
								}
							}
							catch( \UnderflowException $e )
							{
								if ( \IPS\IN_DEV )
								{
									/* Try and get template file */
									list( $app, $key ) = explode( '_', mb_substr( $functionName, 7, -8 ), 2 );
									foreach( array( 'phtml', 'txt' ) AS $type )
									{
										/* We only need one */
										if ( $file = @file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/dev/email/{$key}.{$type}" ) )
										{
											break;
										}
									}
									
									if ( $file !== FALSE )
									{
										preg_match( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', $file, $matches );
										static::$matchesStore = isset( $matches[1] ) ? $matches[1] : '';
									}
									else
									{
										throw new \BadMethodCallException( 'NO_EMAIL_TEMPLATE_FILE - ' . $app . '/' . $key . '.' . $type );
									}
								}
								else
								{
									/* I can't really help you, sorry */
									throw new \LogicException;
								}
							}
						}
					}
				}
			}
			
			\IPS\Theme::makeProcessFunction( $templateContents, $functionName, static::$matchesStore );
		}
				
		return call_user_func_array( 'IPS\\Theme\\'.$functionName, $params );
	}

	/**
	 * Return any additional log data
	 *
	 * @return string
	 */
	public function getLog()
	{
		return '';
	}
	
	/**
	 * Send a Mandrill Request
	 *
	 * @param	string	$method	Method
	 * @param	string	$apiKey	API Key
	 * @param	array	$args	Arguments
	 * @return	array
	 * @thows	\IPS\Http\Request\Exception
	 */
	public static function mandrill( $method, $apiKey, $args=array() )
	{
		return \IPS\Http\Url::external( 'https://mandrillapp.com/api/1.0/' . str_replace( '_', '/', $method ) . '.json' )
			->request()
			->setHeaders( array( 'Content-Type' => 'application/json' ) )
			->post( json_encode( array_merge( array( 'key' => $apiKey ), $args ) ) )
			->decodeJson( FALSE );
	}
	
	/**
	 * Makes HTML acceptable for use in emails
	 *
	 * @param	string	$text	The text
	 * @return	string
	 */
	public function parseTextForEmail( $text )
	{
		try
		{
			$document = new \DomDocument( '1.0', 'UTF-8' );
			$document->loadHTML( "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'/></head>" . $text );
			$this->_parseNodeForEmail( $document );

			return preg_replace( '/^<!DOCTYPE.+?>/', '', str_replace( array( '<html>', '</html>', '<head>', '</head>', '<body>', '</body>', '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' ), '', $document->saveHTML() ) );
		}
		catch( \Exception $e )
		{
			return $text;
		}
	}
		
	/**
	 * Makes HTML acceptable for use in emails
	 *
	 * @param	string	$text	The text
	 * @return	string
	 */
	public function _parseNodeForEmail( \DOMNode &$node )
	{
		if ( $node->hasChildNodes() )
		{
			/* Dom node lists are "live" and if you modify the tree, you may affect the index which also affects php foreach loops.  Subsequently we
				need to capture all the nodes in a loop and store them, and then loop over that store */
			$_nodes = array();

			foreach ( $node->childNodes as $child )
			{
				$_nodes[]	= $child;
			}

			foreach( $_nodes as $_node )
			{
				$this->_parseNodeForEmail( $_node );
			}
		}

		if ( $node instanceof \DOMElement )
		{					
			$parent = $node->parentNode;

			/* Outlook cannot handle protocol-relative URLs like //site.com/image.jpg - it hangs the client and freezes */
			if ( $node->tagName == 'img' )
			{
				if ( $node->getAttribute('src') )
				{
					if ( mb_substr( $node->getAttribute('src'), 0, 2 ) === '//' )
					{
						$baseUrl	= parse_url( \IPS\Settings::i()->base_url );

						/* Try to preserve http vs https */
						if( isset( $baseUrl['scheme'] ) )
						{
							$url = $baseUrl['scheme'] . ':' . $node->getAttribute('src');
						}
						else
						{
							$url = 'http:' . $node->getAttribute('src');
						}

						$node->setAttribute( 'src', $url );
					}
				}
			}

			if ( $node->getAttribute('class') )
			{
				foreach ( explode( ' ', $node->getAttribute('class') ) as $class )
				{					
					switch ( $class )
					{
						/* Make quotes look good */
						case 'ipsQuote':
													
							$cell = $this->_createContainerTable( $parent, $node );
							$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; margin: 0;border: 1px solid #e0e0e0;border-left: 3px solid #adadad;position: relative;font-size: 13px;background: #fdfdfd" );
							
							$citation = $this->_createContainerTable( $cell );
							$citation->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; background: #f5f5f5;padding: 8px 15px;color: #000;font-weight: bold;font-size: 13px;display: block;" );
							$citation->appendChild( new \DOMText( $node->getAttribute('data-cite') ) );
														
							$containerCell = $this->_createContainerTable( $cell );
							$containerCell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; padding-left:15px" );
							
							while( $node->childNodes->length )
							{
								foreach ( $node->childNodes as $child )
								{
									$containerCell->appendChild( $child );
								}
							}

							$parent->removeChild( $node );
							break;
							
						/* Make code look good */
						case 'ipsCode':
							$cell = $this->_createContainerTable( $parent, $node );
							$cell->setAttribute( 'style', "font-family: monospace; line-height: 1.5; font-size: 14px; background: #fafafa; padding: 0; border-left: 4px solid #e0e0e0;" );
							$p = new \DOMElement( 'p' );
							$cell->appendChild( $p );
							$p->setAttribute( 'style', "font-family: monospace; line-height: 1.5; font-size: 14px; padding-left:15px" );

							while( $node->childNodes->length )
							{
								foreach ( $node->childNodes as $child )
								{
									$p->appendChild( $child );
								}
							}

							$parent->removeChild( $node );
							break;
						
						/* Remove spoilers */
						case 'ipsStyle_spoiler':
							$cell = $this->_createContainerTable( $parent, $node );
							$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; margin: 0;padding: 10px;background: #363636;color: #d8d8d8;" );
							$cell->appendChild( new \DOMText( $this->language->addToStack('email_spoiler_line') ) );
							$parent->removeChild( $node );
							break;
						
						/* Remove Videos */
						case 'ipsEmbeddedVideo':
							$cell = $this->_createContainerTable( $parent, $node );
							$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; padding: 10px; margin: 0;border: 1px solid #e0e0e0;border-left: 3px solid #adadad;position: relative;font-size: 13px;background: #fdfdfd" );
							$cell->appendChild( new \DOMText( $this->language->addToStack('email_video_line') ) );
							$parent->removeChild( $node );
							break;
							
						/* Constrain images */
						case 'ipsImage':
							$node->setAttribute( 'style', "max-width:100%" );
							break;
					}
				}				
			}
			
			/* Email clients do not like iframes. */
			if ( $node->tagName == 'iframe' )
			{
				if ( $node->getAttribute('src') )
				{
					$url	= new \IPS\Http\Url( $node->getAttribute('src') );
					
					/* Strip "do" param, but only if it is set to "embed" */
					if ( isset( $url->queryString['do'] ) AND $url->queryString['do'] == 'embed' )
					{
						$url = $url->stripQueryString( 'do' );
					}
					
					$a		= new \DOMElement( 'a' );
					$parent->insertBefore( $a, $node );
					$a->setAttribute( 'href', (string) $url );
					$a->appendChild( new \DOMText( (string) $url ) );
					$parent->removeChild( $node );
				}
			}
		}
	}
	
	/**
	 * Create container table as some email clients can't handle things if they're not in tables
	 *
	 * @param	\DOMNode		$node		The node to put the table into
	 * @param	\DOMNode|null	$replace	If the table should replace an existing node, the node to be replaced
	 * @return	\DOMNode
	 */
	protected function _createContainerTable( $node, $replace=NULL )
	{
		$table = new \DOMElement( 'table' );
		$row = new \DOMElement( 'tr' );
		$cell = new \DOMElement( 'td' );
		
		if ( $replace )
		{
			$node->insertBefore( $table, $replace );
		}
		else
		{
			$node->appendChild( $table );
		}
		
		$table->appendChild( $row );
		$row->appendChild( $cell );
		
		$table->setAttribute( 'width', '100%' );
		$table->setAttribute( 'cellpadding', '0' );
		$table->setAttribute( 'cellspacing', '0' );
		$table->setAttribute( 'border', '0' );
		$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px;" );
		
		return $cell;
	}
}