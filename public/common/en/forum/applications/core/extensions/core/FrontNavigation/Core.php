<?php
/**
 * @brief		Front Navigation Extension: Core
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Core
 * @since		21 Jan 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Core
 */
class _Core
{
	/**
	 * Can access?
	 *
	 * @return	bool
	 */
	public function canView()
	{
		return (bool) ( \IPS\Settings::i()->show_home_link and \IPS\Member::loggedIn()->language()->checkKeyExists( 'home_name_value' ) );
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('home_name_value');
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return \IPS\Http\Url::external( \IPS\Settings::i()->home_url );
	}
	
	/**
	 * Is Active?
	 *
	 * @return	bool
	 */
	public function active()
	{
		return FALSE;
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