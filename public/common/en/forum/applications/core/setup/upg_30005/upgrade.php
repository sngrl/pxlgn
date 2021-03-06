<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		27 May 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\setup\upg_30005;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrade steps
 */
class _Upgrade
{
	/**
	 * Step 1
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->addColumn( 'skin_css', array(
			'name'			=> 'css_modules',
			'type'			=> 'varchar',
			'length'		=> 250,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'skin_cache', array(
			'name'			=> 'cache_key_6',
			'type'			=> 'varchar',
			'length'		=> 200,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		\IPS\Db::i()->addColumn( 'skin_cache', array(
			'name'			=> 'cache_value_6',
			'type'			=> 'varchar',
			'length'		=> 200,
			'allow_null'	=> false,
			'default'		=> ''
		) );

		if( !\IPS\Db::i()->checkForColumn( 'custom_bbcode', 'bbcode_app' ) )
		{
			\IPS\Db::i()->addColumn( 'custom_bbcode', array(
				'name'			=> 'bbcode_app',
				'type'			=> 'varchar',
				'length'		=> 50,
				'allow_null'	=> false,
				'default'		=> ''
			) );
		}

		\IPS\Db::i()->addColumn( 'skin_collections', array(
			'name'			=> 'set_minify',
			'type'			=> 'int',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->delete( 'core_item_markers' );
		\IPS\Db::i()->delete( 'core_item_markers_storage' );

		\IPS\Db::i()->addIndex( 'core_item_markers_storage', array(
			'type'			=> 'key',
			'name'			=> 'item_last_saved',
			'columns'		=> array( 'item_last_saved' )
		) );

		\IPS\Db::i()->addIndex( 'core_item_markers', array(
			'type'			=> 'key',
			'name'			=> 'item_last_saved',
			'columns'		=> array( 'item_last_saved' )
		) );

		\IPS\Db::i()->dropIndex( 'core_item_markers', 'combo_key' );

		\IPS\Db::i()->addIndex( 'core_item_markers', array(
			'type'			=> 'unique',
			'name'			=> 'combo_key',
			'columns'		=> array( 'item_key', 'item_member_id', 'item_app' )
		) );

		\IPS\Db::i()->update( 'core_hooks', array( 'hook_key' => 'recent_topics' ), array( 'hook_name=?', 'Recent Topics' ) );

		/* Finish */
		return TRUE;
	}
}