<?php
/**
 * @brief		eAccelerator Cache Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Sept 2013
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
 * eAccelerator Storage Class
 */
class _Eaccelerator extends \IPS\Data\Cache
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return function_exists('eaccelerator_get');
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
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Get {$key} from eAccelerator (already loaded)", 'cache' );
			return $this->cache[ $key ];
		}

		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Get {$key} from eAccelerator", 'cache' );
		
		$this->cache[ $key ]	= eaccelerator_get( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
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
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Set {$key} in eAccelerator", 'cache' );

		eaccelerator_lock( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
		$result	= (bool) eaccelerator_put( \IPS\SUITE_UNIQUE_KEY . '_' . $key, $value );
		eaccelerator_unlock( \IPS\SUITE_UNIQUE_KEY . '_' . $key );

		return $result;
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
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Check exists {$key} from eAccelerator (already loaded)", 'cache' );
			return TRUE;
		}

		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Check exists {$key} from eAccelerator", 'cache' );
		return (bool) $this->get( $key );
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function delete( $key )
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Delete {$key} from eAccelerator", 'cache' );
		return (bool) eaccelerator_rm( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
	}

	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		if( function_exists( 'eaccelerator_gc' ) )
		{
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "eAccelerator GC", 'cache' );
			eaccelerator_gc();
		}
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Flush all caches from eAccelerator (not implemented)", 'cache' );
	}
}