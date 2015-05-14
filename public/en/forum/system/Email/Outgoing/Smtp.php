<?php
/**
 * @brief		SMTP Email Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Email\Outgoing;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	SMTP Email Class
 */
class _SMTP extends \IPS\Email
{
	/**
	 * @brief	SMTP Conversation Log
	 */
	public $log;
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->log = '';
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		if( static::$smtp !== NULL )
		{
			$this->_sendCommand( 'quit' );

			@fclose( static::$smtp );
			static::$smtp = NULL;
		}
	}

	/**
	 * @brief	SMTP Connection
	 */
	protected static $smtp = NULL;

	/**
	 * Connect to server
	 *
	 * @return void
	 */
	public function connect()
	{
		/* Do we already have a connection? */
		if( static::$smtp !== NULL )
		{
			return;
		}

		/* Connect */
		static::$smtp = @fsockopen( \IPS\Settings::i()->smtp_host, \IPS\Settings::i()->smtp_port, $errno, $errstr );

		if ( !static::$smtp )
		{
			throw new \IPS\Email\Outgoing\Exception( $errstr, $errno );
		}

		/* Check the initial response is okay */
		$responseCode = mb_substr( fgets( static::$smtp ), 0, 3 );

		if ( $responseCode != 220 )
		{
			throw new \IPS\Email\Outgoing\Exception( \IPS\Member::loggedIn()->language()->addToStack('smtpmail_fsock_error_initial', FALSE, array( 'sprintf' => array( $responseCode ) ) ) );
		}
		
		/* HELO/EHLO */
		try
		{
			$responseCode = $this->_sendCommand( 'EHLO ' . \IPS\Settings::i()->smtp_host, 250 );
		}
		catch ( \IPS\Email\Outgoing\Exception $e )
		{
			$responseCode = $this->_sendCommand( 'HELO ' . \IPS\Settings::i()->smtp_host, 250 );
		}

		/* Authenticate */
		if ( \IPS\Settings::i()->smtp_user )
		{
			$responseCode = $this->_sendCommand( 'AUTH LOGIN', 334 );
			$responseCode = $this->_sendCommand( base64_encode( \IPS\Settings::i()->smtp_user ), 334 );
			$responseCode = $this->_sendCommand( base64_encode( \IPS\Settings::i()->smtp_pass ), 235 );
		}
	}
		
	/**
	 * Send
	 *
	 * @throws	\Exception
	 * @note	We use $this->from here instead of the 'From' header, because the header may have a display name embedded
	 */
	protected function _send()
	{
		$this->connect();
		$this->_sendCommand( "MAIL FROM:<{$this->from}>", 250 );
		
		foreach ( $this->recipients as $to )
		{
			$this->_sendCommand( "RCPT TO:<{$to}>", 250 );
		}
				
		$this->_sendCommand( 'DATA', 354 );
		$this->_sendCommand( "{$this->_compileHeaders()}\r\n\r\n{$this->message}\r\n.", 250 );
	}
	
	/**
	 * Send SMTP Command
	 *
	 * @param	string	$command			The command
	 * @param	int		$expectedResponse	The expected response code. Will throw an exception if different.
	 * @return	string	Response
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	protected function _sendCommand( $command, $expectedResponse=NULL )
	{
		/* Send */
		$this->log .= "> {$command}\r\n";
		fputs( static::$smtp, $command . "\r\n" );
		
		/* Read */
		$response = '';
		while ( $line = @fgets( static::$smtp, 515 ) )
		{			
			$response .= $line;
			if ( mb_substr($line, 3, 1) == " " )
			{
				break;
			}
		}
		$this->log .= $response;
		
		/* Get response code */
		$code = intval( mb_substr( $line, 0, 3 ) );
		if ( $expectedResponse !== NULL and $code !== $expectedResponse )
		{
			throw new \IPS\Email\Outgoing\Exception( $response, $code );
		}
		
		/* Return */
		return $response;
	}

	/**
	 * Return the SMTP log
	 *
	 * @return string
	 */
	public function getLog()
	{
		return $this->log;
	}
}