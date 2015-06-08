<?php
/**
 * @brief		File Handler: Database
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
 * File Handler: Database
 */
class _Database extends \IPS\File
{
	/* !ACP Configuration */
	
	/**
	 * Settings
	 *
	 * @return	array
	 */
	public static function settings()
	{
		return array();
	}
	
	/**
	 * Display name
	 *
	 * @param	array	$settings	Configuration settings
	 * @return	string
	 */
	public static function displayName( $settings )
	{
		return \IPS\Member::loggedIn()->language()->addToStack('filehandler__Database');
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
		$check = \IPS\Http\Url::internal( "applications/core/interface/file/", 'none', NULL, array(), \IPS\Http\Url::PROTOCOL_RELATIVE );
		if ( mb_substr( $url, 0, mb_strlen( $check ) ) === $check )
		{
			return TRUE;
		}

		return FALSE;
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
			$record = \IPS\Db::i()->select( '*', 'core_files', array( 'id=? AND salt=?', $this->url->queryString['id'], $this->url->queryString['salt'] ) )->first();
			$this->contents = $record['contents'];
		}
		return $this->contents;
	}
	
	/**
	 * Load File Data
	 *
	 * @return	void
	 */
	public function load()
	{
		try
		{
			if ( !isset( $this->url->queryString['id'] ) OR !isset( $this->url->queryString['salt'] ) )
			{
				throw new \InvalidArgumentException;
			}
			
			$record = \IPS\Db::i()->select( '*', 'core_files', array( 'id=? AND salt=?', $this->url->queryString['id'], $this->url->queryString['salt'] ) )->first();
			$this->filename = $record['filename'];
			$this->originalFilename = $record['filename'];
			$this->container = $record['container'];
		}
		catch ( \UnderflowException $e )
		{

		}
		catch( \InvalidArgumentException $e )
		{
			
		}
	}
	
	/**
	 * Save File
	 *
	 * @return	void
	 */
	public function save()
	{
		$salt = md5( uniqid() );
		
		$id = \IPS\Db::i()->insert( 'core_files', array(
			'filename'	=> $this->filename,
			'salt'		=> $salt,
			'contents'	=> $this->contents(),
			'container'	=> $this->container
		) );

		$this->url = \IPS\Http\Url::internal( "applications/core/interface/file/?id={$id}&salt={$salt}", 'none', NULL, array(), \IPS\Http\Url::PROTOCOL_RELATIVE );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'core_files', array( 'id=? AND salt=?', $this->url->queryString['id'], $this->url->queryString['salt'] ) );
	}
	
	/**
	 * Delete Container
	 *
	 * @param	string	$container	Key
	 * @return	void
	 */
	public function deleteContainer( $container )
	{
		\IPS\Db::i()->delete( 'core_files', array( 'container=?', $container ) );
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

		/* Init */
		$checked	= 0;

		/* Loop over files */
		foreach( \IPS\DB::i()->select( '*', 'core_files', array(), 'id ASC', array( $fileIndex, 100 ) ) as $file )
		{
			$checked++;

			/* Next we will have to loop through each storage engine type and call it to see if the file is valid */
			foreach( $engines as $engine )
			{
				/* If this file is valid for the engine, skip to the next file */
				if( $engine->isValidFile( \IPS\Http\Url::internal( "applications/core/interface/file/?id={$file['id']}&salt={$file['salt']}", 'none' ) ) )
				{
					continue 2;
				}
			}

			/* If we are still here, the file was not valid.  Delete and increment count. */
			\IPS\DB::i()->delete( 'core_files', array( 'id=?', $file['id'] ) );

			$results['count']++;
		}

		$results['fileIndex']	+=	( $checked - $results['count'] );

		/* Are we done? */
		if( !$checked OR $checked < 100 )
		{
			$results['_done']	= TRUE;
		}

		return $results;
	}
}