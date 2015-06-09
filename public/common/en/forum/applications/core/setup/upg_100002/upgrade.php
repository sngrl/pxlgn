<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		25 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\setup\upg_100002;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Fix languages
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if( !\IPS\Db::i()->checkForColumn( 'core_sys_lang', 'lang_enabled' ) )
		{
			\IPS\Db::i()->addColumn( 'core_sys_lang', array(
				'name'			=> 'lang_enabled',
				'type'			=> 'BIT',
				'length'		=> 1,
				'allow_null'	=> false,
				'binary'		=> false,
				'comment'		=> "Indicates if the language is enabled",
				'default'		=> 1,
			)	);
		}

		if( !\IPS\Db::i()->checkForColumn( 'core_members', 'members_cache' ) )
		{
			\IPS\Db::i()->addColumn( 'core_members', array(
				'name'			=> 'members_cache',
				'type'			=> 'MEDIUMTEXT',
				'length'		=> 0,
				'allow_null'	=> true,
				'binary'		=> false,
				'default'		=> null,
			)	);
		}

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Checking languages";
	}

	/**
	 * Step 2
	 * Fix seo titles
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_announcements', array( 'announce_seo_title' => '' ) );
		\IPS\Db::i()->update( 'core_members', array( 'members_seo_name' => '' ) );

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Resetting friendly URL titles";
	}
}