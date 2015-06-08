<?php
/**
 * @brief		File Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		19 Feb 2013
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
 * File Class
 */
abstract class _File
{	
	/**
	 * @brief	File extensions considered safe. Not an exhaustive list but these are the files we're most interested in being recognised.
	 */
	public static $safeFileExtensions = array( 'js', 'css', 'txt', 'xml', 'gif', 'jpg', 'jpe', 'jpeg', 'png', 'mp4', 'mov', 'ogg', 'mp3', 'mpg', 'mpeg', 'ico', 'flv', 'webm', 'wmv', 'avi' );
	
	/**
	 * @brief	Does this storage method support chunked uploads?
	 */
	public static $supportsChunking = FALSE;
	
	/**
	 * @brief	Storage Configurations
	 */
	protected static $storageConfigurations = NULL;
	
	/**
	 * @brief	Thumbnail dimensions
	 */
	protected static $thumbnailDimensions = array();

	/**
	 * @brief	Ignore errors from uploaded files?
	 */
	const IGNORE_UPLOAD_ERRORS	= 1;

	/**
	 * @brief	When moving files, do not delete the original immediately but log for later deletion
	 */
	const MOVE_DELAY_DELETE	= 2;
	
	/**
	 * Get class
	 *
	 * @param	string|int		            $storageExtension	Storage extension or configuration ID
	 * @param   string|\IPS\Http\Url|NULL  $url                 URL to validate storage configs with
	 * @return	\IPS\File
	 */
	public static function getClass( $storageExtension, $url=NULL )
	{
		static::getConfigurations();

		$configurationId = NULL;
		if ( is_int( $storageExtension ) ) 
		{
			$configurationId = $storageExtension;
		}
		else
		{
			$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
			if ( !isset( $settings[ "filestorage__{$storageExtension}" ] ) )
			{
				foreach ( static::$storageConfigurations as $k => $data )
				{
					$configurationId = $k;
					break;
				}
			}
			else
			{
				/* We have an array of IDs when a move is in progress, the first ID is the new storage method, the second ID is the old */
				if ( is_array( $settings[ "filestorage__{$storageExtension}" ] ) )
				{
					/* Do we have a URL to check? */
					if ( $url )
					{
						foreach ( $settings[ "filestorage__{$storageExtension}" ] as $cid )
						{
							if ( isset( static::$storageConfigurations[ $cid ] ) )
							{
								$classname = 'IPS\File\\' . static::$storageConfigurations[ $cid ]['method'];

								if ( $classname::isValidUrl( $url, json_decode( static::$storageConfigurations[ $cid ]['configuration'], TRUE ) ) )
								{
									$configurationId = $cid;
									break;
								}
							}
						}
					}

					if ( ! $configurationId )
					{
						/* Use the first ID as this is the 'new' storage engine */
						$configurationId = array_shift( $settings["filestorage__{$storageExtension}"] );
					}

				}
				else if ( isset( static::$storageConfigurations[ $settings[ "filestorage__{$storageExtension}" ] ] ) )
				{
					$configurationId = $settings[ "filestorage__{$storageExtension}" ];
				}
				else
				{
					$storageConfigurations = static::$storageConfigurations;
					static::$storageConfigurations[ $settings[ "filestorage__{$storageExtension}" ] ] = array_shift( $storageConfigurations );
				}
			}
		}
				
		$classname = 'IPS\File\\' . static::$storageConfigurations[ $configurationId ]['method'];
		$class = new $classname( json_decode( static::$storageConfigurations[ $configurationId ]['configuration'], TRUE ) );
		$class->configurationId = $configurationId;
		return $class;
	}

	/**
	 * Load storage configurations
	 *
	 * @return	void
	 */
	public static function getConfigurations()
	{
		if ( static::$storageConfigurations === NULL )
		{
			if ( isset( \IPS\Data\Store::i()->storageConfigurations ) )
			{
				static::$storageConfigurations = \IPS\Data\Store::i()->storageConfigurations;
			}
			else
			{
				static::$storageConfigurations = iterator_to_array( \IPS\Db::i()->select( '*', 'core_file_storage' )->setKeyField('id') );
				\IPS\Data\Store::i()->storageConfigurations = static::$storageConfigurations;
			}
		}
	}

	/**
	 * Create File
	 *
	 * @param	string		$storageExtension	Storage extension
	 * @param	string		$filename			Filename
	 * @param	string|null	$data				Data (set to null if you intend to use $filePath)
	 * @param	string|null	$container			Key to identify container for storage
	 * @param	boolean		$isSafe				This file is safe and doesn't require security checking
	 * @param	string|null	$filePath			Path to existing file on disk - Filesystem can move file without loading all of the contents into memory if this method is used
	 * @param	bool		$obscure			Controls if an md5 hash should be added to the filename
	 * @return	\IPS\File
	 * @throws	\DomainException
	 * @throws	\RuntimeException
	 */
	public static function create( $storageExtension, $filename, $data=NULL, $container=NULL, $isSafe=FALSE, $filePath=NULL, $obscure=TRUE )
	{
		/* Check we have a file */
		if( $data === NULL AND $filePath === NULL )
		{
			throw new \DomainException( "NO_FILE_UPLOADED", 1 );
		}

		/* Init */
		$class = static::getClass( $storageExtension );
		if ( $container !== NULL )
		{
			$class->container = $container;
		}

		/* Make sure images don't have HTML in the comments, which can cause be an XSS in older versions of IE */
		$ext = mb_substr( $filename, ( mb_strrpos( $filename, '.' ) + 1 ) );
		if( $isSafe === FALSE and in_array( $ext, \IPS\Image::$imageExtensions ) )
		{
			$contentToCheck	= $data;

			if( $data === NULL AND $filePath !== NULL )
			{
				$handle			= fopen( $filePath, 'r');
				$contentToCheck	= fread( $handle, 2048 );
				fclose( $handle );
			}

			if( static::checkXssInFile( $contentToCheck ) )
			{
				throw new \DomainException( "SECURITY_EXCEPTION_RAISED", 99 );
			}
		}

		/* Set the name */
		$class->setFilename( $filename, $obscure );
		
		/* Set the contents */
		if( $data !== NULL )
		{
			$class->contents = $data;
		}
		else
		{
			$class->setFile( $filePath );
		}
		
		/* Save and return */
		$class->save();
		return $class;
	}

	/**
	 * Create \IPS\File objects from uploaded $_FILES array
	 *
	 * @param	string		$storageLocation	The storage location to create the files under (e.g. core_Attachments)
	 * @param	string|NULL	$fieldName			Restrict collection of uploads to this upload field name, or pass NULL to collect any and all uploads
	 * @param	array|NULL	$allowedFileTypes	Array of allowed file extensions, or NULL to allow any extensions
	 * @param	int|NULL	$maxFileSize		The maximum file size in MB, or NULL to allow any size
	 * @param	int|NULL	$totalMaxSize		The maximum total size of all files in MB, or NULL for no limit
	 * @param	int			$flags				\IPS\File::IGNORE_UPLOAD_ERRORS to skip over invalid files rather than throw exception
	 * @param	array|NULL	$callback			Callback function to run against the file contents before creating the file (useful for resizing images, for instance)
	 * @param	string|null	$container			Key to identify container for storage
	 * @return	array		Array of \IPS\File objects
	 * @throws	\DomainException
	 * @throws	\RuntimeException
	 */
	public static function createFromUploads( $storageLocation, $fieldName=NULL, $allowedFileTypes=NULL, $maxFileSize=NULL, $totalMaxSize=NULL, $flags=0, $callback=NULL, $container=NULL )
	{		
		/* Do we have any uploads? */
		if( empty( $_FILES ) )
		{
			return array();
		}

		if( $fieldName !== NULL )
		{
			if( empty( $_FILES[ $fieldName ]['name'] ) )
			{
				return array();
			}
		}

		/* Normalize the files array */
		$files			= static::normalizeFilesArray( $fieldName );
		$fileObjects	= array();
		
		/* Now loop over each file */
		$currentTotal = 0;
		foreach( $files as $file )
		{
			/* First, validate the upload */
			try
			{
				static::validateUpload( $file, $allowedFileTypes, $maxFileSize );
				
				if ( $totalMaxSize !== NULL )
				{
					$currentTotal += $file['size'];
					if ( $currentTotal > ( $totalMaxSize * 1048576 ) )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('uploaderr_total_size', FALSE, array( 'sprintf' => array( $totalMaxSize ) ) ) );
					}
				}

				if( $callback !== NULL )
				{
					$contents	= file_get_contents( $file['tmp_name'] );
					$contents	= call_user_func( $callback, $contents );

					$fileObjects[]	= static::create( $storageLocation, $file['name'], $contents, $container );
				}
				else
				{
					$fileObjects[]	= static::create( $storageLocation, $file['name'], NULL, $container, FALSE, $file['tmp_name'] );
				}

				if( is_file( $file['tmp_name'] ) and file_exists( $file['tmp_name'] ) )
				{
					@unlink( $file['tmp_name'] );
				}
			}
			catch( \DomainException $e )
			{				
				if( is_file( $file['tmp_name'] ) and file_exists( $file['tmp_name'] ) )
				{
					@unlink( $file['tmp_name'] );
				}

				/* Are we ignoring upload errors? */
				if( $flags === \IPS\File::IGNORE_UPLOAD_ERRORS )
				{
					continue;
				}
				else
				{
					throw $e;
				}
			}
		}

		return $fileObjects;
	}

	/**
	 * Normalize the files array
	 *
	 * @param	string|NULL	$fieldName			Restrict collection of uploads to this upload field name, or pass NULL to collect any and all uploads
	 * @return	array
	 */
	public static function normalizeFilesArray( $fieldName=NULL )
	{
		$files			= array();

		foreach( $_FILES as $index => $file )
		{
			if( $fieldName !== NULL AND $fieldName != $index )
			{
				continue;
			}

			/* Do we have $_FILES['field'] = array( 'name' => ..., 'size' => ... ) */
			if( isset( $file['name'] ) AND !is_array( $file['name'] ) )
			{
				$files[]	= $file;
			}
			/* Or do we have $_FILES['field'] = array( 'name' => array( 0 => ..., 1 => ... ), 'size' => array( 0 => ..., 1 => ... ) ) */
			else
			{
				if( is_array( $file['name'] ) )
				{
					foreach( $file as $fieldName => $fields )
					{
						foreach( $fields as $fileIndex => $fileFieldValue )
						{
							$files[ $fileIndex ][ $fieldName ]	= $fileFieldValue;
						}
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Validate the uploaded file is valid
	 *
	 * @param	array 		$file	The uploaded file data
	 * @param	array|NULL	$allowedFileTypes	Array of allowed file extensions, or NULL to allow any extensions
	 * @param	int|NULL	$maxFileSize		The maximum file size in MB, or NULL to allow any size
	 * @return	void
	 * @throws	\DomainException
	 * @note	plupload inherently supports certain errors, so when appropriate we return the error code plupload expects
	 */
	public static function validateUpload( $file, $allowedFileTypes, $maxFileSize )
	{
		/* Was an error registered by PHP already? */
		if( $file['error'] )
		{
			$extraInfo	= NULL;

			switch( $file['error'] )
			{
				case 1:	//UPLOAD_ERR_INI_SIZE
				case 2:	//UPLOAD_ERR_FORM_SIZE
					$errorCode	= "-600";
					$extraInfo	= 2;
				break;

				case 3:	//UPLOAD_ERR_PARTIAL
				case 4: //UPLOAD_ERR_NO_FILE
					$errorCode	= "NO_FILE_UPLOADED";
					$extraInfo	= 1;
				break;

				case 6:	//UPLOAD_ERR_NO_TMP_DIR
				case 7:	//UPLOAD_ERR_CANT_WRITE
				case 8:	//UPLOAD_ERR_EXTENSION
					$errorCode	= "SERVER_CONFIGURATION";
					$extraInfo	= $file['error'];
				break;
			}

			throw new \DomainException( $errorCode, $extraInfo );
		}
		
		/* Do we have a path? */
		if ( empty( $file['tmp_name'] ) AND !isset( $file['_skipUploadCheck'] ) )
		{
			throw new \DomainException( 'upload_error', 1 );
		}

		/* Is this actually an uploaded file? */
		if( !is_uploaded_file( $file['tmp_name'] ) AND !isset( $file['_skipUploadCheck'] ) )
		{
			throw new \DomainException( 'upload_error', 1 );
		}
		
		/* Check size */
		if( $maxFileSize !== NULL )
		{
			$maxFileSize	= $maxFileSize * 1048576;
			if( $file['size'] > $maxFileSize OR ( !isset( $file['_skipUploadCheck'] ) AND filesize( $file['tmp_name'] ) > $maxFileSize ) )
			{
				throw new \DomainException( '-600', 2 );
			}
		}

		/* Check allowed types */
		$ext = mb_substr( $file['name'], mb_strrpos( $file['name'], '.' ) + 1 );
		if( $allowedFileTypes !== NULL and is_array( $allowedFileTypes ) and !empty( $allowedFileTypes ) )
		{
			if( !in_array( mb_strtolower( $ext ), array_map( 'mb_strtolower', $allowedFileTypes ) ) )
			{
				throw new \DomainException( '-601', 3 );
			}
		}

		/* If it's got an image extension, check it's actually a valid image */
		if ( in_array( $ext, \IPS\Image::$imageExtensions ) AND !isset( $file['_skipUploadCheck'] ) )
		{
			$imageAttributes = getimagesize( $file['tmp_name'] );
			if( !in_array( $imageAttributes[2], array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) )
			{
				throw new \DomainException( 'upload_error', 4 );
			}
		}

		return;
	}

	/**
	 * Load File
	 *
	 * @param	string					$storageExtension	Storage extension
	 * @param	string|\IPS\Http|Url	$url				URL to file
	 * @return	\IPS\File
	 */
	public static function get( $storageExtension, $url )
	{
		$class = static::getClass( $storageExtension, $url );
		$class->url = $url instanceof \IPS\Http\Url ? $url : new \IPS\Http\Url( $url );
		$class->load();
		return $class;
	}

	/**
	 * Remove orphaned files based on a given storage configuration
	 *
	 * @param	array		$configuration	Storage configuration
	 * @param	int			$fileIndex		The file offset to start at in a listing
	 * @return	array
	 */
	public static function orphanedFiles( $configuration, $fileIndex )
	{
		$classname	= 'IPS\File\\' . $configuration['method'];
		$class		= new $classname( $configuration['_settings'] );

		return $class->removeOrphanedFiles( $fileIndex, \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE ) );
	}

	/**
	 * Determine if the change in configuration warrants a move process
	 *
	 * @param	array		$configuration	    New Storage configuration
	 * @param	array		$oldConfiguration   Existing Storage Configuration
	 * @return	boolean
	 */
	public static function moveCheck( $configuration, $oldConfiguration )
	{
		$needsMove = FALSE;
		if ( count( array_merge( array_diff( $configuration, $oldConfiguration ), array_diff( $oldConfiguration, $configuration ) ) ) )
		{
			$needsMove = TRUE;
		}

		if ( ! $needsMove )
		{
			foreach( $configuration as $k => $v )
			{
				$pass = TRUE;
				if ( ! isset( $oldConfiguration[ $k ] ) or $v != $oldConfiguration[ $k ] )
				{
					$pass = FALSE;
				}
			}

			$needsMove = ( $pass ) ? FALSE : TRUE;
		}

		return $needsMove;
	}
	
	/**
	 * @brief	Storage Configuration
	 */
	public $configuration = array();
	
	/**
	 * @brief	Storage Configuration ID
	 */
	public $configurationId;
	
	/**
	 * @brief	Original Filename
	 */
	public $originalFilename;
	
	/**
	 * @brief	Filename
	 */
	public $filename;
	
	/**
	 * @brief	Container
	 */
	public $container;
	
	/**
	 * @brief	Cached contents
	 */
	protected $contents;

	/**
	 * @brief	URL
	 */
	public $url;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Storage configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		$this->configuration = $configuration;
	}

	/**
	 * Print the contents of the file
	 *
	 * @param	int|null	$start		Start point to print from (for ranges)
	 * @param	int|null	$length		Length to print to (for ranges)
	 * @param	int|null	$throttle	Throttle speed
	 * @return	void
	 */
	public function printFile( $start=NULL, $length=NULL, $throttle=NULL )
	{
		if( $start AND $length )
		{
			$contents	= substr( $this->contents(), $start, $length );
		}
		else
		{
			$contents	= $this->contents();
		}

		if( $throttle === NULL )
		{
			print $contents;
		}
		else
		{
			$pointer	= 0;

			while( $pointer < \strlen( $contents ) )
			{
				print \substr( $contents, $pointer, $throttle );
				$pointer	+= $throttle;

				sleep( 1 );
			}
		}
	}

	/**
	 * Get Contents
	 *
	 * @param	bool	$refresh	If TRUE, will fetch again
	 * @return	string
	 */
	public function contents( $refresh=FALSE )
	{
		if ( $this->contents === NULL or $refresh === TRUE )
		{
			$this->contents = (string) \IPS\Http\Url::external( $this->url )->request()->get();
		}
		return $this->contents;
	}
	
	/**
	 * Get filesize (in bytes)
	 *
	 * @return	string
	 */
	public function filesize()
	{
		return \strlen( $this->contents() );		
	}

	/**
	 * Set the file
	 *
	 * @param	string	$filepath	The path to the file on disk
	 * @return	void
	 */
	public function setFile( $filepath )
	{
		$this->contents	= file_get_contents( $filepath );
	}

	/**
	 * Set filename
	 *
	 * @param	string	$filename	The filename
	 * @param	bool	$obscure	Controls if an md5 hash should be added to the filename
	 * @return	void
	 */
	public function setFilename( $filename, $obscure=TRUE )
	{
		$this->originalFilename = $filename;

		if ( $obscure )
		{
			$this->filename = static::obscureFilename( $filename );
		}
		else
		{
			$this->filename = $filename;
		}
	}
	
	/**
	 * Obscure Filename
	 *
	 * @param	string	$filename	The filename
	 * @return	string
	 */
	protected function obscureFilename( $filename )
	{
		$ext  = mb_substr( $filename, ( mb_strrpos( $filename, '.' ) + 1 ) );
		$safe = in_array( mb_strtolower( $ext ), static::$safeFileExtensions );

		if ( ! $safe )
		{
			$filename = mb_substr( $filename, 0, ( mb_strrpos( $filename, '.' ) ) ) . '_' . $ext;
		}

		return str_replace( array( ' ', '#' ), '_', $filename ) . '.' . md5( uniqid() ) . ( ( $safe ) ? '.' . $ext : '' );
	}

	/**
	 * "Un"-obscure the filename
	 *
	 * @param	string	$filename	The filename
	 * @return	string
	 */
	protected function unObscureFilename( $filename )
	{
		$ext = mb_substr( $filename, ( mb_strrpos( $filename, '.' ) + 1 ) );

		if ( mb_strlen( $ext ) == 32 )
		{
			preg_match( '#(.*)_([a-z\-]{2,10})\.([a-zA-Z0-9]{32})#i', $filename, $matches );

			if ( isset( $matches[1] ) and isset( $matches[2] ) )
			{
				return $matches[1] . '.' . $matches[2];
			}
		}

		return mb_substr( $filename, 0, mb_strlen( $filename ) - ( ( in_array( mb_strtolower( $ext ), static::$safeFileExtensions ) ) ? ( 34 + mb_strlen( $ext ) ) : 33 ) );
	}

	/**
	 * Load File Data
	 *
	 * @return	void
	 */
	public function load()
	{
		$exploded = explode( '/', $this->url );
		$this->filename = array_pop( $exploded );
		$this->originalFilename = $this->unObscureFilename( $this->filename );

		/* Upon upgrade we don't rename every file, so we need to account for this */
		if( mb_strpos( $this->originalFilename, '.' ) === FALSE )
		{
			$this->originalFilename	= $this->filename;
		}

		$this->container = implode( '/', $exploded );
	}
	
	/**
	 * Replace file contents
	 *
	 * @param	string	$contents	New contents
	 * @return	void
	 */
	public function replace( $contents )
	{
		$this->contents = $contents;
		$this->save();
	}
	
	/**
	 * Save File
	 *
	 * @return	void
	 */
	abstract public function save();
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	abstract public function delete();
	
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @return	void
	 */
	abstract public function deleteContainer( $container );
	
	/**
	 * Get the URL
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return (string) $this->url;
	}
	
	/**
	 * Move file to a different storage location
	 *
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param   int         $flags                  Bitwise Flags
	 * @return	\IPS\File
	 */
	public function move( $storageConfiguration, $flags=0 )
	{
		/* Copy file */
		try
		{
			$class = $this->copy( $storageConfiguration );
		}
		catch( \Exception $e )
		{
			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		try
		{
			if( $flags === \IPS\File::MOVE_DELAY_DELETE )
			{
				$this->log( "file_moved", 'move', array(
					'method'            => static::$storageConfigurations[ $storageConfiguration ]['method'],
					'configuration_id'  => $storageConfiguration,
					'container'         => $class->container,
					'filename'          => $class->filename
				), 'move' );
			}
			else
			{
				/* Delete this one */
				$this->delete();
			}
		}
		catch( \Exception $e )
		{
			$this->log( $e->getMessage(), 'delete' );

			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		/* Return */
		return $class;
	}

	/**
	 * Copy a file to a different storage location
	 *
	 * @param	int			       $storageConfiguration	New storage configuration ID
	 * @return	\IPS\File
	 * @throws  \RuntimeException
	 */
	public function copy( $storageConfiguration )
	{
		/* Load class */
		static::getConfigurations();
		$classname = '\IPS\File\\' . static::$storageConfigurations[ $storageConfiguration ]['method'];
		$class = new $classname( json_decode( static::$storageConfigurations[ $storageConfiguration ]['configuration'], TRUE ) );

		/* Store it there */
		if ( $this->container !== NULL )
		{
			$class->container = trim( $this->container, '/' );
		}

		/* Clean up previous extension/randomization if present */
		$class->setFilename( $this->unObscureFilename( $this->filename ) );

		try
		{
			$class->contents = $this->contents();
		}
		catch( \Exception $e )
		{
			$this->log( $e->getMessage(), 'copy', array(
				'method'            => static::$storageConfigurations[ $storageConfiguration ]['method'],
				'configuration_id'  => $storageConfiguration
			) );

			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		try
		{
			$class->save();
		}
		catch( \Exception $e )
		{
			$this->log( $e->getMessage(), 'copy', array(
				'method'            => static::$storageConfigurations[ $storageConfiguration ]['method'],
				'configuration_id'  => $storageConfiguration
			) );

			throw new \RuntimeException( $e->getMessage(), $e->getCode() );
		}

		/* Return */
		return $class;
	}
	
	/**
	 * @brief Attachment thumbnail URL
	 */
	public $attachmentThumbnailUrl	= NULL;

	/**
	 * Make into an attachment
	 *
	 * @param	string	$postKey	Post key
	 * @return	array
	 * @throws	\DomainException
	 */
	public function makeAttachment( $postKey )
	{
		$ext = mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) + 1 );

		$data = array(
			'attach_ext'			=> $ext,
			'attach_file'			=> $this->originalFilename,
			'attach_location'		=> $this->url,
			'attach_thumb_location'	=> '',
			'attach_thumb_width'	=> 0,
			'attach_thumb_height'	=> 0,
			'attach_is_image'		=> 0,
			'attach_hits'			=> 0,
			'attach_date'			=> time(),
			'attach_post_key'		=> $postKey,
			'attach_member_id'		=> \IPS\Member::loggedIn()->member_id ?: 0,
			'attach_filesize'		=> $this->filesize(),
			'attach_img_width'		=> 0,
			'attach_img_height'		=> 0,
			'attach_is_archived'	=> FALSE
		);
		
		/* If this is an image, grab the appropriate data */
		if ( $this->isImage() )
		{
			try
			{
				$thumbDims = \IPS\Settings::i()->attachment_image_size ? explode( 'x', \IPS\Settings::i()->attachment_image_size ) : array( 1000, 750 );
				$image     = \IPS\Image::create( $this->contents() );
				
				$data['attach_is_image'] = TRUE;
				$data['attach_img_width'] = $image->width;
				$data['attach_img_height'] = $image->height;

				unset( $image );
				
				$data['attach_thumb_location']	= (string) $this->thumbnail( 'core_Attachment', $thumbDims[0], $thumbDims[1] );
				$data['attach_thumb_width']		= static::$thumbnailDimensions[0];
				$data['attach_thumb_height']	= static::$thumbnailDimensions[1];

				$this->attachmentThumbnailUrl	= $data['attach_thumb_location'];
			}
			
			catch ( \InvalidArgumentException $e ) { }
		}
		
		return array_merge( array( 'attach_id' => \IPS\Db::i()->insert( 'core_attachments', $data ) ), $data );
	}

	/**
	 * Determine if the file is an image
	 *
	 * @return	bool
	 */
	public function isImage()
	{
		$ext = mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) + 1 );

		if ( in_array( mb_strtolower( $ext ), \IPS\Image::$imageExtensions ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * If the file is an image, get the dimensions
	 *
	 * @return	array
	 * @throws	\DomainException
	 * @throws	\InvalidArgumentException
	 */
	public function getImageDimensions()
	{
		if( !$this->isImage() )
		{
			throw new \DomainException;
		}

		$image     = \IPS\Image::create( $this->contents() );

		return array( $image->width, $image->height );
	}
	
	/**
	 * Claim Attachments
	 *
	 * @param	string		$autoSaveKey	Auto-save key
	 * @param	int|NULL	$id1			ID 1	
	 * @param	int|NULL	$id2			ID 2		
	 * @param	int|NULL	$id3			ID 3		
	 * @param	bool		$translatable	Are we claiming from a Translatable field?
	 * @return	void
	 * @note	If you call this, it is your responsibility to call unclaimAttachments if/when the thing is deleted
	 */
	public static function claimAttachments( $autoSaveKey, $id1=NULL, $id2=NULL, $id3=NULL, $translatable=FALSE )
	{
		if ( $translatable )
		{
			foreach ( \IPS\Lang::languages() as $lang )
			{
				\IPS\Db::i()->update( 'core_attachments_map', array(
					'id1'	=> $id1,
					'id2'	=> $id2,
					'id3'	=> $id3,
					'temp'	=> NULL
				), array( 'temp=?', md5( $autoSaveKey . $lang->id ) ) );
				
				\IPS\Db::i()->update( 'core_attachments', array( 'attach_post_key' => '' ), array( 'attach_post_key=?', md5( $autoSaveKey . $lang->id . ':' . session_id() ) ) );
			}
		}
		else
		{
			\IPS\Db::i()->update( 'core_attachments_map', array(
				'id1'	=> $id1,
				'id2'	=> $id2,
				'id3'	=> $id3,
				'temp'	=> NULL
			), array( 'temp=?', md5( $autoSaveKey ) ) );
			
			\IPS\Db::i()->update( 'core_attachments', array( 'attach_post_key' => '' ), array( 'attach_post_key=?', md5( $autoSaveKey . ':' . session_id() ) ) );
		}
	}
	
	/**
	 * Unclaim Attachments
	 *
	 * @param	string		$locationKey	Location key (e.g. "forums_Forums")
	 * @param	int|NULL	$id1			ID 1	
	 * @param	int|NULL	$id2			ID 2		
	 * @param	int|NULL	$id3			ID 3		
	 * @return	void
	 * @note	If any of the IDs are NULL, this will unclaim any attachments with any value. This can be useful to unclaim all attachments for all posts in a topic, but caution must be used.
	 */
	public static function unclaimAttachments( $locationKey, $id1=NULL, $id2=NULL, $id3=NULL )
	{
		/* Delete from core_attachments_map */
		$where = array( array( 'location_key=?', $locationKey ) );
		foreach ( range( 1, 3 ) as $i )
		{
			$v = "id{$i}";
			if ( $$v !== NULL )
			{
				$where[] = array( "{$v}=?", $$v );
			}
		}
		\IPS\Db::i()->delete( 'core_attachments_map', $where );
		
		/* Cleanup - this is deliberately slightly different to the one in \IPS\Text\Parser as it will only delete attachments which have been "saved" (i.e. are not in the middle of being posted) */
		foreach ( \IPS\Db::i()->select( '*', 'core_attachments', array( array( 'attach_id NOT IN(?) AND attach_post_key=?', \IPS\Db::i()->select( 'DISTINCT attachment_id', 'core_attachments_map' ), '' ) ) ) as $attachment )
		{
			try
			{
				\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', $attachment['attach_id'] ) );
				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
				if ( $attachment['attach_thumb_location'] )
				{
					\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
				}
			}
			catch ( \Exception $e ) { }
		}
	}

	/**
	 * Make a thumbnail of the file - copies file, resizes and returns new file object
	 *
	 * @param	string	$storageExtension	Storage extension to use for generated thumbnail
	 * @param	int		$maxWidth			Max width (in pixels) - NULL to use \IPS\THUMBNAIL_SIZE
	 * @param	int		$maxHeight			Max height (in pixels) - NULL to use \IPS\THUMBNAIL_SIZE
	 * @param	bool	$cropToSquare		If TRUE, will first crop to a square
	 * @return	\IPS\File
	 */
	public function thumbnail( $storageExtension, $maxWidth=NULL, $maxHeight=NULL, $cropToSquare=FALSE )
	{	
		/* Work out size */	
		$defaultSize = explode( 'x', \IPS\THUMBNAIL_SIZE );
		$maxWidth    = $maxWidth ?: $defaultSize[0];
		$maxHeight   = $maxHeight ?: $defaultSize[1];

		/* Create an \IPS\Image object */
		$image = \IPS\Image::create( $this->contents() );
		
		/* Crop it */
		if ( $cropToSquare and $image->width != $image->height )
		{
			$cropProperty = ( $image->width > $image->height ) ? 'height' : 'width';
			$image->crop( $image->$cropProperty, $image->$cropProperty );
		}
		
		/* Resize it */
		$image->resizeToMax( $maxWidth, $maxHeight );
		
		static::$thumbnailDimensions = array( $image->width, $image->height );
		
		/* What are we calling this? */		
		$thumbnailName = mb_substr( $this->originalFilename, 0, mb_strrpos( $this->originalFilename, '.' ) ) . '.thumb' . mb_substr( $this->originalFilename, mb_strrpos( $this->originalFilename, '.' ) );
		
		/* Create and return */
		return \IPS\File::create( $storageExtension, $thumbnailName, $image );
	}

	/**
	 * Log an error
	 *
	 * @param      string   $message    Message to log
	 * @param      string   $action     Action that triggered the error (copy/move/delete/save)
	 * @param      mixed    $data       Extra data to save
	 * @param      string   $type       Type of log (error/log/copy/move)
	 * @return     void
	 */
	protected function log( $message, $action, $data=NULL, $type='error' )
	{
		\IPS\Db::i()->insert( 'core_file_logs', array (
          'log_action'           => $action,
          'log_type'             => $type,
          'log_configuration_id' => $this->configurationId,
          'log_method'           => static::$storageConfigurations[ $this->configurationId ]['method'],
          'log_filename'         => $this->filename,
          'log_url'              => $this->url,
          'log_container'        => $this->container,
          'log_msg'              => $message,
          'log_date'             => time(),
          'log_data'             => is_array( $data ) ? json_encode( $data ) : NULL
        ) );
	}

	/**
	 * Check a file for XSS content inside it
	 *
	 * @param	string	$data	File data
	 * @return	bool
	 * @note	Thanks to Nicolas Grekas from comments at www.splitbrain.org for helping to identify all vulnerable HTML tags
	 */
	public static function checkXssInFile( $data )
	{
		/* We only need to check the first 1kb of the file...some programs will use more, but this is the most common */
		$firstBytes	= \substr( $data, 0, 1024 );

		if( preg_match( '#(<script|<html|<head|<title|<body|<pre|<table|<a\s+href|<img|<plaintext|<cross\-domain\-policy)(\s|=|>)#si', $firstBytes, $matches ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Get mime type
	 *
	 * @return	string
	 */
	public static function getMimeType( $filename )
	{
		$extension	= mb_strtolower( mb_substr( $filename, mb_strrpos( $filename, '.' ) + 1 ) );
		
		if ( array_key_exists( $extension, self::$mimeTypes ) )
		{
			return self::$mimeTypes[ $extension ];
		}
		
		return 'unknown/unknown';
	}

	/**
	 * Is this URL valid for this engine?
	 *
	 * @param   \IPS\Http\Url   $url
	 * @param   array           $configuration  Specific configuration for this method
	 * @return  bool
	 */
	public static function isValidUrl( $url, $configuration )
	{
		/* You should really overload this */
		return FALSE;
	}
	
	/* !Mime-Type Map */
	
	/**
	 * @brief	Mime-Type Map
	 */
	public static $mimeTypes = array(
        '3dml' => 'text/vnd.in3d.3dml',
        '3g2' => 'video/3gpp2',
        '3gp' => 'video/3gpp',
        '7z' => 'application/x-7z-compressed',
        'aab' => 'application/x-authorware-bin',
        'aac' => 'audio/x-aac',
        'aam' => 'application/x-authorware-map',
        'aas' => 'application/x-authorware-seg',
        'abw' => 'application/x-abiword',
        'ac' => 'application/pkix-attr-cert',
        'acc' => 'application/vnd.americandynamics.acc',
        'ace' => 'application/x-ace-compressed',
        'acu' => 'application/vnd.acucobol',
        'acutc' => 'application/vnd.acucorp',
        'adp' => 'audio/adpcm',
        'aep' => 'application/vnd.audiograph',
        'afm' => 'application/x-font-type1',
        'afp' => 'application/vnd.ibm.modcap',
        'ahead' => 'application/vnd.ahead.space',
        'ai' => 'application/postscript',
        'aif' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'air' => 'application/vnd.adobe.air-application-installer-package+zip',
        'ait' => 'application/vnd.dvb.ait',
        'ami' => 'application/vnd.amiga.ami',
        'apk' => 'application/vnd.android.package-archive',
        'application' => 'application/x-ms-application',
        'apr' => 'application/vnd.lotus-approach',
        'asa' => 'text/plain',
        'asax' => 'application/octet-stream',
        'asc' => 'application/pgp-signature',
        'ascx' => 'text/plain',
        'asf' => 'video/x-ms-asf',
        'ashx' => 'text/plain',
        'asm' => 'text/x-asm',
        'asmx' => 'text/plain',
        'aso' => 'application/vnd.accpac.simply.aso',
        'asp' => 'text/plain',
        'aspx' => 'text/plain',
        'asx' => 'video/x-ms-asf',
        'atc' => 'application/vnd.acucorp',
        'atom' => 'application/atom+xml',
        'atomcat' => 'application/atomcat+xml',
        'atomsvc' => 'application/atomsvc+xml',
        'atx' => 'application/vnd.antix.game-component',
        'au' => 'audio/basic',
        'avi' => 'video/x-msvideo',
        'aw' => 'application/applixware',
        'axd' => 'text/plain',
        'azf' => 'application/vnd.airzip.filesecure.azf',
        'azs' => 'application/vnd.airzip.filesecure.azs',
        'azw' => 'application/vnd.amazon.ebook',
        'bat' => 'application/x-msdownload',
        'bcpio' => 'application/x-bcpio',
        'bdf' => 'application/x-font-bdf',
        'bdm' => 'application/vnd.syncml.dm+wbxml',
        'bed' => 'application/vnd.realvnc.bed',
        'bh2' => 'application/vnd.fujitsu.oasysprs',
        'bin' => 'application/octet-stream',
        'bmi' => 'application/vnd.bmi',
        'bmp' => 'image/bmp',
        'book' => 'application/vnd.framemaker',
        'box' => 'application/vnd.previewsystems.box',
        'boz' => 'application/x-bzip2',
        'bpk' => 'application/octet-stream',
        'btif' => 'image/prs.btif',
        'bz' => 'application/x-bzip',
        'bz2' => 'application/x-bzip2',
        'c' => 'text/x-c',
        'c11amc' => 'application/vnd.cluetrust.cartomobile-config',
        'c11amz' => 'application/vnd.cluetrust.cartomobile-config-pkg',
        'c4d' => 'application/vnd.clonk.c4group',
        'c4f' => 'application/vnd.clonk.c4group',
        'c4g' => 'application/vnd.clonk.c4group',
        'c4p' => 'application/vnd.clonk.c4group',
        'c4u' => 'application/vnd.clonk.c4group',
        'cab' => 'application/vnd.ms-cab-compressed',
        'car' => 'application/vnd.curl.car',
        'cat' => 'application/vnd.ms-pki.seccat',
        'cc' => 'text/x-c',
        'cct' => 'application/x-director',
        'ccxml' => 'application/ccxml+xml',
        'cdbcmsg' => 'application/vnd.contact.cmsg',
        'cdf' => 'application/x-netcdf',
        'cdkey' => 'application/vnd.mediastation.cdkey',
        'cdmia' => 'application/cdmi-capability',
        'cdmic' => 'application/cdmi-container',
        'cdmid' => 'application/cdmi-domain',
        'cdmio' => 'application/cdmi-object',
        'cdmiq' => 'application/cdmi-queue',
        'cdx' => 'chemical/x-cdx',
        'cdxml' => 'application/vnd.chemdraw+xml',
        'cdy' => 'application/vnd.cinderella',
        'cer' => 'application/pkix-cert',
        'cfc' => 'application/x-coldfusion',
        'cfm' => 'application/x-coldfusion',
        'cgm' => 'image/cgm',
        'chat' => 'application/x-chat',
        'chm' => 'application/vnd.ms-htmlhelp',
        'chrt' => 'application/vnd.kde.kchart',
        'cif' => 'chemical/x-cif',
        'cii' => 'application/vnd.anser-web-certificate-issue-initiation',
        'cil' => 'application/vnd.ms-artgalry',
        'cla' => 'application/vnd.claymore',
        'class' => 'application/java-vm',
        'clkk' => 'application/vnd.crick.clicker.keyboard',
        'clkp' => 'application/vnd.crick.clicker.palette',
        'clkt' => 'application/vnd.crick.clicker.template',
        'clkw' => 'application/vnd.crick.clicker.wordbank',
        'clkx' => 'application/vnd.crick.clicker',
        'clp' => 'application/x-msclip',
        'cmc' => 'application/vnd.cosmocaller',
        'cmdf' => 'chemical/x-cmdf',
        'cml' => 'chemical/x-cml',
        'cmp' => 'application/vnd.yellowriver-custom-menu',
        'cmx' => 'image/x-cmx',
        'cod' => 'application/vnd.rim.cod',
        'com' => 'application/x-msdownload',
        'conf' => 'text/plain',
        'cpio' => 'application/x-cpio',
        'cpp' => 'text/x-c',
        'cpt' => 'application/mac-compactpro',
        'crd' => 'application/x-mscardfile',
        'crl' => 'application/pkix-crl',
        'crt' => 'application/x-x509-ca-cert',
        'cryptonote' => 'application/vnd.rig.cryptonote',
        'cs' => 'text/plain',
        'csh' => 'application/x-csh',
        'csml' => 'chemical/x-csml',
        'csp' => 'application/vnd.commonspace',
        'css' => 'text/css',
        'cst' => 'application/x-director',
        'csv' => 'text/csv',
        'cu' => 'application/cu-seeme',
        'curl' => 'text/vnd.curl',
        'cww' => 'application/prs.cww',
        'cxt' => 'application/x-director',
        'cxx' => 'text/x-c',
        'dae' => 'model/vnd.collada+xml',
        'daf' => 'application/vnd.mobius.daf',
        'dataless' => 'application/vnd.fdsn.seed',
        'davmount' => 'application/davmount+xml',
        'dcr' => 'application/x-director',
        'dcurl' => 'text/vnd.curl.dcurl',
        'dd2' => 'application/vnd.oma.dd2+xml',
        'ddd' => 'application/vnd.fujixerox.ddd',
        'deb' => 'application/x-debian-package',
        'def' => 'text/plain',
        'deploy' => 'application/octet-stream',
        'der' => 'application/x-x509-ca-cert',
        'dfac' => 'application/vnd.dreamfactory',
        'dic' => 'text/x-c',
        'dir' => 'application/x-director',
        'dis' => 'application/vnd.mobius.dis',
        'dist' => 'application/octet-stream',
        'distz' => 'application/octet-stream',
        'djv' => 'image/vnd.djvu',
        'djvu' => 'image/vnd.djvu',
        'dll' => 'application/x-msdownload',
        'dmg' => 'application/octet-stream',
        'dms' => 'application/octet-stream',
        'dna' => 'application/vnd.dna',
        'doc' => 'application/msword',
        'docm' => 'application/vnd.ms-word.document.macroenabled.12',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dot' => 'application/msword',
        'dotm' => 'application/vnd.ms-word.template.macroenabled.12',
        'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'dp' => 'application/vnd.osgi.dp',
        'dpg' => 'application/vnd.dpgraph',
        'dra' => 'audio/vnd.dra',
        'dsc' => 'text/prs.lines.tag',
        'dssc' => 'application/dssc+der',
        'dtb' => 'application/x-dtbook+xml',
        'dtd' => 'application/xml-dtd',
        'dts' => 'audio/vnd.dts',
        'dtshd' => 'audio/vnd.dts.hd',
        'dump' => 'application/octet-stream',
        'dvi' => 'application/x-dvi',
        'dwf' => 'model/vnd.dwf',
        'dwg' => 'image/vnd.dwg',
        'dxf' => 'image/vnd.dxf',
        'dxp' => 'application/vnd.spotfire.dxp',
        'dxr' => 'application/x-director',
        'ecelp4800' => 'audio/vnd.nuera.ecelp4800',
        'ecelp7470' => 'audio/vnd.nuera.ecelp7470',
        'ecelp9600' => 'audio/vnd.nuera.ecelp9600',
        'ecma' => 'application/ecmascript',
        'edm' => 'application/vnd.novadigm.edm',
        'edx' => 'application/vnd.novadigm.edx',
        'efif' => 'application/vnd.picsel',
        'ei6' => 'application/vnd.pg.osasli',
        'elc' => 'application/octet-stream',
        'eml' => 'message/rfc822',
        'emma' => 'application/emma+xml',
        'eol' => 'audio/vnd.digital-winds',
        'eot' => 'application/vnd.ms-fontobject',
        'eps' => 'application/postscript',
        'epub' => 'application/epub+zip',
        'es3' => 'application/vnd.eszigno3+xml',
        'esf' => 'application/vnd.epson.esf',
        'et3' => 'application/vnd.eszigno3+xml',
        'etx' => 'text/x-setext',
        'exe' => 'application/x-msdownload',
        'exi' => 'application/exi',
        'ext' => 'application/vnd.novadigm.ext',
        'ez' => 'application/andrew-inset',
        'ez2' => 'application/vnd.ezpix-album',
        'ez3' => 'application/vnd.ezpix-package',
        'f' => 'text/x-fortran',
        'f4v' => 'video/x-f4v',
        'f77' => 'text/x-fortran',
        'f90' => 'text/x-fortran',
        'fbs' => 'image/vnd.fastbidsheet',
        'fcs' => 'application/vnd.isac.fcs',
        'fdf' => 'application/vnd.fdf',
        'fe_launch' => 'application/vnd.denovo.fcselayout-link',
        'fg5' => 'application/vnd.fujitsu.oasysgp',
        'fgd' => 'application/x-director',
        'fh' => 'image/x-freehand',
        'fh4' => 'image/x-freehand',
        'fh5' => 'image/x-freehand',
        'fh7' => 'image/x-freehand',
        'fhc' => 'image/x-freehand',
        'fig' => 'application/x-xfig',
        'fli' => 'video/x-fli',
        'flo' => 'application/vnd.micrografx.flo',
        'flv' => 'video/x-flv',
        'flw' => 'application/vnd.kde.kivio',
        'flx' => 'text/vnd.fmi.flexstor',
        'fly' => 'text/vnd.fly',
        'fm' => 'application/vnd.framemaker',
        'fnc' => 'application/vnd.frogans.fnc',
        'for' => 'text/x-fortran',
        'fpx' => 'image/vnd.fpx',
        'frame' => 'application/vnd.framemaker',
        'fsc' => 'application/vnd.fsc.weblaunch',
        'fst' => 'image/vnd.fst',
        'ftc' => 'application/vnd.fluxtime.clip',
        'fti' => 'application/vnd.anser-web-funds-transfer-initiation',
        'fvt' => 'video/vnd.fvt',
        'fxp' => 'application/vnd.adobe.fxp',
        'fxpl' => 'application/vnd.adobe.fxp',
        'fzs' => 'application/vnd.fuzzysheet',
        'g2w' => 'application/vnd.geoplan',
        'g3' => 'image/g3fax',
        'g3w' => 'application/vnd.geospace',
        'gac' => 'application/vnd.groove-account',
        'gdl' => 'model/vnd.gdl',
        'geo' => 'application/vnd.dynageo',
        'gex' => 'application/vnd.geometry-explorer',
        'ggb' => 'application/vnd.geogebra.file',
        'ggt' => 'application/vnd.geogebra.tool',
        'ghf' => 'application/vnd.groove-help',
        'gif' => 'image/gif',
        'gim' => 'application/vnd.groove-identity-message',
        'gmx' => 'application/vnd.gmx',
        'gnumeric' => 'application/x-gnumeric',
        'gph' => 'application/vnd.flographit',
        'gqf' => 'application/vnd.grafeq',
        'gqs' => 'application/vnd.grafeq',
        'gram' => 'application/srgs',
        'gre' => 'application/vnd.geometry-explorer',
        'grv' => 'application/vnd.groove-injector',
        'grxml' => 'application/srgs+xml',
        'gsf' => 'application/x-font-ghostscript',
        'gtar' => 'application/x-gtar',
        'gtm' => 'application/vnd.groove-tool-message',
        'gtw' => 'model/vnd.gtw',
        'gv' => 'text/vnd.graphviz',
        'gxt' => 'application/vnd.geonext',
        'h' => 'text/x-c',
        'h261' => 'video/h261',
        'h263' => 'video/h263',
        'h264' => 'video/h264',
        'hal' => 'application/vnd.hal+xml',
        'hbci' => 'application/vnd.hbci',
        'hdf' => 'application/x-hdf',
        'hh' => 'text/x-c',
        'hlp' => 'application/winhlp',
        'hpgl' => 'application/vnd.hp-hpgl',
        'hpid' => 'application/vnd.hp-hpid',
        'hps' => 'application/vnd.hp-hps',
        'hqx' => 'application/mac-binhex40',
        'hta' => 'application/octet-stream',
        'htc' => 'text/html',
        'htke' => 'application/vnd.kenameaapp',
        'htm' => 'text/html',
        'html' => 'text/html',
        'hvd' => 'application/vnd.yamaha.hv-dic',
        'hvp' => 'application/vnd.yamaha.hv-voice',
        'hvs' => 'application/vnd.yamaha.hv-script',
        'i2g' => 'application/vnd.intergeo',
        'icc' => 'application/vnd.iccprofile',
        'ice' => 'x-conference/x-cooltalk',
        'icm' => 'application/vnd.iccprofile',
        'ico' => 'image/x-icon',
        'ics' => 'text/calendar',
        'ief' => 'image/ief',
        'ifb' => 'text/calendar',
        'ifm' => 'application/vnd.shana.informed.formdata',
        'iges' => 'model/iges',
        'igl' => 'application/vnd.igloader',
        'igm' => 'application/vnd.insors.igm',
        'igs' => 'model/iges',
        'igx' => 'application/vnd.micrografx.igx',
        'iif' => 'application/vnd.shana.informed.interchange',
        'imp' => 'application/vnd.accpac.simply.imp',
        'ims' => 'application/vnd.ms-ims',
        'in' => 'text/plain',
        'ini' => 'text/plain',
        'ipfix' => 'application/ipfix',
        'ipk' => 'application/vnd.shana.informed.package',
        'irm' => 'application/vnd.ibm.rights-management',
        'irp' => 'application/vnd.irepository.package+xml',
        'iso' => 'application/octet-stream',
        'itp' => 'application/vnd.shana.informed.formtemplate',
        'ivp' => 'application/vnd.immervision-ivp',
        'ivu' => 'application/vnd.immervision-ivu',
        'jad' => 'text/vnd.sun.j2me.app-descriptor',
        'jam' => 'application/vnd.jam',
        'jar' => 'application/java-archive',
        'java' => 'text/x-java-source',
        'jisp' => 'application/vnd.jisp',
        'jlt' => 'application/vnd.hp-jlyt',
        'jnlp' => 'application/x-java-jnlp-file',
        'joda' => 'application/vnd.joost.joda-archive',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jpgm' => 'video/jpm',
        'jpgv' => 'video/jpeg',
        'jpm' => 'video/jpm',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'kar' => 'audio/midi',
        'karbon' => 'application/vnd.kde.karbon',
        'kfo' => 'application/vnd.kde.kformula',
        'kia' => 'application/vnd.kidspiration',
        'kml' => 'application/vnd.google-earth.kml+xml',
        'kmz' => 'application/vnd.google-earth.kmz',
        'kne' => 'application/vnd.kinar',
        'knp' => 'application/vnd.kinar',
        'kon' => 'application/vnd.kde.kontour',
        'kpr' => 'application/vnd.kde.kpresenter',
        'kpt' => 'application/vnd.kde.kpresenter',
        'ksp' => 'application/vnd.kde.kspread',
        'ktr' => 'application/vnd.kahootz',
        'ktx' => 'image/ktx',
        'ktz' => 'application/vnd.kahootz',
        'kwd' => 'application/vnd.kde.kword',
        'kwt' => 'application/vnd.kde.kword',
        'lasxml' => 'application/vnd.las.las+xml',
        'latex' => 'application/x-latex',
        'lbd' => 'application/vnd.llamagraphics.life-balance.desktop',
        'lbe' => 'application/vnd.llamagraphics.life-balance.exchange+xml',
        'les' => 'application/vnd.hhe.lesson-player',
        'lha' => 'application/octet-stream',
        'link66' => 'application/vnd.route66.link66+xml',
        'list' => 'text/plain',
        'list3820' => 'application/vnd.ibm.modcap',
        'listafp' => 'application/vnd.ibm.modcap',
        'log' => 'text/plain',
        'lostxml' => 'application/lost+xml',
        'lrf' => 'application/octet-stream',
        'lrm' => 'application/vnd.ms-lrm',
        'ltf' => 'application/vnd.frogans.ltf',
        'lvp' => 'audio/vnd.lucent.voice',
        'lwp' => 'application/vnd.lotus-wordpro',
        'lzh' => 'application/octet-stream',
        'm13' => 'application/x-msmediaview',
        'm14' => 'application/x-msmediaview',
        'm1v' => 'video/mpeg',
        'm21' => 'application/mp21',
        'm2a' => 'audio/mpeg',
        'm2v' => 'video/mpeg',
        'm3a' => 'audio/mpeg',
        'm3u' => 'audio/x-mpegurl',
        'm3u8' => 'application/vnd.apple.mpegurl',
        'm4a' => 'audio/mp4',
        'm4u' => 'video/vnd.mpegurl',
        'm4v' => 'video/mp4',
        'ma' => 'application/mathematica',
        'mads' => 'application/mads+xml',
        'mag' => 'application/vnd.ecowin.chart',
        'maker' => 'application/vnd.framemaker',
        'man' => 'text/troff',
        'mathml' => 'application/mathml+xml',
        'mb' => 'application/mathematica',
        'mbk' => 'application/vnd.mobius.mbk',
        'mbox' => 'application/mbox',
        'mc1' => 'application/vnd.medcalcdata',
        'mcd' => 'application/vnd.mcd',
        'mcurl' => 'text/vnd.curl.mcurl',
        'mdb' => 'application/x-msaccess',
        'mdi' => 'image/vnd.ms-modi',
        'me' => 'text/troff',
        'mesh' => 'model/mesh',
        'meta4' => 'application/metalink4+xml',
        'mets' => 'application/mets+xml',
        'mfm' => 'application/vnd.mfmp',
        'mgp' => 'application/vnd.osgeo.mapguide.package',
        'mgz' => 'application/vnd.proteus.magazine',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mif' => 'application/vnd.mif',
        'mime' => 'message/rfc822',
        'mj2' => 'video/mj2',
        'mjp2' => 'video/mj2',
        'mlp' => 'application/vnd.dolby.mlp',
        'mmd' => 'application/vnd.chipnuts.karaoke-mmd',
        'mmf' => 'application/vnd.smaf',
        'mmr' => 'image/vnd.fujixerox.edmics-mmr',
        'mny' => 'application/x-msmoney',
        'mobi' => 'application/x-mobipocket-ebook',
        'mods' => 'application/mods+xml',
        'mov' => 'video/quicktime',
        'movie' => 'video/x-sgi-movie',
        'mp2' => 'audio/mpeg',
        'mp21' => 'application/mp21',
        'mp2a' => 'audio/mpeg',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mp4a' => 'audio/mp4',
        'mp4s' => 'application/mp4',
        'mp4v' => 'video/mp4',
        'mpc' => 'application/vnd.mophun.certificate',
        'mpe' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpg4' => 'video/mp4',
        'mpga' => 'audio/mpeg',
        'mpkg' => 'application/vnd.apple.installer+xml',
        'mpm' => 'application/vnd.blueice.multipass',
        'mpn' => 'application/vnd.mophun.application',
        'mpp' => 'application/vnd.ms-project',
        'mpt' => 'application/vnd.ms-project',
        'mpy' => 'application/vnd.ibm.minipay',
        'mqy' => 'application/vnd.mobius.mqy',
        'mrc' => 'application/marc',
        'mrcx' => 'application/marcxml+xml',
        'ms' => 'text/troff',
        'mscml' => 'application/mediaservercontrol+xml',
        'mseed' => 'application/vnd.fdsn.mseed',
        'mseq' => 'application/vnd.mseq',
        'msf' => 'application/vnd.epson.msf',
        'msh' => 'model/mesh',
        'msi' => 'application/x-msdownload',
        'msl' => 'application/vnd.mobius.msl',
        'msty' => 'application/vnd.muvee.style',
        'mts' => 'model/vnd.mts',
        'mus' => 'application/vnd.musician',
        'musicxml' => 'application/vnd.recordare.musicxml+xml',
        'mvb' => 'application/x-msmediaview',
        'mwf' => 'application/vnd.mfer',
        'mxf' => 'application/mxf',
        'mxl' => 'application/vnd.recordare.musicxml',
        'mxml' => 'application/xv+xml',
        'mxs' => 'application/vnd.triscape.mxs',
        'mxu' => 'video/vnd.mpegurl',
        'n-gage' => 'application/vnd.nokia.n-gage.symbian.install',
        'n3' => 'text/n3',
        'nb' => 'application/mathematica',
        'nbp' => 'application/vnd.wolfram.player',
        'nc' => 'application/x-netcdf',
        'ncx' => 'application/x-dtbncx+xml',
        'ngdat' => 'application/vnd.nokia.n-gage.data',
        'nlu' => 'application/vnd.neurolanguage.nlu',
        'nml' => 'application/vnd.enliven',
        'nnd' => 'application/vnd.noblenet-directory',
        'nns' => 'application/vnd.noblenet-sealer',
        'nnw' => 'application/vnd.noblenet-web',
        'npx' => 'image/vnd.net-fpx',
        'nsf' => 'application/vnd.lotus-notes',
        'oa2' => 'application/vnd.fujitsu.oasys2',
        'oa3' => 'application/vnd.fujitsu.oasys3',
        'oas' => 'application/vnd.fujitsu.oasys',
        'obd' => 'application/x-msbinder',
        'oda' => 'application/oda',
        'odb' => 'application/vnd.oasis.opendocument.database',
        'odc' => 'application/vnd.oasis.opendocument.chart',
        'odf' => 'application/vnd.oasis.opendocument.formula',
        'odft' => 'application/vnd.oasis.opendocument.formula-template',
        'odg' => 'application/vnd.oasis.opendocument.graphics',
        'odi' => 'application/vnd.oasis.opendocument.image',
        'odm' => 'application/vnd.oasis.opendocument.text-master',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
        'ogv' => 'video/ogg',
        'ogx' => 'application/ogg',
        'onepkg' => 'application/onenote',
        'onetmp' => 'application/onenote',
        'onetoc' => 'application/onenote',
        'onetoc2' => 'application/onenote',
        'opf' => 'application/oebps-package+xml',
        'oprc' => 'application/vnd.palm',
        'org' => 'application/vnd.lotus-organizer',
        'osf' => 'application/vnd.yamaha.openscoreformat',
        'osfpvg' => 'application/vnd.yamaha.openscoreformat.osfpvg+xml',
        'otc' => 'application/vnd.oasis.opendocument.chart-template',
        'otf' => 'application/x-font-otf',
        'otg' => 'application/vnd.oasis.opendocument.graphics-template',
        'oth' => 'application/vnd.oasis.opendocument.text-web',
        'oti' => 'application/vnd.oasis.opendocument.image-template',
        'otp' => 'application/vnd.oasis.opendocument.presentation-template',
        'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'ott' => 'application/vnd.oasis.opendocument.text-template',
        'oxt' => 'application/vnd.openofficeorg.extension',
        'p' => 'text/x-pascal',
        'p10' => 'application/pkcs10',
        'p12' => 'application/x-pkcs12',
        'p7b' => 'application/x-pkcs7-certificates',
        'p7c' => 'application/pkcs7-mime',
        'p7m' => 'application/pkcs7-mime',
        'p7r' => 'application/x-pkcs7-certreqresp',
        'p7s' => 'application/pkcs7-signature',
        'p8' => 'application/pkcs8',
        'pas' => 'text/x-pascal',
        'paw' => 'application/vnd.pawaafile',
        'pbd' => 'application/vnd.powerbuilder6',
        'pbm' => 'image/x-portable-bitmap',
        'pcf' => 'application/x-font-pcf',
        'pcl' => 'application/vnd.hp-pcl',
        'pclxl' => 'application/vnd.hp-pclxl',
        'pct' => 'image/x-pict',
        'pcurl' => 'application/vnd.curl.pcurl',
        'pcx' => 'image/x-pcx',
        'pdb' => 'application/vnd.palm',
        'pdf' => 'application/pdf',
        'pfa' => 'application/x-font-type1',
        'pfb' => 'application/x-font-type1',
        'pfm' => 'application/x-font-type1',
        'pfr' => 'application/font-tdpfr',
        'pfx' => 'application/x-pkcs12',
        'pgm' => 'image/x-portable-graymap',
        'pgn' => 'application/x-chess-pgn',
        'pgp' => 'application/pgp-encrypted',
        'php' => 'text/x-php',
        'phps' => 'application/x-httpd-phps',
        'pic' => 'image/x-pict',
        'pkg' => 'application/octet-stream',
        'pki' => 'application/pkixcmp',
        'pkipath' => 'application/pkix-pkipath',
        'plb' => 'application/vnd.3gpp.pic-bw-large',
        'plc' => 'application/vnd.mobius.plc',
        'plf' => 'application/vnd.pocketlearn',
        'pls' => 'application/pls+xml',
        'pml' => 'application/vnd.ctc-posml',
        'png' => 'image/png',
        'pnm' => 'image/x-portable-anymap',
        'portpkg' => 'application/vnd.macports.portpkg',
        'pot' => 'application/vnd.ms-powerpoint',
        'potm' => 'application/vnd.ms-powerpoint.template.macroenabled.12',
        'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'ppam' => 'application/vnd.ms-powerpoint.addin.macroenabled.12',
        'ppd' => 'application/vnd.cups-ppd',
        'ppm' => 'image/x-portable-pixmap',
        'pps' => 'application/vnd.ms-powerpoint',
        'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
        'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptm' => 'application/vnd.ms-powerpoint.presentation.macroenabled.12',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pqa' => 'application/vnd.palm',
        'prc' => 'application/x-mobipocket-ebook',
        'pre' => 'application/vnd.lotus-freelance',
        'prf' => 'application/pics-rules',
        'ps' => 'application/postscript',
        'psb' => 'application/vnd.3gpp.pic-bw-small',
        'psd' => 'image/vnd.adobe.photoshop',
        'psf' => 'application/x-font-linux-psf',
        'pskcxml' => 'application/pskc+xml',
        'ptid' => 'application/vnd.pvi.ptid1',
        'pub' => 'application/x-mspublisher',
        'pvb' => 'application/vnd.3gpp.pic-bw-var',
        'pwn' => 'application/vnd.3m.post-it-notes',
        'pya' => 'audio/vnd.ms-playready.media.pya',
        'pyv' => 'video/vnd.ms-playready.media.pyv',
        'qam' => 'application/vnd.epson.quickanime',
        'qbo' => 'application/vnd.intu.qbo',
        'qfx' => 'application/vnd.intu.qfx',
        'qps' => 'application/vnd.publishare-delta-tree',
        'qt' => 'video/quicktime',
        'qwd' => 'application/vnd.quark.quarkxpress',
        'qwt' => 'application/vnd.quark.quarkxpress',
        'qxb' => 'application/vnd.quark.quarkxpress',
        'qxd' => 'application/vnd.quark.quarkxpress',
        'qxl' => 'application/vnd.quark.quarkxpress',
        'qxt' => 'application/vnd.quark.quarkxpress',
        'ra' => 'audio/x-pn-realaudio',
        'ram' => 'audio/x-pn-realaudio',
        'rar' => 'application/x-rar-compressed',
        'ras' => 'image/x-cmu-raster',
        'rb' => 'text/plain',
        'rcprofile' => 'application/vnd.ipunplugged.rcprofile',
        'rdf' => 'application/rdf+xml',
        'rdz' => 'application/vnd.data-vision.rdz',
        'rep' => 'application/vnd.businessobjects',
        'res' => 'application/x-dtbresource+xml',
        'resx' => 'text/xml',
        'rgb' => 'image/x-rgb',
        'rif' => 'application/reginfo+xml',
        'rip' => 'audio/vnd.rip',
        'rl' => 'application/resource-lists+xml',
        'rlc' => 'image/vnd.fujixerox.edmics-rlc',
        'rld' => 'application/resource-lists-diff+xml',
        'rm' => 'application/vnd.rn-realmedia',
        'rmi' => 'audio/midi',
        'rmp' => 'audio/x-pn-realaudio-plugin',
        'rms' => 'application/vnd.jcp.javame.midlet-rms',
        'rnc' => 'application/relax-ng-compact-syntax',
        'roff' => 'text/troff',
        'rp9' => 'application/vnd.cloanto.rp9',
        'rpss' => 'application/vnd.nokia.radio-presets',
        'rpst' => 'application/vnd.nokia.radio-preset',
        'rq' => 'application/sparql-query',
        'rs' => 'application/rls-services+xml',
        'rsd' => 'application/rsd+xml',
        'rss' => 'text/xml',		/* application/rss+xml is not actually a registered IANA mime-type */
        'rtf' => 'application/rtf',
        'rtx' => 'text/richtext',
        's' => 'text/x-asm',
        'saf' => 'application/vnd.yamaha.smaf-audio',
        'sbml' => 'application/sbml+xml',
        'sc' => 'application/vnd.ibm.secure-container',
        'scd' => 'application/x-msschedule',
        'scm' => 'application/vnd.lotus-screencam',
        'scq' => 'application/scvp-cv-request',
        'scs' => 'application/scvp-cv-response',
        'scurl' => 'text/vnd.curl.scurl',
        'sda' => 'application/vnd.stardivision.draw',
        'sdc' => 'application/vnd.stardivision.calc',
        'sdd' => 'application/vnd.stardivision.impress',
        'sdkd' => 'application/vnd.solent.sdkm+xml',
        'sdkm' => 'application/vnd.solent.sdkm+xml',
        'sdp' => 'application/sdp',
        'sdw' => 'application/vnd.stardivision.writer',
        'see' => 'application/vnd.seemail',
        'seed' => 'application/vnd.fdsn.seed',
        'sema' => 'application/vnd.sema',
        'semd' => 'application/vnd.semd',
        'semf' => 'application/vnd.semf',
        'ser' => 'application/java-serialized-object',
        'setpay' => 'application/set-payment-initiation',
        'setreg' => 'application/set-registration-initiation',
        'sfd-hdstx' => 'application/vnd.hydrostatix.sof-data',
        'sfs' => 'application/vnd.spotfire.sfs',
        'sgl' => 'application/vnd.stardivision.writer-global',
        'sgm' => 'text/sgml',
        'sgml' => 'text/sgml',
        'sh' => 'application/x-sh',
        'shar' => 'application/x-shar',
        'shf' => 'application/shf+xml',
        'sig' => 'application/pgp-signature',
        'silo' => 'model/mesh',
        'sis' => 'application/vnd.symbian.install',
        'sisx' => 'application/vnd.symbian.install',
        'sit' => 'application/x-stuffit',
        'sitx' => 'application/x-stuffitx',
        'skd' => 'application/vnd.koan',
        'skm' => 'application/vnd.koan',
        'skp' => 'application/vnd.koan',
        'skt' => 'application/vnd.koan',
        'sldm' => 'application/vnd.ms-powerpoint.slide.macroenabled.12',
        'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
        'slt' => 'application/vnd.epson.salt',
        'sm' => 'application/vnd.stepmania.stepchart',
        'smf' => 'application/vnd.stardivision.math',
        'smi' => 'application/smil+xml',
        'smil' => 'application/smil+xml',
        'snd' => 'audio/basic',
        'snf' => 'application/x-font-snf',
        'so' => 'application/octet-stream',
        'spc' => 'application/x-pkcs7-certificates',
        'spf' => 'application/vnd.yamaha.smaf-phrase',
        'spl' => 'application/x-futuresplash',
        'spot' => 'text/vnd.in3d.spot',
        'spp' => 'application/scvp-vp-response',
        'spq' => 'application/scvp-vp-request',
        'spx' => 'audio/ogg',
        'src' => 'application/x-wais-source',
        'sru' => 'application/sru+xml',
        'srx' => 'application/sparql-results+xml',
        'sse' => 'application/vnd.kodak-descriptor',
        'ssf' => 'application/vnd.epson.ssf',
        'ssml' => 'application/ssml+xml',
        'st' => 'application/vnd.sailingtracker.track',
        'stc' => 'application/vnd.sun.xml.calc.template',
        'std' => 'application/vnd.sun.xml.draw.template',
        'stf' => 'application/vnd.wt.stf',
        'sti' => 'application/vnd.sun.xml.impress.template',
        'stk' => 'application/hyperstudio',
        'stl' => 'application/vnd.ms-pki.stl',
        'str' => 'application/vnd.pg.format',
        'stw' => 'application/vnd.sun.xml.writer.template',
        'sub' => 'image/vnd.dvb.subtitle',
        'sus' => 'application/vnd.sus-calendar',
        'susp' => 'application/vnd.sus-calendar',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc' => 'application/x-sv4crc',
        'svc' => 'application/vnd.dvb.service',
        'svd' => 'application/vnd.svd',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'swa' => 'application/x-director',
        'swf' => 'application/x-shockwave-flash',
        'swi' => 'application/vnd.aristanetworks.swi',
        'sxc' => 'application/vnd.sun.xml.calc',
        'sxd' => 'application/vnd.sun.xml.draw',
        'sxg' => 'application/vnd.sun.xml.writer.global',
        'sxi' => 'application/vnd.sun.xml.impress',
        'sxm' => 'application/vnd.sun.xml.math',
        'sxw' => 'application/vnd.sun.xml.writer',
        't' => 'text/troff',
        'tao' => 'application/vnd.tao.intent-module-archive',
        'tar' => 'application/x-tar',
        'tcap' => 'application/vnd.3gpp2.tcap',
        'tcl' => 'application/x-tcl',
        'teacher' => 'application/vnd.smart.teacher',
        'tei' => 'application/tei+xml',
        'teicorpus' => 'application/tei+xml',
        'tex' => 'application/x-tex',
        'texi' => 'application/x-texinfo',
        'texinfo' => 'application/x-texinfo',
        'text' => 'text/plain',
        'tfi' => 'application/thraud+xml',
        'tfm' => 'application/x-tex-tfm',
        'thmx' => 'application/vnd.ms-officetheme',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'tmo' => 'application/vnd.tmobile-livetv',
        'torrent' => 'application/x-bittorrent',
        'tpl' => 'application/vnd.groove-tool-template',
        'tpt' => 'application/vnd.trid.tpt',
        'tr' => 'text/troff',
        'tra' => 'application/vnd.trueapp',
        'trm' => 'application/x-msterminal',
        'tsd' => 'application/timestamped-data',
        'tsv' => 'text/tab-separated-values',
        'ttc' => 'application/x-font-ttf',
        'ttf' => 'application/x-font-ttf',
        'ttl' => 'text/turtle',
        'twd' => 'application/vnd.simtech-mindmapper',
        'twds' => 'application/vnd.simtech-mindmapper',
        'txd' => 'application/vnd.genomatix.tuxedo',
        'txf' => 'application/vnd.mobius.txf',
        'txt' => 'text/plain',
        'u32' => 'application/x-authorware-bin',
        'udeb' => 'application/x-debian-package',
        'ufd' => 'application/vnd.ufdl',
        'ufdl' => 'application/vnd.ufdl',
        'umj' => 'application/vnd.umajin',
        'unityweb' => 'application/vnd.unity',
        'uoml' => 'application/vnd.uoml+xml',
        'uri' => 'text/uri-list',
        'uris' => 'text/uri-list',
        'urls' => 'text/uri-list',
        'ustar' => 'application/x-ustar',
        'utz' => 'application/vnd.uiq.theme',
        'uu' => 'text/x-uuencode',
        'uva' => 'audio/vnd.dece.audio',
        'uvd' => 'application/vnd.dece.data',
        'uvf' => 'application/vnd.dece.data',
        'uvg' => 'image/vnd.dece.graphic',
        'uvh' => 'video/vnd.dece.hd',
        'uvi' => 'image/vnd.dece.graphic',
        'uvm' => 'video/vnd.dece.mobile',
        'uvp' => 'video/vnd.dece.pd',
        'uvs' => 'video/vnd.dece.sd',
        'uvt' => 'application/vnd.dece.ttml+xml',
        'uvu' => 'video/vnd.uvvu.mp4',
        'uvv' => 'video/vnd.dece.video',
        'uvva' => 'audio/vnd.dece.audio',
        'uvvd' => 'application/vnd.dece.data',
        'uvvf' => 'application/vnd.dece.data',
        'uvvg' => 'image/vnd.dece.graphic',
        'uvvh' => 'video/vnd.dece.hd',
        'uvvi' => 'image/vnd.dece.graphic',
        'uvvm' => 'video/vnd.dece.mobile',
        'uvvp' => 'video/vnd.dece.pd',
        'uvvs' => 'video/vnd.dece.sd',
        'uvvt' => 'application/vnd.dece.ttml+xml',
        'uvvu' => 'video/vnd.uvvu.mp4',
        'uvvv' => 'video/vnd.dece.video',
        'uvvx' => 'application/vnd.dece.unspecified',
        'uvx' => 'application/vnd.dece.unspecified',
        'vcd' => 'application/x-cdlink',
        'vcf' => 'text/x-vcard',
        'vcg' => 'application/vnd.groove-vcard',
        'vcs' => 'text/x-vcalendar',
        'vcx' => 'application/vnd.vcx',
        'vis' => 'application/vnd.visionary',
        'viv' => 'video/vnd.vivo',
        'vor' => 'application/vnd.stardivision.writer',
        'vox' => 'application/x-authorware-bin',
        'vrml' => 'model/vrml',
        'vsd' => 'application/vnd.visio',
        'vsf' => 'application/vnd.vsf',
        'vss' => 'application/vnd.visio',
        'vst' => 'application/vnd.visio',
        'vsw' => 'application/vnd.visio',
        'vtu' => 'model/vnd.vtu',
        'vxml' => 'application/voicexml+xml',
        'w3d' => 'application/x-director',
        'wad' => 'application/x-doom',
        'wav' => 'audio/x-wav',
        'wax' => 'audio/x-ms-wax',
        'wbmp' => 'image/vnd.wap.wbmp',
        'wbs' => 'application/vnd.criticaltools.wbs+xml',
        'wbxml' => 'application/vnd.wap.wbxml',
        'wcm' => 'application/vnd.ms-works',
        'wdb' => 'application/vnd.ms-works',
        'weba' => 'audio/webm',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
        'wg' => 'application/vnd.pmi.widget',
        'wgt' => 'application/widget',
        'wks' => 'application/vnd.ms-works',
        'wm' => 'video/x-ms-wm',
        'wma' => 'audio/x-ms-wma',
        'wmd' => 'application/x-ms-wmd',
        'wmf' => 'application/x-msmetafile',
        'wml' => 'text/vnd.wap.wml',
        'wmlc' => 'application/vnd.wap.wmlc',
        'wmls' => 'text/vnd.wap.wmlscript',
        'wmlsc' => 'application/vnd.wap.wmlscriptc',
        'wmv' => 'video/x-ms-wmv',
        'wmx' => 'video/x-ms-wmx',
        'wmz' => 'application/x-ms-wmz',
        'woff' => 'application/x-font-woff',
        'wpd' => 'application/vnd.wordperfect',
        'wpl' => 'application/vnd.ms-wpl',
        'wps' => 'application/vnd.ms-works',
        'wqd' => 'application/vnd.wqd',
        'wri' => 'application/x-mswrite',
        'wrl' => 'model/vrml',
        'wsdl' => 'application/wsdl+xml',
        'wspolicy' => 'application/wspolicy+xml',
        'wtb' => 'application/vnd.webturbo',
        'wvx' => 'video/x-ms-wvx',
        'x32' => 'application/x-authorware-bin',
        'x3d' => 'application/vnd.hzn-3d-crossword',
        'xap' => 'application/x-silverlight-app',
        'xar' => 'application/vnd.xara',
        'xbap' => 'application/x-ms-xbap',
        'xbd' => 'application/vnd.fujixerox.docuworks.binder',
        'xbm' => 'image/x-xbitmap',
        'xdf' => 'application/xcap-diff+xml',
        'xdm' => 'application/vnd.syncml.dm+xml',
        'xdp' => 'application/vnd.adobe.xdp+xml',
        'xdssc' => 'application/dssc+xml',
        'xdw' => 'application/vnd.fujixerox.docuworks',
        'xenc' => 'application/xenc+xml',
        'xer' => 'application/patch-ops-error+xml',
        'xfdf' => 'application/vnd.adobe.xfdf',
        'xfdl' => 'application/vnd.xfdl',
        'xht' => 'application/xhtml+xml',
        'xhtml' => 'application/xhtml+xml',
        'xhvml' => 'application/xv+xml',
        'xif' => 'image/vnd.xiff',
        'xla' => 'application/vnd.ms-excel',
        'xlam' => 'application/vnd.ms-excel.addin.macroenabled.12',
        'xlc' => 'application/vnd.ms-excel',
        'xlm' => 'application/vnd.ms-excel',
        'xls' => 'application/vnd.ms-excel',
        'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroenabled.12',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroenabled.12',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlt' => 'application/vnd.ms-excel',
        'xltm' => 'application/vnd.ms-excel.template.macroenabled.12',
        'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'xlw' => 'application/vnd.ms-excel',
        'xml' => 'application/xml',
        'xo' => 'application/vnd.olpc-sugar',
        'xop' => 'application/xop+xml',
        'xpi' => 'application/x-xpinstall',
        'xpm' => 'image/x-xpixmap',
        'xpr' => 'application/vnd.is-xpr',
        'xps' => 'application/vnd.ms-xpsdocument',
        'xpw' => 'application/vnd.intercon.formnet',
        'xpx' => 'application/vnd.intercon.formnet',
        'xsl' => 'application/xml',
        'xslt' => 'application/xslt+xml',
        'xsm' => 'application/vnd.syncml+xml',
        'xspf' => 'application/xspf+xml',
        'xul' => 'application/vnd.mozilla.xul+xml',
        'xvm' => 'application/xv+xml',
        'xvml' => 'application/xv+xml',
        'xwd' => 'image/x-xwindowdump',
        'xyz' => 'chemical/x-xyz',
        'yaml' => 'text/yaml',
        'yang' => 'application/yang',
        'yin' => 'application/yin+xml',
        'yml' => 'text/yaml',
        'zaz' => 'application/vnd.zzazz.deck+xml',
        'zip' => 'application/zip',
        'zir' => 'application/vnd.zul',
        'zirz' => 'application/vnd.zul',
        'zmm' => 'application/vnd.handheld-entertainment+xml'
	);

}