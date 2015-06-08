<?php
/**
 * @brief		Moderator Control Panel Extension: Content
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 Dec 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Control Panel Extension: Content
 */
class _Content
{	
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string
	 */
	public function getTab()
	{
		return 'hidden';
	}
	
	/**
	 * Hidden Content
	 *
	 * @return	void
	 */
	public function manage()
	{
		$types = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE ) as $class )
		{
			if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
			{
				$types[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
			}
		}
		
		$currentType = ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $types ) ) ? \IPS\Request::i()->type : NULL;
		
		if ( $currentType )
		{
			$currentClass = $types[ $currentType ];
			
			$where = NULL;
			if ( isset( $currentClass::$databaseColumnMap['hidden'] ) )
			{
				$where = array( $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['hidden'] . '=-1' );
			}
			elseif ( isset( $currentClass::$databaseColumnMap['approved'] ) )
			{
				$where = array( $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['approved'] . '=-1' );
			}
			
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( $currentType ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $currentType );
			
			$table = new \IPS\Helpers\Table\Content( $currentClass, \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=content&type={$currentType}", 'front', 'modcp_content' ), array( $where ) );
			$table->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_hidden' );
			return \IPS\Theme::i()->getTemplate( 'modcp' )->tableWrapper( (string) $table );
		}
		else
		{
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'search_everything' ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'search_everything' );
			
			$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

			if( $page < 1 )
			{
				$page = 1;
			}
			$query = \IPS\Content\Search\Query::init()->setHiddenFilter( \IPS\Content\Search\Query::HIDDEN_HIDDEN )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_UPDATED )->setPage( $page );
			$results = $query->search();
			$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=content", 'front', 'modcp_content' ), ceil( $results->count( TRUE ) / $query->resultsToGet ), $page, $query->resultsToGet ) );
			return \IPS\Theme::i()->getTemplate( 'modcp' )->tableWrapper( \IPS\Theme::i()->getTemplate('search')->resultStream( $results, $pagination, \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=content", 'front', 'modcp_content' ) ), 'modcp_hidden' );
		}
	}
}