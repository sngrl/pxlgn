<?php
/**
 * @brief		Installer
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		3 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\Setup;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Installer
 */
class _Install
{	
	/**
	 * System Requirements
	 *
	 * @return	array
	 */
	public static function systemRequirements()
	{
		$return = array();
		
		/* PHP Version */
		$phpVersion = PHP_VERSION;
		if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_success', FALSE, array( 'sprintf' => array( $phpVersion ) ) )
			);
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_fail', FALSE, array( 'sprintf' => array( $phpVersion ) ) ),
			);
		}
		if ( version_compare( PHP_VERSION, '5.4.0' ) == -1 )
		{
			$return['advice'][] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_advice', FALSE, array( 'sprintf' => array( $phpVersion ) ) );
		}
		
		/* cURL or allow_url_fopen */
		if ( extension_loaded('curl') )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_success' ),
			);
		}
		elseif ( ini_get('allow_url_fopen') )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_fopen' ),
			);
			
			$return['advice'][] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_advice' );
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_fail' ),
			);
		}
		
		/* mbstring can be configured with --disable-mbregex */
		if ( extension_loaded( 'mbstring' ) )
		{
			if ( function_exists( 'mb_eregi' ) )
			{
				$return['requirements']['PHP'][] = array(
					'success'	=> TRUE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_success' ),
				);
			}
			else
			{
				$return['requirements']['PHP'][] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_regex' ),
				);
			}
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_fail' ),
			);
		}

		
		/* Extensions */
		foreach ( array(
			'required'	=> array( 'dom', 'gd', 'mysqli', 'openssl', 'session', 'simplexml', 'xml', 'xmlreader', 'xmlwriter' ),
			'advised'	=> array( 'zip' ),
		) as $type => $extensions )
		{
			foreach ( $extensions as $extension )
			{
				if ( extension_loaded( $extension ) )
				{
					$return['requirements']['PHP'][] = array(
						'success'	=> TRUE,
						'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_success', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "requirements_extension_{$extension}" ) ) ) ),
					);
				}
				elseif ( $type === 'required' )
				{
					$return['requirements']['PHP'][] = array(
						'success'	=> FALSE,
						'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_fail', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "requirements_extension_{$extension}" ) ) ) ),
					);
				}
				elseif ( $type === 'advised' )
				{
					$return['advice'][] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_advice', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "requirements_extension_{$extension}" ) ) ) );
				}
			}
		}
				
		/* Memory Limit */
		$_memoryLimit	= @ini_get('memory_limit');
		$memoryLimit	= $_memoryLimit;
		preg_match( "#^(\d+)(\w+)$#", mb_strtolower($memoryLimit), $match );
		if( $match[2] == 'g' )
		{
			$memoryLimit = intval( $memoryLimit ) * 1024 * 1024 * 1024;
		}
		else if ( $match[2] == 'm' )
		{
			$memoryLimit = intval( $memoryLimit ) * 1024 * 1024;
		}
		else if ( $match[2] == 'k' )
		{
			$memoryLimit = intval( $memoryLimit ) * 1024;
		}
		else
		{
			$memoryLimit = intval( $memoryLimit );
		}
		if ( $memoryLimit >= ( 128 * 1024 * 1024 ) OR $memoryLimit == -1 )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_memory_limit_success', FALSE, array( 'sprintf' => array( ( $memoryLimit != -1 ) ? \IPS\Output\Plugin\Filesize::humanReadableFilesize( $memoryLimit ) : \IPS\Member::loggedIn()->language()->get( 'unlimited' ) ) ) )
			);
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_memory_limit_fail', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $memoryLimit ) ) ) )
			);
		}
		
		/* Suhosin */
		if ( extension_loaded( 'suhosin' ) )
		{
			foreach ( array(
				'suhosin.post.max_vars'					=> 4096,
				'suhosin.request.max_vars'				=> 4096,
				'suhosin.get.max_value_length'			=> 2000,
				'suhosin.post.max_value_length'			=> 10000,
				'suhosin.request.max_value_length'		=> 10000,
				'suhosin.request.max_varname_length'	=> 350,
			) as $setting => $minimum )
			{
				$value = ini_get( 'suhosin.post.max_vars' );
				if ( $value and $value < $minimum )
				{
					$return['advisory'][] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_suhosin_limit', FALSE, array( 'sprintf' => array( $setting, $value, $minimum ) ) );
				}
			}
		}
		
		/* Writeables */
		$writeablesKey = \IPS\Member::loggedIn()->language()->addToStack('requirements_file_system');
		foreach ( array( 'applications', 'datastore', 'plugins', 'uploads' ) as $dir )
		{
			$success = is_writable( \IPS\ROOT_PATH . '/' . $dir );
			
			$return['requirements'][ $writeablesKey ][ $dir ] = array(
				'success'	=> $success,
				'message'	=> $success ?  \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_writable', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH . '/' . $dir ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'err_not_writable', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH . '/' . $dir ) ) )
			);
		}
		$logMethods = \IPS\Log::getUsedMethods();
		if( isset( $logMethods['disk'] ) and !empty( $logMethods['disk'] ) )
		{
			$dir = \IPS\Log::i( 'Disk' )->logDir;
			$success = is_writable( $dir );
			$return['requirements'][ $writeablesKey ][ $dir ] = array(
				'success'	=> $success,
				'message'	=> $success ? \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_writable', FALSE, array( 'sprintf' => array( $dir ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'err_not_writable', FALSE, array( 'sprintf' => array( $dir ) ) )
			);
		}
		try
		{
			if ( !is_writable( \IPS\TEMP_DIRECTORY ) )
			{
				throw new \Exception;
			}
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			if ( $tempFile === FALSE or !file_exists( $tempFile ) )
			{
				throw new \Exception;
			}
		}
		catch( \Exception $e )
		{
			if( file_exists( \IPS\ROOT_PATH . '/constants.php' ) )
			{
				$return['requirements'][ $writeablesKey ]['tmp'] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'err_tmp_dir_adjust', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH ) ) )
				);
			}
			else
			{
				$return['requirements'][ $writeablesKey ]['tmp'] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'err_tmp_dir_create', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH ) ) )
				);
			}
		}
		
		return $return;
	}
	
	/**
	 * @brief	Percentage of *this step* completed (used for the progress bar)
	 */
	protected $stepProgress = 0;

	/**
	 * Constructor
	 *
	 * @param	array	$apps		Application keys of apps to install
	 * @param	string	$defaultApp	The default applicaion
	 * @param	string	$baseUrl	Base URL
	 * @param	array	$path		Base Path
	 * @param	array	$db			Database connection detials [see \IPS\Db::i()]
	 * @param	string	$adminName	Admin Username
	 * @param	string	$adminPass	Admin Password
	 * @param	string	$adminEmail	Admin Email
	 * @return	void
	 * @throws	\InvalidArgumentException
	 * @see		\IPS\Db::i()
	 */
	public function __construct( $apps, $defaultApp, $baseUrl, $path, $db, $adminName, $adminPass, $adminEmail )
	{
		/* Have core app? */
		if ( !in_array( 'core', $apps ) )
		{
			throw new \InvalidArgumentException( 'NO_CORE_APP' );
		}
		
		/* Connect to DB */
		$db = \IPS\Db::i( NULL, $db );
		
		/* Check we have everything else */
		if ( !$baseUrl or !$path or !$adminName or !$adminEmail or !$adminPass )
		{
			throw new \InvalidArgumentException( 'INSUFFICIENT_DATA' );
		}
		
		/* Store data */
		$this->apps			= $apps;
		$this->defaultApp	= $defaultApp;
		$this->baseUrl		= $baseUrl;
		$this->path			= $path;
		$this->adminName	= $adminName;
		$this->adminPass	= $adminPass;
		$this->adminEmail	= $adminEmail;
	}
	
	/**
	 * Process
	 *
	 * @param	array		Multiple-Redirector Data
	 * @return	array|null	Multiple-Redirector Data or NULL indicates done
	 */
	public function process( $data )
	{
		/* Start */
		if ( $data === 0 )
		{
			return array( array( 1 ), \IPS\Member::loggedIn()->language()->addToStack('installing') );
		}
		
		/* Run the step */
		$step = intval( $data[0] );
		
		if ( $step == 12 )
		{
			return NULL;
		}
		elseif ( !method_exists( $this, "step{$step}" ) )
		{
			throw new \BadMethodCallException( 'NO_STEP' );
		}
		$response = call_user_func( array( $this, "step{$step}" ), $data );
		
		return array( $response, \IPS\Member::loggedIn()->language()->addToStack( 'install_step_' . $step ), ( ( ( 100/12 ) * $data[0] + ( ( 100/12 ) / 100 * $this->stepProgress ) ) ) ?: 1 );
	}
	
	/**
	 * App Looper
	 *
	 * @param	array		data	Multiple-Redirector Data
	 * @param	callback	$code	Code to execute for each app
	 * @return	array		Data to Multiple-Redirector Data
	 */
	protected function appLoop( $data, $code )
	{
		$this->stepProgress = 0;
		
		$returnNext = FALSE;
		foreach ( $this->apps as $app )
		{
			$this->stepProgress += ( 100 / count( $this->apps ) );
						
			if ( !isset( $data[1] ) )
			{
				return array( $data[0], $app );
			}
			elseif ( $data[1] == $app )
			{
				$val = call_user_func( $code, $app );
				
				if ( is_array( $val ) )
				{
					return $val;
				}
				else
				{
					$returnNext = true;
				}
			}
			elseif ( $returnNext )
			{
				return array( $data[0], $app );
			}
		}
		
		return array( ( $data[0] + 1 ) );
	}
	
	/**
	 * Step 1
	 * Create database
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step1( $data )
	{
		$this->stepProgress = 0;
		$perAppProgress = floor( 100 / count( $this->apps ) );
				
		$returnNext = FALSE;
		foreach ( $this->apps as $app )
		{
			$this->stepProgress += $perAppProgress;
						
			if ( !isset( $data[1] ) )
			{
				return array( $data[0], $app );
			}
			elseif ( $data[1] == $app )
			{
				if ( !isset( $data[2] ) )
				{
					$data[2] = 0;
				}
				
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/data/schema.json" ) )
				{
					$schema = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/schema.json" ), TRUE );
					if ( count( $schema ) )
					{
						$perTableProgress = ( $perAppProgress / count( $schema ) );
						$i = 0;
						foreach( $schema as $dbTable )
						{
							$i++;
							$this->stepProgress += $perTableProgress;
							while ( $data[2] > $i )
							{
								continue 2;
							}
														
							\IPS\Db::i()->dropTable( $dbTable['name'], TRUE );
							\IPS\Db::i()->createTable( $dbTable );
													
							if ( isset( $dbTable['inserts'] ) )
							{
								foreach ( $dbTable['inserts'] as $insertData )
								{
									$adminName = $this->adminName;
									\IPS\Db::i()->insert( $dbTable['name'], array_map( function( $column ) use( $adminName ) {
										if( !is_string( $column ) )
										{
											return $column;
										}

										$column = str_replace( '<%TIME%>', time(), $column );
										$column = str_replace( '<%ADMIN_NAME%>', $adminName, $column );
										$column = str_replace( '<%IP_ADDRESS%>', $_SERVER['REMOTE_ADDR'], $column );
										return $column;
									}, $insertData ) );
								}
							}
							
							if ( !file_exists( \IPS\ROOT_PATH . "/applications/{$app}/setup/install/queries.json" ) )
							{
								$data[2]++;
								return $data;
							}
						}
					}
				}
				
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/setup/install/queries.json" ) )
				{
					$schema	= json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/setup/install/queries.json" ), TRUE );
		
					ksort($schema);
		
					foreach( $schema as $instruction )
					{
						if ( $instruction['method'] === 'addColumn' )
						{
							/* Check to see if it exists first */
							$tableDefinition = \IPS\Db::i()->getTableDefinition( $instruction['params'][0] );
							
							if ( ! empty( $tableDefinition['columns'][ $instruction['params'][1]['name'] ] ) )
							{
								/* Run an alter instead */
								\IPS\Db::i()->changeColumn( $instruction['params'][0], $instruction['params'][1]['name'], $instruction['params'][1] );
								continue;
							}
						}

						if( isset( $instruction['params'][1] ) and is_array( $instruction['params'][1] ) )
						{
							$groups	= array_filter( iterator_to_array( \IPS\Db::i()->select( 'g_id', 'core_groups' ) ), function( $groupId ) {
								if( $groupId == 2 )
								{
									return FALSE;
								}

								return TRUE;
							});

							foreach( $instruction['params'][1] as $column => $value )
							{
								if( $value === "<%NO_GUESTS%>" )
								{
									$instruction['params'][1][ $column ]	= implode( ",", $groups );
								}
							}
						}
						
						call_user_func_array( array( \IPS\Db::i(), $instruction['method'] ), $instruction['params'] );
					}
				}
							
				$returnNext = TRUE;
			}
			elseif ( $returnNext )
			{
				return array( $data[0], $app );
			}
		}
		
		return array( ( $data[0] + 1 ) );
	}
	
	/**
	 * Step 2
	 * Insert application and module data
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step2( $data )
	{
		$pos = 0;
		$defaultApp = $this->defaultApp;
		return $this->appLoop( $data, function( $app ) use ( &$pos, $defaultApp )
		{
			/* Get version data */
			if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/data/versions.json" ) )
			{
				$versions = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/versions.json" ), TRUE );
				$keys = array_keys( $versions );
				
				$info = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/application.json" ), TRUE );
						
				/* App Data */
				\IPS\Db::i()->insert( 'core_applications', array(
					'app_author'		=> $info['app_author'],
					'app_version'		=> array_pop( $versions ),
					'app_long_version'	=> array_pop( $keys ),
					'app_directory'		=> $app,
					'app_added'			=> time(),
					'app_position'		=> ++$pos,
					'app_protected'		=> ( $app === 'core' ),
					'app_enabled'		=> TRUE,
					'app_default'		=> ( $app === $defaultApp ),
				) );
				
				/* Modules */
				$modules = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/modules.json" ), TRUE );
				$modulePos = 0;
				foreach ( $modules as $area => $areaModules )
				{
					foreach ( $areaModules as $key => $data )
					{
						$insertId = \IPS\Db::i()->insert( 'core_modules', array(
							'sys_module_application'		=> $app,
							'sys_module_key'				=> $key,
							'sys_module_protected'			=> $data['protected'],
							'sys_module_visible'			=> TRUE,
							'sys_module_position'			=> ++$modulePos,
							'sys_module_area'				=> $area,
							'sys_module_default_controller'	=> $data['default_controller'],
						) );
						
						\IPS\Db::i()->insert( 'core_permission_index', array(
							'app'			=> 'core',
							'perm_type'		=> 'module',
							'perm_type_id'	=> $insertId,
							'perm_view'		=> '*',
						) );
					}
				}
			}
		} );
	}
	
	/**
	 * Step 3
	 * Insert Settings
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step3( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			\IPS\Application::load( $app )->installSettings();
			
			if ( $app === 'core' )
			{
				require \IPS\ROOT_PATH . '/conf_global.php';

				\IPS\Db::i()->insert( 'core_file_storage', array(
					'method' => 'FileSystem',
					'configuration' => json_encode( array(
						'dir' => '{root}/uploads',
						'url' => str_replace( 'http://', '//', $INFO['base_url'] ) . 'uploads'
					) )
				) );
			}
			
			/* Set up File Storage methods */
			\IPS\Application::load( $app )->installExtensions();
		} );
	}
	
	/**
	 * Step 4
	 * Create admin account
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step4( $data )
	{
		\IPS\Settings::i()->member_group = 4;
		$member = new \IPS\Member;
		$member->name = $this->adminName;
		$member->email = $this->adminEmail;
		$member->ip_address	= \IPS\Request::i()->ipAddress();
		$member->member_group_id = 4;
		$member->allow_admin_mails = 0;
		$member->joined = time();
		$member->members_pass_salt = $member->generateSalt();
		$member->members_pass_hash = $member->encryptedPassword( $this->adminPass );
		$member->save();
	
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $this->adminEmail ), "conf_key = 'email_out'" );
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $this->adminEmail ), "conf_key = 'email_in'" );

		unset( \IPS\Data\Store::i()->settings );
		
		return array( 5 );
	}
	
	/**
	 * Step 5
	 * Create Tasks
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step5( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			\IPS\Application::load( $app )->installTasks();
		} );
	}
	
	/**
	 * Step 6
	 * Create Languages
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step6( $data )
	{
		return $this->appLoop( $data, function( $app ) use ($data)
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}
			
			$inserted = \IPS\Application::load( $app )->installLanguages( $data[2], 250 );
			
			if ( $inserted )
			{
				$data[2] += $inserted;
				return $data;
			}
			else
			{
				return null;
			}
		} );
	}
	
	/**
	 * Step 7
	 * Create Email Templates
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step7( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			\IPS\Application::load( $app )->installEmailTemplates();
		} );
	}
	
	/**
	 * Step 8
	 * Create Themes
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step8( $data )
	{
		return $this->appLoop( $data, function( $app ) use ($data)
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}

			if( $data[2] == 0 )
			{
				\IPS\Application::load( $app )->installThemeSettings();
			}
			
			$inserted = \IPS\Application::load( $app )->installTemplates( FALSE, $data[2], 150 );
			
			if ( $inserted )
			{
				$data[2] += $inserted;
				return $data;
			}
			else
			{
				\IPS\Theme::load( \IPS\Theme::defaultTheme() )->saveSet();
				return null;
			}
		} );
	}
	
	/**
	 * Step 9
	 * Create Javascript
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step9( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			\IPS\Application::load( $app )->installJavascript();
		} );
	}
	
	/**
	 * Step 10
	 * Create Search Keywords
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step10( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			\IPS\Application::load( $app )->installSearchKeywords();
		} );
	}
	
	/**
	 * Step 11
	 * Install any widgets/extensions that need adding on install
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step11( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			try
			{
				\IPS\Application::load( $app )->installHooks();
				\IPS\Application::load( $app )->installWidgets();
				\IPS\Application::load( $app )->installOther();
			}
			catch( \Exception $e )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( $e, 'install_error' );
				}
				catch( \RuntimeException $e ){}
			}
			
			/* Insert default emoticons */
			if( $app == 'core' )
			{
				$setId = md5( uniqid() );
				$position = 0;
				\IPS\Lang::saveCustom( 'core', "core_emoticon_group_{$setId}", "Default" );

				$inserts = array();

				foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY ."/install/emoticons/data.json" ) ) as $type => $file )
				{
					$fileObj = \IPS\File::create( 'core_Emoticons', $file, file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/emoticons/" . $file ) );

					$inserts[] = array(
						'typed'			=> $type,
						'image'			=> (string) $fileObj,
						'clickable'		=> TRUE,
						'emo_set'		=> $setId,
						'emo_position'	=> ++$position,
					);
				}

				if( count( $inserts ) )
				{
					\IPS\Db::i()->insert( 'core_emoticons', $inserts );
				}
			}
		} );
	}
}