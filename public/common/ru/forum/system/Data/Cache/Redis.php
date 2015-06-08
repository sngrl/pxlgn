<?php
/**
 * @brief		Redis Cache Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Data\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redis Cache Class
 */
class _Redis extends \IPS\Data\Cache
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return class_exists('Redis');
	}
	
	/**
	 * Configuration
	 *
	 * @param	array	$configuration	Existing settings
	 * @return	array	\IPS\Helpers\Form\FormAbstract elements
	 */
	public static function configuration( $configuration )
	{
		return array(
			'server'	=> new \IPS\Helpers\Form\Text( 'server_host', isset( $configuration['server'] ) ? $configuration['server'] : '', FALSE, array( 'placeholder' => '127.0.0.1' ), function( $val )
			{
				if ( \IPS\Request::i()->cache_method === 'Redis' and empty( $val ) )
				{
					throw new \DomainException( 'datastore_redis_servers_err' );
				}
			} ),
			'port'		=> new \IPS\Helpers\Form\Number( 'server_port', isset( $configuration['port'] ) ? $configuration['port'] : NULL, FALSE, array( 'placeholder' => '6379' ), function( $val )
			{
				if ( \IPS\Request::i()->cache_method === 'Redis' AND $val AND ( $val < 0 OR $val > 65535 ) )
				{
					throw new \DomainException( 'datastore_redis_servers_err' );
				}
			} ),
			'password'	=> new \IPS\Helpers\Form\Password( 'server_password', isset( $configuration['password'] ) ? $configuration['password'] : '', FALSE ),
		);
	}

	/**
	 * @brief	Connection resource
	 */
	protected $link = null;

	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		/* Connect to server */
		try
		{
			$this->link	= new \Redis;

			if( $this->link->connect( $configuration['server'], $configuration['port'] ) === FALSE )
			{
				$this->link	= NULL;
			}
			else
			{
				if( $configuration['password'] )
				{
					if( $this->link->auth( $configuration['password'] ) === FALSE )
					{
						$this->link	= NULL;
					}
				}
			}

			if( $this->link !== NULL )
			{
				$this->link->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP );
			}

			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Connected to Redis", 'cache' );
		}
		catch( \RedisException $e )
		{
			$this->link	= NULL;
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Connection to Redis failed", 'cache' );
		}
	}

	/**
	 * Abstract Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 */
	protected function get( $key )
	{
		if( isset( $this->cache[ $key ] ) )
		{
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Get {$key} from Redis (already loaded)", 'cache' );
			return $this->cache[ $key ];
		}

		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Get {$key} from Redis", 'cache' );

		$this->cache[ $key ]	= $this->link->get( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
		return $this->cache[ $key ];
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	bool
	 */
	protected function set( $key, $value )
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Set {$key} in Redis", 'cache' );
		return (bool) $this->link->set( \IPS\SUITE_UNIQUE_KEY . '_' . $key, $value );
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function exists( $key )
	{
		if( isset( $this->cache[ $key ] ) )
		{
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Check exists {$key} from Redis (already loaded)", 'cache' );
			return TRUE;
		}

		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Check exists {$key} from Redis", 'cache' );
		return $this->link->exists( $key );
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function delete( $key )
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Delete {$key} from Redis", 'cache' );
		return (bool) $this->link->delete( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
	}

	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		if( $this->link )
		{
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Disconnect from Redis", 'cache' );

			$this->link->close();
		}
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Flush all caches from Redis (not implemented)", 'cache' );
	}
}