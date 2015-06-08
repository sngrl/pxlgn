<?php
/**
 * @brief		Theme Changer
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Dec 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Theme Changer
 */
class _theme extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Session::i()->csrfCheck();
		
		if( \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Member::loggedIn()->skin = (int) \IPS\Request::i()->id;
			\IPS\Member::loggedIn()->save();
		}
		else
		{
			\IPS\Request::i()->setCookie( 'theme', (int) \IPS\Request::i()->id );
		}
		
		/* Make sure VSE cookie is killed */
		if ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) )
		{
			\IPS\Request::i()->setCookie( 'vseThemeId', 0 );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::external( $_SERVER['HTTP_REFERER'] ) ?: \IPS\Http\Url::internal( '' ) );
	}
}