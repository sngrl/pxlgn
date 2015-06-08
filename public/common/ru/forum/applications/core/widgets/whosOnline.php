<?php
/**
 * @brief		whosOnline Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		28 Jul 2014
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
 * whosOnline Widget
 */
class _whosOnline extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'whosOnline';
	
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
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form|null
	 */
	public function configuration( &$form=null )
	{
		return null;
 	} 
 	
 	 /**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
 		return $values;
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
				
		/* Get Members */
		$members = iterator_to_array( \IPS\Db::i()->select( array( 'member_id', 'member_name', 'seo_name', 'member_group' ), 'core_sessions', array(
			array( 'login_type=' . \IPS\Session\Front::LOGIN_TYPE_MEMBER ),
			array( 'running_time>' . \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() )
		), 'running_time DESC', $this->orientation === 'horizontal' ? NULL : 60 )->setKeyField( 'member_id' ) );
		
		/* Get guests count */
		$memberCount = 0;
		$guests = 0;
		$anonymous = 0;
		if ( $this->orientation === 'horizontal' )
		{
			foreach ( \IPS\Db::i()->select( 'login_type, COUNT(*) AS count', 'core_sessions', array( 'running_time>' . \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() ), NULL, NULL, 'login_type' ) as $row )
			{
				switch ( $row['login_type'] )
				{
					case \IPS\Session\Front::LOGIN_TYPE_MEMBER:
						$memberCount += $row['count'];
						break;
					case \IPS\Session\Front::LOGIN_TYPE_ANONYMOUS:
						$anonymous += $row['count'];
						break;
					case \IPS\Session\Front::LOGIN_TYPE_GUEST:
						$guests += $row['count'];
						break;
				}
			}
		}
		else
		{
			$memberCount = count( $members );
		}
		
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
		return $this->output( $members, $memberCount, $guests, $anonymous );
	}
}