<?php
/**
 * @brief		Twitter Login Handler
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
 * Twitter Login Handler
 */
class _Twitter extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'twitter';
	
	/**
	 * Get Form
	 *
	 * @param	\IPS\Http\Url	$url	The URL for the login page
	 * @return	string
	 */
	public function loginForm( \IPS\Http\Url $url )
	{	
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->twitter( $url->setQueryString( 'loginProcess', 'twitter' ) );
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
		if ( isset( \IPS\Request::i()->denied ) )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		try
		{
			/* Get a request token */
			if ( !isset( \IPS\Request::i()->oauth_token ) )
			{
				$response = $this->sendRequest( 'get', 'https://api.twitter.com/oauth/request_token', array( 'oauth_callback' => (string) $url->setQueryString( 'loginProcess', 'twitter' ) ) )->decodeQueryString('oauth_token');				
				\IPS\Output::i()->redirect( "https://api.twitter.com/oauth/authenticate?oauth_token={$response['oauth_token']}" );
			}
			
			/* Authenticate */
			$response = $this->sendRequest( 'post', 'https://api.twitter.com/oauth/access_token', array( 'oauth_verifier' => \IPS\Request::i()->oauth_verifier ), \IPS\Request::i()->oauth_token )->decodeQueryString('user_id');

			/* Find or create member */
			$newMember = FALSE;
			if ( $member === NULL )
			{
				/* Load existing */
				$member = \IPS\Member::load( $response['user_id'], 'twitter_id' );

				/* Create one if necessary */
				if ( !$member->member_id )
				{
					$member = new \IPS\Member;
					$member->member_group_id = \IPS\Settings::i()->member_group;
					if ( \IPS\Settings::i()->reg_auth_type == 'admin' or \IPS\Settings::i()->reg_auth_type == 'admin_user' )
					{
						$member->members_bitoptions['validating'] = TRUE;
					}
					
					/* Find name */
					$name = NULL;
					if ( $this->settings['name'] == 'screen' )
					{
						$name = $response['screen_name'];
					}
					elseif ( $this->settings['name'] == 'real' )
					{
						try
						{
							$user = $this->sendRequest( 'get', 'https://api.twitter.com/1.1/account/verify_credentials.json', array(), $response['oauth_token'], $response['oauth_token_secret'] )->decodeJson();
							$name = $user['name'];
						}
						catch ( \IPS\Http\Request\Exception $e ) { }
					}
					
					/* Set name */
					if ( $name !== NULL )
					{
						$existingUsername = \IPS\Member::load( $name, 'name' );
						if ( !$existingUsername->member_id )
						{
							$member->name = $name;
						}
					}
					
					/* Set sync preferences */
					$member->profilesync = json_encode( array( 'facebook' => array( 'photo' => TRUE, 'cover' => TRUE, 'status' => '' ) ) );
					$newMember = TRUE;
				}
			}
			
			/* Set data */
			$member->twitter_id		= $response['user_id'];
			$member->twitter_token	= $response['oauth_token'];
			$member->twitter_secret	= $response['oauth_token_secret'];
			$member->save();
			
			/* Sync */
			if ( $newMember )
			{
				if ( \IPS\Settings::i()->reg_auth_type == 'admin_user' )
				{
					\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => 1 ), array( 'member_id=?', $member->member_id ) );
				}
				
				$sync = new \IPS\core\ProfileSync\Twitter( $member );
				$sync->sync();
			}
			
			/* Return */
			return $member;		
		}
		catch ( \Exception $e )
   		{
	   		throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
   		}
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
				'link'		=> \IPS\Http\Url::ips( 'docs/login_twitter' ),
				'target'	=> '_blank',
				'class'		=> ''
			),
		);
		
		return array(
			'consumer_key'		=> new \IPS\Helpers\Form\Text( 'login_twitter_key', ( isset( $this->settings['consumer_key'] ) ) ? $this->settings['consumer_key'] : '', TRUE ),
			'consumer_secret'	=> new \IPS\Helpers\Form\Text( 'login_twitter_secret', ( isset( $this->settings['consumer_secret'] ) ) ? $this->settings['consumer_secret'] : '', TRUE ),
			'name'				=> new \IPS\Helpers\Form\Radio( 'login_twitter_name', ( isset( $this->settings['name'] ) ) ? $this->settings['name'] : 'any', TRUE, array( 'options' => array(
				'real'		=> 'login_twitter_name_real',
				'screen'	=> 'login_twitter_name_screen',
				'any'		=> 'login_twitter_name_any',
			) ) )
		);	
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\LogicException
	 */
	public function testSettings()
	{
		try
		{
			$response = $this->sendRequest( 'get', 'https://api.twitter.com/oauth/request_token', array( 'oauth_callback' => (string) \IPS\Http\Url::internal( '', 'front' ) ) )->decodeQueryString('oauth_token');
			return TRUE;
		}
		catch ( \Exception $e )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Twitter') ) ) ) );
		}
	}
	
	/**
	 * Send Request
	 *
	 * @param	string	$method			HTTP Method
	 * @param	string	$url			URL
	 * @param	array	$params			Parameters
	 * @param	string	$token			OAuth Token
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\Exception
	 */
	public function sendRequest( $method, $url, $params=array(), $token='', $secret='' )
	{		
		/* Generate the OAUTH Authorization Header */
		$OAuthAuthorization = array_merge( array(
			'oauth_consumer_key'	=> $this->settings['consumer_key'],
			'oauth_nonce'			=> md5( uniqid() ),
			'oauth_signature_method'=> 'HMAC-SHA1',
			'oauth_timestamp'		=> time(),
			'oauth_token'			=> $token,
			'oauth_version'			=> '1.0'
		) );
		
		foreach ( $params as $k => $v )
		{
			if ( mb_substr( $k, 0, 6 ) === 'oauth_' )
			{
				$OAuthAuthorization = array_merge( array( $k => $v ), $OAuthAuthorization );
				unset( $params[ $k ] );
			}
		}

		$signatureBaseString = mb_strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( http_build_query( $OAuthAuthorization ) ) . ( count( $params ) ? ( rawurlencode( '&' ) . rawurlencode( http_build_query( $params, NULL, NULL, PHP_QUERY_RFC3986 ) ) ) : '' );			
		$signingKey = rawurlencode( $this->settings['consumer_secret'] ) . '&' . rawurlencode( $secret ?: $token );			
		$OAuthAuthorizationEncoded = array();
		foreach ( $OAuthAuthorization as $k => $v )
		{
			$OAuthAuthorizationEncoded[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
			
			if ( $k === 'oauth_nonce' )
			{
				$signature = base64_encode( hash_hmac( 'sha1', $signatureBaseString, $signingKey, TRUE ) );
				$OAuthAuthorizationEncoded[] = rawurlencode( 'oauth_signature' ) . '="' . rawurlencode( $signature ) . '"';
			}
		}
		$OAuthAuthorizationHeader = 'OAuth ' . implode( ', ', $OAuthAuthorizationEncoded );

		/* Send the request */
		return \IPS\Http\Url::external( $url )->request()->setHeaders( array( 'Authorization' => $OAuthAuthorizationHeader ) )->$method( $params );
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