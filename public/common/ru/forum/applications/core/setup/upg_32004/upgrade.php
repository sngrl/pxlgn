<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		4 Jun 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\setup\upg_32004;

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
		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN( 'posting_allow_rte', 'spider_group' )" );

		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_key' => 'member_photo_gif_animate' ), "conf_key='member_photo_gif_no_animate'" );

		unset( \IPS\Data\Store::i()->settings );

		\IPS\Db::i()->dropColumn( 'groups', array( 'g_email_friend', 'g_email_limit' ) );

		\IPS\Db::i()->changeColumn( 'skin_collections', 'set_permissions', array(
			'name'			=> 'set_permissions',
			'type'			=> 'text',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->dropIndex( 'member_status_updates', 's_hash' );
		\IPS\Db::i()->addIndex( 'member_status_updates', array(
			'type'			=> 'key',
			'name'			=> 's_hash',
			'columns'		=> array( 'status_member_id', 'status_hash', 'status_date' )
		) );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'reputation_cache',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "reputation_cache ADD INDEX `type` (`type`, type_id);"
		) ) );
		
		if ( count( $toRun ) )
		{
			$mr = json_decode( \IPS\Request::i()->mr, TRUE );
			$mr['extra']['_upgradeStep'] = 2;
			
			\IPS\Request::i()->mr = json_encode( $mr );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* Finish */
		return TRUE;
	}
}