<?php
/**
 * @brief		File System Storage Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		07 May 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Data\Store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File System Storage Class
 */
class _FileSystem extends \IPS\Data\Store
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return TRUE;
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
			'path'	=> new \IPS\Helpers\Form\Text( 'datastore_filesystem_path', ( isset( $configuration['path'] ) ) ? rtrim( str_replace( '{root}', \IPS\ROOT_PATH, $configuration['path'] ), '/' ) : \IPS\ROOT_PATH . '/datastore', FALSE, array(), function( $val )
			{
				if ( \IPS\Request::i()->datastore_method === 'FileSystem' )
				{
					if ( !is_dir( $val ) and is_writable( $val ) )
					{
						mkdir( $val );
						chmod( $val, \IPS\IPS_FOLDER_PERMISSION );
						\file_put_contents( $val . '/index.html', '' );
					}
					
					if ( !is_dir( $val ) or !is_writable( $val ) )
					{
						throw new \DomainException( 'datastore_filesystem_path_err' );
					}
				}
			} )
		);
	}
	
	/**
	 * @brief	Cache
	 */
	protected static $cache	= array();
	
	/**
	 * @brief	Storage Path
	 */
	public $_path;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		$this->_path = rtrim( str_replace( '{root}', \IPS\ROOT_PATH, $configuration['path'] ), '/' );
	}
	
	/**
	 * Abstract Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 */
	public function get( $key )
	{
		return file_get_contents( $this->_path . '/' . $key . '.' . \IPS\SUITE_UNIQUE_KEY . '.ipsstore' );
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	bool
	 */
	public function set( $key, $value )
	{
		$result = (bool) @\file_put_contents( $this->_path . '/' . $key . '.' . \IPS\SUITE_UNIQUE_KEY . '.ipsstore', $value );

		@chmod( $this->_path . '/' . $key . '.' . \IPS\SUITE_UNIQUE_KEY . '.ipsstore', \IPS\IPS_FILE_PERMISSION );

		return $result;
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function exists( $key )
	{
		return is_file( $this->_path . '/' . $key . '.' . \IPS\SUITE_UNIQUE_KEY . '.ipsstore' );
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function delete( $key )
	{
		if ( file_exists( $this->_path . '/' . $key . '.' . \IPS\SUITE_UNIQUE_KEY . '.ipsstore' ) )
		{
			return @unlink( $this->_path . '/' . $key . '.' . \IPS\SUITE_UNIQUE_KEY . '.ipsstore' );
		}
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll( $exclude=NULL )
	{
		foreach ( new \DirectoryIterator( $this->_path ) as $file )
		{			
			if ( !$file->isDot() and mb_substr( $file, -9 ) === '.ipsstore' and $file != $exclude . '.ipsstore' )
			{
				@unlink( $this->_path . '/' . $file );
			}
		}
	}
}