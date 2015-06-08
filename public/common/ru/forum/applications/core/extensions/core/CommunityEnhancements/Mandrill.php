<?php
/**
 * @brief		Community Enhancements: Mandrill integration
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 June 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancements: Mandrill integration
 */
class _Mandrill
{
	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= FALSE;

	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "mandrill.png";
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->mandrill_api_key && \IPS\Settings::i()->mandrill_use_for );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'mandrill_use_for', \IPS\Settings::i()->mandrill_use_for, TRUE, array(
					'options'	=> array(
										'0'	=> 'mandrill_donot_use',
										'1'	=> 'mandrill_bulkmail_use',
										'2'	=> 'mandrill_all_use'
										),
					'toggles'	=> array(
										'0'	=> array(),
										'1'	=> array('mandrill_api_key','mandrill_username'),
										'2'	=> array('mandrill_api_key','mandrill_username'),
										)
				) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'mandrill_username', \IPS\Settings::i()->mandrill_username, FALSE, array(), NULL, NULL, NULL, 'mandrill_username' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'mandrill_api_key', \IPS\Settings::i()->mandrill_api_key, FALSE, array(), NULL, NULL, NULL, 'mandrill_api_key' ) );

		if ( $form->values() )
		{
			try
			{
				$this->testSettings( $form->values() );
			}
			catch ( \DomainException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '4S123/1', 500 );
			}
			catch ( \InvalidArgumentException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '', 500 );	//@todo check errorcode
			}

			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__enhancements_edited', array( 'enhancements__core_Mandrill' => TRUE ) );
			\IPS\Output::i()->inlineMessage	= \IPS\Member::loggedIn()->language()->addToStack('saved');
		}
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'		=> array(
				'title'		=> 'learn_more',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/mandrill' ),
				'target'	=> '_blank'
			),
			'signup'	=> array(
				'title'		=> 'mandril_signup',
				'icon'		=> 'external-link-square',
				'link'		=> \IPS\Http\Url::ips( 'docs/mandrill_signup' ),
				'target'	=> '_blank'
			),
		);
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 * @throws	\DomainException
	 */
	public function toggle( $enabled )
	{
		/* If we're disabling, just disable */
		if( !$enabled )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => 0 ), array( 'conf_key=?', 'mandrill_use_for' ) );
			unset( \IPS\Data\Store::i()->settings );
		}

		/* Otherwise if we already have an API key, just toggle bulk mail on */
		if( $enabled && \IPS\Settings::i()->mandrill_api_key )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => 1 ), array( 'conf_key=?', 'mandrill_use_for' ) );
			unset( \IPS\Data\Store::i()->settings );
		}
		else
		{
			/* Otherwise we need to let them enter an API key before we can enable.  Throwing an exception causes you to be redirected to the settings page. */
			throw new \DomainException;
		}
	}
	
	/**
	 * Test Settings
	 *
	 * @param	array 	$values	Form values
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function testSettings( $values )
	{
		/* If we've disabled, just shut off */
		if( (int) $values['mandrill_use_for'] === 0 )
		{
			if( \IPS\Settings::i()->smtp_host == 'smtp.mandrillapp.com' && \IPS\Settings::i()->mail_method == 'smtp' )
			{
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => 'mail' ), array( 'conf_key=?', 'mail_method' ) );
				unset( \IPS\Data\Store::i()->settings );
			}

			return;
		}

		/* If we enable Mandrill but do not supply an API key or username, this is a problem */
		if( !$values['mandrill_username'] OR !$values['mandrill_api_key'] )
		{
			throw new \InvalidArgumentException( "mandrill_enable_need_details" );
		}

		/* Test Mandrill settings */
		try
		{
			$info = \IPS\Email::mandrill( 'users_info', $values['mandrill_api_key'] );
			if ( $info === NULL or $info->username != $values['mandrill_username'] )
			{
				throw new \DomainException('mandrill_bad_credentials');
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \DomainException( 'mandrill_bad_credentials' );
		}
		
		/* Tell IPS */
		$response = NULL;
		try
		{
			$response = \IPS\Http\Url::ips( 'mandrill' )->request()->post( array(
				'key'		=> $values['mandrill_api_key'],
				'username'	=> $values['mandrill_username'],
				'lkey'		=> \IPS\Settings::i()->ipb_reg_number,
				'version'	=> \IPS\Application::load('core')->long_version
			) )->decodeJson();			
		}
		catch ( \IPS\Http\Request\Exception $e ) { }
			
		/* If we want to use Mandrill for "everything", we need to update our local SMTP settings with the values returned from the API call */
		if( $values['mandrill_use_for'] == 2 )
		{
			foreach( $response as $settingKey => $settingValue )
			{
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $settingValue ), array( 'conf_key=?', $settingKey ) );
				unset( \IPS\Data\Store::i()->settings );
			}
		}
		/* Else if we don't want to use Mandrill for everything but we're currently configured to, update our email settings to fall back to standard PHP mail */
		else if( $values['mandrill_use_for'] == 1 && \IPS\Settings::i()->smtp_host == 'smtp.mandrillapp.com' && \IPS\Settings::i()->mail_method == 'smtp' )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => 'mail' ), array( 'conf_key=?', 'mail_method' ) );
			unset( \IPS\Data\Store::i()->settings );
		}
	}
}