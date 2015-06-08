<?php
/**
 * @brief		Upgrader
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 May 2014
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
 * Upgrader
 */
class _Upgrade
{
	/**
	 * System Requirements
	 *
	 * @return	array
	 */
	public static function systemRequirements()
	{
		$return = Install::systemRequirements();
		
		/* MySQL Version */
		$mysqlVersion = \IPS\Db::i()->server_info;
		if ( version_compare( $mysqlVersion, '5.0.3' ) >= 0 )
		{
			$return['requirements']['MySQL'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mysql_version_success', FALSE, array( 'sprintf' => array( $mysqlVersion ) ) )
			);
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mysql_version_fail', FALSE, array( 'sprintf' => array( $mysqlVersion ) ) ),
			);
		}
		if ( version_compare( $mysqlVersion, '5.6.0' ) == -1 AND !mb_strpos( $mysqlVersion, "MariaDB" ) )
		{
			$return['advice'][] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mysql_version_advice', FALSE, array( 'sprintf' => array( $mysqlVersion ) ) );
		}
		
		/* Writeables */
		if( \IPS\Db::i()->checkForTable('core_file_storage') )
		{
			$writeablesKey = \IPS\Member::loggedIn()->language()->addToStack('requirements_file_system');
			$fileSystemItems = array();
			
			foreach ( \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE ) as $k => $v )
			{
				try
				{
					$class = \IPS\File::getClass( $k );
					if ( !isset( $fileSystemItems[ $class->configurationId ] ) )
					{
						if ( method_exists( $class, 'testSettings' ) )
						{
							$class->testSettings( $class->configuration );
						}
						
						$fileSystemItems[ $class->configurationId ] = array(
							'success'	=> TRUE,
							'message'	=> ( $class instanceof \IPS\File\FileSystem ) ? \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_writable', FALSE, array( 'sprintf' => array( str_replace( '{root}', \IPS\ROOT_PATH, $class->configuration['dir'] ) ) ) ) : $class->displayName( $class->configuration )
						);
					}
				}
				catch ( \LogicException $e )
				{
					$fileSystemItems[ $class->configurationId ] = array(
						'success'	=> FALSE,
						'message'	=> $e->getMessage()
					);
				}
			}
			
			array_splice( $return['requirements'][ $writeablesKey ], array_search( 'uploads', array_keys( $return['requirements'][ $writeablesKey ] ) ), 1, $fileSystemItems );
		}
		
		if ( \IPS\Data\Store::i() instanceof \IPS\Data\Store\FileSystem )
		{
			$success = ( is_dir( \IPS\Data\Store::i()->_path ) and is_writeable( \IPS\Data\Store::i()->_path ) );
			$return['requirements'][ $writeablesKey ]['datastore'] = array(
				'success'	=> $success,
				'message'	=> $success ? \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_writable', FALSE, array( 'sprintf' => array( \IPS\Data\Store::i()->_path ) ) ) : \IPS\Member::loggedIn()->language()->addToStack('bad_datastore_configuration', FALSE, array( 'sprintf' => array( \IPS\Data\Store::i()->_path ) ) )
			);
		}
		else
		{
			unset( $return['requirements'][ $writeablesKey ]['datastore'] );
		}
		
				
		return $return;
	}
	
	/**
	 * @brief	Percentage of *this step* completed (used for the progress bar)
	 */
	protected $stepProgress = 0;
	
	/**
	 * @brief	Custom title to use for refresh/progress bar
	 */
	protected $customTitle = NULL;

	/**
	 * Constructor
	 *
	 * @param	array	$apps		Application keys of apps to upgrade
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( $apps )
	{
		/* Store data */
		$this->apps			= $apps;
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
			$data	= array( 0 => 1 );
		}
		
		/* Clear the last SQL error if we are continuing upgrade to prevent it erroneously showing up again later */
		if( isset( \IPS\Request::i()->mr_continue ) )
		{
			unset( $_SESSION['lastSqlError'] );
		}
		
		/* Run the step */
		$step = intval( $data[0] );
		
		/* Write the log */
		@file_put_contents( \IPS\ROOT_PATH . '/uploads/logs/upgrader_data.cgi', json_encode( array(
			'session' => $_SESSION,
			'step'    => $step,
			'data'    => $data
		) ) );
		@chmod( \IPS\ROOT_PATH . '/uploads/logs/upgrader_data.cgi', \IPS\IPS_FILE_PERMISSION );
		
		if ( $step == 10 )
		{
			/* Clear member menu cache */
			\IPS\Member::clearCreateMenu();

			/* Clear widget caches */
			\IPS\Widget::deleteCaches();

			/* Clear theme caches */
			try
			{
				foreach( \IPS\Theme::themes() as $id => $theme )
				{
					$theme->buildResourceMap();
					$theme->saveSet();
				}
			}
			catch( \Exception $e )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( "Error : " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'upgrade_error' );
				}
				catch( \RuntimeException $e ){}
			}

			return NULL;
		}
		elseif ( !method_exists( $this, "step{$step}" ) )
		{
			throw new \BadMethodCallException( 'NO_STEP' );
		}
		
		$response = call_user_func( array( $this, "step{$step}" ), $data );
		
		return array( $response, \IPS\Member::loggedIn()->language()->addToStack( ( $this->customTitle ) ? $this->customTitle : 'upgrade_step_' . $step ), ( ( ( 100/6 ) * $data[0] + ( ( 100/6 ) / 100 * $this->stepProgress ) ) ) ?: 1 );
	}
	
	/**
	 * Fetch the next update ID
	 *
	 * @param	string		$app		Application key
	 * @param	int			$current	Current upgrading ID
	 * @return	int|null				Upgrade or ID if none
	 */
	protected function getNextUpgradeId( $app, $current=0 )
	{
		$currentVersion	= \IPS\Application::load( $app )->long_version;
		$upgradeSteps	= \IPS\Application::load( $app )->getUpgradeSteps( $current ? $current : $currentVersion );
		
		/* Grab next upgrade step to run */
		if ( ! count( $upgradeSteps ) )
		{
			return NULL;
		}
		
		$next	= array_shift( $upgradeSteps );
		$next	= intval( $next );

		if( !isset( $_SESSION['upgrade_steps'] ) )
		{
			$_SESSION['upgrade_steps']	= array( $next => array( $app => $app ) );
		}
		elseif( !isset( $_SESSION['upgrade_steps'][ $next ] ) )
		{
			$_SESSION['upgrade_steps'][ $next ]	= array( $app => $app );
		}
		else
		{
			$_SESSION['upgrade_steps'][ $next ][ $app ]	= $app;
		}

		return $next;
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
	 * Upgrade database
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step1( $data )
	{
		$this->stepProgress	= 0;
		$perAppProgress		= floor( 100 / count( $this->apps ) );
		$returnNext	= FALSE;
		$extra		= array();
		
		$lastAppToRun = end( $this->apps );
		reset( $this->apps );
		
		foreach ( $this->apps as $app )
		{
			$this->stepProgress += $perAppProgress;
						
			if ( !isset( $data[1] ) )
			{
				$this->customTitle = "Preparing to upgrade database";
				
				return array( $data[0], $app );
			}
			/* If we finished with the last app, $returnNext would get set to true and we'd want to run this again */
			elseif ( $data[1] == $app OR $returnNext )
			{
				if ( isset( $_SESSION['updatedData'] ) )
				{
					unset( $_SESSION['updatedData'] );
				}
				
				if ( $returnNext )
				{
					/* Start next app */
					$data['extra']	= array();
					$data[1]        = $app;
					
					$_SESSION['updatedData'] = $data;
					$_SESSION['lastJsonIndex'] = 0;
				}

				try
				{
					\IPS\Log::i( LOG_INFO )->write( "Step1 Loop: " . json_encode( $data ), 'upgrade' );
				}
				catch( \RuntimeException $e ){}
				
				/* Re-initialize $extra variable */
				$extra	= array();
				
				$extra['lastSqlId'] = isset( $data['extra']['lastSqlId'] ) ? $data['extra']['lastSqlId'] : 0;
				
				/* Currently running version */
				if ( isset( $data['extra'] ) and array_key_exists( '_current', $data['extra'] ) ) # Can be null which isset() ignores
				{
					$extra['_current'] = $data['extra']['_current'];
				}
				else
				{
					$extra['_current']	= $this->getNextUpgradeId( $app );
					$extra['lastSqlId']	= 0;
				}

				/* Did we find any? */
				if( $extra['_current'] )
				{
					/* We need to populate \IPS\Request with the extra data returned from the last upgrader step call */
					if( isset( $data['extra']['_upgradeData'] ) )
					{
						\IPS\Request::i()->extra = $data['extra']['_upgradeData'];
					}
					
					/* What step in the upgrader file are we on? */
					$upgradeStep = ( isset($data['extra']['_upgradeStep']) ) ? intval($data['extra']['_upgradeStep']) : 1;

					/* We're on the first step of the current version's upgrade.php, so run the raw queries yet, do so */
					if( $upgradeStep == 1 AND ! isset( $data['extra']['_upgradeData'] ) and ( ! ( isset( $_SESSION['sqlFinished'][ $app ][ $extra['_current'] ] ) AND $_SESSION['sqlFinished'][ $app ][ $extra['_current'] ] ) ) )
					{
						$lastSqlId = ( isset( $extra['lastSqlId'] ) ? $extra['lastSqlId'] : 0 );
						
						$this->customTitle = "Upgrading database (" . ucfirst( $app ) . ': Upgrade ID ' . $extra['_current'] . '-' . $lastSqlId . ')';
						
						try
						{
							\IPS\Log::i( LOG_INFO )->write( "Upgrading database for app: " . $app, 'upgrade' );
						}
						catch( \RuntimeException $e ){}
						
						$_SESSION['lastSqlError'] = null;
						
						try
						{
							$fetched = \IPS\Application::load( $app )->installDatabaseUpdates( $extra['_current'], $lastSqlId, 10, TRUE );

							$extra['lastSqlId'] = $_SESSION['lastJsonIndex'];
							
							try
							{
								\IPS\Log::i( LOG_INFO )->write( (int) $fetched['count'] . ' queries run, last ID ' . $_SESSION['lastJsonIndex'], 'upgrade' );
							}
							catch( \RuntimeException $e ){}
						}
						catch( \IPS\Db\Exception $ex )
						{
							$trace    = $ex->getTrace();
							$queryRun = '';
							$message  = $ex->getMessage();
							
							if ( isset( $trace[0]['args'][0] ) )
							{
								$queryRun = $trace[0]['args'][0];
							}
							
							$_SESSION['lastSqlError'] = $message . ' ' . $queryRun;
							
							try
							{
								\IPS\Log::i( LOG_ERR )->write( "Error: " . $message . ' ' . $queryRun . "\n" . $ex->getTraceAsString(), 'upgrade_error' );
							}
							catch( \RuntimeException $e ){}
							
							/* Throw so ajax returns 500 and stops upgrader */
							throw $ex;
						}
						
						/* Queries to run manually */
						if( is_array( $fetched['queriesToRun'] ) and count( $fetched['queriesToRun'] ) )
						{
							$existing		= json_decode( \IPS\Request::i()->mr, TRUE );
							$existing[1]	= $app;	/* This is needed in case we looped over to the next app, i.e. downloads -> nexus */
							$newExtra		= array_merge( $extra, array( 'lastSqlId' => $_SESSION['lastJsonIndex'] + 1 ) );
							$multiRedirect	= json_encode( array_merge( $existing, array( 'extra' => $newExtra ) ) );
							return \IPS\Theme::i()->getTemplate( 'forms' )->queries( $fetched['queriesToRun'], \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => $multiRedirect ) ) );
						}
						else
						{
							/* Got more? */
							if ( $fetched['count'] > 0 AND $_SESSION['lastJsonIndex'] )
							{
								return array( $data[0], $app, 'extra' => $extra );
							}
							
							$extra['lastSqlId']		= 0;
							$_SESSION['sqlFinished'][ $app ][ $extra['_current'] ] = 1;
							$_SESSION['lastJsonIndex'] = 0;
							$_SESSION['lastSqlError']  = null;
							
							/* All done, allow code to finish this foreach and process below */
						}
					}

					/* Get the object */
					$_className		= "\\IPS\\{$app}\\setup\\upg_{$extra['_current']}\\Upgrade";
					$_methodName	= "step{$upgradeStep}";

					if( class_exists( $_className ) )
					{ 
						$upgrader = new $_className;

						/* If the next step exists, run it */
						if( method_exists( $upgrader, $_methodName ) )
						{
							$this->customTitle = "Running upgrade step " . $upgradeStep . " (" . ucfirst( $app ) . ': Upgrade ID ' . $extra['_current'] . ')';
							
							try
							{
								/* Get custom title first as the step may unset session variables that are being referenced */
								$customTitleMethod = 'step' . $upgradeStep . 'CustomTitle';
								
								if ( method_exists( $upgrader, $customTitleMethod ) )
								{
									$this->customTitle = $upgrader->$customTitleMethod();
								}

								$result = $upgrader->$_methodName();
							}
							catch( \IPS\Db\Exception $ex )
							{
								$trace    = $ex->getTrace();
								$queryRun = '';
								$message  = $ex->getMessage();
								
								if ( isset( $trace[0]['args'][0] ) )
								{
									$queryRun = $trace[0]['args'][0];
								}
								
								$_SESSION['lastSqlError'] = $message . ' ' . $queryRun;
								$_SESSION['updatedData']  = array_merge( $data, array( 'extra' => $extra ), array( 'extra' => array( '_current' => $extra['_current'], '_upgradeStep' => $upgradeStep ) ) );
								
								try
								{
									\IPS\Log::i( LOG_ERR )->write( "(Upgrader " . $_methodName . ") " . $message . ' ' . $queryRun . $ex->getTraceAsString(), 'upgrade_error' );
								}
								catch( \RuntimeException $e ){}
								
								/* Throw so ajax returns 500 and stops upgrader */
								throw $ex;
							}
							catch( \UnderflowException $ex )
							{
								$trace    = $ex->getTrace();
								$message  = $ex->getMessage();
								
								$_SESSION['lastSqlError'] = $message;
								$_SESSION['updatedData']  = array_merge( $data, array( 'extra' => $extra ), array( 'extra' => array( '_current' => $extra['_current'], '_upgradeStep' => $upgradeStep ) ) );
								
								try
								{
									\IPS\Log::i( LOG_ERR )->write( "(Upgrader " . $_methodName . ") " . $message, 'upgrade_error' );
								}
								catch( \RuntimeException $e ){}
								
								throw $ex;
							}
							
							/* If the result is 'true' we move on to the next step */
							if( $result === TRUE )
							{
								/* Reset this for future version IDs */
								$extra['lastSqlId']	= 0;
								$_SESSION['lastJsonIndex'] = 0;
								$_SESSION['lastSqlError']  = null;
								$_SESSION['updatedData']   = null;
								
								$_nextMethodStep = "step" . ( $upgradeStep + 1 );

								if( method_exists( $upgrader, $_nextMethodStep ) )
								{
									/* We have another step to run - set the data and move along */
									$extra['_upgradeStep']	= $upgradeStep + 1;
									
									return array( $data[0], $app, 'extra' => $extra );
								}
								else
								{
									/* Done with this current step, see if there are any more */
									$extra['_current'] = $this->getNextUpgradeId( $app, $extra['_current'] );
									
									if( $extra['_current'] )
									{
										unset( $extra['_upgradeStep'], $extra['_upgradeData'] );

										return array( $data[0], $app, 'extra' => $extra );
									}
								}
							}
							/* If the result is an array with 'html' key, we show that */
							else if( is_array( $result ) AND isset( $result['html'] ) )
							{
								return $result['html'];
							}
							/* Otherwise we need to run the same step again and store the data returned */
							else
							{
								/* Store the data returned, set the step to the same/current one, and re-run */
								$extra['_upgradeData']	= $result;
								$extra['_upgradeStep']	= $upgradeStep;
								
								return array( $data[0], $app, 'extra' => $extra );
							}
						}
						else
						{
							/* Step doesn't exist so move on to next version */
							$extra['_current']	= $this->getNextUpgradeId( $app, $extra['_current'] );
							$extra['lastSqlId']	= 0;

							unset( $extra['_upgradeStep'], $extra['_upgradeData'] );

							return array( $data[0], $app, 'extra' => $extra );
						}
					} # If has upg_xxxxx/Upgrade.php
					else
					{
						/* SQL done, no upgrade steps, lets jog on */
						$extra['_current']	= $this->getNextUpgradeId( $app, $extra['_current'] );
						$extra['lastSqlId']	= 0;

						unset( $extra['_upgradeStep'], $extra['_upgradeData'] );
						
						return array( $data[0], $app, 'extra' => $extra );
					}
					
					$returnNext = TRUE;
					
				} # If current step
				else
				{
					$_SESSION['lastJsonIndex'] = 0;
					$_SESSION['lastSqlError']  = null;
					$_SESSION['updatedData']   = null;
					
					/* Get the next app to upgrade */
					$returnNext = TRUE;
				}
				
			} # If current app
		} # Foreach

		/* We're done, look for finish methods for this upgrade ID */
		if ( $lastAppToRun === $app AND isset( $_SESSION['upgrade_steps'] ) AND count( $_SESSION['upgrade_steps'] ) )
		{
			$this->customTitle = "Running finish step (" . ucfirst( $app ) . ': Upgrade ID ' . $extra['_current'] . ')';
			
			foreach( $_SESSION['upgrade_steps'] as $_versionId => $_versionApps )
			{
				foreach( $_versionApps as $_versionApp )
				{
					try
					{
						\IPS\Log::i( LOG_INFO )->write( "Running finish step for: {$_versionApp} version {$_versionId}", 'upgrade' );
					}
					catch( \RuntimeException $e ){}

					/* Get the object */
					$_className = "\\IPS\\{$_versionApp}\\setup\\upg_{$_versionId}\\Upgrade";

					if( class_exists( $_className ) )
					{
						$finisher = new $_className;
						
						if ( method_exists( $finisher, 'finish' ) )
						{
							try
							{
								$finisher->finish();
							}
							catch( \Exception $ex )
							{
								$_SESSION['lastSqlError'] = $ex->getMessage() . ' (' . $_className . '->finish() )';
								
								throw $ex;
							}
						} 
					}
				}
			}
		}
		
		/* Move on to next step */
		return array( ( $data[0] + 1 ), 'extra' => $extra );
	}

	/**
	 * Step 2
	 * Insert app data
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step2( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			try
			{
				$application = \IPS\Application::load( $app );
				$application->installJsonData( TRUE );

				/* Upgrade history */
				$versions		= $application->getAllVersions();
				$longVersions	= array_keys( $versions );
				$humanVersions	= array_values( $versions );

				if( count($versions) )
				{
					$latestLVersion	= array_pop( $longVersions );
					$latestHVersion	= array_pop( $humanVersions );

					\IPS\Db::i()->insert( 'core_upgrade_history', array( 'upgrade_version_human' => $latestHVersion, 'upgrade_version_id' => $latestLVersion, 'upgrade_date' => time(), 'upgrade_mid' => (int) \IPS\Member::loggedIn()->member_id, 'upgrade_app' => $app ) );
				}
			}
			catch( \Exception $e )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( $e, 'upgrade_error' );
				}
				catch( \RuntimeException $ex ){}

				throw $e;
			}
			
			/* Update application data */
			if( file_exists( \IPS\ROOT_PATH . '/applications/' . $app . '/data/application.json' ) )
			{
				try
				{
					\IPS\Log::i( LOG_INFO )->write( "Installing application data for " . $app, 'upgrade' );
				}
				catch( \RuntimeException $e ){}
				
				$application	= json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/' . $app . '/data/application.json' ), TRUE );

				//\IPS\Lang::saveCustom( $app, "__app_{$app}", $application['application_title'] );

				unset( $application['app_directory'], $application['app_protected'], $application['application_title'] );

				$app	= \IPS\Application::load( $app );

				foreach( $application as $column => $value )
				{
					$column = str_replace( 'app_', '', $column );
					$app->$column	= $value;
				}

				$app->save( TRUE );
			}
			else
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( "Error: Missing app data", 'upgrade_error' );
				}
				catch( \RuntimeException $e ){}
				throw new \LogicException( 'MISSING_APP_DATA' );
			}
		} );
	}

	/**
	 * Step 3
	 * Update settings, tasks, etc.
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step3( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			try
			{
				\IPS\Log::i( LOG_INFO )->write( "Installing settings, tasks and keywords for " . $app, 'upgrade' );
			}
			catch( \RuntimeException $e ){}
			
			\IPS\Application::load( $app )->installSettings();
			\IPS\Application::load( $app )->installTasks();
			\IPS\Application::load( $app )->installSearchKeywords();
		} );
	}

	/**
	 * Step 4
	 * Update Languages
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step4( $data )
	{
		return $this->appLoop( $data, function( $app ) use ($data)
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}
			
			try
			{
				$inserted = \IPS\Application::load( $app )->installLanguages( $data[2], 250 );
				
				try
				{
					\IPS\Log::i( LOG_INFO )->write( "Inserted language keys for " . $app . ", offset of " . $data[2], 'upgrade' );
				}
				catch( \RuntimeException $e ){}
			}
			catch( \Exception $ex )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( "Step 3, " . $app . ' ' . $ex->getMessage(), 'upgrade_error' );
				}
				catch( \RuntimeException $e ){}
				
				throw $ex;
			}

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
	 * Step 5
	 * Update Email Templates
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step5( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			\IPS\Application::load( $app )->installEmailTemplates();
		} );
	}

	/**
	 * Step 6
	 * Update theme settings
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step6( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			try
			{
				\IPS\Application::load( $app )->installThemeSettings( TRUE );
			}
			catch( \Exception $e )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( "Error : " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'upgrade_error' );
				}
				catch( \RuntimeException $e ){}

				throw $e;
			}
		
			try
			{
				\IPS\Log::i( LOG_INFO )->write( "Installed theme settings for " . $app, 'upgrade' );
			}
			catch( \RuntimeException $e ){}

			return null;
		} );
	}

	/**
	 * Step 7
	 * Clear existing templates
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step7( $data )
	{
		/* Clear old caches */
		\IPS\Data\Cache::i()->clearAll();
		\IPS\Data\Store::i()->clearAll();
		 
		\IPS\Theme::clearFiles( \IPS\Theme::IMAGES );
		
		return $this->appLoop( $data, function( $app ) use( $data )
		{
			try
			{
				/* Deletes old data from the database */
				\IPS\Application::load( $app )->clearTemplates();
			}
			catch( \Exception $e )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( "Error : " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'upgrade_error' );
				}
				catch( \RuntimeException $e ){}
			}
		} );
	}

	/**
	 * Step 8
	 * Update templates
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step8( $data )
	{
		unset( \IPS\Data\Store::i()->settings, \IPS\Data\Store::i()->storageConfigurations );
		
		return $this->appLoop( $data, function( $app ) use( $data )
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}

			try
			{
				$inserted = \IPS\Application::load( $app )->installTemplates( TRUE, $data[2], 150 );
			}
			catch( \Exception $e )
			{
				try
				{
					\IPS\Log::i( LOG_ERR )->write( "Error : " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'upgrade_error' );
				}
				catch( \RuntimeException $e ){}

				throw $e;
			}
			
			try
			{	
				\IPS\Log::i( LOG_INFO )->write( "Installed templates for " . $app . ", offset of " . $data[2], 'upgrade' );
			}
			catch( \RuntimeException $e ){}
			
			if ( $inserted )
			{
				$data[2] += $inserted;
				return $data;
			}
			else
			{
				/* Clear old caches to prevent race conditions attempting to recompile templates before all template bits have been installed */
				\IPS\Data\Cache::i()->clearAll();
				\IPS\Data\Store::i()->clearAll();
		
				return null;
			}
		} );
	}

	/**
	 * Step 9
	 * Update Javascript
	 *
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step9( $data )
	{
		return $this->appLoop( $data, function( $app ) use( $data )
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}

			$inserted = \IPS\Application::load( $app )->installJavascript( $data[2], 150 );
			
			try
			{
				\IPS\Log::i( LOG_INFO )->write( "Installed javascript for " . $app, 'upgrade' );
			}
			catch( \RuntimeException $e ){}

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
	 * Class static methods
	 *
	 */
	 
	 /**
	  * Runs a bunch of legacy SQL queries
	  *
	  * @param	string	$app		App key to run
	  * @param	int		$upgradeId	Current upgrade ID
	  * @param	string	$file		File holding $SQL array
	  * @return TRUE|\IPS\Db\Exception
	  * @note	We ignore some database errors that shouldn't prevent us from continuing.
	  * @li	1050: Can't rename a table as it already exists
	  * @li	1060: Can't add a column as it already exists
	  * @li	1062: Can't add an index as index already exists
	  * @li	1062: Can't add a row as PKEY already exists
	  * @li	1091: Can't drop key or column because it does not exist
	  */
	 public static function runLegacySql( $app, $upgradeId, $file='queries.php' )
	 {
	 	$queryFile = \IPS\ROOT_PATH . '/applications/' . $app . '/setup/upg_' . $upgradeId . '/' . $file;
		$lastIndex = ( isset( $_SESSION['lastJsonIndex'] ) ) ? $_SESSION['lastJsonIndex'] : 0;
		
		if ( file_exists( $queryFile ) )
		{
			require( $queryFile );
			
			if ( is_array( $SQL ) )
			{
				foreach( $SQL as $k => $query )
				{
					if ( $lastIndex AND $lastIndex >= $k )
					{
						continue;
					}

					$_SESSION['lastJsonIndex'] = $k;
					
					try
					{
						\IPS\Db::i()->query( static::addPrefixToQuery( $query ) );
					}
					catch( \IPS\Db\Exception $e )
					{
						if ( ! in_array( $e->getCode(), array( 1050, 1060, 1061, 1062, 1091 ) ) )
						{
							throw $e;
						}
					}
				}
			}
		}
	 }
	 
	/**
	 * Add SQL Prefix to Query
	 *
	 * @param	string		SQL Query
	 * @return  string
	 */
	public static function addPrefixToQuery( $query )
	{
		if ( \IPS\Db::i()->prefix )
		{
			$query = preg_replace( "#^CREATE TABLE(?:\s+?)?(\S+)#is"        , "CREATE TABLE "  . \IPS\Db::i()->prefix . "\\1 ", $query );
			$query = preg_replace( "#^RENAME TABLE(?:\s+?)?(\S+)\s+?TO\s+?(\S+?)(\s|$)#i"     , "RENAME TABLE "  . \IPS\Db::i()->prefix . "\\1 TO " . \IPS\Db::i()->prefix ."\\2", $query );
			$query = preg_replace( "#^DROP TABLE( IF EXISTS)?(?:\s+?)?(\S+)(\s+?)?#i"    , "DROP TABLE \\1 "    . \IPS\Db::i()->prefix . "\\2 ", $query );
			$query = preg_replace( "#^TRUNCATE TABLE(?:\s+?)?(\S+)(\s+?)?#i", "TRUNCATE TABLE ". \IPS\Db::i()->prefix . "\\1 ", $query );
			$query = preg_replace( "#^DELETE FROM(?:\s+?)?(\S+)(\s+?)?#i"   , "DELETE FROM "   . \IPS\Db::i()->prefix . "\\1 ", $query );
			$query = preg_replace( "#^INSERT INTO(?:\s+?)?(\S+)\s+?#i"      , "INSERT INTO "   . \IPS\Db::i()->prefix . "\\1 ", $query );
			//$query = preg_replace( "#^INSERT IGNORE INTO(?:\s+?)?(\S+)\s+?#i", "INSERT IGNORE INTO "   . \IPS\Db::i()->prefix . "\\1 ", $query );
			$query = preg_replace( "#^UPDATE(?:\s+?)?(\S+)\s+?#i"           , "UPDATE "        . \IPS\Db::i()->prefix . "\\1 ", $query );
			$query = preg_replace( "#^REPLACE INTO(?:\s+?)?(\S+)\s+?#i"     , "REPLACE INTO "  . \IPS\Db::i()->prefix . "\\1 ", $query );
			$query = preg_replace( "#^ALTER TABLE(?:\s+?)?(\S+)\s+?#i"      , "ALTER TABLE "   . \IPS\Db::i()->prefix . "\\1 ", $query );			
			
			$query = preg_replace( "#^CREATE INDEX (\S+) ON (\S+) #", "CREATE INDEX \\1 ON " . \IPS\Db::i()->prefix . "\\2 " , $query );
			$query = preg_replace( "#^CREATE UNIQUE INDEX (\S+) ON (\S+) #", "CREATE UNIQUE INDEX \\1 ON " . \IPS\Db::i()->prefix . "\\2 " , $query );
		}

		return $query;
	}
	
	/**
	 * Run manual queries, that may be rather large
	 *
	 * @param	array	$queries		Array of queries in the following format ( table => x, query = x );
	 * @return  array
	 */
	public static function runManualQueries( $queries )
	{
		$toReturn    = array();
		$tableCounts = array();
		
		foreach( $queries as $id => $data )
		{
			if ( ! isset( $tableCounts[ $data['table'] ] ) and \IPS\Db::i()->checkForTable( $data['table'] ) )
			{
				$tableCounts[ $data['table'] ] = \IPS\Db::i()->select( 'count(*)', $data['table'] )->first();
			}
		}
		
		foreach( $queries as $id => $data )
		{
			if( isset( $tableCounts[ $data['table'] ] ) AND $tableCounts[ $data['table'] ] > \IPS\UPGRADE_MANUAL_THRESHOLD )
	        {
	        	try
	        	{
	            	\IPS\Log::i( LOG_INFO )->write( "Big table " . $data['table'] . ", storing query to run manually", 'upgrade' );
	            }
	            catch( \RuntimeException $e ){}
	            
	            $toReturn[] = $data['query'];
	
	            continue;
			}
			else
			{
				\IPS\Db::i()->query( $data['query'] );
			}
		}
		
		return $toReturn;
	}

	/**
	 * Determine what our cutoff should be for long running queries
	 *
	 * @return  null|int
	 */
	public static function determineCutoff()
	{
		$cutOff	= 30;

		if( $maxExecution = @ini_get( 'max_execution_time' ) AND $maxExecution < $cutOff )
		{
			$cutOff	= $maxExecution;
		}

		return time() + ( $cutOff * .6 );
	}
}