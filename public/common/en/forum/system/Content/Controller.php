<?php
/**
 * @brief		Content Controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		5 Jul 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Controller
 */
class _Controller extends \IPS\Helpers\CoverPhoto\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( isset( \IPS\Request::i()->page ) )
		{
			\IPS\Request::i()->page	= intval( \IPS\Request::i()->page );
			if ( !\IPS\Request::i()->page OR \IPS\Request::i()->page < 1 )
			{
				\IPS\Request::i()->page	= 1;
			}
		}

		/* Ensure JS loaded for forms/content functions such as moderation */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_core.js', 'core' ) );

		return parent::execute();
	}

	/**
	 * View Item
	 *
	 * @return	\IPS\Content\Item|NULL
	 */
	protected function manage()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			/* If this is an AJAX request (like the topic hovercard), we don't want to do any of the below like update views and mark read */
			if ( \IPS\Request::i()->isAjax() )
			{
				/* But we do want to mark read if we are paging through the content */
				if( $item instanceof \IPS\Content\ReadMarkers AND isset( \IPS\Request::i()->page ) AND \IPS\Request::i()->page AND $item->isLastPage() )
				{
					$item->markRead();
				}

				return $item;
			}
			
			/* Check we're on a valid page */
			if ( isset( \IPS\Request::i()->page ) )
			{
				$paginationType = 'comment';
				if ( isset( \IPS\Request::i()->tab ) and  \IPS\Request::i()->tab === 'reviews' )
				{
					$paginationType = 'review';
				}
				
				$pageCount = call_user_func( array( $item, "{$paginationType}PageCount" ) );
				if ( $pageCount and \IPS\Request::i()->page > $pageCount )
				{
					\IPS\Output::i()->redirect( call_user_func( array( $item, 'last' . ucfirst( $paginationType ) . 'PageUrl' ) ), NULL, 303 );
				} 
			}
						
			/* Update Views */
			$idColumn = $class::$databaseColumnId;
			if ( in_array( 'IPS\Content\Views', class_implements( $class ) ) AND isset( $class::$databaseColumnMap['views'] ) )
			{
				\IPS\Db::i()->update(
					$class::$databaseTable,
					"`{$class::$databasePrefix}{$class::$databaseColumnMap['views']}`=`{$class::$databasePrefix}{$class::$databaseColumnMap['views']}`+1",
					array( "{$class::$databasePrefix}{$class::$databaseColumnId}=?", $item->$idColumn ),
					array(),
					NULL,
					\IPS\Db::LOW_PRIORITY
				);
			}
						
			/* Mark read */
			if( $item instanceof \IPS\Content\ReadMarkers )
			{	
				/* Note time last read before we mark it read so that the line is in the right place */
				$item->timeLastRead();
				
				if ( $item->isLastPage() )
				{
					$item->markRead();
				}
			}
			
			/* Have we moved? */
			if ( isset( $class::$databaseColumnMap['state'] ) AND isset( $class::$databaseColumnMap['moved_to'] ) )
			{
				$stateColumn	= $class::$databaseColumnMap['state'];
				$movedToColumn	= $class::$databaseColumnMap['moved_to'];
				$movedTo		= explode( '&', $item->$movedToColumn );
				
				if ( $item->$stateColumn == 'link' )
				{
					try
					{
						$moved = $class::loadAndCheckPerms( $movedTo[0] );
						\IPS\Output::i()->redirect( $moved->url(), '', 301 );
					}
					catch( \OutOfRangeException $e ) { }
				}
			}
			
			/* Set navigation and title */
			$this->_setBreadcrumbAndTitle( $item, FALSE );
			
			/* Set meta tags */
			\IPS\Output::i()->linkTags['canonical'] = (string) ( \IPS\Request::i()->page > 1 ) ? $item->url()->setQueryString( 'page', \IPS\Request::i()->page ) : $item->url() ;
			\IPS\Output::i()->metaTags['description'] = strip_tags( $item->metaDescription() );
			\IPS\Output::i()->metaTags['og:title'] = $item->mapped('title');
			\IPS\Output::i()->metaTags['og:type'] = 'object';
			\IPS\Output::i()->metaTags['og:url'] = (string) $item->url();
			\IPS\Output::i()->metaTags['og:description'] = strip_tags( $item->metaDescription() );
			if( $item->mapped('updated') OR $item->mapped('last_comment') OR $item->mapped('last_review') )
			{
				\IPS\Output::i()->metaTags['og:updated_time'] = \IPS\DateTime::ts( $item->mapped('updated') ? $item->mapped('updated') : ( $item->mapped('last_comment') ? $item->mapped('last_comment') : $item->mapped('last_review') ) )->rfc3339();
			}
			
			/* Add contextual search options */
			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) ) ] = array( 'type' => mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ), 'item' => $item->$idColumn );
			try
			{
				$container = $item->container();
				\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $container::$nodeTitle . '_sg' ) ) ) ) ] = array( 'type' => mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ), 'nodes' => $container->_id );
			}
			catch ( \BadMethodCallException $e ) { }
			
			/* Return */
			return $item;
			
		}
		catch ( \LogicException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * AJAX - check for new replies
	 *
	 * @return	\IPS\Content\Item|NULL
	 */
	protected function checkForNewReplies()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$commentClass = $class::$commentClass;
			$commentIdColumn = $commentClass::$databaseColumnId;
			$commentDateColumn = $commentClass::$databaseColumnMap['date'];
			
			/* The form field has an underscore, but this value is sent in a query string value without an underscore via AJAX */
			if( ! \IPS\Request::i()->lastSeenID or ! \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->json( array( 'count' => 0 ) );
			}

			$lastComment = $commentClass::load( \IPS\Request::i()->lastSeenID );
			$authorColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'];
			
			$newComments = $item->comments( NULL, 0, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $lastComment->$commentDateColumn ), "{$authorColumn} != " . \IPS\Member::loggedIn()->member_id );
			
			if ( \IPS\Request::i()->type === 'count' )
			{
				$data = array( 'count' => (int) count( $newComments ), 'totalCount' => $item->mapped('num_comments') );

				if( $data['count'] === 1 ){
					$itemData = reset( $newComments );
					$author = $itemData->author();

					$data['name'] = htmlspecialchars( $author->name, \IPS\HTMLENTITIES, 'UTF-8', FALSE );
					$data['photo'] = (string) $author->photo;
				}

				\IPS\Output::i()->json( $data );
			}
			else
			{
				$output = array();
				$lastId = 0;
				foreach ( $newComments as $newComment )
				{
					$output[] = $newComment->html();
					$lastId = ( $newComment->$commentIdColumn > $lastId ) ? $newComment->$commentIdColumn : $lastId;
				}

				$item->markRead();
			}
			
			\IPS\Output::i()->json( array( 'content' => $output, 'id' => $lastId, 'totalCount' => $item->mapped('num_comments') ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->json( $e->getMessage(), 500 );
		}
	}
	
	/**
	 * Edit Item
	 *
	 * @return	void
	 */
	protected function edit()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canEdit() )
			{
				throw new \DomainException;
			}
						
			$form = new \IPS\Helpers\Form( 'form', \IPS\Member::loggedIn()->language()->addToStack( $class::$formLangPrefix . '_save' ) ? $class::$formLangPrefix . '_save' : 'save' );
			$form->class = 'ipsForm_vertical';
			
			$container = NULL;
			try
			{
				$container = $item->container();
			}
			catch ( \BadMethodCallException $e ) {}
			
			$formElements = $class::formElements( $item, $container );
			if ( isset( $formElements['poll'] ) AND !\IPS\Request::i()->_report )
			{
				$form->addTab( $class::$formLangPrefix . 'mainTab' );
			}
			foreach ( $formElements as $key => $object )
			{
				if ( \IPS\Request::i()->_report AND !in_array( $key, array( 'title', 'content', 'description' ) ) )
				{
					continue;
				}
				
				if ( $key === 'poll' )
				{
					$form->addTab( $class::$formLangPrefix . 'pollTab' );
				}
				
				$form->add( $object );
			}

			if ( $values = $form->values() )
			{
				$item->processForm( $values );
				if ( isset( $item::$databaseColumnMap['updated'] ) )
				{
					$column = $item::$databaseColumnMap['updated'];
					$item->$column = time();
				}

				if ( isset( $item::$databaseColumnMap['date'] ) and isset( $values[ $item::$formLangPrefix . 'date' ] ) )
				{
					$column = $item::$databaseColumnMap['date'];

					if ( $values[ $item::$formLangPrefix . 'date' ] instanceof \IPS\DateTime )
					{
						$item->$column = $values[ $item::$formLangPrefix . 'date' ]->getTimestamp();
					}
					else
					{
						$item->$column = time();
					}
				}

				$item->save();
				$item->processAfterEdit( $values );

				\IPS\Output::i()->redirect( $item->url() );
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->output = $form;
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/E', 404, '' );
		}
	}
	
	/**
	 * Set the breadcrumb and title
	 *
	 * @param	\IPS\Content\Item	$item	Content item
	 * @param	bool				$link	Link the content item element in the breadcrumb
	 * @return	void
	 */
	protected function _setBreadcrumbAndTitle( $item, $link=TRUE )
	{
		$container	= NULL;
		try
		{
			$container = $item->container();
			foreach ( $container->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $link ? $item->url() : NULL, $item->mapped('title') );

		$title = ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $item->mapped('title'), \IPS\Request::i()->page ) ) ) : $item->mapped('title');
		\IPS\Output::i()->title = $container ? ( $title . ' - ' . $container->_title ) : $title;
	}

	/**
	 * Moderate
	 *
	 * @return	void
	 */
	protected function moderate()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
						
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			if ( $item::$hideLogKey and \IPS\Request::i()->action === 'hide' )
			{
				$this->_setBreadcrumbAndTitle( $item );
				
				$form = new \IPS\Helpers\Form;
				$form->add( new \IPS\Helpers\Form\Text( 'hide_reason' ) );
				if ( $values = $form->values() )
				{
					$item->modAction( \IPS\Request::i()->action, NULL, $values['hide_reason'] );
				}
				else
				{
					\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
					return;
				}
			}
			else
			{
				$item->modAction( \IPS\Request::i()->action );
			}
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
			else
			{
				if( \IPS\Request::i()->action == 'delete' )
				{
					\IPS\Output::i()->redirect( $item->container()->url() );
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url(), 'mod_confirm_' . \IPS\Request::i()->action );
				}
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1', 404, '' );
		}
	}
	
	/**
	 * Move
	 *
	 * @return	void
	 */
	protected function move()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMove() )
			{
				throw new \DomainException;
			}
			
			$form = new \IPS\Helpers\Form( 'form', 'move' );
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Node( 'move_to', NULL, TRUE, array(
				'class'				=> get_class( $item->container() ),
				'permissionCheck'	=> function( $node ) use ( $item )
				{
					if ( $node->id != $item->container()->id )
					{
						try
						{
							if ( $node->can( 'add' ) )
							{
								return true;
							}
						}
						catch( \OutOfBoundsException $e ) { }
					}
					
					return false;
				}
			) ) );
			
			if ( isset( $class::$databaseColumnMap['moved_to'] ) )
			{
				$form->add( new \IPS\Helpers\Form\Checkbox( 'move_keep_link' ) );
				
				if ( \IPS\Settings::i()->topic_redirect_prune )
				{
					\IPS\Member::loggedIn()->language()->words['move_keep_link_desc'] = \IPS\Member::loggedIn()->language()->addToStack( '_move_keep_link_desc', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->topic_redirect_prune ) ) );
				}
			}
			
			if ( $values = $form->values() )
			{
				if ( $values['move_to'] === NULL OR !$values['move_to']->can('add') OR $values['move_to']->id == $item->container()->id )
				{
					\IPS\Output::i()->error( 'node_move_invalid', '1S136/L', 403, '' );
				}
				
				$item->move( $values['move_to'], isset( $values['move_keep_link'] ) ? $values['move_keep_link'] : FALSE );
				\IPS\Output::i()->redirect( $item->url() );
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'move_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title ) ) ) );
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
			
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/D', 403, '' );
		}
	}
	
	/**
	 * Merge
	 *
	 * @return	void
	 */
	protected function merge()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMerge() )
			{
				throw new \DomainException;
			}
			$form = new \IPS\Helpers\Form( 'form', 'merge' );
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Url( 'merge_with', NULL, TRUE, array(), function ( $val ) use ( $class )
			{
				try
				{
					$class::loadFromUrl( $val );
				}
				catch ( \Exception $e )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ) );
				}
			
			} ) );
			\IPS\Member::loggedIn()->language()->words['merge_with_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'merge_with__desc', FALSE, array( 'sprintf' => array( $item->indefiniteArticle(), $item->mapped('title') ) ) );
			if ( $values = $form->values() )
			{
				$item->mergeIn( array( $class::loadFromUrl( $values['merge_with'] ) ) );
				\IPS\Output::i()->redirect( $item->url() );
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'merge_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title ) ) ) );
				
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
			
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/G', 403, '' );
		}
	}

	/**
	 * Send email
	 *
	 * @return	void
	 */
	protected function email()
	{
		/* Share link enabled */
		$node = \IPS\core\ShareLinks\Service::load( 'email', 'share_key' );
		
		if( !( $node->enabled and ( $node->groups === "*" or \IPS\Member::loggedIn()->inGroup( explode( ',', $node->groups ) ) ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/N', 403, '' );
		}
		
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( $item instanceof \IPS\Content\Shareable )
			{
				$form = new \IPS\Helpers\Form( 'form', 'send' );
				$form->class = 'ipsForm_vertical';

				if( !\IPS\Member::loggedIn()->member_id )
				{
					$form->add( new \IPS\Helpers\Form\Text( 'mail_from_name', NULL, TRUE ) );
					$form->add( new \IPS\Helpers\Form\Email( 'mail_from_email', NULL, TRUE ) );
				}
				$form->add( new \IPS\Helpers\Form\Text( 'email_subject', $item->mapped('title'), TRUE, array( 'maxLength' => 255 ) ) );
				$form->add( new \IPS\Helpers\Form\Email( 'email_email', NULL, TRUE ) );
								
				$idColumn	= $class::$databaseColumnId;
				$url		= $item->url();
				
				if ( \IPS\Request::i()->comment )
				{
					$url	= $url->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->comment ) );
				}
				
				$defaultEmail	= \IPS\Member::loggedIn()->language()->addToStack('default_email_content', FALSE, array( 'sprintf' => array( $item->mapped('title'), $url ) ) );
				$form->add( new \IPS\Helpers\Form\Editor( 'email_content', $defaultEmail, TRUE, array( 'app' => 'core', 'key' => 'ShareLinks', 'autoSaveKey' => 'contentEdit-' . $class::$application . '/' . $class::$module . '-' . $item->$idColumn ) ) );

				if( !\IPS\Member::loggedIn()->member_id )
				{
					$form->add( new \IPS\Helpers\Form\Captcha );
				}

				if( $values = $form->values() )
				{
					$email				= \IPS\Email::buildFromContent( $values['email_subject'], $values['email_content'] );
					$email->from		= ( isset( $values['mail_from_email'] ) ) ? $values['mail_from_email'] : \IPS\Member::loggedIn()->email;
					$email->fromName	= ( isset( $values['mail_from_name'] ) ) ? $values['mail_from_name'] : \IPS\Member::loggedIn()->name;

					$email->emailBody['html'] = $email->parseTextForEmail( $email->emailBody['html'] );
					$email->send( $values['email_email'] );
	
					if ( \IPS\Request::i()->isAjax() )
					{
						return;
					}
					else
					{
						$url = $item->url();
						
						if ( \IPS\Request::i()->comment )
						{
							$url	= $url->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->comment ) );
						}
						
						\IPS\Output::i()->redirect( $url, 'email_sent' );
					}
				}
	
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('send_email_form');
				\IPS\Output::i()->output	= $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
			}
			else
			{
				throw new \BadMethodCallException;
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/5', 404, '' );
		}
	}
	
	/**
	 * Report Item
	 *
	 * @return	void
	 */
	protected function report()
	{
		try
		{
			/* Init */
			$class = static::$contentModel;
			$commentClass = $class::$commentClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			/* Permission check */
			$canReport = $item->canReport();
			if ( $canReport !== TRUE )
			{
				\IPS\Output::i()->error( $canReport, '2S136/6', 404, '' );
			}
			
			/* Show form */
			$form = new \IPS\Helpers\Form( NULL, 'report_submit' );
			$form->class = 'ipsForm_vertical';
			$idColumn = $class::$databaseColumnId;
			$form->add( new \IPS\Helpers\Form\Editor( 'report_message', NULL, FALSE, array( 'app' => 'core', 'key' => 'Reports', 'autoSaveKey' => "report-{$class::$application}-{$class::$module}-{$item->$idColumn}", 'minimize' => 'report_message_placeholder' ) ) );
			if ( $values = $form->values() )
			{
				$report = $item->report( $values['report_message'] );
				\IPS\File::claimAttachments( "report-{$class::$application}-{$class::$module}-{$item->$idColumn}", $report->id );
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack('report_submit_success') );
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url(), 'report_submit_success' );
				}
			}

			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/7', 404, '' );
		}
	}
	
	/**
	 * Get Next Unread Item
	 *
	 * @return	void
	 */
	protected function nextUnread()
	{
		try
		{
			$class		= static::$contentModel;
			$item		= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$next		= $item->nextUnread();

			if ( $next instanceof \IPS\Content\Item )
			{
				\IPS\Output::i()->redirect( $next->url()->setQueryString( array( 'do' => 'getNewComment' ) ) );
			}
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'next_unread_not_found', '2S136/J', 404, '' );
		}
	}
	
	/**
	 * Rep Item
	 *
	 * @return	void
	 */
	protected function rep()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
			
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$type = intval( \IPS\Request::i()->rep ) === 1 ? 1 : -1;
			$item->giveReputation( $type );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				if ( \IPS\Request::i()->mini )
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->reputationMini( $item ) );	
				}
				else
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->reputation( $item ) );	
				}				
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch ( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1S136/H', 403, '' );
			}
		}
	}
	
	/**
	 * Show Comment/Review Rep
	 *
	 * @return	void
	 */
	protected function showRep()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			/* Set navigation */
			$this->_setBreadcrumbAndTitle( $item );

			\IPS\Output::i()->output = $item->reputationTable();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/O', 404, '' );
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/P', 403, '' );
		}
	}
	
	/**
	 * Moderator Log
	 *
	 * @return	void
	 */
	protected function modLog()
	{
		if( !\IPS\Member::loggedIn()->modPermission('can_view_moderation_log') )
		{
			throw new \DomainException;
		}
		
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
		
			/* Set navigation */
			$this->_setBreadcrumbAndTitle( $item );

			\IPS\Output::i()->output = $item->moderationTable();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/T', 404, '' );
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/U', 403, '' );
		}
	}
	
	/**
	 * Go to new comment.
	 *
	 * @return	void
	 */
	public function getNewComment()
	{
		try
		{
			$class	= static::$contentModel;
			$item	= $class::loadAndCheckPerms( \IPS\Request::i()->id );

			$timeLastRead = $item->timeLastRead();
			if ( $timeLastRead instanceof \IPS\DateTime )
			{
				$comment = $item->comments( 1, NULL, 'date', 'asc', NULL, NULL, $timeLastRead );
				\IPS\Output::i()->redirect( $comment ? $comment->url() : $item->url( 'getLastComment' ) );
			}
			else
			{
				if ( $item->unread() )
				{
					/* If we do not have a time last read set for this content, fallback to the reset time */
					$resetTimes = \IPS\Member::loggedIn()->markersResetTimes( $class::$application );
					if ( array_key_exists( $item->container()->_id, $resetTimes ) )
					{
						$comment = $item->comments( 1, NULL, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $resetTimes[ $item->container()->_id ] ) );
						\IPS\Output::i()->redirect( $comment ? $comment->url() : $item->url() );
					}
					else
					{
						\IPS\Output::i()->redirect( $item->url() );
					}
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url() );
				}
			}
		}
		catch( \BadMethodCallException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/I', 404, '' );
		}
		catch( \LogicException $e )
		{
			$class = static::$contentModel;
			$item = $class::load( \IPS\Request::i()->id );

			\IPS\Output::i()->error( ( !$item->canView() and method_exists( $item->container(), 'errorMessage' ) ) ? $item->container()->errorMessage() : 'node_error', '2S136/R', 404, '' );
		}
	}
	
	/**
	 * Go to last comment
	 *
	 * @return	void
	 */
	public function getLastComment()
	{
		try
		{
			$class	= static::$contentModel;
			$item	= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$comment = $item->comments( 1, NULL, 'date', 'desc' );
			
			if ( $comment !== NULL )
			{
				\IPS\Output::i()->redirect( $comment->url() );
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch( \BadMethodCallException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/K', 404, '' );
		}
		catch( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/Q', 404, '' );
		}
	}
	
	/**
	 * Rate Review as helpful/unhelpful
	 *
	 * @return	void
	 */
	public function rateReview()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
			
			/* Only logged in members */
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				throw new \DomainException;
			}
			
			/* Init */
			$class = static::$contentModel;
			$reviewClass = $class::$reviewClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$review = $reviewClass::load( \IPS\Request::i()->review );
			
			/* Review authors can't rate their own reviews */
			if ( $review->author()->member_id === \IPS\Member::loggedIn()->member_id )
			{
				throw new \DomainException;
			}
			
			/* Have we already rated? */
			$dataColumn = $reviewClass::$databaseColumnMap['votes_data'];
			$votesData = $review->mapped('votes_data') ? json_decode( $review->mapped('votes_data'), TRUE ) : array();
			if ( array_key_exists( \IPS\Member::loggedIn()->member_id, $votesData ) )
			{
				\IPS\Output::i()->error( 'you_have_already_rated', '2S136/A', 403, '' );
			}
			
			/* Add it */
			$votesData[ \IPS\Member::loggedIn()->member_id ] = intval( \IPS\Request::i()->helpful );
			if ( \IPS\Request::i()->helpful )
			{
				$helpful = $reviewClass::$databaseColumnMap['votes_helpful'];
				$review->$helpful++;
			}
			$total = $reviewClass::$databaseColumnMap['votes_total'];
			$review->$total++;
			$review->$dataColumn = json_encode( $votesData );
			$review->save();
			
			/* Boink */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( $review->html() );
			}
			else
			{
				\IPS\Output::i()->redirect( $review->url() );
			}
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/9', 404, '' );
		}
	}
		
	/**
	 * Stuff that applies to both comments and reviews
	 *
	 * @param	string	$method	Desired method
	 * @param	array	$args	Arguments
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		$class = static::$contentModel;
		
		try
		{
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$comment = NULL;
			if ( mb_substr( $method, -7 ) === 'Comment' )
			{
				
				$class = $class::$commentClass;
				$method = '_' . mb_substr( $method, 0, mb_strlen( $method ) - 7 );
				
				$comment = $method === '_multimod' ? NULL : $class::load( \IPS\Request::i()->comment );
			}
			elseif ( mb_substr( $method, -6 ) === 'Review' )
			{
				$class = $class::$reviewClass;
				$method = '_' . mb_substr( $method, 0, mb_strlen( $method ) - 6 );
				$comment = $method === '_multimod' ? NULL : $class::load( \IPS\Request::i()->review );
			}
			
			if ( $method === '_multimod' )
			{
				$this->_multimod( $class, $item );
			}
									
			if ( !$comment or !method_exists( $this, $method ) )
			{
				\IPS\Output::i()->error( 'page_not_found', '2S136/B', 404, '' );
			}
			else
			{
				$this->$method( $class, $comment, $item );
			}
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/C', 404, '' );
		}
	}
	
	/**
	 * Find a Comment / Review (do=findComment/findReview)
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 */
	public function _find( $commentClass, $comment, $item )
	{
		$idColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnId;

		$_SESSION['_findComment']	= $comment->$idColumn;

		\IPS\Output::i()->redirect( $comment->url() );
	}
	
	/**
	 * Hide Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _hide( $commentClass, $comment, $item  )
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( $comment::$hideLogKey )
		{
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Text( 'hide_reason' ) );
			if ( $values = $form->values() )
			{
				$comment->modAction( 'hide', NULL, $values['hide_reason'] );
			}
			else
			{
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
				return;
			}
		}
		else
		{
			$comment->modAction('hide');
		}
		
		\IPS\Output::i()->redirect( $comment->url() );
	}
	
	/**
	 * Unhide Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _unhide( $commentClass, $comment, $item  )
	{
		\IPS\Session::i()->csrfCheck();
		$comment->modAction('unhide');

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $comment->html(), 200, 'text/html' );
			return;
		}
		else
		{
			\IPS\Output::i()->redirect( $comment->url() );
		}
	}


	/**
	 * Edit Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _edit( $commentClass, $comment, $item )
	{
		$class = static::$contentModel;
		$valueField = $commentClass::$databaseColumnMap['content'];
		$idField = $commentClass::$databaseColumnId;
		$itemIdField = $item::$databaseColumnId;
		
		if ( $comment->canEdit() )
		{
			$form = new \IPS\Helpers\Form;
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Editor( 'comment_value', $comment->$valueField, TRUE, array(
				'app'			=> $class::$application,
				'key'			=> ucfirst( $class::$module ),
				'autoSaveKey' 	=> 'editComment-' . $class::$application . '/' . $class::$module . '-' . $comment->id,
				'attachIds'		=> $comment->attachmentIds()
			) ) );
			
			$form->addButton( 'cancel', 'link', $item->url()->setQueryString( array( 'do' => 'findComment', 'comment' => $comment->$idField ) ), 'ipsButton ipsButton_link', array( 'data-action' => 'cancelEditComment', 'data-comment-id' => $comment->$idField ) );

			if ( $comment instanceof \IPS\Content\EditHistory and \IPS\Settings::i()->edit_log )
			{
				if ( \IPS\Settings::i()->edit_log == 2 or isset( $commentClass::$databaseColumnMap['edit_reason'] ) )
				{
					$form->add( new \IPS\Helpers\Form\Text( 'comment_edit_reason', ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) ) ? $comment->mapped('edit_reason') : NULL, FALSE, array( 'maxLength' => 255 ) ) );
				}
				if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
				{
					$form->add( new \IPS\Helpers\Form\Checkbox( 'comment_log_edit', \IPS\Member::loggedIn()->member_id == $comment->author()->member_id ) );
				}
			}
			
			if ( $values = $form->values() )
			{
				if ( $comment instanceof \IPS\Content\EditHistory )
				{
					$editIsPublic = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
					
					if ( \IPS\Settings::i()->edit_log == 2 )
					{
						\IPS\Db::i()->insert( 'core_edit_history', array(
							'class'			=> get_class( $comment ),
							'comment_id'	=> $comment->$idField,
							'member'		=> \IPS\Member::loggedIn()->member_id,
							'time'			=> time(),
							'old'			=> $comment->$valueField,
							'new'			=> $values['comment_value'],
							'public'		=> $editIsPublic,
							'reason'		=> isset( $values['comment_edit_reason'] ) ? $values['comment_edit_reason'] : NULL,
						) );
					}
					
					if ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) and isset( $values['comment_edit_reason'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_reason'];
						$comment->$field = $values['comment_edit_reason'];
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_time'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_time'];
						$comment->$field = time();
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_member_id'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_member_id'];
						$comment->$field = \IPS\Member::loggedIn()->member_id;
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_member_name'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_member_name'];
						$comment->$field = \IPS\Member::loggedIn()->name;
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_show'] ) and $editIsPublic )
					{
						$field = $commentClass::$databaseColumnMap['edit_show'];
						$comment->$field = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
					}
				}
				
				$oldValue = $comment->$valueField;
				$comment->$valueField = $values['comment_value'];
				$comment->save();
				$comment->sendAfterEditNotifications( $oldValue );
				
				/* Log */
				\IPS\Session::i()->modLog( 'modlog__comment_edit', array( $comment->url()->__toString() => FALSE, $item::$title => FALSE, $item->url()->__toString() => FALSE, $item->mapped('title') => FALSE ), $item );
				
				if ( $comment instanceof \IPS\Content\Searchable )
				{
					\IPS\Content\Search\Index::i()->index( $comment );
				}
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->output = $comment->html();
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $comment->url() );
				}
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('edit_comment') );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('edit_comment') . ' - ' . $item->mapped('title');
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
		}
		else
		{
			throw new \UnexpectedValueException;
		}
	}
	
	/**
	 * Delete Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _delete( $commentClass, $comment, $item )
	{
		\IPS\Session::i()->csrfCheck();

		$currentPageCount = $item->commentPageCount();

		$valueField = $commentClass::$databaseColumnMap['content'];
		
		if ( $item::$firstCommentRequired and $comment->mapped('first') )
		{
			if ( $item->canDelete() )
			{
				$item->delete();
			}
			else
			{
				throw new \UnderflowException;
			}
		}
		else
		{
			if ( $comment->canDelete() )
			{
				$comment->delete();
				
				/* Log */
				\IPS\Session::i()->modLog( 'modlog__comment_delete', array( $item::$title => FALSE, $item->url()->__toString() => FALSE, $item->mapped('title') => FALSE ), $item );
			}
			else
			{
				throw new \UnexpectedValueException;
			}
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			$currentPageCount = \IPS\Request::i()->currentPage;
			$newPageCount = $item->commentPageCount( TRUE );
			if ( $currentPageCount != $newPageCount )
			{
				\IPS\Output::i()->json( array( 'type' => 'redirect', 'total' => $item->mapped( 'num_comments' ), 'url' => (string) $item->lastCommentPageUrl() ) );
			}
			else
			{
				\IPS\Output::i()->json( array( 'page' => $newPageCount, 'total' => $item->mapped( 'num_comments' ) ) );
			}
		}
		else
		{
			\IPS\Output::i()->redirect( $item->url() );
		}
	}
	
	/**
	 * Split Comment
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _split( $commentClass, $comment, $item )
	{
		if ( $comment->canSplit() )
		{
			$itemClass = $comment::$itemClass;
			$idColumn = $itemClass::$databaseColumnId;
			$commentIdColumn = $comment::$databaseColumnId;
			
			/* Create a copy of the old item for logging */
			$oldItem = $item;
			
			/* Construct a form */
			$form = $this->_splitForm( $item );

			/* Handle submissions */
			if ( $values = $form->values() )
			{
				/* Are we creating or using an existing? */
				if ( isset( $values['_split_type'] ) and $values['_split_type'] === 'new' )
				{
					$item = $itemClass::createItem( $comment->author(), $comment->mapped('ip_address'), \IPS\DateTime::ts( $comment->mapped('date') ), isset( $values[ $itemClass::$formLangPrefix . 'container' ] ) ? $values[ $itemClass::$formLangPrefix . 'container' ] : NULL );
					$item->processForm( $values );
					if ( isset( $itemClass::$databaseColumnMap['first_comment_id'] ) )
					{
						$firstCommentIdColumn = $itemClass::$databaseColumnMap['first_comment_id'];
						$item->$firstCommentIdColumn = $comment->$commentIdColumn;
					}
					
					$item->save();
				}
				else
				{
					$item = $itemClass::loadFromUrl( $values['_split_into_url'] );
				}
				
				$comment->move( $item );
				
				/* Log it */
				\IPS\Session::i()->modLog( 'modlog__action_split', array(
					$item::$title					=> FALSE,
					$item->url()->__toString()		=> FALSE,
					$item->mapped('title')			=> FALSE,
					$oldItem->url()->__toString()	=> FALSE,
					$oldItem->mapped('title')		=> FALSE
				), $item );
			
				/* Redirect to it */
				\IPS\Output::i()->redirect( $item->url() );
			}
			
			/* Display */
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
		}
		else
		{
			throw new \DomainException;
		}
	}
	
	/**
	 * Edit Log
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _editlog( $commentClass, $comment, $item )
	{
		/* Permission check */
		if ( \IPS\Settings::i()->edit_log != 2 or ( !\IPS\Settings::i()->edit_log_public and !\IPS\Member::loggedIn()->modPermission('can_view_editlog') ) )
		{
			throw new \DomainException;
		}
		
		/* Display */
		$container = NULL;
		try
		{
			$container = $item->container();
			foreach ( $container->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $comment->url(), $item->mapped('title') );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'edit_history_title' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'edit_history_title' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->commentEditHistory( $comment->editHistory( \IPS\Member::loggedIn()->modPermission('can_view_editlog') ), $comment );
	}
	
	/**
	 * Report Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _report( $commentClass, $comment, $item )
	{
		$class = static::$contentModel;
		
		/* Permission check */
		$canReport = $comment->canReport();
		if ( $canReport !== TRUE )
		{
			\IPS\Output::i()->error( $canReport, '2S136/4', 404, '' );
		}
		
		/* Show form */
		$form = new \IPS\Helpers\Form( NULL, 'report_submit' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Editor( 'report_message', NULL, FALSE, array( 'app' => 'core', 'key' => 'Reports', 'autoSaveKey' => "report-{$class::$application}-{$class::$module}-{$item->id}-{$comment->id}", 'minimize' => 'report_message_placeholder' ) ) );
		if( !\IPS\Member::loggedIn()->member_id )
		{
			$form->add( new \IPS\Helpers\Form\Captcha );
		}

		if ( $values = $form->values() )
		{
			$report = $comment->report( $values['report_message'] );
			\IPS\File::claimAttachments( "report-{$class::$application}-{$class::$module}-{$item->id}-{$comment->id}", $report->id );
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack('report_submit_success') );
			}
			else
			{
				\IPS\Output::i()->redirect( $comment->url(), 'report_submit_success' );
			}
		}
		$this->_setBreadcrumbAndTitle( $item );
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}
	
	/**
	 * Rep Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _rep( $commentClass, $comment, $item )
	{
		\IPS\Session::i()->csrfCheck();
		
		$type = intval( \IPS\Request::i()->rep ) === 1 ? 1 : -1;
		
		try
		{
			$comment->giveReputation( $type );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->reputation( $comment ) );
			}
			else
			{
				\IPS\Output::i()->redirect( $comment->url() );
			}
		}
		catch ( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1S136/M', 403, '' );
			}
		}
	}
	
	/**
	 * Show Comment/Review Rep
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _showRep( $commentClass, $comment, $item )
	{
		/* Set navigation */
		$this->_setBreadcrumbAndTitle( $item );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $comment->reputationTable();
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->genericBlock( $comment->reputationTable(), \IPS\Member::loggedIn()->language()->addToStack('see_who_repped') );
		}
	}
	
	/**
	 * Multimod
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _multimod( $commentClass, $item )
	{
		\IPS\Session::i()->csrfCheck();
		if ( \IPS\Request::i()->modaction == 'split' )
		{
			$form = $this->_splitForm( $item );
			$form->hiddenValues['modaction'] = 'split';
			foreach ( \IPS\Request::i()->multimod as $k => $v )
			{
				$form->hiddenValues['multimod['.$k.']'] = $v;
			}
			if ( $values = $form->values() )
			{
				$itemIdColumn = $item::$databaseColumnId;
				$commentIdColumn = $commentClass::$databaseColumnId;
				
				/* Create a copy of the old item for logging */
				$oldItem = $item;
				
				$comments = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select(
					'*',
					$commentClass::$databaseTable,
					array(
						array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $item->$itemIdColumn ),
						\IPS\Db::i()->in( $commentClass::$databasePrefix . $commentClass::$databaseColumnId, array_keys( \IPS\Request::i()->multimod ) )
					),
					$commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date']
				), $commentClass );
				
				if ( isset( $values['_split_type'] ) and $values['_split_type'] === 'new' )
				{
					foreach ( $comments as $comment )
					{
						$firstComment = $comment;
						break;
					}
					
					$item = $item::createItem( $firstComment->author(), $firstComment->mapped('ip_address'), \IPS\DateTime::ts( $firstComment->mapped('date') ), $values[ $item::$formLangPrefix . 'container' ] );
					$item->processForm( $values );
					if ( isset( $item::$databaseColumnMap['first_comment_id'] ) )
					{
						$firstCommentIdColumn = $item::$databaseColumnMap['first_comment_id'];
						$item->$firstCommentIdColumn = $comment->$commentIdColumn;
					}
					$item->save();

				}
				else
				{
					$item = $item::loadFromUrl( $values['_split_into_url'] );
				}
				
				foreach ( $comments as $comment )
				{
					$comment->move( $item );
				}
				
				$item->rebuildFirstAndLastCommentData();
				$oldItem->rebuildFirstAndLastCommentData();
				
				/* Log it */
				\IPS\Session::i()->modLog( 'modlog__action_split', array(
					$item::$title					=> FALSE,
					$item->url()->__toString()		=> FALSE,
					$item->mapped('title')			=> FALSE,
					$oldItem->url()->__toString()	=> FALSE,
					$oldItem->mapped('title')		=> FALSE
				), $item );
				
				\IPS\Output::i()->redirect( $item->url() );
			}
			else
			{
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Output::i()->output  );
				}
				else
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
				}
				return;
			}
		}
		elseif ( \IPS\Request::i()->modaction == 'merge' )
		{
			if ( !( count( \IPS\Request::i()->multimod ) > 1 ) )
			{
				\IPS\Output::i()->error( 'cannot_merge_one_post', '1S136/S', 403, '' );
			}
			
			$comments	= array();
			$authors	= array();
			$content	= array();
			foreach( array_keys( \IPS\Request::i()->multimod ) AS $id )
			{
				try
				{
					$comments[$id]	= $commentClass::loadAndCheckPerms( $id );
					$content[]		= $comments[$id]->mapped('content');
				}
				catch( \Exception $e ) {}
			}
			
			$form = new \IPS\Helpers\Form;
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Editor( 'final_comment_content', implode( '<p>&nbsp;</p>', $content ), TRUE, array(
				'app'			=> $item::$application,
				'key'			=> ucwords( $item::$module ),
				'autoSaveKey'	=> 'mod-merge-' . implode( '-', array_keys( $comments ) ),
			) ) );

			if ( $values = $form->values() )
			{
				$idColumn			= $item::$databaseColumnId;
				$commentIdColumn	= $commentClass::$databaseColumnId;
				$commentIds			= array_keys( \IPS\Request::i()->multimod );
				$firstComment		= $commentClass::loadAndCheckPerms( array_shift( $commentIds ) );
				$contentColumn		= $commentClass::$databaseColumnMap['content'];
				$firstComment->$contentColumn = $values['final_comment_content'];
				$firstComment->save();
				
				foreach( $commentIds AS $id )
				{
					try
					{
						$comment = $commentClass::loadAndCheckPerms( $id );
						\IPS\Db::i()->update( 'core_attachments_map', array(
							'id1'	=> $item->$idColumn,
							'id2'	=> $firstComment->$commentIdColumn,
						), array( 'location_key=? AND id1=? AND id2=?', (string) $item::$application . '_' . ucfirst( $item::$module ), $item->$idColumn, $comment->$commentIdColumn ) );
						$comment->delete();
					}
					catch( \Exception $e ) {}
				}
				
				$item->rebuildFirstAndLastCommentData();
				
				\IPS\Output::i()->redirect( $firstComment->url() );
			}
			else
			{
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Output::i()->output );
				}
				else
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
				}
				
				return;
			}
		}
		else
		{
			$object = NULL;
			foreach ( array_keys( \IPS\Request::i()->multimod ) as $id )
			{
				try
				{
					$object = $commentClass::loadAndCheckPerms( $id );
					$object->modAction( \IPS\Request::i()->modaction, \IPS\Member::loggedIn() );
				}
				catch ( \Exception $e ) {}
			}
		
			if ( $object and \IPS\Request::i()->multimod != 'delete' )
			{
				\IPS\Output::i()->redirect( $object->url() );
			}
			elseif ( in_array( 'IPS\Content\Review', class_parents( $commentClass ) ) )
			{
				\IPS\Output::i()->redirect( $item->lastReviewPageUrl() );
			}
			else
			{
				\IPS\Output::i()->redirect( $item->lastCommentPageUrl() );
			}
		}
	}
	
	/**
	 * Form for splitting
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	\IPS\Helpers\Form
	 */
	protected function _splitForm( \IPS\Content\Item $item )
	{
		try
		{
			$container = $item->container();
		}
		catch ( \Exception $e )
		{
			$container = NULL;	
		}
		
		$form = new \IPS\Helpers\Form;
		if ( $item::canCreate( \IPS\Member::loggedIn(), $container ) )
		{
			$toAdd = array();
			$toggles = array();
							
			foreach ( $item::formElements( $item ) as $k => $field )
			{				
				if ( !in_array( $k, array( 'poll', 'content', 'comment_edit_reason', 'comment_log_edit' ) ) )
				{
					if ( $k === 'container' )
					{
						$field->defaultValue = $container;
						if ( !$field->value )
						{
							$field->value = $field->defaultValue;
						}
					}
					
					if ( !$field->htmlId )
					{
						$field->htmlId = $field->name;
					}
					$toggles[] = $field->htmlId;
					
					$toAdd[] = $field;
				}
			}
			
			$form->add( new \IPS\Helpers\Form\Radio( '_split_type', 'new', FALSE, array(
				'options' => array(
					'new'		=> \IPS\Member::loggedIn()->language()->addToStack( 'split_type_new', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) ),
					'existing'	=> \IPS\Member::loggedIn()->language()->addToStack( 'split_type_existing', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) )
				),
				'toggles' => array( 'new' => $toggles, 'existing' => array( 'split_into_url' ) ),
			) ) );

			foreach ( $toAdd as $field )
			{
				$form->add( $field );
			}
		}
		$form->add( new \IPS\Helpers\Form\Url( '_split_into_url', NULL, FALSE, array(), function ( $val ) use ( $item )
		{
			try
			{
				if ( $val )
				{
					$item::loadFromUrl( $val );
				}
			}
			catch ( \Exception $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) ) );
			}
		
		}, NULL, NULL, 'split_into_url' ) );
		
		return $form;
	}

	/**
	 * Retrieve content tagged the same
	 *
	 * @note	Used with a widget, but can be used elsewhere too
	 * @return	array|NULL
	 */
	public function getSimilarContent()
	{
		if( !isset( static::$contentModel ) )
		{
			return NULL;
		}

		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			if( $item->tags() === NULL )
			{
				return NULL;
			}

			$select = \IPS\Db::i()->select(
				'tag_meta_app,tag_meta_area,tag_meta_id',
				'core_tags',
				array(
					array( '(' . \IPS\Db::i()->in( 'tag_text', $item->tags() ) . ')' ),
					array( '!(tag_meta_app=? and tag_meta_area=? and tag_meta_id=?)', $class::$application, $class::$module, \IPS\Request::i()->id ),
					array( '(' . \IPS\Db::i()->findInSet( 'tag_perm_text', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'tag_perm_text=? )', '*' ),
					array( 'tag_perm_visible=1' )
				),
				'tag_added DESC',
				array( 0, 5 ),
				array( 'tag_aai_lookup' )
			)->join(
				'core_tags_perms',
				array( 'tag_perm_aai_lookup=tag_aai_lookup' )
			);
			
			$items	= array();

			foreach( $select as $result )
			{
				foreach( \IPS\Application::load( $result['tag_meta_app'] )->extensions( 'core', 'ContentRouter' ) as $key => $router )
				{
					$itemClass	= $router->classes[0];

					if( $itemClass::$module == $result['tag_meta_area'] )
					{
						try
						{
							$items[]	= $itemClass::loadAndCheckPerms( $result['tag_meta_id'] );
						}
						catch( \Exception $e ){}
					}
				}

			}
			
			return $items;
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	protected function _coverPhotoStorageExtension()
	{
		$class = static::$contentModel;
		return $class::$coverPhotoStorageExtension;
	}
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @return	void
	 */
	protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo )
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$photoColumn = $class::$databaseColumnMap['cover_photo'];
			$item->$photoColumn = (string) $photo->file;
			
			$offsetColumn = $class::$databaseColumnMap['cover_photo_offset'];
			$item->$offsetColumn = $photo->offset;
			
			$item->save();
		}
		catch ( \OutOfRangeException $e ){}
	}

	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	protected function _coverPhotoGet()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			return $item->coverPhoto();
		}
		catch ( \OutOfRangeException $e )
		{
			return new \IPS\Helpers\CoverPhoto;
		}
	}
}