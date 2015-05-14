<?php
/**
 * @brief		File Handler: Amazon S3
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 Jul 2013
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
 * File Handler: Amazon S3
 */
class _Amazon extends \IPS\File
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
			'bucket'		=> 'Text',
			'access_key'	=> 'Text',
			'secret_key'	=> 'Text',
		);
	}
	
	/**
	 * Display name
	 *
	 * @param	array	$settings	Configuration settings
	 * @return	string
	 */
	public static function displayName( $settings )
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'filehandler_display_name', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('filehandler__Amazon'), $settings['bucket'] ) ) );
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
		$check = rtrim( static::buildBaseUrl( $configuration ), '/' );

		if ( mb_substr( $url, 0, mb_strlen( $check ) ) == $check )
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
		$this->container = 'monthly_' . date( 'Y' ) . '_' . date( 'm' );
		parent::__construct( $configuration );
	}

	/**
	 * Load File Data
	 *
	 * @return	void
	 */
	public function load()
	{		
		if ( preg_match( '/^https?:\/\/' . preg_quote( "{$this->configuration['bucket']}.s3.amazonaws.com/", '/' ) . '/', $this->url, $matches ) )
		{
			$exploded = explode( '/', mb_substr( $this->url, mb_strlen( $matches[0] ) ) );
		}
		else
		{
			$exploded = explode( '/', mb_substr( $this->url, mb_strlen( static::buildBaseUrl( $this->configuration ) ) ) );
		}
				
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
	 * Save File
	 *
	 * @return	void
	 */
	public function save()
	{
		/* @link http://docs.aws.amazon.com/AmazonS3/latest/dev/UsingMetadata.html regarding cleaning filenames */
		if ( preg_match( '#[^a-zA-Z0-9!\-_\.\*\(\)]#', $this->filename ) )
		{
			$this->filename = uniqid() . preg_replace( '#[^a-zA-Z0-9!\-_\.\*\(\)]#', '', $this->filename );
		}

		$this->container = trim( $this->container, '/' );
		$this->url = static::buildBaseUrl( $this->configuration ) . "{$this->container}/{$this->filename}";
		$response  = static::makeRequest( "{$this->container}/{$this->filename}", 'PUT', $this->configuration, $this->configurationId, (string) $this->contents() );

		if ( $response->httpResponseCode != 200 )
		{
			throw new \RuntimeException('COULD_NOT_SAVE_FILE');
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
			$this->contents = (string) static::makeRequest( "{$this->container}/{$this->filename}", 'GET', $this->configuration, $this->configurationId );
		}
		return $this->contents;
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		$this->container = trim( $this->container, '/' );

		$response = static::makeRequest( "{$this->container}/{$this->filename}", 'DELETE', $this->configuration, $this->configurationId );

		if ( $response->httpResponseCode != 200 )
		{
			throw new \RuntimeException('COULD_NOT_DELETE_FILE');
		}
	}
	
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @return	void
	 */
	public function deleteContainer( $container )
	{
		$response = static::makeRequest( $container, 'DELETE', $this->configuration, $this->configurationId );

		if ( $response->httpResponseCode != 200 )
		{
			throw new \RuntimeException('COULD_NOT_DELETE_CONTAINER');
		}
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
		$filename = md5( uniqid() ) . '.ips.txt';
		$response = static::makeRequest( "test/{$filename}", 'PUT', $values, NULL, "OK" );

		if ( $response->httpResponseCode != 200 AND $response->httpResponseCode != 307 )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_test_error_amazon', FALSE, array( 'sprintf' => array( $values['bucket'], $response->httpResponseCode ) ) ) );
		}

		static::makeRequest( "test/{$filename}", 'DELETE', $values, NULL );
	}

	/* !Amazon Utility Methods */
	
	/**
	 * Sign and make request
	 *
	 * @param	string		$uri				The URI (relative to the bucket)
	 * @param	string		$verb				The HTTP verb to use
	 * @param	array 		$configuration		The configuration for this instance
	 * @param	int			$configurationId	The configuration ID
	 * @param	string|null	$content			The content to send
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\Exception
	 */
	protected static function makeRequest( $uri, $verb, $configuration, $configurationId, $content=NULL )
	{
		/* Build a request */
		$request = \IPS\Http\Url::external( static::buildBaseUrl( $configuration ) . $uri )->request();
		$date    = date('r');
		$headers = array( 'Date' => $date, 'Content-Type' => \IPS\File::getMimeType( $uri ), 'Content-MD5' => base64_encode( md5( $content, TRUE ) ), 'x-amz-acl' => 'public-read' );

		if( mb_strtoupper( $verb ) === 'PUT' )
		{
			$headers['Content-Length']	= \strlen( $content );
		}

		/* We need to strip query string parameters for the signature, but not always (e.g. a subresource such as ?acl needs to be included and multi-
			object delete requests must include the query string params).  Let the callee decide to do this or not. */
		if( isset( $configuration['_strip_querystring'] ) AND $configuration['_strip_querystring'] === TRUE )
		{
			$uri	= preg_replace( "/^(.*?)\?.*$/", "$1", $uri );
		}

		$string = implode( "\n", array(
			mb_strtoupper( $verb ),
			$headers['Content-MD5'],
			$headers['Content-Type'],
			$date,
			"x-amz-acl:{$headers['x-amz-acl']}",
			"/{$configuration['bucket']}/{$uri}"
		) );
		
		/* Build a signature */
		$signature = base64_encode( hash_hmac( 'sha1', $string, $configuration['secret_key'], true ) );

		/* Sign the request */
		$headers['Authorization'] = "AWS {$configuration['access_key']}:{$signature}";
		$request->setHeaders( $headers );

		/* Make the request */
		$verb = mb_strtolower( $verb );

		$response = $request->$verb( $content );

		/* Need a different region? */
		if ( $response->httpResponseCode == 307 AND $configurationId )
		{
			$xml = $response->decodeXml();
			$configuration['region'] = mb_substr( $xml->Endpoint, mb_strlen( $configuration['bucket'] ) + 1, -mb_strlen( '.amazonaws.com' ) );
			\IPS\Db::i()->update( 'core_file_storage', array( 'configuration' => json_encode( $configuration ) ), array( 'id=?', $configurationId ) );
			unset( \IPS\Data\Store::i()->storageConfigurations );
			return static::makeRequest( $uri, $verb, $configuration, $configurationId, $content );
		}

		/* Return */
		return $response;
	}

	/**
	 * Build up the base Amazon URL
	 * @param   array   $configuration  Configuration data
	 * @return string
	 */
	public static function buildBaseUrl( $configuration )
	{
		return ( \IPS\Request::i()->isSecure() ? "https" : "http" ) . "://{$configuration['bucket']}" . ( ( isset( $configuration['region'] ) AND ! empty( $configuration['region'] ) ) ? ".{$configuration['region']}" : '.s3' ) . ".amazonaws.com/";
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
		$_strip		= array( '_strip_querystring' => TRUE );

		if( $fileIndex )
		{
			$response	= static::makeRequest( "?marker={$fileIndex}&max-keys=100", 'GET', array_merge( $this->configuration, $_strip ), $this->configurationId );
		}
		else
		{
			$response	= static::makeRequest( "?max-keys=100", 'GET', array_merge( $this->configuration, $_strip ), $this->configurationId );
		}

		/* Parse XML document */
		$document	= \IPS\Xml\SimpleXML::loadString( $response );

		/* Loop over dom document */
		foreach( $document->Contents as $result )
		{
			$checked++;

			/* Next we will have to loop through each storage engine type and call it to see if the file is valid */
			foreach( $engines as $engine )
			{
				/* If this file is valid for the engine, skip to the next file */
				if( $engine->isValidFile( \IPS\Http\Url::external( ( \IPS\Request::i()->isSecure() ? "https" : "http" ) . "://{$this->configuration['bucket']}.s3.amazonaws.com/{$result->Key}" ) ) )
				{
					continue 2;
				}
			}

			/* If we are still here, the file was not valid.  Delete and increment count. */
			static::makeRequest( $result->Key, 'DELETE', $this->configuration, $this->configurationId );

			$_lastKey	= $result->Key;
			$results['count']++;
		}

		if( $document->IsTruncated == 'true' AND $checked == 100 )
		{
			$results['fileIndex']	= $_lastKey;
		}

		/* Are we done? */
		if( !$checked OR $checked < 100 )
		{
			$results['_done']	= TRUE;
		}

		return $results;
	}
}