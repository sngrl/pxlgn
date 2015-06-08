<?php
/**
 * @brief		POP3 Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		16 Apr 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * POP3 Task
 */
class _pop extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		/* If we haven't set up POP3, disable ourselves */
		if ( !\IPS\Settings::i()->pop3_server )
		{
			$this->enabled = FALSE;
			$this->save();
			return;
		}
		
		/* Or do it */
		require_once \IPS\ROOT_PATH . '/system/3rd_party/pop3/pop3.class.inc';
		$pop3 = new \POP3;
		try
		{
			/* Connect */
			if ( !$pop3->connect( ( \IPS\Settings::i()->pop3_tls ? 'ssl://' : '' ) . \IPS\Settings::i()->pop3_server, \IPS\Settings::i()->pop3_port ) )
			{
				throw new \IPS\Task\Exception( $this, \IPS\Member::loggedIn()->language()->addToStack( 'pop3_cant_connect' ) );
			}
			
			/* Login */
			if ( !$pop3->login( \IPS\Settings::i()->pop3_user, \IPS\Settings::i()->pop3_password ) )
			{
				throw new \IPS\Task\Exception( $this, \IPS\Member::loggedIn()->language()->addToStack( 'pop3_cant_login ' ) );
			}
			
			/* Got any? */
			$status = @$pop3->get_office_status();
			if ( $status['count_mails'] )
			{
				foreach ( range( 1, $status['count_mails'] ) as $i )
				{					
					if( $pop3->_putline( "RETR $i" ) )
					{
						if ( mb_substr( @$pop3->_getnextstring(), 0, 3 ) == "+OK" )
						{	
							$message = '';

							while ( TRUE )
							{
								$line = @$pop3->_getnextstring();
								if ( preg_match( "/^\.\r\n/", $line ) )
								{
									break;
								}
								$message .= $line;
							}
														
							\IPS\Email\Incoming::i( $message )->route();
							
							$pop3->delete_mail( $i );
						}
					}
				}
			}
						
			/* Close Connection */
			$pop3->close();
		}
		catch ( \Exception $e )
		{
			\IPS\Log::i( \LOG_CRIT )->write( get_class( $exception ) . "\n" . $exception->getCode() . ": " . $exception->getMessage() . "\n" . $exception->getTraceAsString() );
			throw new \IPS\Task\Exception( $this, $e->getMessage() );
		}
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}