<?php
/**
 * @brief		general
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * general
 */
class _general extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'general_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'board_name', \IPS\Settings::i()->board_name, TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'site_online', \IPS\Settings::i()->site_online, FALSE, array(
			'togglesOff'	=> array( 'site_offline_message_id' ),
		) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'site_offline_message', \IPS\Settings::i()->site_offline_message, FALSE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'onlineoffline', 'attachIds' => array( NULL, NULL, 'site_offline_message' ) ), NULL, NULL, NULL, 'site_offline_message_id' ) );
		$form->add( new \IPS\Helpers\Form\Address( 'site_address', \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->site_address ), FALSE ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'copyright_line', NULL, FALSE, array( 'app' => 'core', 'key' => 'copyright_line_value', 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('copyright_line_placeholder') ) ) );
		
		$form->addHeader( 'home_link' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'show_home_link', \IPS\Settings::i()->show_home_link, FALSE, array(
				'togglesOn'	=> array( 'home_name', 'home_url' ),
		) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'home_name', NULL, FALSE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('home_name_placeholder'), 'app' => 'core', 'key' => 'home_name_value' ), NULL, NULL, NULL, 'home_name' ) );
		$form->add( new \IPS\Helpers\Form\Url( 'home_url', \IPS\Settings::i()->home_url, FALSE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('home_url_placeholder') ), NULL, NULL, NULL, 'home_url' ) );
		
		if ( $values = $form->values() )
		{
			\IPS\Lang::saveCustom( 'core', "copyright_line_value", $values['copyright_line'] );
			\IPS\Lang::saveCustom( 'core', "home_name_value", $values['home_name'] );
			unset( $values['copyright_line'], $values['home_name'] );
			
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplogs__general_settings' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_general');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_settings_general', $form );
	}
}