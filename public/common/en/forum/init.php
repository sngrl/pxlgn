<?php
/**
 * @brief		Initiates IPS Social Suite constants, autoloader and exception handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/**
 * Class to contain IPS Social Suite autoloader and exception handler
 */
class IPS
{	
	/**
	 * @brief	Classes that have hooks on
	 */
	public static $hooks = array();
	
	/**
	 * @brief	Unique key for this suite (used in http requests to defy browser caching)
	 */
	public static $suiteUniqueKey = NULL;
	
	/**
	 * @brief	Developer Code to be added to all namespaces
	 */
	private static $inDevCode = '';
	
	/**
	 * @brief	Namespaces developer code has been imported to
	 */
	private static $inDevCodeImportedTo = array();
	
	/**
	 * @brief	Vendors to use PSR-0 autoloader for
	 */
	public static $PSR0Namespaces = array();

	/**
	 * Get default constants
	 *
	 * @return	array
	 */
	public static function defaultConstants()
	{
		return array(
			'CP_DIRECTORY'						=> 'admin',
			'IN_DEV'							=> FALSE,
			'DEV_USE_WHOOPS'					=> TRUE,
			'DEV_USE_FURL_CACHE'				=> FALSE,
			'DEBUG_JS'							=> FALSE,
			'DEV_DEBUG_JS'						=> TRUE,
			'DEV_DEBUG_CSS'						=> FALSE,
			'DEV_DEBUG_TEMPLATES'				=> FALSE,
			'IPS_FOLDER_PERMISSION'				=> 0777,
			'FOLDER_PERMISSION_NO_WRITE'		=> 0755,
			'IPS_FILE_PERMISSION'				=> 0644,
			'ROOT_PATH'							=> __DIR__,
			'NO_WRITES'							=> FALSE,
			'LOG_METHOD'						=> '{}',
			'STORE_METHOD'						=> 'FileSystem',
			'STORE_CONFIG'						=> '{"path":"{root}/datastore"}',
			'CACHE_METHOD'						=> 'None',
			'CACHE_CONFIG'						=> '{}',
			'CACHE_PAGE_TIMEOUT'				=> 180,
			'TEST_CACHING'						=> FALSE,
			'EMAIL_DEBUG_PATH'					=> NULL,
			'BULK_MAILS_PER_CYCLE'				=> 0,
			'JAVA_PATH'							=> "",
			'ERROR_PAGE'						=> 'error.html',
			'QUERY_LOG'							=> FALSE,
			'CACHING_LOG'						=> FALSE,
			'ENFORCE_ACCESS'					=> FALSE,
			'THUMBNAIL_SIZE'					=> '500x500',
			'COOKIE_DOMAIN'						=> NULL,
			'COOKIE_PREFIX'						=> 'ips4_',
			'COOKIE_PATH'						=> NULL,
			'COOKIE_BYPASS_SSLONLY'				=> FALSE,
			'CONNECT_NOSYNC_NAMES'				=> FALSE,
			'BYPASS_CURL'						=> FALSE,
			'FORCE_CURL'						=> FALSE,
			'NEXUS_TEST_GATEWAYS'				=> FALSE,
			'UPGRADE_MANUAL_THRESHOLD'			=> 150000,
			'UPGRADE_LARGE_THRESHOLD'			=> 100000,
			'HTMLENTITIES'						=> ( version_compare( phpversion(), '5.4.0', '<' ) ) ? ENT_IGNORE : ENT_DISALLOWED,
			'SUITE_UNIQUE_KEY'					=> mb_substr( md5( 'a97a9' . '$Rev: 3023$'), 10, 10 ),
			'USE_DEVELOPMENT_BUILDS'			=> FALSE,
			'DEV_WHOOPS_EDITOR'					=> NULL,
			'DEFAULT_REQUEST_TIMEOUT'			=> 5,		// In seconds
			'TEMP_DIRECTORY'					=> sys_get_temp_dir()
			);
	}
	
	/**
	 * Initiate IPS Social Suite constants, autoloader and exception handler
	 *
	 * @return	void
	 */
	public static function init()
	{
		/* Set timezone */
		date_default_timezone_set( 'UTC' );

		/* Set default MB internal encoding */
		mb_internal_encoding('UTF-8');

		/* Define the IN_IPB constant - this needs to be in the global namespace for backwards compatibility */
		define( 'IN_IPB', TRUE );
			
		/* Load constants.php */
		if( file_exists( __DIR__ . '/constants.php' ) )
		{
			@include_once( __DIR__ . '/constants.php' );
		}
				
		/* Import and set defaults */
		$defaultConstants = static::defaultConstants();

		foreach ( $defaultConstants as $k => $v )
		{
			if( defined( $k ) )
			{
				define( 'IPS\\' . $k, constant( $k ) );
			}
			else
			{
				define( 'IPS\\' . $k, $v );
			}
		}

		/* If they have customized the ACP directory but it doesn't exist, throw an error */
		if( !is_dir( ROOT_PATH . '/' . CP_DIRECTORY ) AND CP_DIRECTORY != $defaultConstants['CP_DIRECTORY'] )
		{
			die( "You have defined a custom ACP directory (CP_DIRECTORY) in constants.php, however it is not valid.  Please remove or correct this constant definition." );
		}
		
		/* Load developer code */
		if( IN_DEV and file_exists( ROOT_PATH . '/dev/function_overrides.php' ) )
		{
			self::$inDevCode = file_get_contents( ROOT_PATH . '/dev/function_overrides.php' );
		}
		
		/* Set autoloader */
		spl_autoload_register( '\IPS\IPS::autoloader', true, true );
				
		/* Set error handlers */
		if ( \IPS\IN_DEV AND \IPS\DEV_USE_WHOOPS and file_exists( ROOT_PATH . '/dev/Whoops/Run.php' ) )
		{
			self::$PSR0Namespaces['Whoops'] = ROOT_PATH . '/dev/Whoops';
			$whoops = new \Whoops\Run;
			$ipsHandler =  new \Whoops\Handler\IPSHandler;
			if ( \IPS\DEV_WHOOPS_EDITOR )
			{
				$ipsHandler->setEditor( \IPS\DEV_WHOOPS_EDITOR );
			}
			$whoops->pushHandler( $ipsHandler );
			$jsonHandler = new \Whoops\Handler\JsonResponseHandler;
			$jsonHandler->onlyForAjaxRequests( true );
			$whoops->pushHandler( $jsonHandler );
			$whoops->register();
		}
		else
		{
			set_error_handler( '\IPS\IPS::errorHandler' );
			set_exception_handler( '\IPS\IPS::exceptionHandler' );
		}

		/* Init hooks */
		if ( file_exists( \IPS\ROOT_PATH . '/plugins/hooks.txt' ) )
		{
			$mtime = filemtime( \IPS\ROOT_PATH . '/plugins/hooks.txt' );
			
			$encrypted = file_get_contents( \IPS\ROOT_PATH . '/plugins/hooks.txt' );			
			$decrypted = '';
			for ( $i = 0; $i < mb_strlen( $encrypted ); $i++ )
			{
				$decrypted .= chr( ord( mb_substr( $encrypted, $i, 1 ) ) - mb_substr( $mtime, ( $i % mb_strlen( $mtime ) ) - 1, 1 ) );
			}
						
			if ( !( self::$hooks = @json_decode( $decrypted, TRUE ) ) )
			{
				self::$hooks = array();
			}
		}
	}

	/**
	 * Autoloader
	 *
	 * @param	string	$classname	Class to load
	 * @return	void
	 */
	public static function autoloader( $classname )
	{
		/* Separate by namespace */
		$bits = explode( '\\', ltrim( $classname, '\\' ) );
								
		/* If this doesn't belong to us, try a PSR-0 loader or ignore it */
		$vendorName = array_shift( $bits );
		if( $vendorName !== 'IPS' )
		{			
			if ( isset( self::$PSR0Namespaces[ $vendorName ] ) )
			{
				@include_once( self::$PSR0Namespaces[ $vendorName ] . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $bits ) . '.php' );
			}
			
			return;
		}
		
		/* Work out what namespace we're in */
		$class = array_pop( $bits );
		$namespace = empty( $bits ) ? 'IPS' : ( 'IPS\\' . implode( '\\', $bits ) );
		$inDevCode = '';
				
		/* We only need to load the file if we don't have the underscore-prefixed one */
		if( !class_exists( "{$namespace}\\_{$class}", FALSE ) )
		{			
			/* Locate file */
			$path = ROOT_PATH . '/';
			$sourcesDirSet = FALSE;
			foreach ( array_merge( $bits, array( $class ) ) as $i => $bit )
			{
				if( preg_match( "/^[a-z0-9]/", $bit ) )
				{
					if( $i === 0 )
					{
						$path .= 'applications/';
					}
					else
					{
						$sourcesDirSet = TRUE;
					}
				}
				elseif ( $i === 3 and $bit === 'Upgrade' )
				{
					$bit = mb_strtolower( $bit );
				}
				elseif( $sourcesDirSet === FALSE )
				{
					if( $i === 0 )
					{
						$path .= 'system/';
					}
					elseif ( $i === 1 and $bit === 'Application' )
					{
						// do nothing
					}
					else
					{
						$path .= 'sources/';
					}
					$sourcesDirSet = TRUE;
				}
							
				$path .= "{$bit}/";
			}
						
			/* Load it */
			$path = \substr( $path, 0, -1 ) . '.php';
			if( !file_exists( $path ) )
			{
				$path = \substr( $path, 0, -4 ) . \substr( $path, \strrpos( $path, '/' ) );
				if ( !file_exists( $path ) )
				{
					return FALSE;
				}
			}			
			require_once( $path );
			
			/* Is it an interface? */
			if ( interface_exists( "{$namespace}\\{$class}", FALSE ) )
			{
				return;
			}
							
			/* Doesn't exist? */
			if( !class_exists( "{$namespace}\\_{$class}", FALSE ) )
			{
				trigger_error( "Class {$classname} could not be loaded. Ensure it has been properly prefixed with an underscore and is in the correct namespace.", E_USER_ERROR );
			}
						
			/* Stuff for developer mode */
			if( IN_DEV )
			{
				try
				{
					$reflection = new \ReflectionClass( "{$namespace}\\_{$class}" );
					
					/* Import our code to override forbidden functions */
					if( !in_array( \strtolower( $namespace ), self::$inDevCodeImportedTo ) )
					{
						$inDevCode = self::$inDevCode;
						self::$inDevCodeImportedTo[] = \strtolower( $namespace );
					}
										
					/* Any classes which extend a core PHP class are exempt from our rules */
					$parent = $reflection->getParentClass();
					if( $parent === false or \substr( $parent->getNamespaceName(), 0, 3 ) === 'IPS' )
					{
						/* Make sure it's name follows our standards */
						if( !preg_match( '/^_[A-Z]+$/i', $reflection->getShortName() ) )
						{
							trigger_error( "{$classname} does not follow our naming conventions. Please rename using only alphabetic characters and PascalCase. (PHP Coding Standards: Classes.5)", E_USER_ERROR );
						}
						
						/* Loop methods */
						$hasNonAbstract = FALSE;
						$hasNonStatic = FALSE;
						foreach ( $reflection->getMethods() as $method )
						{	
							if ( \substr( $method->getDeclaringClass()->getName(), 0, 3 ) === 'IPS' )
							{
								/* Make sure it's not private */
								if( $method->isPrivate() )
								{
									trigger_error( "{$classname}::{$method->name} is declared as private. In order to ensure that hooks are able to work freely, please use protected instead. (PHP Coding Standards: Functions and Methods.4)", E_USER_ERROR );
								}
							
								/* We need to know for later if we have non-abstract methods */
								if( !$method->isAbstract() )
								{
									$hasNonAbstract = TRUE;
								}
								
								/* We need to know for later if we have non-static methods */
								if( !$method->isStatic() )
								{
									$hasNonStatic = TRUE;
								}
								
								/* Make sure the name follows our conventions */
								if(
									!preg_match( '/^_?[a-z][A-Za-z0-9]*$/', $method->name )	// Normal pattern most methods should match
									and
									!preg_match( '/^get_/i', $method->name )		// get_* is allowed
									and
									!preg_match( '/^set_/i', $method->name )		// set_* is allowed
									and
									!preg_match( '/^parse_/i', $method->name )		// parse_* is allowed
									and
									!preg_match( '/^setBitwise_/i', $method->name )	// set_Bitiwse_* is allowed
									and
									!in_array( $method->name, array(					// PHP's magic methods are allowed (except __sleep and __wakeup as we don't allow serializing)
										'__construct',
										'__destruct',
										'__call',
										'__callStatic',
										'__get',
										'__set',
										'__isset',
										'__unset',
										'__toString',
										'__invoke',
										'__set_state',
										'__clone',
									) )
								) {
									trigger_error( "{$classname}::{$method->name} does not follow our naming conventions. Please rename using only alphabetic characters and camelCase. (PHP Coding Standards: Functions and Methods.1-3)", E_USER_ERROR );
								}
							}
						}
						
						/* Loop properties */
						foreach ( $reflection->getProperties() as $property )
						{
							$hasNonAbstract = TRUE;
							
							/* Make sure it's not private */
							if( $property->isPrivate() )
							{
								trigger_error( "{$classname}::\${$property->name} is declared as private. In order to ensure that hooks are able to work freely, please use protected instead. (PHP Coding Standards: Properties and Variables.3)", E_USER_ERROR );
							}
						
							/* Make sure the name follows our conventions */
							if( !preg_match( '/^_?[a-z][A-Za-z]*$/', $property->name ) )
							{
								trigger_error( "{$classname}::\${$property->name} does not follow our naming conventions. Please rename using only alphabetic characters and camelCase. (PHP Coding Standards: Properties and Variables.1-2)", E_USER_ERROR );
							}
						}
						
						/* Check an interface wouldn't be more appropriate */
						if( !$hasNonAbstract )
						{
							trigger_error( "You do not have any non-abstract methods in {$classname}. Please use an interface instead. (PHP Coding Standards: Classes.7)", E_USER_ERROR );
						}
						
						/* Check we have at least one non-static method (unless this class is abstract or has a parent) */
						elseif( !$reflection->isAbstract() and $reflection->getParentClass() === FALSE and !$hasNonStatic and $reflection->getNamespacename() !== 'IPS\Output\Plugin' and !in_array( 'extensions', $bits ) and !in_array( 'templateplugins', $bits ) )
						{
							trigger_error( "You do not have any methods in {$classname} which are not static. Please refactor. (PHP Coding Standards: Functions and Methods.6)", E_USER_ERROR );
						}
					}
				}
				catch ( \Exception $e ) {}
			}
		}
										
		/* Monkey Patch */
		self::monkeyPatch( $namespace, $class, $inDevCode );
	}
	
	/**
	 * Monkey Patch
	 *
	 * @param	string	$namespace	The namespace
	 * @param	string	$finalClass	The final class name we want to be able to use (without namespace)
	 * @param	string	$extraCode	Any additonal code to import before the class is defined
	 * @return	null
	 */
	public static function monkeyPatch( $namespace, $finalClass, $extraCode = '' )
	{		
		$realClass = "_{$finalClass}";
		if( isset( self::$hooks[ "\\{$namespace}\\{$finalClass}" ] ) )
		{
			foreach ( self::$hooks[ "\\{$namespace}\\{$finalClass}" ] as $id => $data )
			{
				$contents = "namespace {$namespace}; ". str_replace( '_HOOK_CLASS_', $realClass, file_get_contents( ROOT_PATH . '/' . $data['file'] ) );
				if( @eval( $contents ) !== FALSE )
				{
					$realClass = $data['class'];
				}
			}
		}
		
		$reflection = new \ReflectionClass( "{$namespace}\\_{$finalClass}" );
		if( eval( "namespace {$namespace}; ". $extraCode . ( $reflection->isAbstract() ? 'abstract' : '' )." class {$finalClass} extends {$realClass} {}" ) === FALSE )
		{
			trigger_error( "There was an error initiating the class {$namespace}\\{$finalClass}.", E_USER_ERROR );
		}		
	}
	
	/**
	 * Error Handler
	 *
	 * @param	int		$errno		Error number
	 * @param	errstr	$errstr		Error message
	 * @param	string	$errfile	File
	 * @param	int		$errline	Line
	 * @param	array	$trace		Backtrace
	 * @return	void
	 */
	public static function errorHandler( $errno, $errstr, $errfile, $errline, $trace=NULL )
	{
		/* We don't care about these in production */
		if ( in_array( $errno, array( E_NOTICE, E_DEPRECATED ) ) )
		{
			return;
		}

		/* This means the error suppressor was used, so we should ignore any non-fatal errors */
		if ( error_reporting() === 0 )
		{
			return false;
		}
		
		throw new \ErrorException( $errstr, $errno, 0, $errfile, $errline );
	}
	
	/**
	 * Exception Handler
	 *
	 * @param	\Exception	$exception	Exception class
	 * @return	void
	 */
	public static function exceptionHandler( $exception )
	{
		try
		{
			\IPS\Log::i( \LOG_CRIT )->write( get_class( $exception ) . "\n" . $exception->getCode() . ": " . $exception->getMessage() . "\n" . $exception->getTraceAsString() );
		}
		catch ( \Exception $e ){}
		
		try
		{
			\IPS\Output::i()->error( 'generic_error', "EX{$exception->getCode()}", 500 );
		}
		catch ( \Exception $e )
		{
			if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
			{
				header( "HTTP/1.0 500 Internal Server Error" );
			}
			else
			{
				header( "HTTP/1.1 500 Internal Server Error" );
			}
						
			require \IPS\ROOT_PATH . '/' . \IPS\ERROR_PAGE;
		}
		exit;
	}
	
	/**
	 * Get license key data
	 *
	 * @return	array|NULL
	 */
	public static function licenseKey()
	{
		/* Get the cached value */
		$cached = NULL;
		if ( isset( \IPS\Data\Store::i()->license_data ) )
		{
			$cached = \IPS\Data\Store::i()->license_data;

			/* If it's younger than 21 days, just use that */
			if ( $cached['fetched'] > ( time() - 1814400 ) )
			{
				return $cached['data'];
			}
		}

		/* Call the main server */
		try
		{
			$response = \IPS\Http\Url::ips( 'license/' . \IPS\Settings::i()->ipb_reg_number )->request()->get();
			if ( $response->httpResponseCode == 404 )
			{
				return NULL;
			}
			$response = $response->decodeJson();
		}
		catch ( \Exception $e )
		{
			/* If the server is offline right now, use the cached value from above */
			return $cached;
		}
		
		/* Update the license info in the store */
		\IPS\Data\Store::i()->license_data	= array( 'fetched' => time(), 'data' => $response );

		/* Return */
		return $response;
	}
	
	/**
	 * Check license key
	 *
	 * @param	string	The license key
	 * @param	string	The site URL
	 * @return	void
	 * @throws	\DomainException
	 */
	public static function checkLicenseKey( $val, $url )
	{
		$test = FALSE;
		if ( mb_substr( $val, -12 ) === '-TESTINSTALL' )
		{
			$test = TRUE;
			$val = mb_substr( $val, 0, -12 );
		}
		$urlKey = $test ? 'test_url' : 'url';
				
		try
		{
			$response = \IPS\Http\Url::ips( 'license/' . $val )->setQueryString( $urlKey, $url )->request()->get();
			switch ( $response->httpResponseCode )
			{
				case 200:
					$response = json_decode( $response, TRUE );
					if ( $response['legacy'] )
					{
						throw new \DomainException( 'license_key_legacy' );
					}
					
					/* Previously, URL's were stored as http://domain.com/forums - this check here may throw a "key already in use" error if upgrading or setting up a test install */
					if ( rtrim( $response[ $urlKey ], '/' ) !== rtrim( $url, '/' ) )
					{
						if ( $response[ $urlKey ] )
						{
							throw new \DomainException( $test ? 'license_key_test_active' : 'license_key_active' );
						}
						else
						{
							\IPS\Http\Url::ips( 'license/' . $val )->request()->post( array(
								$urlKey	=> $url
							) );
						}
					}
					break;
					
				case 404:
					throw new \DomainException( 'license_key_not_found' );
				
				default:
					throw new \DomainException( 'generic_license_error' );
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \DomainException( sprintf( \IPS\Member::loggedIn()->language()->get( 'license_server_error' ), $e->getMessage() ) );
		}
	}
}

/* Init */
IPS::init();