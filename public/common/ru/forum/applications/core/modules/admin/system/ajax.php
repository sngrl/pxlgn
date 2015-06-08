<?php
/**
 * @brief		Core AJAX Responders
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		13 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core AJAX Responders
 */
class _ajax extends \IPS\core\modules\front\system\ajax
{
	/**
	 * Save ACP Tabs
	 *
	 * @return	void
	 */
	protected function saveTabs()
	{
		if ( is_array( \IPS\Request::i()->tabOrder ) )
		{
			$tabs	= array();

			foreach( \IPS\Request::i()->tabOrder as $topLevelTab )
			{
				$tabs[ str_replace( "tab_", "", $topLevelTab ) ]	= ( isset( \IPS\Request::i()->menuOrder[ $topLevelTab ] ) ) ? \IPS\Request::i()->menuOrder[ $topLevelTab ] : array();
			}

			$key = 'acpTabs-' . \IPS\Member::loggedIn()->member_id;
			\IPS\Data\Store::i()->$key = $tabs;
			\IPS\Request::i()->setCookie( 'acpTabs', json_encode( $tabs ) );
		}
		
		\IPS\Output::i()->json( 'ok' );
	}
	
	/**
	 * Save search keywords
	 *
	 * @return	void
	 */
	protected function searchKeywords()
	{
		if ( \IPS\IN_DEV )
		{
			$url = base64_decode( \IPS\Request::i()->url );
			$qs = array();
			parse_str( $url, $qs );
			
			\IPS\Db::i()->delete( 'core_acp_search_index', array( 'url=?', $url ) );
			
			$inserts = array();
			foreach ( \IPS\Request::i()->keywords as $word )
			{
				$inserts[] = array(
					'url'			=> $url,
					'keyword'		=> $word,
					'app'			=> $qs['app'],
					'lang_key'		=> \IPS\Request::i()->lang_key,
					'restriction'	=> \IPS\Request::i()->restriction ?: NULL
				);
			}
			
			if( count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_acp_search_index', $inserts );
			}
						
			$keywords = array();
			foreach ( \IPS\Db::i()->select( '*', 'core_acp_search_index', array( 'app=?', $qs['app'] ) ) as $word )
			{
				$keywords[ $word['url'] ]['lang_key'] = $word['lang_key'];
				$keywords[ $word['url'] ]['restriction'] = $word['restriction'];
				$keywords[ $word['url'] ]['keywords'][] = $word['keyword'];
			}
						
			\file_put_contents( \IPS\ROOT_PATH . "/applications/{$qs['app']}/data/acpsearch.json", json_encode( $keywords ) );
		}
		
		\IPS\Output::i()->json( 'ok' );
	}
}