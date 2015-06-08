<?php
/**
 * @brief		Upgrader: Login
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 May 2014
 * @version		SVN_VERSION_NUMBER
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Login
 */
class _login extends \IPS\Dispatcher\Controller
{
	/**
	 * Show login form and/or process login form
	 *
	 * @todo	[Upgrade] Will also need to account for things in the input (e.g. password) that would be replaced, like & to &amp;
	 * @return	void
	 */
	public function manage()
	{
		/* Clear previous session data */
		if( count( $_SESSION ) )
		{
			foreach( $_SESSION as $k => $v )
			{
				unset( $_SESSION[ $k ] );
			}
		}

		$login = new \IPS\Login( \IPS\Http\Url::internal( "controller=login&start=1", NULL, NULL, NULL, \IPS\Settings::i()->logins_over_https ) );
		$login->flagOptions	= FALSE;
				
		/* < 4.0.0 */
		$legacy = FALSE;
		if( \IPS\Db::i()->checkForTable( 'login_methods' ) )
		{
			$legacy = TRUE;
		}
		
		/* Restoring a part finished upgrade means no log in hander rows even though table has been renamed */
		if ( \IPS\Db::i()->checkForTable( 'core_login_handlers' ) )
		{
			$legacy = FALSE;
			
			if ( ! \IPS\Db::i()->select( 'COUNT(*)', 'core_login_handlers' )->first() )
			{
				$legacy = TRUE;
			}
		}
		
		if ( $legacy === TRUE )
		{
			/* Force internal only as we don't have the framework installed (JS/templates, etc) at this point to run external log in modules */
			\IPS\Login\LoginAbstract::$databaseTable = 'login_methods';
			
			$login::$allHandlers['internal'] = \IPS\Login\LoginAbstract::constructFromData( array (
				'login_key'      => 'Upgrade',
				'login_enabled'  => 1,
				'login_settings' => '{"auth_types":"3"}',
				'login_order'    => 1,
				'login_acp'      => 1
			) );
			
			$login::$handlers = $login::$allHandlers;
		}

		$handlers = \IPS\Login::handlers();

		/* Process */
		$error = NULL;
		try
		{
			$member = $login->authenticate();
			if ( $member !== NULL )
			{
				/* Create a unique session key and redirect */
				$_SESSION['uniqueKey']	= md5( uniqid( microtime(), TRUE ) );

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=systemcheck" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
			}
		}
		catch ( \Exception $e )
		{
			$error = $e->getMessage();
		}

		/* Output */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('login');
		\IPS\Output::i()->output 	.= \IPS\Theme::i()->getTemplate( 'forms' )->login( $login->forms( TRUE ), $error );
	}
}