<?php
/**
 * @brief		Log Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		07 Nov 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Log Class
 */
abstract class _Log
{
	/**
	 * @brief	Multitons
	 */
	protected static $instances = array();
	
	/**
	 * @brief	Configuration
	 */
	protected static $configuration = NULL;
	
	/**
	 * Log
	 *
	 * Severity constants
	 * @li LOG_EMERG	system is unusable
	 * @li LOG_ALERT	action must be taken immediately
	 * @li LOG_CRIT		critical conditions
	 * @li LOG_ERR		error conditions
	 * @li LOG_WARNING	warning conditions
	 * @li LOG_NOTICE	normal, but significant, condition
	 * @li LOG_INFO		informational message
	 * @li LOG_DEBUG	debug-level message
	 * 
	 * LOG_METHOD example JSON object
	 * [
	 * 	{
	 * 	  'levels': [0,1,2,3,4,5],
	 *    'method': 'disk'
	 *  },
	 *  {
	 *    'levels': [6,7],
	 *    'method': 'email',
	 *    'config': { 'to': 'sales@invisionpower.com', 'subject': 'New error log from My Community' }
	 *  }
	 * ]
	 * 
	 * Acceptable methods are "disk", "email" and "syslog".
	 * 
	 * Caveats:
	 * @li Email method falls back to PHPs 'mail' command if using the framework fails (critical DB error, etc). If your server cannot use
	 * 	   PHPs mail, then do not use the email method.
	 * 
	 * Any missing levels (between 0 and 6) are populated by 'disk' method. 7 is debug and can be left blank
	 * 
	 * @param		int|string	$key		PHP syslog constant (int) or method name (string)
	 * @return		object      \IPS\Log
	 *
	 * @see			<a href='http://tools.ietf.org/html/rfc5424'>RFC 5424</a>
	 * @see			<a href='http://php.net/syslog'>PHP's syslog</a>
	 */
	public static function i( $key )
	{
		if ( is_string( $key ) )
		{
			$severity = static::getSeverityFromMethod( $key );
		}
		else
		{
			$severity = intval( $key );
		}
		
		$config = static::getConfiguration();
		
		if ( !isset( static::$instances[ $severity ] ) )
		{
			$classname = '\IPS\Log\\' . ucfirst( $config[ $severity ]['method'] );
			static::$instances[ $severity ] = new $classname( $config[ $severity ]['config'], $severity );
		}
	   	
		/* One method can have multiple severities, so lets set the current severity for this call */
		static::$instances[ $severity ]->setSeverity( $severity );
		
		return static::$instances[ $severity ];
	}
	
	/**
	 * Return a severity level based on method name (disk, syslog, etc)
	 *
	 * @param	string	$method		Method name (disk, syslog, etc)
	 * @return  int
	 */
	public static function getSeverityFromMethod( $method )
	{
		foreach( static::getUsedMethods() as $method => $levels )
		{
			$severity = array_pop( $levels );
			return $severity;
		}	
		
		return LOG_DEBUG;
	}
	
	/**
	 * Return an array of used methods with the severity levels assigned
	 * 
	 * @return array
	 */
	public static function getUsedMethods()
	{
		$config  = static::getConfiguration();
		$methods = array();
		
		foreach( $config as $level => $data )
		{
			$methods[ $data['method'] ][] = $level;
		}
		
		return $methods;
	}
	
	/**
	 * Returns the configuration from LOG_METHOD parsed and checked
	 * 
	 * @return array
	 */
	protected static function getConfiguration()
	{
		if ( static::$configuration === NULL )
		{
			$json = @json_decode( LOG_METHOD, TRUE );
		
			if ( is_array( $json )  )
			{
				foreach( $json as $row )
				{
					if ( isset( $row['levels'] ) AND isset( $row['method'] ) AND is_array( $row['levels'] ) )
					{
						foreach( $row['levels'] as $level )
						{
							static::$configuration[ $level ]['method'] = $row['method'];
							static::$configuration[ $level ]['config'] = ( isset( $row['config'] ) ) ? $row['config'] : NULL;
						}
					}
				}
			}
		
			/* Ensure everything except DEBUG has a value */
			foreach( range( 0, 6 ) as $level )
			{
				if ( ! isset( static::$configuration[ $level ] ) )
				{
					static::$configuration[ $level ]['method'] = 'disk';
					static::$configuration[ $level ]['config'] = NULL;
				}
			}

			/* And then make sure DEBUG has a value set to None if not already set */
			if ( ! isset( static::$configuration[ 7 ] ) )
			{
				static::$configuration[ 7 ]['method'] = 'none';
				static::$configuration[ 7 ]['config'] = NULL;
			}
		}
		
		return static::$configuration;
	}
	
	/**
	 * Prune logs where possible
	 * 
	 * @param	int		$days	Older than (days) to prune
	 * @return void
	 */
	public static function pruneLogs( $days )
	{
		if ( ! $days )
		{
			return;
		}
		
		foreach( static::getUsedMethods() as $method => $levels )
		{
			\IPS\Log::i( $method )->prune( $days );
		}
	}
	
	/* @Brief Configuration array  */
	protected $config = NULL;
	
	/* @Brief Severity level */
	protected $severity = NULL;
	
	/**
	 * The constructor
	 *
	 * @param   array|null $config
	 * @param	int		   $severity
	 * @return	void
	 */
	public function __construct( $config, $severity )
	{
		$this->config   = $config;
		$this->setSeverity( $severity );
	}
	
	/**
	 * Store the severity for this call
	 * 
	 * @param	int	$severity	Severity
	 * @return	\IPS\Log
	 */
	public function setSeverity( $severity )
	{
		$this->severity = $severity;
		return $this;
	}

	/**
	 * @brief	IP address to use or NULL to retrieve from Request class
	 */
	protected $ipAddress	= NULL;

	/**
	 * Set the IP address
	 * 
	 * @param	string	$ip	IP address
	 * @return	\IPS\Log
	 * @note	This method is designed so that we can bypass issues with \IPS\Settings not loaded yet for instance when logging cache-related calls (since settings are stored in cache)
	 */
	public function setIpAddress( $ip )
	{
		$this->ipAddress = $ip;
		return $this;
	}

	/**
	 * Get the IP address
	 * 
	 * @return	string
	 * @note	This call will reset the stored IP address every time so that other log requests use the correct IP
	 */
	public function getIpAddress()
	{
		$result	= ( $this->ipAddress !== NULL ) ? $this->ipAddress : \IPS\Request::i()->ipAddress();
		$this->ipAddress	= NULL;
		return $result;
	}
	
	/**
	 * Prune logs
	 *
	 * @param	int		$days	Older than (days) to prune
	 * @return	void
	 */
	public function prune( $days )
	{
		
	}
}