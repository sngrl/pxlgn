<?php
/**
 * @brief		AJAX actions
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		04 Apr 2013
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
 * AJAX actions
 */
class _ajax extends \IPS\Dispatcher\Controller
{
	/**
	 * Find Member
	 *
	 * @retun	void
	 */
	public function findMember()
	{
		$results = array();
		
		$input = mb_strtolower( \IPS\Request::i()->input );
		
		$where = array( "name LIKE CONCAT('%', ?, '%')" );
		$binds = array( $input );
		if ( \IPS\Dispatcher::i()->controllerLocation === 'admin' )
		{
			$where[] = "email LIKE CONCAT('%', ?, '%')";
			$binds[] = $input;
			
			if ( is_numeric( \IPS\Request::i()->input ) )
			{
				$where[] = "member_id=?";
				$binds[] = intval( \IPS\Request::i()->input );
			}
		}
				
		/* Build the array item for this member after constructing a record */
		/* The value should be just the name so that it's inserted into the input properly, but for display, we wrap it in the group *fix */
		foreach ( \IPS\Db::i()->select( '*', 'core_members', array_merge( array( implode( ' OR ', $where ) ), $binds ) ) as $row )
		{
			$member = \IPS\Member::constructFromData( $row );
			
			$results[] = array(
				'id'	=> 	$member->member_id,
				'value' => 	$member->name,
				'name'	=> 	\IPS\Dispatcher::i()->controllerLocation == 'admin' ? $member->group['prefix'] . htmlentities( $member->name, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) . $member->group['suffix'] : htmlentities( $member->name, \IPS\HTMLENTITIES, 'UTF-8', FALSE ),
				'extra'	=> 	\IPS\Dispatcher::i()->controllerLocation == 'admin' ? $member->email : $member->groupName,
				'photo'	=> 	(string) $member->photo,
			);
		}
				
		\IPS\Output::i()->json( $results );
	}
	
	/**
	 * Returns boolean in json indicating whether the supplied username already exists
	 *
	 * @return	void
	 */
	public function usernameExists()
	{
		$result = array( 'result' => 'ok' );
		
		/* Check is valid */
		if ( mb_strlen( \IPS\Request::i()->input ) > \IPS\Settings::i()->max_user_name_length )
		{
			$result = array( 'result' => 'fail', 'message' => \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_length_max', FALSE, array( 'pluralize' => \IPS\Settings::i()->max_user_name_length ) ) );
		}
		if ( \IPS\Settings::i()->username_characters and !preg_match( '/^[' . str_replace( '\-', '-', preg_quote( \IPS\Settings::i()->username_characters, '/' ) ) . ']*$/i', \IPS\Request::i()->input ) )
		{
			$result = array( 'result' => 'fail', 'message' => \IPS\Member::loggedIn()->language()->addToStack('form_bad_value') );
		}

		/* Check if it exists */
		foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
		{
			if ( $handler->usernameIsInUse( \IPS\Request::i()->input ) === TRUE )
			{
				if ( \IPS\Member::loggedIn()->isAdmin() )
				{
					$result = array( 'result' => 'fail', 'message' => \IPS\Member::loggedIn()->language()->addToStack('member_name_exists_admin', FALSE, array( 'sprintf' => array( $k ) ) ) );
				}
				else
				{
					$result = array( 'result' => 'fail', 'message' => \IPS\Member::loggedIn()->language()->addToStack('member_name_exists') );
				}
			}
		}

		\IPS\Output::i()->json( $result );	
	}

	/**
	 * Returns boolean in json indicating whether the supplied email already exists
	 *
	 * @return	void
	 */
	public function emailExists()
	{
		$result = array( 'result' => 'ok' );

		/* Check if it exists */
		foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
		{
			if ( $handler->emailIsInUse( \IPS\Request::i()->input ) === TRUE )
			{
				if ( \IPS\Member::loggedIn()->isAdmin() )
				{
					$result = array( 'result' => 'fail', 'message' => \IPS\Member::loggedIn()->language()->addToStack('member_email_exists_admin', FALSE, array( 'sprintf' => array( $k ) ) ) );
				}
				else
				{
					$result = array( 'result' => 'fail', 'message' => \IPS\Member::loggedIn()->language()->addToStack('member_email_exists') );
				}
			}
		}

		\IPS\Output::i()->json( $result );	
	}

	/**
	 * Get state/region list for country
	 *
	 * @return	void
	 */
	public function states()
	{
		$states = array();
		if ( array_key_exists( \IPS\Request::i()->country, \IPS\GeoLocation::$states ) )
		{
			$states = \IPS\GeoLocation::$states[ \IPS\Request::i()->country ];
		}
		
		\IPS\Output::i()->json( $states );
	}
}