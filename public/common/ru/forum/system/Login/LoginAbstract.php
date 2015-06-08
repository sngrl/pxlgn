<?php
/**
 * @brief		Abstract Login Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		15 Mar 2013
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
 * Abstract Login Handler
 */
abstract class _LoginAbstract extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_login_handlers';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'login_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'key';
			
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'login_handlers';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'order';
	
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'lock';

	/**
	 * @brief	Disable the copy button - useful when the forms are very distinctly different
	 */
	public $noCopyButton	= TRUE;

	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		/* Initiate an object */
		$classname = '\\IPS\Login\\' . ucfirst( $data['login_key'] );
		 
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix )
			{
				$k = \substr( $k, \strlen( static::$databasePrefix ) );
			}
			
			$obj->$k = $v;
		}
		$obj->changed = array();
		$obj->init();
			
		/* Return */
		return $obj;
	}
	
	/**
	 * [Node] Get Node Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		$key = "login_handler_" . ucfirst($this->key);
		return \IPS\Member::loggedIn()->language()->addToStack( $key );
	}

	/**
	 * [Node] Get the log key
	 *
	 * @return	string|null
	 */
	protected function get__logKey()
	{
		return "login_handler_" . ucfirst($this->key);
	}
		
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return $this->enabled;
	}
	
	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool	$value	Enable Node?
	 * @return	void
	 */
	protected function set__enabled( $value )
	{
		$this->enabled = $value;
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to add a child node to this node?
	 *
	 * @return	bool
	 */
	public function canAdd()
	{
		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to add aa root node?
	 *
	 * @return	bool
	 */
	public static function canAddRoot()
	{
		return FALSE;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		foreach ( $this->acpForm() as $k => $v )
		{
			if ( is_string( $v ) )
			{
				$form->addHeader( $v );
			}
			else
			{		
				$form->add( $v );
				$names[ $v->name ] = $k;
			}
		}
		
		if ( $this->key !== 'internal' )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'login_acp', $this->acp ) );
		}
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		$names = array();
		foreach ( $this->acpForm() as $k => $v )
		{
			if ( !is_string( $v ) )
			{
				$names[ $v->name ] = $k;
			}
		}
		
		$save = array();
		foreach ( $names as $formName => $saveName )
		{
			if( array_key_exists( $formName, $values ) )
			{
				$save[ $saveName ] = $values[ $formName ];
				unset( $values[ $formName ] );
			}
		}
		
		$values['settings'] = json_encode( $save );
		$this->settings = $values['settings'];

		$this->testSettings();

		return $values;
	}
	
	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	function save()
	{
		parent::save();
		unset( \IPS\Data\Store::i()->loginHandlers );
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		unset( \IPS\Data\Store::i()->loginHandlers );
	}
		
	/**
	 * Get settings
	 *
	 * @return	array
	 */
	public function get_settings()
	{
		return json_decode( $this->_data['settings'], TRUE );
	}
	
	/**
	 * Initiate
	 *
	 * @return	void
	 */
	public function init()
	{
		
	}
		
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\LogicException
	 */
	public function testSettings()
	{
		return TRUE;
	}

	/**
	 * Fetch the type as a textual string
	 *
	 * @param	int		$type	The valid configured types
	 * @return	string
	 */
	public function getLoginType( $type )
	{
		switch ( $type )
		{
			case \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL:
				return 'username_or_email';
				break;
				
			case \IPS\Login::AUTH_TYPE_USERNAME:
				return 'username';
				break;
				
			case \IPS\Login::AUTH_TYPE_EMAIL:
				return 'email_address';
				break;
		}

		return 'username';
	}
	
	/**
	 * Email is in use?
	 * Used when registering or changing an email address to check the new one is available
	 *
	 * @param	string				$email	Email Address
	 * @param	\IPS\Member|NULL	$eclude	Member to exclude
	 * @return	bool|NULL Boolean indicates if email is in use (TRUE means is in use and thus not registerable) or NULL if this handler does not support such an API
	 */
	public function emailIsInUse( $email, \IPS\Member $exclude=NULL )
	{
		return NULL;
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
		return NULL;
	}
	
	/**
	 * Change Email Address
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	string		$oldEmail	Old Email Address
	 * @param	string		$newEmail	New Email Address
	 * @return	void
	 * @throws	\Exception
	 */
	public function changeEmail( \IPS\Member $member, $oldEmail, $newEmail )
	{
		if ( !$this->canChange( 'email', $member ) )
		{
			throw new \BadMethodCallException;
		}
	}
	
	/**
	 * Change Password
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$newPassword	New Password
	 * @return	void
	 * @throws	\Exception
	 */
	public function changePassword( \IPS\Member $member, $newPassword )
	{
		if ( !$this->canChange( 'password', $member ) )
		{
			throw new \BadMethodCallException;
		}
	}
	
	/**
	 * Change Username
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$oldUsername	Old Username
	 * @param	string		$newUsername	New Username
	 * @return	void
	 * @throws	\Exception
	 */
	public function changeUsername( \IPS\Member $member, $oldUsername, $newUsername )
	{
		// By default do nothing. Handlers can extend.
	}

	/**
	 * Log an account off
	 *
	 * @param	\IPS\Member		$member			The member that was just logged out
	 * @param	\IPS\Http\Url	$redirectUrl	The URL to send the user back to
	 * @return	void
	 * @throws	\Exception
	 * @note	This is NOT called if you force log out all users from the ACP on an individual site
	 */
	public function logoutAccount( \IPS\Member $member, \IPS\Http\Url $redirectUrl )
	{
		// By default do nothing. Handlers can extend.
	}

	/**
	 * Create an account
	 *
	 * @param	\IPS\Member	$member			The member that was just created
	 * @return	void
	 * @throws	\Exception
	 */
	public function createAccount( \IPS\Member $member )
	{
		// By default do nothing. Handlers can extend.
	}

	/**
	 * Validate account
	 *
	 * @param	\IPS\Member	$member			The member
	 * @return	void
	 * @throws	\Exception
	 */
	public function validateAccount( \IPS\Member $member )
	{
		// By default do nothing. Handlers can extend.
	}

	/**
	 * Delete account
	 *
	 * @param	\IPS\Member	$member			The member
	 * @return	void
	 * @throws	\Exception
	 */
	public function deleteAccount( \IPS\Member $member )
	{
		// By default do nothing. Handlers can extend.
	}

	/**
	 * Ban or unban account
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	bool						TRUE means member is being banned, FALSE means they are being unbanned
	 * @return	void
	 * @throws	\Exception
	 */
	public function banAccount( \IPS\Member $member, $ban=TRUE )
	{
		// By default do nothing. Handlers can extend.
	}

	/**
	 * Merge two accounts
	 *
	 * @param	\IPS\Member	$member			The member to keep with original data
	 * @param	\IPS\Member	$member2		The member that will be deleted
	 * @return	void
	 * @throws	\Exception
	 */
	public function mergeAccounts( \IPS\Member $member, \IPS\Member $member2 )
	{
		// By default do nothing. Handlers can extend.
	}
}