<?php
/**
 * @brief		Debug Email Class
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
 * Debug Email Class
 */
class _Debug extends \IPS\Email
{
	/**
	 * @brief	Debug path to write the email files
	 */
	public $debugPath	= '';

	/**
	 * Send
	 *
	 * @return	bool
	 * @throws	\IPS\Email\Exception
	 */
	protected function _send()
	{
		if( !is_dir( $this->debugPath ) )
		{
			throw new \IPS\Email\Outgoing\Exception( sprintf( \IPS\Member::loggedIn()->language()->get('no_path_email_debug'), $this->debugPath ) );
		}

		$debugString	= $this->subject . "\n";
		$debugString	.= "To: " . implode( ', ', $this->to ) . "\n";
		$debugString	.= str_repeat( '-', 40 ) . "\n";
		$debugString	.= $this->_compileHeaders() . "\n";
		$debugString	.= str_repeat( '-', 40 ) . "\n";
		$debugString	.= $this->message;

		$fileName		= date("M-j-Y") . '-' . microtime() . '-' . urlencode( mb_substr( implode( ',', $this->to ), 0, 200 ) ) . ".txt";

		$this->writeLog( $fileName, $debugString );

		return TRUE;
	}

	/**
	 * Write the debug file
	 *
	 * @param	string	$filename	File name
	 * @param	string	$data		Data to write to the file
	 * @return void
	 */
	protected function writeLog( $filename, $data )
	{
		if( !@\file_put_contents( rtrim( $this->debugPath, '/' ) . '/' . $filename, $data ) )
		{
			throw new \IPS\Email\Outgoing\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'no_write_email_debug', FALSE, array( 'sprintf' => array( rtrim( $this->debugPath, '/' ) . '/' . $filename ) ) ) );
		}
	}
}