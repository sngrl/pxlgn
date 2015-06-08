<?php
/**
 * @brief		File Handler: FTP
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		06 May 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\File;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Handler: FTP
 */
class _Ftp extends \IPS\File
{
	/* !ACP Configuration */
	
	/**
	 * Settings
	 *
	 * @return	array
	 */
	public static function settings()
	{
		return array(
			'ftp_details'	=> array( 'type' => 'Ftp', 'options' => array( 'validate' => FALSE ) ),
			'url'			=> array( 'type' => 'Text', 'options' => array( 'placeholder' => 'http://www.example.com/example' ) ),
		);
	}
	
	/**
	 * Test Settings
	 *
	 * @param	array	$values	The submitted values
	 * @return	void
	 * @throws	\LogicException
	 */
	public static function testSettings( &$values )
	{		
		if ( !function_exists( 'ftp_connect' ) )
		{
			throw new \BadFunctionCallException( 'ftp_err_no_ext' );
		}
		elseif ( $values['ftp_details']['protocol'] === 'ssl_ftp' and !function_exists( 'ftp_ssl_connect' ) )
		{
			throw new \BadFunctionCallException( 'ftp_err_no_ssl' );
		}
		elseif ( $values['ftp_details']['protocol'] === 'sftp' and !function_exists( 'ssh2_connect' ) )
		{
			throw new \BadFunctionCallException( 'ftp_err_no_sftp' );
		}
		
		$values['url'] = rtrim( $values['url'], '/' );
		
		try
		{
			if ( $values['ftp_details']['protocol'] == 'sftp' )
			{
				$ftp = new \IPS\Ftp\Sftp( $values['ftp_details']['server'], $values['ftp_details']['un'], $values['ftp_details']['pw'], $values['ftp_details']['port'] );
			}
			else
			{
				$ftp = new \IPS\Ftp( $values['ftp_details']['server'], $values['ftp_details']['un'], $values['ftp_details']['pw'], $values['ftp_details']['port'], ( $values['ftp_details']['protocol'] == 'ssl_ftp' ) );
			}
			$ftp->chdir( $values['ftp_details']['path'] );
			
			if ( $values['url'] )
			{
				$filename = md5( uniqid() ) . '.ips.txt';
				$tmpFileName = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
				\file_put_contents( $tmpFileName, 'OK' );
				$ftp->upload( $filename, $tmpFileName );
				$result = \IPS\Http\Url::external( $values['url'] . '/' . $filename)->request()->get();
				$ftp->delete( $filename );
				unlink( $tmpFileName );

				if ( (string) $result !== 'OK' )
				{
					if ( $result->httpResponseCode == 200 )
					{
						throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_test_ftp_unexpected_response', FALSE, array( 'sprintf' => array( $values['ftp_details']['server'] ) ) ) );
					}
					else
					{
						throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_test_error_ftp', FALSE, array( 'sprintf' => array( $values['ftp_details']['server'], $result->httpResponseCode ) ) ) );
					}
				}
			}
		}
		catch ( \IPS\Ftp\Exception $e )
		{
			throw new \DomainException( 'ftp_err-' . $e->getMessage() );
		}		
	}
	
	/**
	 * Display name
	 *
	 * @param	array	$settings	Configuration settings
	 * @return	string
	 */
	public static function displayName( $settings )
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'filehandler_display_name', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('filehandler__Ftp'), $settings['ftp_details']['server'] ) ) );
	}
	
	/* !File Handling */

	/**
	 * Is this URL valid for this engine?
	 *
	 * @param   \IPS\Http\Url   $url            URL
	 * @param   array           $configuration  Specific configuration for this method
	 * @return  bool
	 */
	public static function isValidUrl( $url, $configuration )
	{
		if ( mb_substr( $url, 0, mb_strlen( $configuration['url'] ) ) === $configuration['url'] )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Storage configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		$this->container = 'monthly_' . date( 'm' ) . '_' . date( 'Y' );
		parent::__construct( $configuration );
	}
	
	/**
	 * Save File
	 *
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function save()
	{
		/* Get contents */
		$contents = $this->contents();
				
		/* Create a temporary file */
		$tmpFileName = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
		\file_put_contents( $tmpFileName, $contents );
				
		/* Move into the correct folder */		
		if( !in_array( $this->container, $this->ftp()->ls() ) )
		{
			/* Make dir */
			$this->ftp()->mkdir( $this->container );
			
			/* Stick an index.html file in */
			$tmpFileName = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			\file_put_contents( $tmpFileName, '' );
			$this->ftp()->upload( 'index.html', $tmpFileName );
		}
		$this->ftp()->chdir( $this->container );
		
		/* Upload */
		$this->ftp()->upload( $this->filename, $tmpFileName );
		
		/* Move back */
		$this->ftp()->cdup();
		
		/* Destroy the temporary file */
		unlink( $tmpFileName );
		
		/* Set the URL */
		$this->url = new \IPS\Http\Url( $this->configuration['url'] . '/' . $this->container . '/' . $this->filename );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function delete()
	{
		$this->ftp()->delete( $this->container . '/' . $this->filename );
	}
		
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function deleteContainer( $container )
	{
		$this->ftp()->rmdir( $container, TRUE );
	}
	
	/* !FTP Utility Methods */
	
	/**
	 * @brief	FTP Connection
	 */
	protected static $ftp = NULL;
	
	/**
	 * Get FTP Connection
	 *
	 * @return	\IPS\Ftp
	 * @throws	\IPS\Ftp\Exception
	 */
	protected function ftp()
	{
		if ( static::$ftp === NULL )
		{
			if ( $this->configuration['ftp_details']['protocol'] == 'sftp' )
			{
				static::$ftp = new \IPS\Ftp\Sftp( $this->configuration['ftp_details']['server'], $this->configuration['ftp_details']['un'], $this->configuration['ftp_details']['pw'], $this->configuration['ftp_details']['port'] );
			}
			else
			{
				static::$ftp = new \IPS\Ftp( $this->configuration['ftp_details']['server'], $this->configuration['ftp_details']['un'], $this->configuration['ftp_details']['pw'], $this->configuration['ftp_details']['port'], ( $this->configuration['ftp_details']['protocol'] == 'ssl_ftp' ) );
			}

			static::$ftp->chdir( $this->configuration['ftp_details']['path'] );
		}
		return static::$ftp;
	}

	/**
	 * Remove orphaned files
	 *
	 * @param	int			$fileIndex		The file offset to start at in a listing
	 * @param	array	$engines	All file storage engine extension objects
	 * @return	array
	 */
	public function removeOrphanedFiles( $fileIndex, $engines )
	{
		/* Start off our results array */
		$results	= array(
			'_done'				=> FALSE,
			'count'				=> 0,
			'fileIndex'			=> $fileIndex,
		);

		/* We don't really care about the container index for the database method...just look for files based on the file offset */
		$checked	= 0;
		$skipped	= 0;

		$iterator	= new \RecursiveIteratorIterator( new \IPS\Ftp\RecursiveDirectoryFtpIterator( $this->ftp(), $this->configuration['ftp_details']['path'] ) );

		while( $iterator->valid() )
		{
			/* We aren't checking directories */
			if( $iterator->current()->isDir() OR $iterator->current()->getFilename() == 'index.html' )
			{
				$iterator->next();
				continue;
			}

			/* Have we hit our limit?  If so we need to stop. */
			if( $checked >= 100 )
			{
				break;
			}

			/* Is there an offset?  If so we need to skip */
			if( $fileIndex > 0 AND $fileIndex > $skipped )
			{
				$skipped++;
				$iterator->next();
				continue;
			}

			$checked++;

			$currentName	= str_replace( $this->configuration['ftp_details']['path'] . '/', '', $iterator->current()->getPathname() );

			/* Next we will have to loop through each storage engine type and call it to see if the file is valid */
			foreach( $engines as $engine )
			{
				/* If this file is valid for the engine, skip to the next file */
				if( $engine->isValidFile( \IPS\Http\Url::external( "{$this->configuration['url']}/{$currentName}" ) ) )
				{
					$iterator->next();
					continue 2;
				}
			}

			/* If we are still here, the file was not valid */
			$this->ftp()->delete( $iterator->current()->getPathname() );

			$results['count']++;

			$iterator->next();
		}

		$results['fileIndex']	+=	$checked;

		/* Are we done? */
		if( !$checked OR $checked < 100 )
		{
			$results['_done']	= TRUE;
		}

		return $results;
	}
}