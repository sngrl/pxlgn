<?php
/**
 * @brief		FTP Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		06 May 2013
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
 * FTP Class
 */
class _Ftp
{
	/**
	 * @brief	Connection resource
	 */
	protected $ftp;

	/**
	 * Constructor
	 *
	 * @param	string	$host		Hostname
	 * @param	string	$username	Username
	 * @param	string	$password	Password
	 * @param	int		$port		Port
	 * @param	bool	$secure		Use secure SSL-FTP connection?
	 * @param	int		$timeout	Timeout (in seconds)
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function __construct( $host, $username, $password, $port=21, $secure=FALSE, $timeout=10 )
	{
		if ( $secure )
		{
			$this->ftp = @ftp_ssl_connect( $host, $port, $timeout );
		}
		else
		{
			$this->ftp = @ftp_connect( $host, $port, $timeout );
		}
		
		if ( $this->ftp === FALSE )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_CONNECT' );
		}
		if ( !@ftp_login( $this->ftp, $username, $password ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_LOGIN' );
		}

		/* Typically if passive mode is required, ftp_nlist will return FALSE instead of an array */
		if( $this->ls() === FALSE )
		{
			@ftp_pasv( $this->ftp, true );
		}
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		@ftp_close( $this->ftp );
	}
	
	/**
	 * chdir
	 *
	 * @param	string	$dir	Directory
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function chdir( $dir )
	{
		if ( !@ftp_chdir( $this->ftp, $dir ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_CHDIR' );
		}
	}
	
	/**
	 * cdup
	 *
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function cdup()
	{
		if ( !@ftp_cdup( $this->ftp ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_CDUP' );
		}
	}
	
	/**
	 * mkdir
	 *
	 * @param	string	$dir	Directory
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function mkdir( $dir )
	{
		if ( !@ftp_mkdir( $this->ftp, $dir ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_MKDIR' );
		}
	}
	
	/**
	 * ls
	 *
	 * @param	string	$path	Argument to pass to ftp_nlist
	 * @return	array|bool
	 */
	public function ls( $path = '.' )
	{
		return ftp_nlist( $this->ftp, $path );
	}

	/**
	 * Raw list
	 *
	 * @param	string	$path	Argument to pass to ftp_nlist
	 * @return	array
	 */
	public function rawList( $path = '.', $recursive = FALSE )
	{
		return ftp_rawlist( $this->ftp, $path, $recursive );
	}
	
	/**
	 * Upload File
	 *
	 * @param	string	$filename	Filename to use
	 * @param	string	$file		Path to local file
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function upload( $filename, $file )
	{
		if ( !@ftp_put( $this->ftp, $filename, $file, FTP_BINARY ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_UPLOAD' );
		}
	}
	
	/**
	 * Download File
	 *
	 * @param	string		$filename	The file to download
	 * @param	string|null	$target		Location to save downloaded file or NULL to return contents
	 * @return	string		File contents
	 * @throws	\IPS\Ftp\Exception
	 */
	public function download( $filename, $target=NULL )
	{
		$temp = FALSE;
		if ( $target === NULL )
		{
			$temp = TRUE;
			$target = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
		}
		
		if ( !@ftp_get( $this->ftp, $target, $filename, FTP_BINARY ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_DOWNLOAD' );
		}
		$result = file_get_contents( $target );
		
		if ( $temp )
		{
			@unlink( $target );
		}
		
		return $result;		
	}
	
	/**
	 * Delete file
	 *
	 * @param	string	$file		Path to file
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function delete( $file )
	{
		if ( !@ftp_delete( $this->ftp, $file ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_DELETE' );
		}
	}
	
	/**
	 * Delete directory
	 *
	 * @param	string	$dir		Path to directory
	 * @param	bool	$recursive	Recursive? (If FALSE and directory is not empty, operation will fail)
	 * @return	void
	 * @throws	\IPS\Ftp\Exception
	 */
	public function rmdir( $dir, $recursive=FALSE )
	{	
		if ( $recursive )
		{
			$this->chdir( $dir );
			foreach ( ftp_rawlist( $this->ftp, '.' ) as $data )
			{
				preg_match( '/^(.).*\s(.*)$/', $data, $matches );
				if ( $matches[2] !== '.' and $matches[2] !== '..' )
				{
					if ( $matches[1] === 'd' )
					{
						$this->rmdir( $matches[2], TRUE );
					}
					else
					{
						$this->delete( $matches[2] );
					}
				}
			}
			$this->cdup();
		}
				
		if ( !@ftp_rmdir( $this->ftp, $dir ) )
		{
			throw new \IPS\Ftp\Exception( 'COULD_NOT_DELETE' );
		}
	}
}