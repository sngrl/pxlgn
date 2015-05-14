<?php
/**
 * @brief		IPS Connect Login Handler
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
 * IPS Connect Login Handler
 */
class _Ipsconnect extends LoginAbstract
{
	/**
	 * @brief	Authentication types
	 */
	public $authTypes;

	/**
	 * Initiate
	 *
	 * @return	void
	 */
	public function init()
	{
		$this->authTypes = ( !empty( $this->settings['auth_types'] ) ) ? $this->settings['auth_types'] : \IPS\Login::AUTH_TYPE_USERNAME;
	}

	/**
	 * Authenticate
	 *
	 * @param	array	$values	Values from form
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( $values )
	{
		try
		{
			/* Are we being returned from a cross-domain login? */
			if( \IPS\Request::i()->loginProcess == 'ipsconnect' AND \IPS\Request::i()->id AND \IPS\Request::i()->key )
			{
				$member	= \IPS\Member::load( \IPS\Request::i()->id, 'ipsconnect_id' );

				if( $member->member_id AND \IPS\Login::compareHashes( \IPS\Request::i()->key, md5( $this->settings['key'] . $member->ipsconnect_id ) ) )
				{
					return $member;
				}
			}

			/* First, we need to fetch the user's salt to prevent sending password in plaintext */
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'fetchSalt', 'key' => md5( $this->settings['key'] . $values['auth'] ), 'idType' => $this->authTypes, 'id' => $values['auth'] ) )
				->request()
				->get()
				->decodeJson();

			/* No response or status is not 'SUCCESS' */
			if ( empty( $response ) OR empty( $response['status'] ) OR $response['status'] != 'SUCCESS' OR !$response['pass_salt'] )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}

			/* Now create a dummy user to get password hash */
			$testUser	= new \IPS\Member;
			$testUser->members_pass_salt	= $response['pass_salt'];
			$password	= $testUser->encryptedPassword( $values['password'] );

			/* Now try to login */
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'login', 'key' => md5( $this->settings['key'] . $values['auth'] ), 'idType' => $this->authTypes, 'id' => $values['auth'], 'password' => $password ) )
				->request()
				->get()
				->decodeJson();

			/* No response or status is not 'SUCCESS' */
			if ( empty( $response ) OR empty( $response['status'] ) OR $response['status'] != 'SUCCESS' )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}

			/* No connect status means account not found */
			if( empty( $response['connect_status'] ) )
			{
				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack('login_err_no_account', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $this->getLoginType( $this->authTypes ) ) ) ) ), \IPS\Login\Exception::NO_ACCOUNT );
			}

			/* Load the local account now, or create it if it does not exist */
			$member	= \IPS\Member::load( $response['connect_id'], 'ipsconnect_id' );

			if( !$member->member_id )
			{
				$member	= \IPS\Member::load( $response['email'], 'email' );
			}

			/* Update any changes on existing accounts */
			if( $member->member_id )
			{
				if( $response['connect_id'] != $member->ipsconnect_id )
				{
					$member->ipsconnect_id	= $response['connect_id'];
				}

				if( $response['email'] != $member->email )
				{
					$member->email	= $response['email'];
				}

				if( $response['name'] != $member->name AND \IPS\CONNECT_NOSYNC_NAMES === FALSE )
				{
					$member->name	= $response['name'];
				}

				$member->save();
			}
			/* Otherwise create the account if it does not exist yet */
			else
			{
				$member->member_group_id	= \IPS\Settings::i()->member_group;
				$member->ipsconnect_id		= $response['connect_id'];
				$member->name				= $response['name'];
				$member->email				= $response['email'];
				$member->members_pass_salt	= $testUser->members_pass_salt;
				$member->members_pass_hash	= $password;
				$member->save();
			}

			/* Do we have any custom callbacks we need to call? */
			$this->connectSuccessful( $member );

			/* Are we validating? */
			if( $response['connect_status'] == 'VALIDATING' )
			{
				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack('login_err_remote_validate', FALSE, array( 'sprintf' => array( $response['connect_revalidate_url'] ) ) ) );
			}

			/* If we are still here and successful, redirect to master to log us in to each installation */
			$url = \IPS\Http\Url::internal( '' );
			\IPS\Request::i()->ref		= (string) \IPS\Http\Url::external( $this->settings['url'] )->setQueryString( 
				array( 
					'do'		=> 'crossLogin', 
					'key'		=> md5( $this->settings['key'] . $member->ipsconnect_id ), 
					'url'		=> \IPS\Settings::i()->base_url, 
					'id'		=> $member->ipsconnect_id, 
					'returnTo'	=> (string) $url->setQueryString( 
						array( 
							'loginProcess'	=> 'ipsconnect', 
							'key'			=> md5( $this->settings['key'] . $member->ipsconnect_id ), 
							'id'			=> $member->ipsconnect_id, 
							'ref'			=> \IPS\Login::getDestination() 
						)
					)
				)
			);

			return $member;
		}
		/* Have to catch \RuntimeException to catch the BAD_JSON throw from Response.php */
		catch ( \RuntimeException $e )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}

		/* If we are still here, throw a generic bad password exception */
		throw new \IPS\Login\Exception( 'login_err_bad_password', \IPS\Login\Exception::BAD_PASSWORD );
	}

	/**
	 * Generic method called upon a successful login - useful for plugins to do "something" when login is successful
	 *
	 * @param	\IPS\Member	$member		Member that is logged in
	 * @return	void
	 * @note	By default this method does nothing, but is abstracted to allow easy extending
	 */
	public function connectSuccessful( \IPS\Member $member )
	{
		return;
	}

	/**
	 * Fetch the URL to redirect to following registration, if any
	 *
	 * @param	\IPS\Member	$member		The member that just registered
	 * @return	\IPS\Http\Url
	 */
	public function getRegistrationDestination( $member )
	{
		return \IPS\Http\Url::external( $this->settings['url'] )->setQueryString( array( 'do' => 'crossLogin', 'key' => md5( $this->settings['key'] . $member->ipsconnect_id ), 'url' => \IPS\Settings::i()->base_url, 'id' => $member->ipsconnect_id, 'returnTo' => (string) \IPS\Http\Url::internal( '', 'front' ) ) );
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
		return array(
			'url'			=>  new \IPS\Helpers\Form\Text( 'login_ipsconnect_url', $this->settings['url'], TRUE ),
			'key'			=>  new \IPS\Helpers\Form\Text( 'login_ipsconnect_key', $this->settings['key'], TRUE ),
			'auth_types'	=> new \IPS\Helpers\Form\Select( 'login_auth_types', isset( $this->settings['auth_types'] ) ? $this->settings['auth_types'] : \IPS\Login::AUTH_TYPE_USERNAME, TRUE, array( 'options' => array(
				\IPS\Login::AUTH_TYPE_USERNAME => 'username',
				\IPS\Login::AUTH_TYPE_EMAIL	=> 'email_address',
				\IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => 'username_or_email',
			) ) )
		);
	}
	
	/**
	 * Can a member change their email/password with this login handler?
	 *
	 * @param	string		$type	'username' or 'email' or 'password'
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function canChange( $type, \IPS\Member $member )
	{
		/* We can only change if they logged in with IPS Connect */
		return (bool) $member->ipsconnect_id;
	}

	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings()
	{
		if( !$this->settings['url'] or $this->settings['url'] == (string) \IPS\Http\Url::internal( 'applications/core/interface/ipsconnect/ipsconnect.php', 'none' ) )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_error_ipsconnect_self') );
		}

		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'verifySettings', 'key' => $this->settings['key'], 'url' => \IPS\Settings::i()->base_url, 'ourKey' => md5( md5( \IPS\Settings::i()->sql_user . \IPS\Settings::i()->sql_pass ) . \IPS\Settings::i()->board_start ) ) )
				->request()
				->get()
				->decodeJson();

			if ( empty( $response ) OR empty( $response['status'] ) OR $response['status'] != 'SUCCESS' )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Ipsconnect') ) ) ) );
			}
		}
		/* Have to catch \RuntimeException to catch the BAD_JSON throw from Response.php */
		catch ( \RuntimeException $e )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('login_3p_bad', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('login_handler_Ipsconnect') ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Retrieve the user's associated IPS Connect ID
	 *
	 * @param	\IPS\Member	$member		The member
	 * @return	int|NULL	The IPS Connect ID or NULL if none found
	 */
	protected function _getConnectId( \IPS\Member $member )
	{
		/* Already have it? */
		if( $member->ipsconnect_id )
		{
			return $member->ipsconnect_id;
		}

		/* Fetch it from the master */
		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'login', 'key' => md5( $this->settings['key'] . $member->email ), 'id' => $member->email, 'idType' => 'email', 'fetchId' => 1 ) )
				->request()
				->get()
				->decodeJson();

			if ( !empty( $response ) AND !empty( $response['status'] ) AND $response['status'] == 'SUCCESS' AND $response['connect_id'] )
			{
				$member->ipsconnect_id	= $response['connect_id'];
				$member->save();
			}
		}
		/* Have to catch \RuntimeException to catch the BAD_JSON throw from Response.php */
		catch ( \RuntimeException $e )
		{
		}

		return NULL;
	}
	
	/**
	 * Email is in use?
	 * Used when registering or changing an email address to check the new one is available
	 *
	 * @param	string				$email		Email Address
	 * @param	\IPS\Member|NULL	$exclude	Member to exclude
	 * @return	bool|NULL Boolean indicates if email is in use (TRUE means is in use and thus not registerable) or NULL if this handler does not support such an API
	 */
	public function emailIsInUse( $email, \IPS\Member $exclude=NULL )
	{
		if( $exclude !== NULL AND $exclude instanceof \IPS\Member AND $email == $exclude->email )
		{
			return FALSE;
		}

		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'checkEmail', 'key' => $this->settings['key'], 'email' => $email ) )
				->request()
				->get()
				->decodeJson();

			if ( !empty( $response ) AND !empty( $response['status'] ) AND $response['status'] == 'SUCCESS' AND $response['used'] != 0 )
			{
				return TRUE;
			}
		}
		/* Have to catch \RuntimeException to catch the BAD_JSON throw from Response.php */
		catch ( \RuntimeException $e )
		{
		}

		return FALSE;
	}
	
	/**
	 * Username is in use?
	 * Used when registering or changing an username to check the new one is available
	 *
	 * @param	string	$username	Username
	 * @return	bool|NULL			Boolean indicates if username is in use (TRUE means is in use and thus not registerable) or NULL if this handler does not support such an API
	 */
	public function usernameIsInUse( $username )
	{
		if( \IPS\CONNECT_NOSYNC_NAMES === TRUE )
		{
			return FALSE;
		}

		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'checkName', 'key' => $this->settings['key'], 'name' => $username ) )
				->request()
				->get()
				->decodeJson();

			if ( !empty( $response ) AND !empty( $response['status'] ) AND $response['status'] == 'SUCCESS' AND $response['used'] != 0 )
			{
				return TRUE;
			}
		}
		/* Have to catch \RuntimeException to catch the BAD_JSON throw from Response.php */
		catch ( \RuntimeException $e )
		{
		}

		return FALSE;
	}

	/**
	 * Log an account off
	 *
	 * @param	\IPS\Member		$member			The member that was just logged out
	 * @param	\IPS\Http\Url	$redirectUrl	The URL to send the user back to
	 * @return	void
	 * @note	This is NOT called if you force log out all users from the ACP on an individual site
	 */
	public function logoutAccount( \IPS\Member $member, \IPS\Http\Url $redirectUrl )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		\IPS\Output::i()->redirect( 
			\IPS\Http\Url::external( $this->settings['url'] )->setQueryString( array( 
				'do'		=> 'logout', 
				'key'		=> md5( $this->settings['key'] . $connectId ), 
				'url'		=> \IPS\Settings::i()->base_url, 
				'id'		=> $connectId, 
				'returnTo'	=> (string) $redirectUrl, 
			) )
		);
	}
	
	/**
	 * Change Email Address
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	string		$oldEmail	Old Email Address
	 * @param	string		$newEmail	New Email Address
	 * @return	void
	 */
	public function changeEmail( \IPS\Member $member, $oldEmail, $newEmail )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'changeEmail', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'email' => $newEmail, 'id' => $connectId ) )
				->request()
				->get()
				->decodeJson();
		}
		catch( \RuntimeException $e ){}
	}
	
	/**
	 * Change Password
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$newPassword	New Password
	 * @return	void
	 */
	public function changePassword( \IPS\Member $member, $newPassword )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		/* Now create a dummy user to get password hash */
		$testUser	= new \IPS\Member;
		$testUser->members_pass_salt	= $testUser->generateSalt();
		$password	= $testUser->encryptedPassword( $newPassword );

		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'changePassword', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'pass_hash' => $password, 'pass_salt' => $testUser->members_pass_salt, 'id' => $connectId ) )
				->request()
				->get()
				->decodeJson();
		}
		catch( \RuntimeException $e ){}
	}
	
	/**
	 * Change Username
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$oldUsername	Old Username
	 * @param	string		$newUsername	New Username
	 * @return	void
	 */
	public function changeUsername( \IPS\Member $member, $oldUsername, $newUsername )
	{
		if( \IPS\CONNECT_NOSYNC_NAMES === TRUE )
		{
			return FALSE;
		}

		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		try
		{
			$response = \IPS\Http\Url::external( $this->settings['url'] )
				->setQueryString( array( 'do' => 'changeName', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'name' => $newUsername, 'id' => $connectId ) )
				->request()
				->get()
				->decodeJson();
		}
		catch( \RuntimeException $e ){}
	}

	/**
	 * Create an account
	 *
	 * @param	\IPS\Member	$member			The member that was just created
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function createAccount( \IPS\Member $member )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$response = \IPS\Http\Url::external( $this->settings['url'] )
			->setQueryString( array( 
				'do'			=> 'register',
				'key'			=> $this->settings['key'],
				'name'			=> $member->name,
				'email'			=> $member->email,
				'pass_hash'		=> $member->members_pass_hash,
				'pass_salt'		=> $member->members_pass_salt,
				'revalidateUrl'	=> ( $member->members_bitoptions['validating'] == TRUE ) ? (string) \IPS\Http\Url::internal( "", 'front' ) : '',
				'url'			=> \IPS\Settings::i()->base_url
			) )
			->request()
			->get()
			->decodeJson();

		if( !empty( $response ) AND !empty( $response['status'] ) AND $response['status'] == 'SUCCESS' AND !empty( $response['connect_id'] ) )
		{
			$member->ipsconnect_id	= $response['connect_id'];
			$member->save();
		}
	}

	/**
	 * Validate account
	 *
	 * @param	\IPS\Member	$member			The member
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function validateAccount( \IPS\Member $member )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		$response = \IPS\Http\Url::external( $this->settings['url'] )
			->setQueryString( array( 'do' => 'validate', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'id' => $connectId ) )
			->request()
			->get()
			->decodeJson();
	}

	/**
	 * Delete account
	 *
	 * @param	\IPS\Member	$member			The member
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function deleteAccount( \IPS\Member $member )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		$response = \IPS\Http\Url::external( $this->settings['url'] )
			->setQueryString( array( 'do' => 'delete', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'id' => $connectId ) )
			->request()
			->get()
			->decodeJson();
	}

	/**
	 * Ban or unban account
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	bool						TRUE means member is being banned, FALSE means they are being unbanned
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function banAccount( \IPS\Member $member, $ban=TRUE )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );

		$response = \IPS\Http\Url::external( $this->settings['url'] )
			->setQueryString( array( 'do' => 'ban', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'status' => (int) $ban, 'id' => $connectId ) )
			->request()
			->get()
			->decodeJson();
	}

	/**
	 * Merge two accounts
	 *
	 * @param	\IPS\Member	$member			The member to keep with original data
	 * @param	\IPS\Member	$member2		The member that will be deleted
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function mergeAccounts( \IPS\Member $member, \IPS\Member $member2 )
	{
		if( \IPS\Request::i()->slaveCall )
		{
			return;
		}

		$connectId	= $this->_getConnectId( $member );
		$connectId2	= $this->_getConnectId( $member2 );

		$response = \IPS\Http\Url::external( $this->settings['url'] )
			->setQueryString( array( 'do' => 'merge', 'key' => md5( $this->settings['key'] . $connectId ), 'url' => \IPS\Settings::i()->base_url, 'remove' => $connectId2, 'id' => $connectId ) )
			->request()
			->get()
			->decodeJson();
	}
}