<?php
/**
 * @brief		LinkedIn Login Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Login;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * LinkedIn Login Handler
 */
class _Linkedin extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'linkedin';
	
	/**
	 * Get Form
	 *
	 * @param	string	$url	The URL for the login page
	 * @param	bool	$ucp	Is UCP? (as opposed to login form)
	 * @return	string
	 */
	public function loginForm( $url, $ucp=FALSE )
	{			
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->linkedin( (string) \IPS\Http\Url::external( "https://www.linkedin.com/uas/oauth2/authorization" )->setQueryString( array(
			'response_type'	=> 'code',
			'client_id'		=> $this->settings['api_key'],
			'scope'			=> 'r_basicprofile r_emailaddress rw_nus',
			'state'			=> ( $ucp ? 'ucp' : \IPS\Dispatcher::i()->controllerLocation ),
			'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/linkedin/auth.php', 'none' ),
		) ) );
	}
	
	/**
	 * Authenticate
	 *
	 * @param	string	$url	The URL for the login page
	 * @param	\IPS\Member		$member	If we want to integrate this login method with an existing member, provide the member object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( $url, $member=NULL )
	{
		try
		{									
			/* Get a token */
			$response = \IPS\Http\Url::external( "https://www.linkedin.com/uas/oauth2/accessToken?" . http_build_query( array(
				'grant_type'	=> 'authorization_code',
				'code'			=> \IPS\Request::i()->code,
				'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/linkedin/auth.php', 'none' ),
				'client_id'		=> $this->settings['api_key'],
				'client_secret'	=> $this->settings['secret_key'],
			) ) )->request()->post()->decodeJson();

			if ( isset( $response['error'] ) or !isset( $response['access_token'] ) )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Get user data */
			$userData = array();
			foreach ( \IPS\Http\Url::external( "https://api.linkedin.com/v1/people/~:(id,formatted-name,email-address)?oauth2_access_token={$response['access_token']}" )->request()->get()->decodeXml() as $k => $v )
			{
				$userData[ $k ] = (string) $v;
			}
			
			/* Find or create member */
			$newMember = FALSE;
			if ( $member === NULL )
			{
				$member = \IPS\Member::load( $userData['id'], 'linkedin_id' );
				if ( !$member->member_id )
				{
					$existingEmail = \IPS\Member::load( $userData['email-address'], 'email' );
					if ( $existingEmail->member_id )
					{
						$exception = new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT );
						$exception->handler = 'linkedin';
						$exception->member = $existingEmail;
						$exception->details = $response['access_token'];
						throw $exception;
					}
					
					$member = new \IPS\Member;
					$member->member_group_id = \IPS\Settings::i()->member_group;
					if ( \IPS\Settings::i()->reg_auth_type == 'admin' or \IPS\Settings::i()->reg_auth_type == 'admin_user' )
					{
						$member->members_bitoptions['validating'] = TRUE;
					}

					if ( $this->settings['real_name'] )
					{
						$existingUsername = \IPS\Member::load( $userData['formatted-name'], 'name' );
						if ( !$existingUsername->member_id )
						{
							$member->name = $userData['formatted-name'];
						}
					}
					
					$member->email = $userData['email-address'];
					$member->profilesync = json_encode( array( 'linkedin' => array( 'photo' => TRUE ) ) );
					$newMember = TRUE;
				}
			}
			
			/* Update details */
			$member->linkedin_id = $userData['id'];
			$member->linkedin_token = $response['access_token'];
			$member->save();
			
			/* Sync */
			if ( $newMember )
			{
				if ( \IPS\Settings::i()->reg_auth_type == 'admin_user' )
				{
					\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => 1 ), array( 'member_id=?', $member->member_id ) );
				}
				
				$sync = new \IPS\core\ProfileSync\LinkedIn( $member );
				$sync->sync();
			}
			
			/* Return */
			return $member;
		}
		catch ( \IPS\Http\Request\Exception $e )
   		{
	   		throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
   		}
	}
	
	/**
	 * Link Account
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	mixed		$details	Details as they were passed to the exception thrown in authenticate()
	 * @return	void
	 */
	public static function link( \IPS\Member $member, $details )
	{
		$userData = array();
		foreach ( \IPS\Http\Url::external( "https://api.linkedin.com/v1/people/~:(id,formatted-name,email-address)?oauth2_access_token={$details}" )->request()->get()->decodeXml() as $k => $v )
		{
			$userData[ $k ] = (string) $v;
		}

		$member->linkedin_id = $userData['id'];
		$member->linkedin_token = $details;
		$member->save();
	}
	
	/**
	 * ACP Settings Form
	 *
	 * @param	string	$url	URL to redirect user to after successful submission
	 * @return	array	List of settings to save - settings will be stored to core_login_handlers.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'help',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/login_linkedin' ),
				'target'	=> '_blank',
				'class'		=> ''
			),
		);
		
		return array(
			'api_key'		=> new \IPS\Helpers\Form\Text( 'login_linkedin_key', ( isset( $this->settings['api_key'] ) ) ? $this->settings['api_key'] : '', TRUE ),
			'secret_key'	=> new \IPS\Helpers\Form\Text( 'login_linkedin_secret', ( isset( $this->settings['secret_key'] ) ) ? $this->settings['secret_key'] : '', TRUE ),
			'real_name'		=> new \IPS\Helpers\Form\YesNo( 'login_real_name', ( isset( $this->settings['real_name'] ) ) ? $this->settings['real_name'] : FALSE, TRUE )
		);
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\UnexpectedValueException	If response code is not 200
	 */
	public function testSettings()
	{
		try
		{			
			$response = \IPS\Http\Url::external( "https://www.linkedin.com/uas/oauth2/authorization" )->setQueryString( array(
				'response_type'	=> 'code',
				'client_id'		=> $this->settings['api_key'],
				'scope'			=> 'r_basicprofile r_emailaddress',
				'state'			=> \IPS\Session::i()->csrfKey,
				'redirect_uri'	=> \IPS\Settings::i()->base_url
			) )->request()->get();
			
			if ( $response->httpResponseCode != 200 )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Linkedin') ) ) ) );
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Linkedin') ) ) ) );
		}
		return TRUE;
	}
	
	/**
	 * Can a member change their email/password with this login handler?
	 *
	 * @param	string		$type	'email' or 'password'
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function canChange( $type, \IPS\Member $member )
	{
		return FALSE;
	}
}