<?php
/**
 * @brief		Status Updates Feed
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		15 Aug 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\status;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Status Updates Feed
 */
class _feed extends \IPS\Dispatcher\Controller
{
	/**
	 * Status Updates Feed
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( !\IPS\Settings::i()->profile_comments )
		{
			\IPS\Output::i()->error( 'node_error', '2C231/2', 403, '' );
		}
		
		if ( isset( \IPS\Request::i()->following ) AND !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C231/1', 403, '' );
		}
		
		$where		= array();
		$statuses	= array();
		
		if ( isset( \IPS\Request::i()->following ) )
		{
			$members = array();
			foreach( \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_member_id=?', 'core', 'member', \IPS\Member::loggedIn()->member_id ) ) AS $follow )
			{
				$members[] = $follow['follow_rel_id'];
			}
			
			if ( count( $members ) )
			{
				$where[] = array( \IPS\Db::i()->in( 'status_author_id', $members ) . ' OR ' . \IPS\Db::i()->in( 'status_member_id', $members ) );
			}
		}
		$statuses = array();
		if ( !isset( \IPS\Request::i()->following ) OR count( $where ) > 0 )
		{
			$select					= \IPS\core\Statuses\Status::getItemsWithPermission( $where, NULL, array( ( intval( \IPS\Request::i()->statusPage ?: 1 ) - 1 ) * 25, 25 ), 'read', NULL );
			$statuses['count']		= (int) \IPS\Db::i()->select( 'COUNT(*)', 'core_member_status_updates' )->first();

			$statuses['statuses']	= iterator_to_array( $select );
		}
		
		\IPS\Output::i()->title			= \IPS\Member::loggedIn()->language()->addToStack( 'status_updates' );
		
		if ( isset( \IPS\Request::i()->following ) )
		{
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'people_i_follow' ) );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/statuses.css' ) );
		
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/statuses_responsive.css' ) );
		}
		
		if ( \IPS\core\Statuses\Status::canCreate( \IPS\Member::loggedIn() ) AND !isset( \IPS\Request::i()->following ) )
		{
			$form = new \IPS\Helpers\Form( 'new_status', 'status_new' );
			foreach( \IPS\core\Statuses\Status::formElements() AS $k => $element )
			{
				$form->add( $element );
			}
			
			if ( $values = $form->values() )
			{				
				$status = \IPS\core\Statuses\Status::createFromForm( $values );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'profile', 'core', 'front' )->statusContainer( $status ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $status->url() );
				}
			}
			
			$formTpl = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'statusTemplate' ) );
			
			if ( \IPS\core\Statuses\Status::moderateNewItems( \IPS\Member::loggedIn() ) )
			{
				$formTpl = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts ) . $formTpl;
			}
		}
		else
		{
			$formTpl = NULL;
		}

		$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( isset( \IPS\Request::i()->following ) ? \IPS\Http\Url::internal( 'app=core&module=status&controller=feed&following=1', NULL, 'status_following' ) : \IPS\Http\Url::internal( 'app=core&module=status&controller=feed', NULL, 'status' ), ceil( $statuses['count'] / 25 ), \IPS\Request::i()->statusPage ?: 1, 25, TRUE, 'statusPage' ) );

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'status' )->feed( $statuses, $formTpl, $pagination );
	}
}