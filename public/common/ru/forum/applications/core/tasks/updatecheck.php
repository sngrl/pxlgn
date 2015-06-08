<?php
/**
 * @brief		updatecheck Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		14 Aug 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * updatecheck Task
 */
class _updatecheck extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * @return	mixed	Message to log or NULL
	 */
	public function execute()
	{
		$fails = array();
		
		/* Do IPS apps */
		$versions = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_applications', \IPS\Db::i()->in( 'app_directory', \IPS\Application::$ipsApps ) ) as $app )
		{
			if ( $app['app_enabled'] )
			{
				$versions[] = $app['app_long_version'];
			}
		}
		$version = min( $versions );
		$url = \IPS\Http\Url::ips('updateCheck');
		if ( \IPS\USE_DEVELOPMENT_BUILDS )
		{
			$url = $url->setQueryString( 'development', 1 );
		}
		try
		{
			$response = $url->request()->get()->decodeJson();
			$coreApp = \IPS\Application::load('core');
			$coreApp->update_version = json_encode( $response );
			$coreApp->update_last_check = time();
			$coreApp->save();
		}
		catch ( \Exception $e ) { }
		
		/* Do everything else */
		foreach ( \IPS\Db::i()->union(
			array(
				\IPS\Db::i()->select( "'core_applications' AS `table`, app_directory AS `id`, app_update_check AS `url`, app_update_last_check AS `last`, app_long_version AS `current`", 'core_applications', "( app_update_check<>'' AND app_update_check IS NOT NULL )" ),
				\IPS\Db::i()->select( "'core_plugins' AS `table`, plugin_id AS id, plugin_update_check as url, plugin_update_check_last AS last, plugin_version_long AS `current`", 'core_plugins', "plugin_update_check<>'' AND plugin_update_check IS NOT NULL" ),
				\IPS\Db::i()->select( "'core_themes' AS `table`, set_id AS `id`, set_update_check AS `url`, set_update_last_check AS `last`, set_long_version AS `current`", 'core_themes', "set_update_check<>'' AND set_update_check IS NOT NULL" )
			),
			'last ASC',
			3
		) as $row )
		{
			try
			{
				$url = \IPS\Http\Url::external( $row['url'] )->setQueryString( 'version', $row['current'] );				
				$response = $url->request()->get()->decodeJson();
				
				switch ( $row['table'] )
				{
					case 'core_applications':
						$dataColumn = 'app_update_version';
						$timeColumn = 'app_update_last_check';
						$idColumn	= 'app_directory';
						break;
						
					case 'core_plugins':
						$dataColumn = 'plugin_update_check_data';
						$timeColumn = 'plugin_update_check_last';
						$idColumn	= 'plugin_id';
						break;
						
					case 'core_themes':
						$dataColumn = 'set_update_data';
						$timeColumn = 'set_update_last_check';
						$idColumn	= 'set_id';
						break;
				}
				
				\IPS\Db::i()->update( $row['table'], array(
					$dataColumn => json_encode( array(
						'version'		=> $response['version'],
						'longversion'	=> $response['longversion'],
						'released'		=> $response['released'],
						'updateurl'		=> $response['updateurl'],
					) ),
					$timeColumn	=> time()
				), array( "{$idColumn}=?", $row['id'] ) );
			}
			/* \RuntimeException catches BAD_JSON and \IPS\Http\Request\Exception both */
			catch ( \RuntimeException $e )
			{
				$fails[] = $e->getMessage();
			}
		}
		
		if ( !empty( $fails ) )
		{
			return $fails;
		}
		
		return NULL;
	}
}