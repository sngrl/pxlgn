<?php
/**
 * @brief		Google Login Handler
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
 * Google Login Handler
 */
class _Google extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'google-plus';
	
	/**
	 * Get Form
	 *
	 * @param	string	$url	The URL for the login page
	 * @param	bool	$ucp	Is UCP? (as opposed to login form)
	 * @return	string
	 */
	public function loginForm( $url, $ucp=FALSE )
	{
		$params = array(
			'response_type'	=> 'code',
			'client_id'		=> $this->settings['client_id'],
			'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/google/auth.php', 'none' ),
			'scope'			=> 'https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
			'access_type'	=> 'offline',
			'state'			=> ( $ucp ? 'ucp' : \IPS\Dispatcher::i()->controllerLocation ) . '-' . \IPS\Session::i()->csrfKey
		);
		
		if ( $ucp )
		{
			$params['approval_prompt'] = 'force';
		}

		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->google( (string) \IPS\Http\Url::external( "https://accounts.google.com/o/oauth2/auth" )->setQueryString( $params ) );
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
			/* CSRF Check */
			if ( \IPS\Request::i()->state !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Get a token */
			$response = \IPS\Http\Url::external( "https://accounts.google.com/o/oauth2/token" )->request()->post( array(
				'code'			=> \IPS\Request::i()->code,
				'client_id'		=> $this->settings['client_id'],
				'client_secret'	=> $this->settings['client_secret'],
				'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/google/auth.php', 'none' ),
				'grant_type'	=> 'authorization_code',
			) )->decodeJson();
						
			if ( isset( $response['error'] ) or !isset( $response['access_token'] ) )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
						
			/* Get user data */
			$userData = \IPS\Http\Url::external( "https://www.googleapis.com/plus/v1/people/me?access_token={$response['access_token']}" )->request()->get()->decodeJson('id');
			$email = $userData = \IPS\Http\Url::external( "https://www.googleapis.com/oauth2/v1/userinfo?access_token={$response['access_token']}" )->request()->get()->decodeJson('email');
									
			/* Find  member */
			$newMember = FALSE;
			if ( $member === NULL )
			{
				$member = \IPS\Member::load( $userData['id'], 'google_id' );
				if ( !$member->member_id )
				{
					$existingEmail = \IPS\Member::load( $userData['email'], 'email' );
					if ( $existingEmail->member_id )
					{
						$exception = new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT );
						$exception->handler = 'google';
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
						$existingUsername = \IPS\Member::load( $userData['name'], 'name' );
						if ( !$existingUsername->member_id )
						{
							$member->name = $userData['name'];
						}
					}
					
					$member->email = $userData['email'];
					$member->profilesync = json_encode( array( 'google' => array( 'photo' => TRUE, 'cover' => TRUE, 'status' => '' ) ) );
					$newMember = TRUE;
				}
			}
			
			/* Create member */
			$member->google_id = $userData['id'];
			$member->google_token = $response['access_token'];
			$member->save();
			
			/* Sync */
			if ( $newMember )
			{
				if ( \IPS\Settings::i()->reg_auth_type == 'admin_user' )
				{
					\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => 1 ), array( 'member_id=?', $member->member_id ) );
				}
				
				$sync = new \IPS\core\ProfileSync\Google( $member );
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
		$userData = \IPS\Http\Url::external( "https://www.googleapis.com/plus/v1/people/me?access_token={$details}" )->request()->get()->decodeJson('id');
		$member->google_id = $userData['id'];
		$member->google_token = $details;
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
				'link'		=> \IPS\Http\Url::ips( 'docs/login_google' ),
				'target'	=> '_blank',
				'class'		=> ''
			),
		);
		
		return array(
			'client_id'		=> new \IPS\Helpers\Form\Text( 'login_google_id', ( isset( $this->settings['client_id'] ) ) ? $this->settings['client_id'] : '', TRUE ),
			'client_secret'	=> new \IPS\Helpers\Form\Text( 'login_google_secret', ( isset( $this->settings['client_secret'] ) ) ? $this->settings['client_secret'] : '', TRUE ),
			'real_name'		=> new \IPS\Helpers\Form\YesNo( 'login_real_name', ( isset( $this->settings['real_name'] ) ) ? $this->settings['real_name'] : FALSE, TRUE )
		);
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\IPS\Http\Request\Exception
	 * @throws	\UnexpectedValueException	If response code is not 302
	 */
	public function testSettings()
	{
		try
		{
			$response = \IPS\Http\Url::external( "https://accounts.google.com/o/oauth2/auth" )->setQueryString( array(
				'response_type'	=> 'code',
				'client_id'		=> $this->settings['client_id'],
				'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/google/auth.php', 'none' ),
				'scope'			=> 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
				'state'			=> 'admin-' . \IPS\Session::i()->csrfKey
			) )->request()->get();		
				
			if ( $response->httpResponseCode != 200 )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Google') ) ) ) );
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Google') ) ) ) );
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