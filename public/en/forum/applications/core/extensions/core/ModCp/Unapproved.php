<?php
/**
 * @brief		Moderator Control Panel Extension: Content Pending Approva
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		11 Dec 2013
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
 * Content Pending Approval
 */
class _Unapproved
{
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string
	 */
	public function getTab()
	{
		return 'approval';
	}
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('modcp_approval') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('modcp_approval');
		
		if ( isset( \IPS\Request::i()->go ) )
		{
			$showSplash = FALSE;
			\IPS\Request::i()->setCookie( 'ipsApprovalQueueSplash', !intval( isset( \IPS\Request::i()->skipnext ) ), \IPS\DateTime::ts( time() )->add( new \DateInterval( 'P1Y' ) ) );
			$_SESSION['ipsApprovalQueueSplash'] = TRUE;
		}
		elseif ( isset( $_SESSION['ipsApprovalQueueSplash'] ) )
		{
			$showSplash = FALSE;
		}
		elseif ( isset( \IPS\Request::i()->cookie['ipsApprovalQueueSplash'] ) )
		{
			$showSplash = \IPS\Request::i()->cookie['ipsApprovalQueueSplash'];
			$skipNextTime = FALSE;
		}
		else
		{
			$showSplash = TRUE;
			$skipNextTime = TRUE;
		}
		
		if ( $showSplash )
		{
			return \IPS\Theme::i()->getTemplate('modcp')->approvalQueueSplash( $skipNextTime );
		}
		else
		{
			return $this->_getNext();
		}
	}
	
	/**
	 * Get Next Content
	 *
	 * @return	string
	 */
	protected function _getNext()
	{
		if ( !isset( $_SESSION['ipsApprovalQueueSkip'] ) )
		{
			$_SESSION['ipsApprovalQueueSkip'] = array();
		}
		if ( isset( \IPS\Request::i()->skip ) )
		{
			if ( !isset( $_SESSION['ipsApprovalQueueSkip'][ \IPS\Request::i()->class ] ) )
			{
				$_SESSION['ipsApprovalQueueSkip'][ \IPS\Request::i()->class ] = array();
			}
			$_SESSION['ipsApprovalQueueSkip'][ \IPS\Request::i()->class ][] = \IPS\Request::i()->id;
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
		}
				
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE ) as $class )
		{
			if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
			{
				$where = array();				
				if ( isset( $class::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['hidden'] . '=1' );
				}
				elseif ( isset( $class::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['approved'] . '=0' );
				}
				else
				{
					continue;
				}
				
				if ( isset( $_SESSION['ipsApprovalQueueSkip'][ md5( $class ) ] ) )
				{
					$where[] = '!' . \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnId, $_SESSION['ipsApprovalQueueSkip'][ md5( $class ) ] );
				}
				
				foreach ( $class::getItemsWithPermission( $where, NULL, 1 ) as $item )
				{
					$idColumn = $item::$databaseColumnId;
					$container = NULL;
					if ( $item instanceof \IPS\Content\Comment )
					{
						$approveUrl = $item->url()->setQueryString( array( 'do' => 'unhideComment', 'comment' => $item->$idColumn ) )->csrf();
						$deleteUrl = $item->url()->setQueryString( array( 'do' => 'deleteComment', 'comment' => $item->$idColumn ) )->csrf();
						$itemClass = $item::$itemClass;
						$ref = base64_encode( json_encode( array( 'app' => $itemClass::$application, 'module' => $itemClass::$module, 'id_1' => $item->mapped('item'), 'id_2' => $item->id ) ) );
						$title = $item->item()->mapped('title');
					}
					else
					{
						$approveUrl = $item->url()->setQueryString( array( 'do' => 'moderate', 'action' => 'unhide' ) )->csrf();
						$deleteUrl = $item->url()->setQueryString( array( 'do' => 'moderate', 'action' => 'delete' ) )->csrf();
						$ref = base64_encode( json_encode( array( 'app' => $item::$application, 'module' => $item::$module, 'id_1' => $item->id ) ) );

						try
						{
							$container = $item->container();
						}
						catch ( \Exception $e ) { }

						$title = $item->mapped('title');
					}
					$skipUrl = \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=approval', 'front', 'modcp_approval' )->setQueryString( array( 'skip' => '1', 'class' => md5( $class ), 'id' => $item->$idColumn ) );

					$return = \IPS\Theme::i()->getTemplate('modcp')->approvalQueueHeader( $item, $approveUrl, $skipUrl, $deleteUrl );

					$return .= $item->approvalQueueHtml( $ref, $container, $title );

					if ( \IPS\Request::i()->isAjax() )
					{
						return \IPS\Output::i()->json( array( 'html' => $return, 'count' => \IPS\Content\Search\Query::init()->setHiddenFilter( \IPS\Content\Search\Query::HIDDEN_UNAPPROVED )->search()->count( TRUE ) ) );
					}
					else
					{
						return \IPS\Theme::i()->getTemplate('modcp')->approvalQueue( $return );
					}
				}
			}
		}
		
		/* Did we skip any? If so, clear it and loop through again. */
		if ( count( $_SESSION['ipsApprovalQueueSkip'] ) )
		{
			$_SESSION['ipsApprovalQueueSkip'] = array();
			return $this->_getNext();
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			return \IPS\Output::i()->json( array( 'html' => \IPS\Theme::i()->getTemplate('modcp')->approvalQueueEmpty(), 'count' => 0 ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate('modcp')->approvalQueueEmpty();
		}
	}
	
	
}