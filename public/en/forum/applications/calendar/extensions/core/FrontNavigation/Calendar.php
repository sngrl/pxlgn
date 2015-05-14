<?php
/**
 * @brief		Front Navigation Extension: Calendar
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Calendar
 * @since		23 Dec 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Calendar
 */
class _Calendar
{
	/**
	 * Can access?
	 *
	 * @return	bool
	 */
	public function canView()
	{
		return !\IPS\Application::load('calendar')->hide_tab and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'calendar', 'calendar' ) );
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('__app_calendar');
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=view", 'front', 'calendar' );
	}
	
	/**
	 * Is Active?
	 *
	 * @return	bool
	 */
	public function active()
	{
		return \IPS\Dispatcher::i()->application->directory === 'calendar';
	}
	
	/**
	 * Children
	 *
	 * @return	array
	 */
	public function children()
	{
		return array();
	}
}