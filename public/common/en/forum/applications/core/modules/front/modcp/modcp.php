<?php
/**
 * @brief		Moderator Control Panel
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		24 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\modcp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Control Panel
 */
class _modcp extends \IPS\Dispatcher\Controller
{
	/**
	 * Moderator Control Panel
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Check we're not a guest */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S194/1', 403, '' );
		}

		/* Make sure we are a moderator */
		if ( \IPS\Member::loggedIn()->modPermission() === FALSE )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S194/2', 403, '' );
		}
		
		/* Set up the tabs */
		$activeTab	= \IPS\Request::i()->tab ?: 'overview';
		$tabs		= array( 'reports' => array(), 'approval' => array() );
		$content	= '';
		foreach ( \IPS\Application::allExtensions( 'core', 'ModCp', TRUE ) as $key => $extension )
		{
			if( method_exists( $extension, 'getTab' ) )
			{
				$tab = $extension->getTab();
								
				if ( $tab )
				{
					$tabs[ $tab ][] = $key;
				}
			}

			if( mb_strtolower( $extension->getTab() ) == mb_strtolower( $activeTab ) )
			{
				$method = \IPS\Request::i()->action ?: 'manage';
				if ( method_exists( $extension, $method ) or method_exists( $extension, '__call' ) )
				{
					$content = call_user_func( array( $extension, $method ) );
					if ( !$content )
					{
						$content = \IPS\Output::i()->output;
					}
				}
			}
		}
		$tabs = array_filter( $tabs, 'count' );
		
		/* Got a page? */
		if ( !$content )
		{
			foreach ( $tabs as $k => $data )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab={$k}", 'front', "modcp_{$k}" ) );
			}
		}
		
		/* Content Types */
		$types = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE, FALSE, TRUE ) as $class )
		{
			if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
			}
			
			if ( isset( $class::$commentClass ) and in_array( 'IPS\Content\Hideable', class_implements( $class::$commentClass ) ) )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$commentClass, 4 ) ) ) ] = $class::$commentClass;
			}
			
			if ( isset( $class::$reviewClass ) and in_array( 'IPS\Content\Hideable', class_implements( $class::$reviewClass ) ) )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$reviewClass, 4 ) ) ) ] = $class::$reviewClass;
			}
		}

		/* Display */
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/modcp.css' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/modcp_responsive.css' ) );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_modcp.js', 'core' ) );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $content;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'modcp' )->template( $content, $tabs, $activeTab, $types, \IPS\Content\Search\Query::init()->setHiddenFilter( \IPS\Content\Search\Query::HIDDEN_UNAPPROVED )->search()->count( TRUE ) );
		}
	}
	
	
}