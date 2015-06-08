<?php
/**
 * @brief		PHP Email Class
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
 * PHP Email Class
 */
class _PHP extends \IPS\Email
{
	/**
	 * Send
	 *
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	protected function _send()
	{
		try
		{
			if ( !mail( $this->headers['To'], mb_encode_mimeheader( $this->subject ), $this->message, $this->_compileHeaders( TRUE ), \IPS\Settings::i()->php_mail_extra ) )
			{
				$error = error_get_last();
				preg_match( "/^(\d+)/", $error['message'], $matches );
				throw new \IPS\Email\Outgoing\Exception( $error['message'], isset( $matches[1] ) ? $matches[1] : NULL );
			}
		}
		catch ( \Exception $e )
		{
			throw new \IPS\Email\Outgoing\Exception( $e->getMessage() );
		}

		return TRUE;
	}
}