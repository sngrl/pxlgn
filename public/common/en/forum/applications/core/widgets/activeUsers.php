<?php
/**
 * @brief		activeUsers Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		19 Nov 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * activeUsers Widget
 */
class _activeUsers extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'activeUsers';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Specify widget configuration
	 *
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration()
 	{
 		return NULL;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* Do we have permission? */
		if ( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'online' ) ) )
		{
			return "";
		}
				
		/* Build WHERE clause */
		$parts = parse_url( (string) \IPS\Request::i()->url() );
		$url = $parts['scheme'] . "://" . $parts['host'] . $parts['path'];

		$where = array(
			array( 'login_type=' . \IPS\Session\Front::LOGIN_TYPE_MEMBER ),
			array( 'current_appcomponent=?', \IPS\Dispatcher::i()->application->directory ),
			array( 'current_module=?', \IPS\Dispatcher::i()->module->key ),
			array( 'current_controller=?', \IPS\Dispatcher::i()->controller ),
			array( 'running_time>' . \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() ),
			array( 'location_url IS NOT NULL AND location_url LIKE ?', "{$url}%" ),
			array( 'member_id IS NOT NULL' )
		);

		if( \IPS\Request::i()->id )
		{
			$where[] = array( 'current_id = ?', \IPS\Request::i()->id );
		}

		/* Get members */
		if ( $this->orientation === 'vertical' )
		{
			$members = \IPS\Db::i()->select( array( 'member_id', 'member_name', 'seo_name', 'member_group' ), 'core_sessions', $where, 'running_time DESC', 60, NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS )->setKeyField( 'member_id' );
			$memberCount = $members->count( TRUE );			
		}
		else
		{
			$members = \IPS\Db::i()->select( array( 'member_id', 'member_name', 'seo_name', 'member_group' ), 'core_sessions', $where, 'running_time DESC' )->setKeyField( 'member_id' );
			$memberCount = $members->count();
		}

		$members = iterator_to_array( $members );

		if( \IPS\Member::loggedIn()->member_id )
		{
			if( !isset( $members[ \IPS\Member::loggedIn()->member_id ] ) )
			{
				$memberCount++;
			}
			
			$members[ \IPS\Member::loggedIn()->member_id ]	= array(
				'member_id'			=> \IPS\Member::loggedIn()->member_id,
				'member_name'		=> \IPS\Member::loggedIn()->name,
				'seo_name'			=> \IPS\Member::loggedIn()->members_seo_name,
				'member_group'		=> \IPS\Member::loggedIn()->member_group_id
			);
		}

		/* Display */
		return $this->output( $members, $memberCount );
	}
}