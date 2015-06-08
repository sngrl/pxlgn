<?php
/**
 * @brief		Facebook Login Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Mar 2013
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
 * Facebook Login Handler
 */
class _Facebook extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'facebook-square';
	
	/**
	 * Get Form
	 *
	 * @param	\IPS\Http\Url	$url	The URL for the login page
	 * @return	string
	 */
	public function loginForm( $url, $ucp=FALSE )
	{
		$url = \IPS\Http\Url::internal( 'applications/core/interface/facebook/auth.php', 'none' );
		
		if ( $ucp )
		{
			$state = "ucp-" . \IPS\Session::i()->csrfKey;
		}
		else
		{
			$state = \IPS\Dispatcher::i()->controllerLocation . "-" . \IPS\Session::i()->csrfKey;
		}

		$scope = 'email,share_item';

		if ( \IPS\Settings::i()->profile_comments )
		{
			$scope .= ',user_status';
		}
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->facebook( "https://www.facebook.com/dialog/oauth?client_id={$this->settings['app_id']}&amp;scope={$scope}&amp;redirect_uri=".urlencode( $url ) . "&amp;state={$state}"  );
	}
	
	/**
	 * Authenticate
	 *
	 * @param	string			$url	The URL for the login page
	 * @param	\IPS\Member		$member	If we want to integrate this login method with an existing member, provide the member object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( $url, $member=NULL )
	{
		$url = $url->setQueryString( 'loginProcess', 'facebook' );
		
		try
		{
			/* CSRF Check */
			if ( \IPS\Request::i()->state !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Get a token */
			try
			{
				$response = \IPS\Http\Url::external( "https://graph.facebook.com/oauth/access_token" )->request()->post( array(
					'client_id'		=> $this->settings['app_id'],
					'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/facebook/auth.php', 'none' ),
					'client_secret'	=> $this->settings['app_secret'],
					'code'			=> \IPS\Request::i()->code
				) )->decodeQueryString('access_token');
			}
			catch( \RuntimeException $e )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Now exchange it for a one that will last a bit longer in case the user wants to use syncing */
			try
			{
				$response = \IPS\Http\Url::external( "https://graph.facebook.com/oauth/access_token" )->request()->post( array(
					'grant_type'		=> 'fb_exchange_token',
					'client_id'			=> $this->settings['app_id'],
					'client_secret'		=> $this->settings['app_secret'],
					'fb_exchange_token'	=> $response['access_token']
				) )->decodeQueryString('access_token');				
			}
			catch( \RuntimeException $e )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Get the user data */
			$userData = \IPS\Http\Url::external( "https://graph.facebook.com/me?access_token={$response['access_token']}" )->request()->get()->decodeJson();
			
   			/* Find or create member */
   			$newMember = FALSE;
   			if ( $member === NULL )
   			{
				$member = \IPS\Member::load( $userData['id'], 'fb_uid' );
				if ( !$member->member_id )
				{
					$existingEmail = \IPS\Member::load( $userData['email'], 'email' );
					if ( $existingEmail->member_id )
					{
						$exception = new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT );
						$exception->handler = 'facebook';
						$exception->member = $existingEmail;
						$exception->details = $response['access_token'];
						throw $exception;
					}
					
					$member = new \IPS\Member;
					if ( \IPS\Settings::i()->reg_auth_type == 'admin' or \IPS\Settings::i()->reg_auth_type == 'admin_user' )
					{
						$member->members_bitoptions['validating'] = TRUE;
					}
					$member->member_group_id = \IPS\Settings::i()->member_group;
					$member->email = (string) $userData['email'];
					if ( $this->settings['real_name'] )
					{
						$existingUsername = \IPS\Member::load( $userData['name'], 'name' );
						
						if ( !$existingUsername->member_id )
						{
							$member->name = $userData['name'];
						}
					}
					$member->profilesync = json_encode( array( 'facebook' => array( 'photo' => TRUE, 'cover' => TRUE, 'status' => '' ) ) );
					$newMember = TRUE;
				}
			}
						
			/* Update details */
			$member->fb_uid = $userData['id'];
			$member->fb_token = $response['access_token'];
			$member->save();
			
			/* Sync */
			if ( $newMember )
			{
				if ( \IPS\Settings::i()->reg_auth_type == 'admin_user' )
				{
					\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => 1 ), array( 'member_id=?', $member->member_id ) );
				}
				
				$sync = new \IPS\core\ProfileSync\Facebook( $member );
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
		$userData = \IPS\Http\Url::external( "https://graph.facebook.com/me?access_token={$details}" )->request()->get()->decodeJson();
		$member->fb_uid = $userData['id'];
		$member->fb_token = $details;
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
				'link'		=> \IPS\Http\Url::ips( 'docs/login_facebook' ),
				'target'	=> '_blank',
				'class'		=> ''
			),
		);
		
		return array(
			'app_id'		=> new \IPS\Helpers\Form\Text( 'login_facebook_app', ( isset( $this->settings['app_id'] ) ) ? $this->settings['app_id'] : '', TRUE ),
			'app_secret'	=> new \IPS\Helpers\Form\Text( 'login_facebook_secret', ( isset( $this->settings['app_secret'] ) ) ? $this->settings['app_secret'] : '', TRUE ),
			'real_name'		=> new \IPS\Helpers\Form\YesNo( 'login_real_name', ( isset( $this->settings['real_name'] ) ) ? $this->settings['real_name'] : FALSE, TRUE )
		);
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings()
	{
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