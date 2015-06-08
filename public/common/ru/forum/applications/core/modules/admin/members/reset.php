<?php
/**
 * @brief		Recount and Reset Tools
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		24 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Recount and Reset Tools
 */
class _reset extends \IPS\Dispatcher\Controller
{	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_recount_content' );
		return parent::execute();
	}
	
	/**
	 * Redirector to rebuild things
	 * 
	 */
	public function __call( $method, $args )
	{
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'app=core&module=members&controller=reset&do=redirector&member_recount_each_content_check=' . intval( $method == 'posts' ) . '&member_recount_each_reputation_check=' . intval( $method == 'rep' ) ),
			function( $data )
			{
				/* Is this the first cycle? */
				if ( ! is_array( $data ) )
				{
					/* Start importing */
					$data = array(
							'member_recount_each_content_check'    => \IPS\Request::i()->member_recount_each_content_check,
							'member_recount_each_reputation_check' => \IPS\Request::i()->member_recount_each_reputation_check,
							'lastId' => 0,
							'processing' => true,
							'total'	=> \IPS\Db::i()->select( 'COUNT(*)', 'core_members' )->first(),
							'done' => 0
					);
						
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
	
				/* Grab something to build */
				if ( $data['processing'] )
				{
					try
					{
						$member_id = \IPS\Db::i()->select( 'member_id', 'core_members', array( 'member_id > ?', intval( $data['lastId'] ) ), 'member_id ASC', array( 0, 1 ) )->first();
						
						if ( ! empty( $member_id ) )
						{
							$member = \IPS\Member::load( $member_id );
							if ( $data['member_recount_each_content_check'] )
							{
								$member->recountContent();
							}
							
							if ( $data['member_recount_each_reputation_check'] )
							{
								$member->recountReputation();
							}
							
							$data['lastId'] = $member_id;
							$data['done']++;
						}
						else
						{
							$data['lastId']     = 0;
							$data['processing'] = false;
						}
						
						return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing'), 100 / $data['total'] * $data['done'] );
					}
					catch( \UnderflowException $ex )
					{
						return null;
					}
				}
				else
				{
					/* All Done.. */
					return null;
				}
			},
			function()
			{
				/* Finished */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members' ), 'completed' );
			}
		);
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('member_reset_recount_title');
		
		/* Output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			\IPS\Output::i()->buildMetaTags();
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		
		return;
	}

}