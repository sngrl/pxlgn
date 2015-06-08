<?php
/**
 * @brief		Incoming email parsing and routing
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 June 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Email;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PHP Email Class
 */
class _Incoming
{
	/**
	 * @brief	Debug mode enabled
	 */
	protected $debugMode	= FALSE;

	/**
	 * @brief	Basic email details we need to store
	 */
	public $data			= array(
									'to'			=> '',
									'from'			=> '',
									'subject'		=> '',
									'cc'			=> '',
									'message'		=> '',
									'quoted'		=> '',
									'raw'			=> '',
									'rawbody'		=> '',
									'alternative'	=> '',
									'attachments'	=> array(),
									);

	/**
	 * @brief	Track attachment increments so we can reference them
	 */
	protected $attachmentCount	= 0;

	/**
	 * @brief	Store a reference to the PEAR mail object
	 */
	public $mail	= NULL;

	/**
	 * Initialize an instance of the class
	 *
	 * @param	string		$email	Email text complete with headers
	 * @return	\IPS\Email\Incoming
	 */
	public static function i( $email )
	{
		$obj	= new static();

		return $obj->parse( $email );
	}

	/**
	 * Enable or disable debug mode
	 *
	 * @param	bool	$enable		Enable or disable
	 * @return	\IPS\Email\Incoming
	 */
	public function setDebugMode( $enable )
	{
		$this->debugMode	= (bool) $enable;

		return $this;
	}

	/**
	 * Parse the email into pieces
	 *
	 * @param	string		$email	Email text complete with headers
	 * @return	\IPS\Email\Incoming
	 */
	protected function parse( $email )
	{
		$this->data['raw']	= $email;

		/* Get the PEAR email library to parse the email */
		require_once \IPS\ROOT_PATH . "/system/3rd_party/PEAR/Mail/mimeDecode.php";
		$decoder	= new \Mail_mimeDecode( $email );
		$this->mail	= $decoder->decode( array( 
											'include_bodies'	=> TRUE,
											'decode_bodies'		=> TRUE,
											'decode_headers'	=> TRUE,
									)		);

		/* Assign default 'to' addresses */
		$to	= array();
		if ( mb_strpos( $this->mail->headers['to'], ',' ) === FALSE )
		{
			$this->mail->headers['to'] = array( $this->mail->headers['to'] );
		}
		else
		{
			$this->mail->headers['to'] = explode( ',', $this->mail->headers['to'] );
		}
		foreach ( $this->mail->headers['to'] as $_to )
		{
			if ( preg_match( "/.+? <(.+?)>/", $_to, $matches ) )
			{
				$to[]	= $matches[1];
			}
			else
			{
				$to[]	= trim( $_to, '<>' );
			}
		}
		$this->data['to'] = implode( ',', $to );

		/* Assign default 'from' address */
		if ( preg_match( "/.+? <(.+?)>/", $this->mail->headers['from'], $matches ) )
		{
			$this->data['from']	= $matches[1];
		}
		else
		{
			$this->data['from']	= trim( $this->mail->headers['from'], '<>' );
		}

		/* Assign the subject */
		$this->data['subject']	= ( (bool) trim( $this->mail->headers['subject'] ) ) ? $this->mail->headers['subject'] : \IPS\Member::load( $this->data['from'], 'email' )->language()->addToStack('incoming_email_no_subject');

		/* Assign any 'CC' addresses */
		$cc	= array();

		if( !empty($this->mail->headers['cc']) )
		{
			$this->mail->headers['cc']	= preg_replace( '/".+?" <(.+?)>/', '$1', $this->mail->headers['cc'] );

			if ( mb_strpos( $this->mail->headers['cc'], ',' ) === FALSE )
			{
				$this->mail->headers['cc'] = array( $this->mail->headers['cc'] );
			}
			else
			{
				$this->mail->headers['cc'] = explode( ',', $this->mail->headers['cc'] );
			}

			foreach ( $this->mail->headers['cc'] as $_cc )
			{
				if ( preg_match( "/.+? <(.+?)>/", $_cc, $matches ) )
				{
					$cc[]	= $matches[1];
				}
				else
				{
					$cc[]	= trim( $_cc, '<> ' );
				}
			}
		}

		$this->data['cc']	= implode( ',', $cc );

		/* Now start processing the body */
		$this->data['message']	= '';

		$this->_parsePart( $this->mail );

		/* Clean up the message before passing to HTML Purifier */
		$this->data['message']	= str_replace( '&nbsp;', ' ', $this->data['message'] );

		/* Parse the message */
		$this->data['message']	= \IPS\Text\Parser::parseStatic( $this->data['message'], FALSE, NULL, \IPS\Member::load( $this->data['from'], 'email' ), TRUE, TRUE, TRUE, function( $config ){
			$config->set( 'HTML.TargetBlank', TRUE );
			$config->set( 'URI.Munge', (string) \IPS\Http\Url::internal( 'app=core&module=system&controller=redirect&url=%s&key=%t&resource=%r', 'front' ) );
			$config->set( 'URI.MungeResources', TRUE );
			$config->set( 'URI.MungeSecretKey', md5( \IPS\Settings::i()->sql_pass . \IPS\Settings::i()->base_url . \IPS\Settings::i()->sql_database ) );
		});

		/* Parse the quote too if any */
		if( $this->data['quoted'] )
		{
			$this->data['quoted']	= \IPS\Text\Parser::parseStatic( $this->data['quoted'], FALSE, NULL, \IPS\Member::load( $this->data['from'], 'email' ), TRUE, TRUE, TRUE, function( $config ){
				$config->set( 'HTML.TargetBlank', TRUE );
				$config->set( 'URI.Munge', (string) \IPS\Http\Url::internal( 'app=core&module=system&controller=redirect&url=%s&key=%t&resource=%r', 'front' ) );
				$config->set( 'URI.MungeResources', TRUE );
				$config->set( 'URI.MungeSecretKey', md5( \IPS\Settings::i()->sql_pass . \IPS\Settings::i()->base_url . \IPS\Settings::i()->sql_database ) );
			});
		}

		/* And return */
		return $this;
	}

	/**
	 * Override basic values extracted from the email.
	 *
	 * @param	array 	$override	[Optional] Pass an array that allows you to override data in the email
	 * @return \IPS\Email\Incoming
	 */
	public function setOverrides( $override )
	{
		/* Are we overriding any of the values? */
		if( count($override) )
		{
			foreach( $override as $type => $value )
			{
				$this->data[ $type ]	= $value;
			}
		}

		return $this;
	}

	/**
	 * Route the email and pass off to the appropriate handler
	 *
	 * @return	void
	 */
	public function route()
	{
		/* If debug mode is enabled, print out the appropriate data - we don't routing to happen in this case */
		if( $this->debugMode )
		{
			print "Is this an autoresponder? ";
			var_dump($this->isAutoreply());
			print "<hr>";
			print $this->data['message'];
			print '<hr>';
			print $this->data['quoted'];

			print '<pre>';
			$this->data['raw']		= htmlentities($this->data['raw'], \IPS\HTMLENTITIES, 'UTF-8', FALSE);
			$this->data['rawbody']	= htmlentities($this->data['rawbody'], \IPS\HTMLENTITIES, 'UTF-8', FALSE);
			$this->data['quoted']	= htmlentities($this->data['quoted'], \IPS\HTMLENTITIES, 'UTF-8', FALSE);
			$this->data['message']	= htmlentities($this->data['message'], \IPS\HTMLENTITIES, 'UTF-8', FALSE);
			print_r( $this->data );
			//print_r( $this->mail );
			print '</pre>';
			exit;
		}

		/* Ignore auto-responder messages */
		if( $this->isAutoreply() )
		{
			return;
		}

		/* Initialize some vars */
		$routed		= FALSE;

		/* Get our filter rules from the database */
		foreach ( \IPS\Db::i()->select( '*', 'core_incoming_emails' ) as $row )
		{
			/* Reset some vars */
			$analyze	= NULL;
			$match		= FALSE;

			/* Field to check */
			switch ( $row['rule_criteria_field'] )
			{
				case 'to':
					$analyse = $this->data['to'];
					break;
					
				case 'from':
					$analyse = $this->data['from'];
					break;
					
				case 'sbjt':
					$analyse = $this->data['subject'];
					break;
					
				case 'body':
					$analyse = $this->data['message'];
					break;
			}

			/* Now check if we match the supplied rule */
			switch ( $row['rule_criteria_type'] )
			{
				case 'ctns':
					$match = (bool) ( mb_strpos( $analyse, $row['rule_criteria_value'] ) !== FALSE );
					break;
					
				case 'eqls':
					if ( mb_strpos( $analyse, ',' ) !== FALSE )
					{
						$match = (bool) in_array( $analyse, explode( ',', $analyse ) );
					}
					else
					{
						$match = (bool) ( $analyse == $row['rule_criteria_value'] );
					}
					break;
					
				case 'regx':
					$match = (bool) ( preg_match( "/{$row['rule_criteria_value']}/", $analyse ) == 1 );
					break;
			}

			/* Do we have a match? */
			if ( $match === TRUE )
			{
				$routed	= TRUE;
				break;
			}
		}
		
		/* If we are still here, check each app to see if it wants to handle this unrouted incoming email */
		if ( !$routed )
		{
			/* Loop over all apps that have an incoming email extension */
			foreach ( \IPS\Application::appsWithExtension( 'core', 'IncomingEmail' ) as $dir => $app )
			{
				/* Get all IncomingEmail extension classes for the app */
				$extensions	= $app->extensions( 'core', 'IncomingEmail' );

				if( count($extensions) )
				{
					/* Loop over the extensions */
					foreach( $extensions as $_instance )
					{
						/* And if it returns true, the unrouted email has now been handled.  We can break. */
						if( $routed = $_instance->process( $this ) )
						{
							break;
						}
					}
				}
			}
		}

		/* If we are still here, send an "unrouted email" email to the sender */
		if ( !$routed  )
		{
			if ( \IPS\Email::hasTemplate( 'core', 'unrouted' ) )
			{
				$email			= \IPS\Email::buildFromTemplate( 'core', 'unrouted', $this->data );
				$email->from	= $this->data['to'];
				$email->subject	= 'Re: ' . $this->data['subject'];
				$email->send( $this->data['from'] );
			}
		}
	}

	/**
	 * Is this an auto-reply?  Try to detect to prevent auto-reply loops.
	 *
	 * @return	bool
	 * @link	https://github.com/opennorth/multi_mail/wiki/Detecting-autoresponders
	 */
	protected function isAutoreply()
	{
		/* RFC http://tools.ietf.org/html/rfc3834 */
		if( !empty($this->mail->headers['auto-submitted']) AND mb_strtolower($this->mail->headers['auto-submitted']) != 'no' )
		{
			return TRUE;
		}

		/* If any of these headers are present with any values, ignore the email */
		if( !empty($this->mail->headers['x-auto-response-suppress']) OR 
			!empty($this->mail->headers['x-autorespond']) OR 
			!empty($this->mail->headers['x-autoreply']) OR
			!empty($this->mail->headers['x-autoreply-From']) OR
			!empty($this->mail->headers['x-mail-autoreply'])
			)
		{
			return TRUE;
		}

		/* Now we check for a null return-path (which is considered the "proper" way to prevent auto-responses) */
		if( !empty($this->mail->headers['return-path']) AND $this->mail->headers['return-path'] == '<>' )
		{
			return TRUE;
		}

		/* Now check for specific headers with specific values */
		if( !empty($this->mail->headers['x-autogenerated']) AND in_array( mb_strtolower($this->mail->headers['x-autogenerated']), array( 'forward', 'group', 'letter', 'mirror', 'redirect', 'reply' ) ) )
		{
			return TRUE;
		}
		
		if( !empty($this->mail->headers['precedence']) AND in_array( mb_strtolower($this->mail->headers['precedence']), array( 'auto_reply', 'list', 'bulk' ) ) )
		{
			return TRUE;
		}

		if( !empty($this->mail->headers['x-precedence']) AND in_array( mb_strtolower($this->mail->headers['x-precedence']), array( 'auto_reply', 'list', 'bulk' ) ) )
		{
			return TRUE;
		}

		if( !empty($this->mail->headers['x-fc-machinegenerated']) AND mb_strtolower($this->mail->headers['x-fc-machinegenerated']) == 'true' )
		{
			return TRUE;
		}

		if( !empty($this->mail->headers['x-post-messageclass']) AND mb_strtolower($this->mail->headers['x-post-messageclass']) == '9; autoresponder' )
		{
			return TRUE;
		}

		if( !empty($this->mail->headers['delivered-to']) AND mb_strtolower($this->mail->headers['delivered-to']) == 'autoresponder' )
		{
			return TRUE;
		}

		/* And finally, some basic checks on the subject line */
		if( mb_stripos( $this->mail->headers['subject'], "out of office: " ) === 0 OR 
			mb_stripos( $this->mail->headers['subject'], "out of office autoreply:" ) === 0 OR
			mb_stripos( $this->mail->headers['subject'], "automatic reply: " ) === 0 OR
			mb_strtolower($this->mail->headers['subject']) === "out of office"
			)
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Parse a "part"
	 *
	 * @param	stdClass	As returned by mailMime_decode
	 */
	protected function _parsePart( $part )
	{
		/* Some clients use uppercase, but we check by lowercase */
		$part->ctype_primary	= mb_strtolower( $part->ctype_primary );
		$part->ctype_secondary	= mb_strtolower( $part->ctype_secondary );
						
		/* Make sure .txt files get attached as .txt files */
		if ( $part->ctype_primary == 'text' AND isset($part->disposition) AND $part->disposition == 'attachment' )
		{
			$part->ctype_primary	= 'unknown';
			$part->ctype_secondary	= 'unknown';
		}
		
		/* What is this? */
		switch ( $part->ctype_primary )
		{
			/* Multipart - means there's more than one part to this part so we have to figure out which to use */
			case 'multipart':
				/* "Alternative" means there's more than one way to interpret this part, usually means we have plain text and HTML varients */
				if ( $part->ctype_secondary == 'alternative' )
				{
					/* First, check if we have html and plaintext */
					if ( !$this->data['alternative'] )
					{
						$haveHtml	= false;
						$havePlain	= false;

						foreach ( $part->parts as $p )
						{
							if ( $p->ctype_primary == 'text' )
							{
								if ( $p->ctype_secondary == 'html' )
								{
									$haveHtml	= true;
								}
								elseif ( $p->ctype_secondary == 'plain' )
								{
									$havePlain	= true;
								}
							}
						}
					}

					/* Determine which part is better for us */
					$preferredPart	= array_shift( $part->parts );

					foreach ( $part->parts as $p )
					{
						if ( $this->_isBetter( $preferredPart, $p ) )
						{
							$preferredPart = $p;
						}
					}

					/* What did we choose? */
					if ( !$this->data['alternative'] and $haveHtml and $havePlain )
					{
						if ( $preferredPart->ctype_secondary == 'html' )
						{
							$this->data['alternative'] = 'h';
						}
						elseif ( $preferredPart->ctype_secondary == 'html' )
						{
							$this->data['alternative'] = 'p';
						}
					}
					
					return $this->_parsePart( $preferredPart );
				}
				
				/* Otherwise, parse all parts */
				foreach ( $part->parts as $p )
				{
					$this->_parsePart( $p );
				}

				return;
				
			/* Parse text (email body) */
			case 'text':
				$body 					= $part->body;
				$this->data['rawbody']	.= $body;
												
				/* If this is a plaintext email, we need to convert newlines to line breaks */
				if ( $part->ctype_secondary != 'html' )
				{
					$body	= nl2br( $body );
				}				

				/* Convert the character set if necessary */
				if ( isset( $part->ctype_parameters['charset'] ) and mb_strtolower($part->ctype_parameters['charset']) != 'utf-8' )
				{
					if( in_array( mb_strtolower($part->ctype_parameters['charset']), array_map( 'mb_strtolower', mb_list_encodings() ) ) )
					{
						$body = mb_convert_encoding( $body, 'UTF-8', $part->ctype_parameters['charset'] );
						$part->ctype_parameters['charset']	= 'UTF-8';
					}
				}

				/* Parse out > style quotes - mainly plaintext emails */
				$_quote	= array();
				$_body	= array();
				$_seen	= FALSE;
				$inQuote = FALSE;
				$exploded = explode( "<br />", $body );
				if ( count( $exploded ) === 1 )
				{
					$exploded = explode( '<br>', $body );
				}
				foreach ( $exploded as $k => $line )
				{
					$line = trim( $line );

					if ( mb_substr( $line, 0, 1 ) == '>' )
					{
						$line = ltrim( $line, '> ' );
						
						/* If we are just now hitting a quote line, go back one line to see if it's a "on .. wrote:" line */
						if( !$_seen )
						{
							/* Get the last 2 lines we pushed to the body block.  Sometimes you have "on ... wrote:" followed by an empty line. */
							$_last	= array_pop($_body);
							$_last2	= array_pop($_body);

							$_check	= ( !trim($_last) ) ? $_last2 : $_last;

							/* If it ends with a colon push it to the quote block instead, otherwise put it back */
							if( mb_substr( trim($_check), -1 ) == ':' )
							{
								$_quote[]	= $_last;
								$_quote[]	= $_last2;
							}
							else
							{
								$_body[]	= $_last;
								$_body[]	= $_last2;
							}

							/* Don't do this again */
							$_seen	= true;
						}

						$_quote[]	= $line;
					}
					else
					{
						if ( !$inQuote and preg_match( '/(\s|^)-* ?((Original)|(Forwarded)) Message:? ?-*/i', $line ) )
						{
							$line = preg_replace( '/-* ?(Begin )?((Original)|(Forwarded)) Message:? ?-*/i', '', $line );
							$inQuote = TRUE;
						}
						
						if ( $inQuote )
						{
							$_quote[] = $line;
						}
						else
						{
							$_body[] = $line;
						}
					}
				}
																				
				$quote = implode( $_quote, "<br />" );
				$message = implode( $_body, "<br />" );
				
				/* Parse out <blockquote> tags which are typically used in HTML emails.  Remember that blockquotes
					can be nested and that our own quote routine uses blockquotes, so we have to be careful.  Look for data-ips* attributes to try to weed out our own. */
				if( mb_strpos( $message, '</blockquote>' ) !== FALSE )
				{
					/* First get the position of the last closing blockquote tag */
					$_lastClosingBlockquote	= strrpos( $message, "</blockquote>" );

					/* Now get the position of the first opening blockquote tag */
					preg_match( '/<blockquote(?! data-ips).+?>/s', $message, $matches, PREG_OFFSET_CAPTURE );

					if( $matches[0][1] )
					{
						/* Check for common "on x so and so wrote:" type lines preceeding this position */
						preg_match( '/<div([^>]+?)?>((?!<div).)*:(<br(>| \/>))?\s*<\/div>/s', $message, $header, PREG_OFFSET_CAPTURE );
						
						if( !empty($header) AND $header[0][1] < $matches[0][1] )
						{
							$matches[0][1]	= $header[0][1];
						}

						preg_match( '/<div class=[\'"][^>]*?quote[^>]*?[\'"]>(.*?):(<br(>| \/>))?\s*<blockquote(?! data-ips).+?>/s', $message, $header, PREG_OFFSET_CAPTURE );

						if( !empty($header) AND $header[1][1] < $matches[0][1] )
						{
							$matches[0][1]	= $header[1][1];
						}

						/* Now take everything between these positions and move into the quoted content.  The "13" here is "</blockquote>". */
						$quote = mb_substr( $message, $matches[0][1], ( $_lastClosingBlockquote - $matches[0][1] ) + 13 );

						/* And finally, remove the quoted stuff from our email message */
						$message = substr_replace( $message, '', $matches[0][1], ( $_lastClosingBlockquote - $matches[0][1] ) + 13 );
					}
				}
								
				$this->data['quoted']	.= $quote;
				$this->data['message']	.= $message;

				return;
				
			/* Parse attachments */
			default:
				if ( $part->ctype_primary == '_text' )
				{
					$part->ctype_primary = 'text';
				}

				$this->attachmentCount++;
				
				/* Get the details for this file */
				$mime		= "{$part->ctype_primary}/{$part->ctype_secondary}";
				$name		= ( !empty($part->ctype_parameters['name']) ) ? $part->ctype_parameters['name'] : $part->d_parameters['filename'];
				$ext		= mb_substr( $name, ( strrpos( $name, '.' ) + 1 ) );

				/* Store the attachment.  Later, classes can just call this object ->makeAttachment() passing an appropriate post-key in. */
				$file = \IPS\File::create( 'core_Attachment', $name, $part->body );
				$this->data['attachments'][ $this->attachmentCount ] = $file;
				if ( $file->isImage() )
				{
					$this->data['message'] .= "<img src='{$file}' class='ipsImage ipsImage_thumbnailed'>";
				}
				else
				{
					$this->data['message'] .= "<a class='ipsAttachLink' href='{$file}'>{$file->originalFilename}</a>";
				}

				return;
		}
	}
	
	/**
	 * Decide if one part is better than another for parsing multipart/alternative
	 *
	 * @param	stdClass	$part1	Part 1
	 * @param	stdClass	$part2	Part 2
	 * @return	bool
	 */
	protected function _isBetter( $part1, $part2 )
	{
		/* Define our types */
		$p1Primary		= $part1->ctype_primary;
		$p1Secondary	= $part1->ctype_secondary;
		$p2Primary		= $part2->ctype_primary;
		$p2Secondary	= $part2->ctype_secondary;

		/* If they're the same, return false */
		if ( $p1Primary == $p2Primary and $p1Secondary == $p2Secondary )
		{
			return false;
		}

		/* Do we have a multipart email?  Use that first, as it will contain the attachments */
		if( $p1Primary == 'multipart' )
		{
			if( $p2Primary != 'multipart' )
			{
				return false;
			}
		}
		else if( $p2Primary == 'multipart' )
		{
			return true;
		}

		/* Check for a text email - check for html first and then fall back to plaintext */
		if( $p1Primary == 'text' )
		{
			if( $p2Primary == 'text' )
			{
				if( $p1Secondary == 'html' )
				{
					return false;
				}
				else if( $p2Secondary == 'html' )
				{
					return true;
				}
				else if( $p1Secondary == 'plain' )
				{
					return false;
				}
				else if( $p2Secondary == 'plain ')
				{
					return true;
				}
			}
		}
		else if( $p2Primary == 'text' )
		{
			return true;
		}

		/* If we're still here, neither part satisfies our requests - assume Part 1 is better */
		return false;
	}
}