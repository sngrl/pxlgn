<?php
/**
 * @brief		New Content
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Apr 2014
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
 * New Content
 */
class _vnc extends \IPS\Dispatcher\Controller
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
		
		/* Logged in only */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C232/1', 403, '' );
		}
		elseif( !\IPS\Request::i()->updateFilters )
		{
			$preferences	= json_decode( \IPS\Member::loggedIn()->vnc_preferences, TRUE );

			if( isset( $preferences['onlyFollowed'] ) AND !isset( \IPS\Request::i()->onlyFollowed ) )
			{
				\IPS\Request::i()->onlyFollowed	= $preferences['onlyFollowed'];
			}

			if( isset( $preferences['onlyParticipated'] ) AND !isset( \IPS\Request::i()->onlyParticipated ) )
			{
				\IPS\Request::i()->onlyParticipated	= $preferences['onlyParticipated'];
			}

			if( isset( $preferences['onlyStarted'] ) AND !isset( \IPS\Request::i()->onlyStarted ) )
			{
				\IPS\Request::i()->onlyStarted	= $preferences['onlyStarted'];
			}

			if( isset( $preferences['onlyUnread'] ) AND !isset( \IPS\Request::i()->onlyUnread ) )
			{
				\IPS\Request::i()->onlyUnread	= $preferences['onlyUnread'];
			}

			if( isset( $preferences['searchType'] ) AND !isset( \IPS\Request::i()->type ) )
			{
				\IPS\Request::i()->type	= $preferences['searchType'];
			}

			if( isset( $preferences['vncTimePeriod'] ) AND !isset( \IPS\Request::i()->vncTimePeriod ) )
			{
				\IPS\Request::i()->vncTimePeriod	= $preferences['vncTimePeriod'];
			}

			if( isset( $preferences['container'] ) AND !isset( \IPS\Request::i()->nodes ) )
			{
				\IPS\Request::i()->nodes	= $preferences['container'];
			}
		}
		
		/* Though this isn't part of search, disabling the search module will disable it */
		if ( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'search' ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C232/2', 403, '' );
		}
		
		/* Init */
		$baseUrl = \IPS\Http\Url::internal( 'app=core&module=system&controller=vnc&updateFilters=1', 'front', 'vnc' );
		$nodeSelect = NULL;
		$nodeTitle = NULL;
		
		/* Get types */
		$types = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE, FALSE, TRUE ) as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\ReadMarkers' ) )
			{	
				$key = mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) );
				$types[ $key ] = $class;
			}
		}

		/* If a user last filtered by "downloads_file" and then the download manager app was disabled we need to clear this otherwise you get a fatal error */
		if ( \IPS\Request::i()->type AND \IPS\Request::i()->type !== 'all' AND !isset( $types[ \IPS\Request::i()->type ] ) )
		{
			\IPS\Request::i()->type = '';
		}

		/* Force a type if only one available */
		if( count( $types ) == 1 )
		{
			$keys = array_keys( $types );
			\IPS\Request::i()->type = array_shift( $keys );
		}

		/* Set filters */
		$where = array();
		$start = NULL;
		$end = NULL;
		$onlyUnread = FALSE;
		if ( isset( \IPS\Request::i()->onlyUnread ) and \IPS\Request::i()->onlyUnread )
		{
			$baseUrl = $baseUrl->setQueryString( 'onlyUnread', 1 );			
			$onlyUnread = TRUE;
		}
		$onlyFollowed = FALSE;
		if ( isset( \IPS\Request::i()->onlyFollowed ) and \IPS\Request::i()->onlyFollowed )
		{
			$baseUrl = $baseUrl->setQueryString( 'onlyFollowed', 1 );			
			$onlyFollowed = TRUE;
			$where[] = array( 'follow_id IS NOT NULL' );
		}
		$onlyParticipated = FALSE;
		if ( isset( \IPS\Request::i()->onlyParticipated ) and \IPS\Request::i()->onlyParticipated )
		{
			$baseUrl = $baseUrl->setQueryString( 'onlyParticipated', 1 );
			$onlyParticipated = TRUE;
		}
		$onlyStarted = FALSE;
		if ( isset( \IPS\Request::i()->onlyStarted ) and \IPS\Request::i()->onlyStarted )
		{
			$baseUrl = $baseUrl->setQueryString( 'onlyStarted', 1 );
			$onlyStarted = TRUE;
		}

		/* Get cutoff */
		$interval = NULL;
		$vncTimePeriod = NULL;
		if ( isset( \IPS\Request::i()->vncTimePeriod ) )
		{
			$vncTimePeriod	= \IPS\Request::i()->vncTimePeriod;
			$baseUrl = $baseUrl->setQueryString( 'vncTimePeriod', $vncTimePeriod );
			if ( isset( \IPS\Request::i()->vncTime ) )
			{
				$start = \IPS\DateTime::ts( \IPS\Request::i()->vncTime );
			}

			switch ( $vncTimePeriod )
			{
               	case 'all':
					$cutoff = NULL;
					break;
					
				case 'day':
					$interval = new \DateInterval( 'P1D' );
					if ( !$start )
					{
						$start = \IPS\DateTime::create()->sub( $interval );
					}
					else
					{
						$end = \IPS\DateTime::ts( $start->getTimestamp() )->add( $interval );
					}
					break;
					
				case 'week':
					$interval = new \DateInterval( 'P1W' );
					if ( !$start )
					{
						$start = \IPS\DateTime::create()->sub( $interval );
					}
					else
					{
						$end = \IPS\DateTime::ts( $start->getTimestamp() )->add( $interval );
					}
					break;
					
				case 'month':
					$interval = new \DateInterval( 'P1M' );
					if ( !$start )
					{
						$start = \IPS\DateTime::create()->sub( $interval );
					}
					else
					{
						$end = \IPS\DateTime::ts( $start->getTimestamp() )->add( $interval );
					}
					break;
				
				default:
					$start = \IPS\DateTime::ts( \IPS\Member::loggedIn()->last_visit );
					break;
			}
		}
        else
        {
            $start = \IPS\DateTime::ts( \IPS\Member::loggedIn()->last_visit );
        }

		if( \IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->updateFilters ) )
		{			
			$vncPreferences	= json_encode( array(
				'onlyFollowed'		=> $onlyFollowed,
				'onlyParticipated'	=> $onlyParticipated,
				'onlyStarted'		=> $onlyStarted,
				'onlyUnread'		=> $onlyUnread,
				'searchType'		=> \IPS\Request::i()->type ?: NULL,
				'vncTimePeriod'		=> $vncTimePeriod,
				'container'			=> \IPS\Request::i()->nodes ?: NULL,
			) );
			
			if ( $vncPreferences != \IPS\Member::loggedIn()->vnc_preferences )
			{
				\IPS\Member::loggedIn()->vnc_preferences	= $vncPreferences;
				\IPS\Member::loggedIn()->save();
			}
		}
		
		/* Get results - if we're just searching one type, it's a bit simpler */
		if ( \IPS\Request::i()->type AND \IPS\Request::i()->type !== 'all' )
		{
			/* Init */
			$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
			$class = $types[ \IPS\Request::i()->type ];
			
			if ( $onlyUnread )
			{
				$unreadWhere = $this->_getUnreadWhere( $class );
				
				if ( count( $unreadWhere ) )
				{
					foreach( $unreadWhere as $id => $item )
					{
						$where[] = $item;
					}
				}
			}
	
			/* Where */
			if ( $onlyParticipated or $onlyStarted )
			{
				if( isset( $class::$databaseColumnMap['author'] ) )
				{
					$participatedWhere[]	= $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['author'] . '=' . \IPS\Member::loggedIn()->member_id;
				}
				if ( !$onlyStarted )
				{
					if( isset( $class::$commentClass ) )
					{
						$commentClass	= $class::$commentClass;
		
						$participatedWhere[]	= '(' . str_replace( "`", '', (string) \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] . '=' . \IPS\Member::loggedIn()->member_id . ' AND ' .
							$commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' .  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId ) ) . ') > 0';
					}
					if( isset( $class::$reviewClass ) )
					{
						$reviewClass	= $class::$reviewClass;
		
						$participatedWhere[]	= '(' . str_replace( "`", '', (string) \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=' . \IPS\Member::loggedIn()->member_id . ' AND ' .
							$reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=' .  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId ) ) . ') > 0';
					}
				}
				$where[] = array( '( ' . implode( ' OR ', $participatedWhere ) . ' )' );
			}
			
			/* Node Filter */
			if ( $class::$containerNodeClass )
			{
				$node = NULL;
				$nodeClass = $class::$containerNodeClass;
				$nodeTitle = \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle );
				if ( isset( \IPS\Request::i()->nodes ) and \IPS\Request::i()->nodes )
				{
					try
					{
						$baseUrl = $baseUrl->setQueryString( 'nodes', \IPS\Request::i()->nodes );
						$where[] = array( \IPS\Db::i()->in( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['container'], is_array( \IPS\Request::i()->nodes ) ? \IPS\Request::i()->nodes : explode( ',', \IPS\Request::i()->nodes ) ) );
					}
					catch ( \OutOfRangeException $e ) { }
				}
				$nodeSelect = new \IPS\Helpers\Form\Node( 'nodes', ( is_array( \IPS\Request::i()->nodes ) ) ? \IPS\Request::i()->nodes :  explode( ',', \IPS\Request::i()->nodes ), FALSE, array( 'class' => $class::$containerNodeClass, 'permissionCheck' => 'read', 'url' => $baseUrl, 'multiple' => TRUE ) );
			}

			/* Cutoff */
			$dateColumnExpression = $this->_getDateExpression( $class );
			
			if ( $start )
			{
				$where[] = array( $dateColumnExpression . '>?', $start->getTimestamp() );
			}
			if ( $end )
			{
				$where[] = array( $dateColumnExpression . '<?', $end->getTimestamp() );
			}
			
			if ( isset( $class::$databaseColumnMap['state'] ) )
			{
				$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['state'] . "!=?", 'link' );
			}

			/* Create table */	
			$table = new \IPS\Helpers\Table\Content( $class, $baseUrl, $where, NULL, NULL, 'view', FALSE );
			$table->sortOptions = array();
			$table->noModerate = TRUE;
						
			/* Joins */
			if ( $onlyFollowed )
			{
				$followArea = mb_strtolower( mb_substr( $class, mb_strrpos( $class, '\\' ) + 1 ) );
				$table->joins[] = array( 'from' => 'core_follow', 'where' => array( "follow_app=? AND follow_area=? AND follow_member_id=? AND follow_rel_id={$class::$databasePrefix}{$class::$databaseColumnId}", $class::$application, $followArea, \IPS\Member::loggedIn()->member_id ) );
			}
		}
		/* If we're searching them all, we need to go all union */
		else
		{
			/* Build the selects */
			$selects = array();
			foreach ( $types as $key => $class )
			{
				$dateColumnExpression = $this->_getDateExpression( $class );
								
				/* Normalize the columns */
				$contentWhere = array();

				$columns = array(
					$class::$databasePrefix.$class::$databaseColumnId				=> 'id',
					$dateColumnExpression											=> 'date',
					$class::$databasePrefix.$class::$databaseColumnMap['author']	=> 'author'
				);
				if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) and !\IPS\Member::loggedIn()->modPermission( "can_view_hidden_content" ) )
				{
					if ( $class::$databaseColumnMap['approved'] )
					{
						$columns[ $class::$databasePrefix.$class::$databaseColumnMap['approved'] . '-1' ] = 'hidden';
						$contentWhere[] = array( $class::$databasePrefix.$class::$databaseColumnMap['approved'] . '-1=0' );
					}
					else
					{
						$columns[ $class::$databasePrefix.$class::$databaseColumnMap['hidden'] ] = 'hidden';
						$contentWhere[] = array( $class::$databasePrefix.$class::$databaseColumnMap['hidden'] . '=0' );
					}
				}
								
				$select = array( "'".str_replace( '\\', '\\\\' , $class ) ."' AS class" );
				foreach ( $columns as $local => $normalized )
				{
					$select[] = "{$local} AS {$normalized}";
				}
				
				if ( $onlyFollowed )
				{
					$select[] = "'".$class::$application."' AS _app";
					$followArea = mb_strtolower( mb_substr( $class, mb_strrpos( $class, '\\' ) + 1 ) );
					$select[] = "'{$followArea}' AS _follow_area";
				}
				$select = implode( ', ', $select );
				
				/* Permissions */
				if ( in_array( 'IPS\Content\Permissions', class_implements( $class ) ) )
				{
					$containerClass = $class::$containerNodeClass;
					$categories = array();
					foreach( \IPS\Db::i()->select( 'perm_type_id', 'core_permission_index', array( "core_permission_index.app='" . $containerClass::$permApp . "' AND core_permission_index.perm_type='" . $containerClass::$permType . "' AND (" . \IPS\Db::i()->findInSet( 'perm_' . $containerClass::$permissionMap['read'], \IPS\Member::loggedIn()->groups ) . ' OR ' . 'perm_' . $containerClass::$permissionMap['read'] . "='*' )" ) ) as $result )
					{
						$categories[] = $result;
					}

					if( count( $categories ) )
					{
						$contentWhere[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['container'] . ' IN(' . implode( ',', $categories ) . ')' );
					}
					else
					{
						$contentWhere[]	= array( $class::$databaseTable . "." . $class::$databasePrefix . $class::$databaseColumnMap['container'] . '=0' );
					}
				}
				
				/* Add app-sepcific wheres */
				$joinContainer = FALSE;
				$joins = array();
				$contentWhere = array_merge( $contentWhere, $class::vncWhere( $joinContainer, $joins ) );
								
				/* Only unread */
				if ( $onlyUnread )
				{
					$unreadWhere = $this->_getUnreadWhere( $class );
					
					if ( count( $unreadWhere ) )
					{
						foreach( $unreadWhere as $id => $item )
						{
							$contentWhere[] = $item;
						}
					}
				}
				
				/* Participated Only */
				if ( $onlyParticipated or $onlyStarted )
				{
					$participatedWhere = array();
					if( isset( $class::$databaseColumnMap['author'] ) )
					{
						$participatedWhere[]	= $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['author'] . '=' . \IPS\Member::loggedIn()->member_id;
					}
					if ( !$onlyStarted )
					{
						if( isset( $class::$commentClass ) )
						{
							$commentClass	= $class::$commentClass;
			
							$participatedWhere[]	= '(' . str_replace( "`", '', (string) \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] . '=' . \IPS\Member::loggedIn()->member_id . ' AND ' .
								$commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' .  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId ) ) . ') > 0';
						}
						if( isset( $class::$reviewClass ) )
						{
							$reviewClass	= $class::$reviewClass;
			
							$participatedWhere[]	= '(' . str_replace( "`", '', (string) \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=' . \IPS\Member::loggedIn()->member_id . ' AND ' .
								$reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=' .  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId ) ) . ') > 0';
						}
					}
					
					$contentWhere[] = '( ' . implode( ' OR ', $participatedWhere ) . ' )';
				}
				
				if ( isset( $class::$databaseColumnMap['state'] ) )
				{
					$contentWhere[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['state'] . "!='link'" );
				}
				
				/* Add to the list */
				$select = \IPS\Db::i()->select( $select, $class::$databaseTable, $contentWhere );
				if ( $joinContainer )
				{
					$containerClass = $class::$containerNodeClass;
					$select->join( $containerClass::$databaseTable, $class::$databasePrefix . $class::$databaseColumnMap['container'] . '=' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId );
				}
				if ( count( $joins ) )
				{
					foreach ( $joins as $join )
					{
						$select->join( $join['from'], $join['where'] );
					}
				}
				$selects[] = $select;
			}
			
			/* Cutoff */
			if ( $start )
			{
				$where[] = array( 'date>?', $start->getTimestamp() );
			}
			if ( $end )
			{
				$where[] = array( 'date<?', $end->getTimestamp() );
			}

			$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

			if( $page < 1 )
			{
				$page = 1;
			}

			/* Run */
			$results = \IPS\Db::i()->union( $selects, 'date DESC', array( ( ( $page - 1 ) * 25 ), 25 ), ( $onlyFollowed ) ? '_app, _follow_area, id' : NULL, FALSE, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS, $where );
			
			/* Joins */
			if ( $onlyFollowed )
			{
				$results->join( 'core_follow', "follow_app=_app AND follow_area=_follow_area AND follow_rel_id=id" );
			}
			
			$resultPages = ceil( $results->count( TRUE ) / 25 );
			
			/* Build a table */
			$table = new \IPS\Helpers\Table\Custom( iterator_to_array( $results ), $baseUrl );
			$table->pages = $resultPages;
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'vnc' ), 'genericRows' );
		}
				
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array(
				'sidebar' => \IPS\Theme::i()->getTemplate('vnc')->filters( $baseUrl, $types, $nodeSelect, $nodeTitle ),
				'content' => \IPS\Theme::i()->getTemplate('vnc')->results( $baseUrl, (string) $table, \IPS\Request::i()->vncTimePeriod, $interval, $start, $end ?: \IPS\DateTime::create() ),
			) );
		}
		else
		{
			\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_vnc.js', 'core' ) );
			\IPS\Output::i()->sidebar['enabled'] 	= FALSE;
			\IPS\Output::i()->breadcrumb[]			= array( NULL, \IPS\Member::loggedIn()->language()->addToStack('search_vnc_title') );
			\IPS\Output::i()->title					= \IPS\Member::loggedIn()->language()->addToStack('search_vnc_title');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('vnc')->template( $baseUrl, $types, (string) $table, \IPS\Request::i()->vncTimePeriod, $interval, $start, $end ?: \IPS\DateTime::create(), $nodeSelect, $nodeTitle );
		}
	}
	
	/**
	 * Get the 'unread' where SQL
	 *
	 * @param	string	$class 		Content class (\IPS\forums\Forum)
	 * @return	array
	 */
	protected function _getUnreadWhere( $class )
	{
		$classBits	    = explode( "\\", $class );
		$application    = $classBits[1];
		$resetTimes	    = \IPS\Member::loggedIn()->markersResetTimes( $application );
		$oldestTime	    = time();
		$markers	    = array();
		$excludeIds     = array();
		$where          = array();
		$unreadWheres	= array();
		$containerIds	= array();
		$containerClass = ( $class::$containerNodeClass ) ? $class::$containerNodeClass : NULL;
		
		/* What is the best date column? */
		$dateColumns = array();
		foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
		{
			if ( isset( $class::$databaseColumnMap[ $k ] ) )
			{
				if ( is_array( $class::$databaseColumnMap[ $k ] ) )
				{
					foreach ( $class::$databaseColumnMap[ $k ] as $v )
					{
						$dateColumns[] = " IFNULL( " . $class::$databaseTable . '.'. $class::$databasePrefix . $v . ", 0 )";
					}
				}
				else
				{
					$dateColumns[] = " IFNULL( " . $class::$databaseTable . '.'. $class::$databasePrefix . $class::$databaseColumnMap[ $k ] . ", 0 )";
				}
			}
		}
		
		$dateColumnExpression = count( $dateColumns ) > 1 ? ( 'GREATEST(' . implode( ',', $dateColumns ) . ')' ) : array_pop( $dateColumns );
		
		foreach( $resetTimes as $containerId => $timestamp )
		{
			$container = NULL;
	
			if( $containerId AND $containerClass )
			{
				try
				{
					$container = $containerClass::load( $containerId );
				}
				catch ( \OutOfRangeException $e)
				{
					continue;
				}
			}
	
			$timestamp	= $timestamp ?: \IPS\Member::loggedIn()->marked_site_read;
	
			$containerIds[]	= $containerId;
			$unreadWheres[]	= '( ' . $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['container'] . '=' . $containerId . ' AND ' . $dateColumnExpression . ' > ' . (int) $timestamp . ')';
			
			$items = \IPS\Member::loggedIn()->markersItems( $application, \IPS\Content\Item::makeMarkerKey( $container ) );
			
			if ( count( $items ) )
			{
				foreach( $items as $mid => $mtime )
				{
					if ( $mtime > $timestamp )
					{
						$markers[ $mtime . '.' . $mid ] = $mid;
					}
				}
			}
		}
		
		if( count( $containerIds ) )
		{
			$unreadWheres[]	= "( " . $dateColumnExpression . " > " . \IPS\Member::loggedIn()->marked_site_read . " AND ( " . $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['container'] . " NOT IN(" . implode( ',', $containerIds ) . ") ) )";
		}
		else
		{
			$unreadWheres[]	= "( " . $dateColumnExpression . " > " . \IPS\Member::loggedIn()->marked_site_read . ")";
		}
	
		if( count( $unreadWheres ) )
		{
			$where[] = array( "(" . implode( " OR ", $unreadWheres ) . ")" );
		}
	
		if ( count( $markers ) )
		{
			/* Avoid packet issues */
			krsort( $markers );
			$useIds = array_flip( array_slice( $markers, 0, 500, TRUE ) );
			$select = '';
			$from   = '';
			$notIn  = array();
			
			foreach( \IPS\Db::i()->select( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId. ' as _id, ' . $dateColumnExpression . ' as _date', $class::$databaseTable, \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnId, array_keys( $useIds ) ) ) as $row )
			{
				if ( isset( $useIds[ $row['_id'] ] ) )
				{
					if ( $useIds[ $row['_id'] ] >= $row['_date'] )
					{
						/* Still read */
						$notIn[] = intval( $row['_id'] );
					}
				}
			}
			
			if ( count( $notIn ) )
			{
				$where[] = array( "( " . $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId . " NOT IN (" . implode( ',', $notIn ) . ") )" );
			}
		}
		
		return $where;
	}
	
	/**
	 * Get the date Column expression
	 *
	 * @param	string	$class 		Content class (\IPS\forums\Forum)
	 * @return	string
	 */
	protected function _getDateExpression( $class )
	{
		/* What is the best date column? */
		$dateColumns = array();
		foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
		{
			if ( isset( $class::$databaseColumnMap[ $k ] ) )
			{
				if ( is_array( $class::$databaseColumnMap[ $k ] ) )
				{
					foreach ( $class::$databaseColumnMap[ $k ] as $v )
					{
						$dateColumns[] = " IFNULL( " . $class::$databaseTable . '.'. $class::$databasePrefix . $v . ", 0 )";
					}
				}
				else
				{
					$dateColumns[] = " IFNULL( " . $class::$databaseTable . '.'. $class::$databasePrefix . $class::$databaseColumnMap[ $k ] . ", 0 )";
				}
			}
		}
		
		return count( $dateColumns ) > 1 ? ( 'GREATEST(' . implode( ',', $dateColumns ) . ')' ) : array_pop( $dateColumns );
	}
}