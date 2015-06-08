<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		3 Jun 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\setup\upg_33009;

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
		\IPS\Db::i()->addColumn( 'dnames_change', array(
			'name'			=> 'dname_discount',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		if( !\IPS\Db::i()->checkForColumn( 'members', 'unacknowledged_warnings' ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
				'table' => 'members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD COLUMN unacknowledged_warnings TINYINT(1) NULL DEFAULT NULL;"
			) ) );
			
			if ( count( $toRun ) )
			{
				$mr = json_decode( \IPS\Request::i()->mr, TRUE );
				$mr['extra']['_upgradeStep'] = 2;
				
				\IPS\Request::i()->mr = json_encode( $mr );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
		}

		/* Finish */
		return TRUE;
	}
}