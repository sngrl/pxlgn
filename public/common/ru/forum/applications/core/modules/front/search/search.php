<?php
/**
 * @brief		Search
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Apr 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Search
 */
class _search extends \IPS\Dispatcher\Controller
{
	/**
	 * Search Form
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		/* Init stuff for the output */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/search.css' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/search_responsive.css' ) );
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_search.js', 'core' ) );	
		\IPS\Output::i()->metaTags['robots'] = 'noindex'; // Tell search engines not to index search pages
		
		/* Get the form */
		$form = $this->_form();

		/* If we have the term, show the results */
		if ( \IPS\Request::i()->isAjax() or isset( \IPS\Request::i()->q ) or isset( \IPS\Request::i()->tags ) or ( \IPS\Request::i()->type == 'core_members' ) )
		{
			if ( !\IPS\Request::i()->isAjax() and !\IPS\Request::i()->q and !\IPS\Request::i()->tags and \IPS\Request::i()->type !== 'core_members' )
			{
				if ( isset( \IPS\Request::i()->csrfKey ) )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('no_search_term');
					\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'advanced_search' );
					\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'advancedSearchForm' ) );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' ) );
				}
				return;
			}
			
			return $this->_results();
		}
		/* Otherwise, show the advanced search form */
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'advanced_search' );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'advancedSearchForm' ) );
		}
	}
	
	/**
	 * Get Results
	 *
	 * @return	void
	 */
	protected function _results()
	{
		/* Init */
		$baseUrl = \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' );
		if( \IPS\Request::i()->q )
		{
			$baseUrl = $baseUrl->setQueryString( 'q', \IPS\Request::i()->q );
		}

		$types = $this->_contentTypes();

		/* Flood control */
		\IPS\Request::floodCheck();

		/* Are we searching members? */
		if ( isset( \IPS\Request::i()->type ) and \IPS\Request::i()->type === 'core_members' )
		{
			$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
			if ( \IPS\Request::i()->q )
			{
				$where = array( array( 'LOWER(core_members.name) LIKE ?', '%' . mb_strtolower( \IPS\Request::i()->q ) . '%' ) );
			}
			else
			{
				$where = array( array( 'core_members.name<>?', '' ) );
			}
			
			if ( isset( \IPS\Request::i()->start_before ) or isset( \IPS\Request::i()->start_after ) )
			{
				foreach ( array( 'before', 'after' ) as $l )
				{
					$$l = NULL;
					$key = "start_{$l}";
					if ( isset( \IPS\Request::i()->$key ) )
					{
						switch ( \IPS\Request::i()->$key )
						{
							case 'day':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) );
								break;
								
							case 'week':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) );
								break;
								
							case 'month':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) );
								break;
								
							case 'six_months':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P6M' ) );
								break;
								
							case 'year':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) );
								break;
								
							default:
								$$l = \IPS\DateTime::ts( \IPS\Request::i()->$key );
								break;
						}
					}
				}
				
				if ( $before )
				{
					$where[] = array( 'core_members.joined<?', $before->getTimestamp() );
				}
				if ( $after )
				{
					$where[] = array( 'core_members.joined>?', $after->getTimestamp() );
				}
			}

			if ( isset( \IPS\Request::i()->group ) )
			{

				$baseUrl = $baseUrl->setQueryString( 'group', \IPS\Request::i()->group );
				$where[] = \IPS\Db::i()->in( 'core_members.member_group_id', ( is_array( \IPS\Request::i()->group ) ) ? array_filter( array_keys( \IPS\Request::i()->group ), function( $val ){ 
					if( $val == '__EMPTY' )
					{
						return false;
					}

					return true;
				} ) : explode( ',', \IPS\Request::i()->group ) );
			}

			/* Figure out member custom field filters */
			foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\PROFILE ) as $group => $fields )
			{
				/* Fields */
				foreach ( $fields as $id => $field )
				{
					/* Work out the object type so we can show the appropriate field */
					$type = get_class( $field );

					switch ( $type )
					{
						case 'IPS\Helpers\Form\Text':
						case 'IPS\Helpers\Form\Tel':
						case 'IPS\Helpers\Form\Editor':
						case 'IPS\Helpers\Form\Email':
						case 'IPS\Helpers\Form\TextArea':
						case 'IPS\Helpers\Form\Url':
						case 'IPS\Helpers\Form\Date':
						case 'IPS\Helpers\Form\Number':
						case 'IPS\Helpers\Form\Select':
						case 'IPS\Helpers\Form\Radio':
							$fieldName	= 'core_pfield_' . $id;

							if( isset( \IPS\Request::i()->$fieldName ) )
							{
								$where[] = array( 'LOWER(core_pfields_content.field_' . $id . ') LIKE ?', '%' . mb_strtolower( \IPS\Request::i()->$fieldName ) . '%' );
								$baseUrl = $baseUrl->setQueryString( $fieldName, \IPS\Request::i()->$fieldName );
							}
							break;
					}
				}
			}

			if( isset( \IPS\Request::i()->sortby ) AND in_array( mb_strtolower( \IPS\Request::i()->sortby ), array( 'joined', 'name', 'member_posts', 'pp_reputation_points' ) ) )
			{
				$direction	= ( isset( \IPS\Request::i()->sortdirection ) AND in_array( mb_strtolower( \IPS\Request::i()->sortdirection ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortdirection : 'asc';
				$order		= mb_strtolower( \IPS\Request::i()->sortby ) . ' ' . $direction;

				$baseUrl = $baseUrl->setQueryString( array( 'sortby' => \IPS\Request::i()->sortby, 'sortdirection' => \IPS\Request::i()->sortdirection ) );
			}
			else
			{
				if( htmlspecialchars( \IPS\Request::i()->q, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE ) )
				{
					$order = "INSTR( name, '" . mb_strtolower(htmlspecialchars(\IPS\Request::i()->q, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE)) . "' ) ASC, LENGTH( name ) ASC, name ASC";
				}
				else
				{
					$order = "name ASC";
				}
			}
			
			$page	= isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

			if( $page < 1 )
			{
				$page = 1;
			}

			$select	= \IPS\Db::i()->select( 'core_members.*', 'core_members', $where, $order, array( ( $page - 1 ) * 25, 25 ), NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
			$select->join( 'core_pfields_content', 'core_pfields_content.member_id=core_members.member_id' );

			$results	= new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\Member' );

			$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ceil( $results->count( TRUE ) / 25 ), $page, 1 ) );
			if ( !\IPS\Request::i()->q )
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'members' );
			}
			else
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_results_title_term_area', FALSE, array( 'sprintf' => array( \IPS\Request::i()->q, \IPS\Member::loggedIn()->language()->addToStack( 'core_members_pl' ) ) ) );
			}

			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'filters'	=> $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ) ),
					'content'	=> \IPS\Theme::i()->getTemplate( 'search' )->results( \IPS\Request::i()->q, $title, $results, $pagination, $baseUrl ),
					'title'		=> $title,
					'css'		=> array()
				) );
			}
			else
			{
				\IPS\Output::i()->title = $title;
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'search' )->search( \IPS\Request::i()->q, $title, $results, $pagination, $baseUrl, $types, $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ) ) );
			}
			return;
		}
		
		/* Init */
		$query = \IPS\Content\Search\Query::init();

		/* Work out the title */
		if ( \IPS\Request::i()->tags and !\IPS\Request::i()->q )
		{
			$baseUrl = $baseUrl->setQueryString( 'tags', \IPS\Request::i()->tags );
			$tagList = array_map( function( $val )
			{
				return '\'' . htmlentities( $val, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) . '\'';
			}, explode( ',', \IPS\Request::i()->tags ) );
			$term = \IPS\Member::loggedIn()->language()->formatList( $tagList, \IPS\Member::loggedIn()->language()->get('or_list_format') );
			
			$titleKey = 'search_results_title_tag';
		}
		else
		{
			$term = urldecode( \IPS\Request::i()->q );
			$titleKey = 'search_results_title_term';
		}

		if ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $types ) )
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( $titleKey . '_area', FALSE, array( 'sprintf' => array( $term, \IPS\Member::loggedIn()->language()->addToStack( \IPS\Request::i()->type . '_pl' ) ) ) );
			
			if ( isset( \IPS\Request::i()->item ) )
			{
				$class = $types[ \IPS\Request::i()->type ];
				try
				{
					$item = $class::loadAndCheckPerms( \IPS\Request::i()->item );
					$title = \IPS\Member::loggedIn()->language()->addToStack( $titleKey . '_area', FALSE, array( 'sprintf' => array( $term, $item->mapped('title') ) ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			if ( isset( \IPS\Request::i()->nodes ) and mb_strstr( \IPS\Request::i()->nodes, ',' ) === FALSE )
			{
				$class = $types[ \IPS\Request::i()->type ];
				$nodeClass = $class::$containerNodeClass;
				try
				{
					$node = $nodeClass::loadAndCheckPerms( \IPS\Request::i()->nodes );
					$title = \IPS\Member::loggedIn()->language()->addToStack( $titleKey . '_area', FALSE, array( 'sprintf' => array( $term, $node->_title ) ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		else
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( $titleKey, FALSE, array( 'sprintf' => array( $term ) ) );
		}
		
		/* Set content type */
		if ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $types ) )
		{	
			if ( isset( \IPS\Request::i()->item ) )
			{
				$class = $types[ \IPS\Request::i()->type ];
				try
				{
					$query->filterByItem( $class::load( \IPS\Request::i()->item ) );
					$baseUrl = $baseUrl->setQueryString( 'item', intval( \IPS\Request::i()->item ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			else
			{
				$nodes = NULL;
				if ( isset( \IPS\Request::i()->nodes ) )
				{
					$nodes = explode( ',', \IPS\Request::i()->nodes );
				}
				
				$query->filterByContent( $types[ \IPS\Request::i()->type ], $nodes, isset( \IPS\Request::i()->comments ) ? \IPS\Request::i()->comments : ( isset( \IPS\Request::i()->replies ) ? ( \IPS\Request::i()->replies + 1 ) : NULL ), isset( \IPS\Request::i()->reviews ) ? \IPS\Request::i()->reviews : NULL, isset( \IPS\Request::i()->views ) ? \IPS\Request::i()->views : NULL );
				$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
				if ( $nodes )
				{
					$baseUrl = $baseUrl->setQueryString( 'nodes', \IPS\Request::i()->nodes );
				}
				if ( isset( \IPS\Request::i()->comments ) )
				{
					$baseUrl = $baseUrl->setQueryString( 'comments', \IPS\Request::i()->comments );
				}
				if ( isset( \IPS\Request::i()->replies ) )
				{
					$baseUrl = $baseUrl->setQueryString( 'replies', \IPS\Request::i()->replies );
				}
				if ( isset( \IPS\Request::i()->reviews ) )
				{
					$baseUrl = $baseUrl->setQueryString( 'reviews', \IPS\Request::i()->reviews );
				}
				if ( isset( \IPS\Request::i()->views ) )
				{
					$baseUrl = $baseUrl->setQueryString( 'views', \IPS\Request::i()->views );
				}
			}
		}
		
		/* Filter by author */
		if ( isset( \IPS\Request::i()->author ) )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author, 'name' );
			if ( $author->member_id )
			{
				$query->filterByAuthor( $author );
				$baseUrl = $baseUrl->setQueryString( 'author', $author->name );
			}
		}
		
		/* Set time cutoffs */
		foreach ( array( 'start' => 'filterByCreateDate', 'updated' => 'filterByLastUpdatedDate' ) as $k => $method )
		{
			$beforeKey = "{$k}_before";
			$afterKey = "{$k}_after";
			if ( isset( \IPS\Request::i()->$beforeKey ) or isset( \IPS\Request::i()->$afterKey ) )
			{
				foreach ( array( 'before', 'after' ) as $l )
				{
					$$l = NULL;
					$key = "{$l}Key";
					if ( isset( \IPS\Request::i()->$$key ) )
					{
						switch ( \IPS\Request::i()->$$key )
						{
							case 'day':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) );
								break;
								
							case 'week':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) );
								break;
								
							case 'month':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) );
								break;
								
							case 'six_months':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P6M' ) );
								break;
								
							case 'year':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) );
								break;
								
							default:
								$$l = \IPS\DateTime::ts( \IPS\Request::i()->$$key );
								break;
						}
					}
				}
				
				$query->$method( $after, $before );
			}
		}
		
		/* Set page */
		if ( isset( \IPS\Request::i()->page ) )
		{
			$query->setPage( intval( \IPS\Request::i()->page ) );
			$baseUrl = $baseUrl->setQueryString( 'page', intval( \IPS\Request::i()->page ) );
		}
		
		/* Set Order */
		if ( isset( \IPS\Request::i()->sortby ) )
		{
			switch( \IPS\Request::i()->sortby )
			{
				case 'newest':
					$query->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED );
					break;

				case 'relevancy':
					$query->setOrder( \IPS\Content\Search\Query::ORDER_RELEVANCY );
					break;
			}
			
			$baseUrl = $baseUrl->setQueryString( 'sortby', \IPS\Request::i()->sortby );	
		}
		
		/* Run query */
		$results = $query->excludeDisabledApps()->search(
			isset( \IPS\Request::i()->q ) ? ( \IPS\Request::i()->q ) : NULL,
			isset( \IPS\Request::i()->tags ) ? explode( ',', \IPS\Request::i()->tags ) : NULL,
			( isset( \IPS\Request::i()->eitherTermsOrTags ) and \IPS\Request::i()->eitherTermsOrTags === 'and' ) ? \IPS\Content\Search\Query::TERM_AND_TAGS : \IPS\Content\Search\Query::TERM_OR_TAGS
		);
				
		/* Get pagination */
		$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ceil( $results->count( TRUE ) / $query->resultsToGet ), $page, $query->resultsToGet ) );
				
		/* Display results */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array(
				'filters'	=> $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ) ),
				'content'	=> \IPS\Theme::i()->getTemplate( 'search' )->results( htmlentities( $term, \IPS\HTMLENTITIES, 'UTF-8', FALSE ), $title, $results, $pagination, $baseUrl ),
				'title'		=> $title,
				'css'		=> array()
			) );
		}
		else
		{
			$httpHeaders = array( 'Expires'		=> \IPS\DateTime::create()->add( new \DateInterval( 'PT3M' ) )->rfc1123() ,
								  'Cache-Control'	=> "max-age=" . 30 * 60 . ", public" );

			\IPS\Output::i()->httpHeaders += $httpHeaders;

			\IPS\Output::i()->title = $title;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'search' )->search( htmlentities( $term, \IPS\HTMLENTITIES, 'UTF-8', FALSE ), $title, $results, $pagination, $baseUrl, $types, $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ) ) );
		}
	}
	
	/**
	 * Get the search form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public function _form()
	{
		/* Init */
		$form = new \IPS\Helpers\Form;

		/* Types */
		$types				= array( '' => 'search_everything' );
		$contentTypes		= $this->_contentTypes();
		$contentToggles		= array( 'tags', 'eitherTermsOrTags', 'author', 'elAdvancedSearch_dateFilters', 'startDate', 'updatedDate' );
		$typeFields			= array();
		$typeFieldToggles	= array( '' => $contentToggles );
		$haveCommentClass	= FALSE;
		$haveReplyClass		= FALSE;
		$haveReviewClass	= FALSE;

		/* Figure out member fields to set toggles */
		$memberToggles	= array( 'joinedDate', 'core_members_group' );
		foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\PROFILE ) as $group => $fields )
		{
			foreach ( $fields as $id => $field )
			{
				switch ( get_class( $field ) )
				{
					case 'IPS\Helpers\Form\Text':
					case 'IPS\Helpers\Form\Tel':
					case 'IPS\Helpers\Form\Editor':
					case 'IPS\Helpers\Form\Email':
					case 'IPS\Helpers\Form\TextArea':
					case 'IPS\Helpers\Form\Url':
					case 'IPS\Helpers\Form\Date':
					case 'IPS\Helpers\Form\Number':
					case 'IPS\Helpers\Form\Select':
					case 'IPS\Helpers\Form\Radio':
						$memberToggles[]	= 'core_pfield_' . $id;
						break;
				}
			}
		}

		/* Type select */
		if ( isset( \IPS\Request::i()->type ) and isset( $contentTypes[ \IPS\Request::i()->type ] ) )
		{
			$contentTypes = array( $contentTypes[ \IPS\Request::i()->type ] );
		}
		foreach ( $contentTypes as $k => $class )
		{
			$types[ $k ] = $k . '_pl';
			if ( $k !== 'core_members' )
			{
				$typeFieldToggles[ $k ] = array_merge( $contentToggles, array( $k . '_node', 'search_min_views' ) );
				if ( isset( $class::$commentClass ) )
				{
					if ( $class::$firstCommentRequired )
					{
						$haveReplyClass = TRUE;
						$typeFieldToggles[ $k ][] = 'search_min_replies';
					}
					else
					{
						$haveCommentClass = TRUE;
						$typeFieldToggles[ $k ][] = 'search_min_comments';
					}
				}
				if ( isset( $class::$reviewClass ) )
				{
					$haveReviewClass = TRUE;
					$typeFieldToggles[ $k ][] = 'search_min_reviews';
				}
			}
			else
			{
				$typeFieldToggles[ $k ]	= $memberToggles;
			}
			$typeFieldToggles[ $k ][] = 'elAdvancedSearch_appFilters';
		}
		$form->add( new \IPS\Helpers\Form\Radio( 'searchType', '', FALSE, array( 'options' => $types, 'toggles' => $typeFieldToggles ) ) );
		
		/* Term */
		$form->add( new \IPS\Helpers\Form\Text( 'q' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'tags', \IPS\Request::i()->tags, FALSE, array( 'autocomplete' => array() ), NULL, NULL, NULL, 'tags' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'eitherTermsOrTags', \IPS\Request::i()->eitherTermsOrTags, FALSE, array( 'options' => array( 'or' => 'termsortags_or_desc', 'and' => 'termsortags_and_desc' ) ), NULL, NULL, NULL, 'eitherTermsOrTags' ) );
		
		/* Author */
		$form->add( new \IPS\Helpers\Form\Member( 'author', NULL, FALSE, array(), NULL, NULL, NULL, 'author' ) );
		
		/* Dates */
		$dateOptions = array(
			'any'			=> 'any',
			'day'			=> 'last_24hr',
			'week'			=> 'last_week',
			'month'			=> 'last_month',
			'six_months'	=> 'six_months',
			'year'			=> 'year',
			'custom'		=> 'custom'
		);
		$form->add( new \IPS\Helpers\Form\Select( 'startDate', ( isset( \IPS\Request::i()->start_before ) or ( isset( \IPS\Request::i()->start_after ) and is_numeric( \IPS\Request::i()->start_after ) ) ) ? 'custom' : \IPS\Request::i()->start_after, FALSE, array( 'options' => $dateOptions, 'toggles' => array( 'custom' => array( 'elCustomDate_startDate' ) ) ), NULL, NULL, NULL, 'startDate' ) );
		$form->add( new \IPS\Helpers\Form\DateRange( 'startDateCustom', array( 'start' => ( isset( \IPS\Request::i()->start_after ) and is_numeric(  \IPS\Request::i()->start_after ) ) ? \IPS\DateTime::ts( \IPS\Request::i()->start_after ) : NULL, 'end' => isset( \IPS\Request::i()->start_before ) ? \IPS\DateTime::ts( \IPS\Request::i()->start_before ) : NULL ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'updatedDate', ( isset( \IPS\Request::i()->updated_before ) or ( isset( \IPS\Request::i()->updated_after ) and is_numeric( \IPS\Request::i()->updated_after ) ) ) ? 'custom' : \IPS\Request::i()->updated_after, FALSE, array( 'options' => $dateOptions, 'toggles' => array( 'custom' => array( 'elCustomDate_updatedDate' ) ) ), NULL, NULL, NULL, 'updatedDate' ) );
		$form->add( new \IPS\Helpers\Form\DateRange( 'updatedDateCustom', array( 'start' => ( isset( \IPS\Request::i()->updated_after ) and is_numeric( \IPS\Request::i()->updated_after ) ) ? \IPS\DateTime::ts( \IPS\Request::i()->updated_after ) : NULL, 'end' => isset( \IPS\Request::i()->updated_before ) ? \IPS\DateTime::ts( \IPS\Request::i()->updated_before ) : NULL ) ) );
				
		/* Nodes */
		foreach ( $contentTypes as $k => $class )
		{
			if ( isset( $class::$containerNodeClass ) and isset( \IPS\Request::i()->type ) and \IPS\Request::i()->type === $k )
			{
				$nodeClass = $class::$containerNodeClass;
				$field = new \IPS\Helpers\Form\Node( 'nodes', ( isset( \IPS\Request::i()->nodes ) ) ? \IPS\Request::i()->nodes : NULL, FALSE, array( 'class' => $nodeClass, 'multiple' => TRUE, 'permissionCheck' => 'view', ), NULL, NULL, NULL, $k . '_node' );
				$field->label = \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle );
				$form->add( $field );
			}
		}

		/* Member group and joined */
		if( ( isset( \IPS\Request::i()->searchType ) and \IPS\Request::i()->searchType === 'core_members' ) or !isset( \IPS\Request::i()->q ) )
		{
			$form->add(new \IPS\Helpers\Form\CheckboxSet('group', (isset(\IPS\Request::i()->group)) ? array_keys(\IPS\Request::i()->group) : array(), FALSE, array('options' => \IPS\Member\Group::groups(TRUE, FALSE), 'parse' => 'normal'), NULL, NULL, NULL, 'core_members_group'));
			$form->add(new \IPS\Helpers\Form\Select('joinedDate', (isset(\IPS\Request::i()->start_before) or (isset(\IPS\Request::i()->start_after) and is_numeric(\IPS\Request::i()->start_after))) ? 'custom' : \IPS\Request::i()->start_after, FALSE, array('options' => $dateOptions, 'toggles' => array('custom' => array('elCustomDate_joinedDate'))), NULL, NULL, NULL, 'joinedDate'));
			$form->add(new \IPS\Helpers\Form\DateRange('joinedDateCustom', array('start' => (isset(\IPS\Request::i()->start_after) and is_numeric(\IPS\Request::i()->start_after)) ? \IPS\DateTime::ts(\IPS\Request::i()->start_after) : NULL, 'end' => isset(\IPS\Request::i()->start_before) ? \IPS\DateTime::ts(\IPS\Request::i()->start_before) : NULL)));
		}
		
		/* Comments/Views */
		$queryClass = \IPS\Content\Search\Query::init();
		if ( $queryClass::SUPPORTS_JOIN_FILTERS )
		{
			if ( $haveCommentClass )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_comments', isset( \IPS\Request::i()->comments ) ? \IPS\Request::i()->comments : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_comments' ) );
			}
			if ( $haveReplyClass )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_replies', isset( \IPS\Request::i()->replies ) ? \IPS\Request::i()->replies : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_replies' ) );
			}
			if ( $haveReviewClass )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_reviews', isset( \IPS\Request::i()->reviews ) ? \IPS\Request::i()->reviews : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_reviews' ) );
			}
			$form->add( new \IPS\Helpers\Form\Number( 'search_min_views', isset( \IPS\Request::i()->views ) ? \IPS\Request::i()->views : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_views' ) );
		}

		/* Profile fields for member searches */
		$memberFields	= array();
		foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\PROFILE ) as $group => $fields )
		{
			$fieldsToAdd	= array();
			
			/* Fields */
			foreach ( $fields as $id => $field )
			{
				/* Alias the lang keys */
				$realLangKey = "core_pfield_{$id}";

				/* Work out the object type so we can show the appropriate field */
				$type = get_class( $field );
				$helper = NULL;
				
				switch ( $type )
				{
					case 'IPS\Helpers\Form\Text':
					case 'IPS\Helpers\Form\Tel':
					case 'IPS\Helpers\Form\Editor':
					case 'IPS\Helpers\Form\Email':
					case 'IPS\Helpers\Form\TextArea':
					case 'IPS\Helpers\Form\Url':
						$helper = new \IPS\Helpers\Form\Text( 'core_pfield_' . $id, NULL, FALSE, array(), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
					case 'IPS\Helpers\Form\Date':
						$helper = new \IPS\Helpers\Form\DateRange( 'core_pfield_' . $id, NULL, FALSE, array(), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
					case 'IPS\Helpers\Form\Number':
						$helper = new \IPS\Helpers\Form\Number( 'core_pfield_' . $id, NULL, FALSE, array(), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
					case 'IPS\Helpers\Form\Select':
					case 'IPS\Helpers\Form\Radio':
						$options = array( '' => "" );
						foreach ( $field->options['options'] as $option )
						{
							$options[ $option ] = $option;
						}
						
						$helper = new \IPS\Helpers\Form\Select( 'core_pfield_' . $id, NULL, FALSE, array( 'options' => $options ), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
				}
				
				if ( $helper )
				{
					$fieldsToAdd[] = $helper;
				}
			}
			
			if( count( $fieldsToAdd ) )
			{
				foreach( $fieldsToAdd as $field )
				{
					$form->add( $field );
				}
			}
		}

		/* If they submitted the advanced search form, redirect back (searching is a GET not a POST) */
		if ( $values = $form->values() )
		{
			if( !\IPS\Request::i()->isAjax() AND ( ( $values['q'] or $values['tags'] ) or $values['searchType'] == 'core_members' ) )
			{
				$url = \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' );
							
				if ( $values['q'] )
				{
					$url = $url->setQueryString( 'q', $values['q'] );
				}
				if ( $values['tags'] )
				{
					$url = $url->setQueryString( 'tags', implode( ',', $values['tags'] ) );
				}
				if ( $values['q'] and $values['tags'] )
				{
					$url = $url->setQueryString( 'eitherTermsOrTags', $values['eitherTermsOrTags'] );
				}
				if ( $values['searchType'] )
				{
					$url = $url->setQueryString( 'type', $values['searchType'] );
					
					if ( isset( $values[ $values['searchType'] . '_node' ] ) and !empty( $values[ $values['searchType'] . '_node' ] ) )
					{
						$url = $url->setQueryString( 'nodes', implode( ',', array_keys( $values[ $values['searchType'] . '_node' ] ) ) );
					}
					
					if ( isset( $values['search_min_comments'] ) and $values['search_min_comments'] )
					{
						$url = $url->setQueryString( 'comments', $values['search_min_comments'] );
					}
					if ( isset( $values['search_min_replies'] ) and $values['search_min_replies'] )
					{
						$url = $url->setQueryString( 'replies', $values['search_min_replies'] );
					}
					if ( isset( $values['search_min_reviews'] ) and $values['search_min_reviews'] )
					{
						$url = $url->setQueryString( 'reviews', $values['search_min_reviews'] );
					}
					if ( isset( $values['search_min_views'] ) and $values['search_min_views'] )
					{
						$url = $url->setQueryString( 'views', $values['search_min_views'] );
					}
				}
				if ( isset( $values['author'] ) and $values['author'] )
				{
					$url = $url->setQueryString( 'author', $values['author']->name );
				}

				if ( isset( $values['group'] ) and $values['group'] )
				{

					$values['group']	= array_flip( $values['group'] );

					array_walk( $values['group'], function( &$value, $key ){
						$value = 1;
					} );

					$url = $url->setQueryString( 'group', $values['group'] );
				}

				foreach( $memberFields as $fieldName )
				{
					if( isset( $values[ $fieldName ] ) AND $values[ $fieldName ] )
					{
						$url = $url->setQueryString( $fieldName, $values[ $fieldName ] );
					}
				}

				foreach ( array( 'start', 'updated' ) as $k )
				{
					if ( $values[ $k . 'Date' ] != 'any' )
					{
						if ( $values[ $k . 'Date' ] === 'custom' )
						{
							if ( $values[ $k . 'DateCustom' ]['start'] )
							{
								$url = $url->setQueryString( $k . '_after', $values[ $k . 'DateCustom' ]['start']->getTimestamp() );
							}
							if ( $values[ $k . 'DateCustom' ]['end'] )
							{
								$url = $url->setQueryString( $k . '_before', $values[ $k . 'DateCustom' ]['end']->getTimestamp() );
							}
						}
						else
						{
							$url = $url->setQueryString( $k . '_after', $values[ $k . 'Date' ] );
						}
					}
				}
				\IPS\Output::i()->redirect( $url );
			}
		}

		return $form;
	}
	
	/**
	 * Get the different content type extensions
	 *
	 * @return	array
	 */
	protected function _contentTypes()
	{
		$types = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE, FALSE, TRUE ) as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
			{	
				$key = mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) );
				$types[ $key ] = $class;
			}
		}
		
		if ( \IPS\Application\Module::get( 'core', 'members', 'front' )->can('view') )
		{
			$types['core_members'] = 'IPS\Member';
		}

		return $types;
	}
	
	/**
	 * Global filter options (AJAX Request)
	 *
	 * @return	void
	 */
	protected function globalFilterOptions()
	{
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'search' )->globalSearchMenuOptions() );
	}
}