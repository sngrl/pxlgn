<?php
/**
 * @brief		Staff directory
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		19 Sep 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Staff directory
 */
class _directory extends \IPS\Dispatcher\Controller
{
	/**
	 * Main execute entry point - used to override breadcrumb
	 *
	 * @return void
	 */
	public function execute()
	{
		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=core&module=members&controller=directory', 'front', 'staffdirectory' ), \IPS\Member::loggedIn()->language()->addToStack('staff') );

		parent::execute();
	}

	/**
	 * Show staff directory
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$groups = \IPS\core\StaffDirectory\Group::roots();

		try
		{
			\IPS\core\StaffDirectory\User::load( \IPS\Member::loggedIn()->member_id, 'leader_type_id', array( 'leader_type=?', 'm' ) );
			$userIsStaff	= TRUE;
		}
		catch( \OutOfRangeException $e )
		{
			$userIsStaff	= FALSE;
		}

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/staff_responsive.css' ) );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('staff_directory');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'staffdirectory' )->template( $groups, $userIsStaff );
	}

	/**
	 * Edit your own information
	 *
	 * @return	void
	 */
	protected function form()
	{
		try
		{
			$user = \IPS\core\StaffDirectory\User::load( \IPS\Member::loggedIn()->member_id, 'leader_type_id', array( 'leader_type=?', 'm' ) );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C156/1', 404, '' );
		}

		$form	= new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'leader_custom_name', ( \IPS\Member::loggedIn()->language()->checkKeyExists("core_staff_directory_name_{$user->id}") ) ? \IPS\Member::loggedIn()->language()->addToStack("core_staff_directory_name_{$user->id}") : \IPS\Member::loggedIn()->name, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'leader_custom_title', ( \IPS\Member::loggedIn()->language()->checkKeyExists("core_staff_directory_title_{$user->id}") ) ? \IPS\Member::loggedIn()->language()->addToStack("core_staff_directory_title_{$user->id}") : \IPS\Member::loggedIn()->member_title, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'leader_custom_bio', \IPS\Member::loggedIn()->language()->addToStack("core_staff_directory_bio_{$user->id}"), FALSE, array(
				'app'			=> 'core',
				'key'			=> 'Staffdirectory',
				'autoSaveKey'	=> 'leader-' . $user->id,
				'attachIds'		=> array( $user->id )
		) ) );

		if( $values = $form->values() )
		{
			/* Save */
			\IPS\Lang::saveCustom( 'core', "core_staff_directory_name_{$user->id}", $values['leader_custom_name'] );
			\IPS\Lang::saveCustom( 'core', "core_staff_directory_title_{$user->id}", $values['leader_custom_title'] );
			\IPS\Lang::saveCustom( 'core', "core_staff_directory_bio_{$user->id}", $values['leader_custom_bio'] );

			//$user->save();

			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=directory", 'front', 'staffdirectory' ), 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('leader_edit_mine');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->genericBlock( $form, '', 'ipsPad' );
	}
}