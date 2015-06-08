<?php
/**
 * @brief		Activity Stream
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		2 Sep 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\activity;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Activity Stream
 */
class _activity extends \IPS\Dispatcher\Controller
{
	/**
	 * New Content
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Flood control */
		\IPS\Request::floodCheck();
		
		/* Get the different types */
		$types			= array();
		$hasCallback	= array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', TRUE, NULL, NULL, TRUE, TRUE ) as $router )
		{
			foreach( $router->classes as $class )
			{
				if( !is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					continue;
				}

				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
				
				if ( isset( $class::$commentClass ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$commentClass, 4 ) ) ) ] = $class::$commentClass;
				}
				
				if ( isset( $class::$reviewClass ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$reviewClass, 4 ) ) ) ] = $class::$reviewClass;
				}

				if( method_exists( $class, 'customTableHelper' ) )
				{
					$hasCallback[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ]					= $router;

					if ( isset( $class::$commentClass ) )
					{
						$hasCallback[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$commentClass, 4 ) ) ) ]	= $router;
					}

					if ( isset( $class::$reviewClass ) )
					{
						$hasCallback[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$reviewClass, 4 ) ) ) ]		= $router;
					}
				}
			}
		}
		
		/* What type are we looking at? */
		$currentAppModule = NULL;
		$currentType = NULL;
		if ( isset( \IPS\Request::i()->type ) )
		{
			foreach ( $types as $appModule => $_types )
			{
				if ( array_key_exists( \IPS\Request::i()->type, $_types ) )
				{
					$currentAppModule = $appModule;
					$currentType = \IPS\Request::i()->type;
					break;
				}
			}
		}

		$url = \IPS\Http\Url::internal( "app=core&module=activity&controller=activity", 'front', 'activity' );

		/* Build Output */
		if ( !$currentType )
		{
			$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

			if( $page < 1 )
			{
				$page = 1;
			}

			$query = \IPS\Content\Search\Query::init()->excludeFirstPostContentItems()->excludeDisabledApps()->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED )->setPage( $page );
			$results = $query->search();
			$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $url, ceil( $results->count( TRUE ) / $query->resultsToGet ), $page, $query->resultsToGet ) );
			$output = \IPS\Theme::i()->getTemplate('system')->activityStream( $results, $pagination );
		}
		else
		{
			$currentClass = $types[ $currentAppModule ][ $currentType ];
			$currentAppArray = explode( '_', $currentAppModule );
			$currentApp = $currentAppArray[0];
			if( isset( $hasCallback[ $currentType ] ) )
			{
				$output	= $hasCallback[ $currentType ]->customTableHelper( $currentClass, $url->setQueryString( array( 'type' => $currentType ) ) );
			}
			else
			{
				$where = array();
				if ( isset( $currentClass::$databaseColumnMap['state'] ) )
				{
					$where[] = array( $currentClass::$databaseColumnMap['state'] . ' != ?', 'link' );
				}
				$output = new \IPS\Helpers\Table\Content( $currentClass, $url->setQueryString( array( 'type' => $currentType ) ), $where, NULL, NULL, 'read', FALSE );
			}

			$output->showFilters	= FALSE;
		}
		
		/* If we've clicked from the tab section */
		if ( \IPS\Request::i()->isAjax() && \IPS\Request::i()->change_section )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'system' )->activitySection( $url, $types, $currentAppModule, $currentType, (string) $output ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			/* Display */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->get('activity_stream');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->activity( $url, $types, $currentAppModule, $currentType, (string) $output );
		}
	}
}