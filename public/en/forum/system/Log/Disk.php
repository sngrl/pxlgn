<?php
/**
 * @brief		Disk Log Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 Nov 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Log;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Disk log class
 */
class _Disk extends \IPS\Log
{
	/* @Brief Log directory */
	public $logDir = NULL;
	
	/**
	 * The constructor
	 *
	 * @param   array|null $config
	 * @param	int		   $severity
	 * @return	void
	 */
	public function __construct( $config, $severity )
	{
		$this->logDir = \IPS\ROOT_PATH . '/uploads/logs';
		
		parent::__construct( $config, $severity );
	}
	
	/**
	 * Get the most recent log titles
	 * 
	 * @param	int		$limit
	 * @return	array	array( array( 'date' => unixTimeStamp, 'title' => title, 'suffix' => suffix ), ... );
	 */
	public function getMostRecentLogsTitles( $limit=50 )
	{
		$logs = array();
		
		if ( is_dir( $this->logDir ) )
		{
			$dir = new \DirectoryIterator( $this->logDir );
			
			$files = array();
			foreach ( $dir as $file )
			{
				if ( ! $file->isDir() and ! $file->isDot() and mb_substr( $file, -4 ) === '.cgi' and mb_substr( $file, 0, 6 ) !== 'latest' )
				{
					$files[ $file->getMTime() ] = $file->getFilename();
				}
			}
		
			krsort( $files );
			
			$set = array_slice( $files, 0, $limit, true );
			
			foreach( $set as $mtime => $name )
			{
				$suffix = mb_substr( mb_substr( $name, 0, -4 ), 11 ); #[YYYY-mm-dd_]suffix[.cgi]
				
				$logs[] = array( 'date' => $mtime, 'title' => $name, 'suffix' => $suffix );
			}
		}
		
		return $logs;
	}
	
	/**
	 * Get log content
	 * 
	 * @param	string	$title	Title of log to fetch
	 * @return	string	Raw log contents
	 */
	public function getLog( $title )
	{
		/* Paranoid clean */
		$title = preg_replace( '#^[^a-z0-9-_\.]+?$#i', '', $title );
		
		if ( file_exists( $this->logDir . '/' . $title ) )
		{
			/* Get the last 300 lines max */
			$handle  = @fopen( $this->logDir . '/' . $title, 'r' );
			$content = '';
			$lines   = 300;
			
			if ( $handle )
			{
				$l   = $lines;
				$pos = -2;
				$beg = false;
			
				while ( $l > 0 )
				{
					$_t = " ";
						
					while( $_t != "\n" )
					{
						if ( @fseek( $handle, $pos, SEEK_END ) == -1 )
						{
							$beg = true;
							break;
						}
						 
						$_t = @fgetc( $handle );
						$pos--;
					}
					 
					$l--;
					 
					if ( $beg )
					{
						rewind( $handle );
					}
					 
					$t[ $lines - $l - 1 ] = @fgets( $handle );
					 
					if ( $beg )
					{
						break;
					}
				}
				 
				@fclose ($handle);
				 
				$content = trim( implode( "", array_reverse( $t ) ) );
			}
			
			return $content;
		}
		
		return NULL;
	}
	
	/**
	 * Prune logs
	 *
	 * @param	int		$days	Older than (days) to prune
	 * @return	void
	 */
	public function prune( $days )
	{
		try
		{
			foreach( new \DirectoryIterator( $this->logDir ) as $file )
			{
				if( $file->isDot() OR !$file->isFile() )
				{
					continue;
				}
		
				if( preg_match( "#.cgi$#", $file->getFilename(), $matches ) )
				{
					if( $file->getMTime() < ( time() - ( 60 * 60 * 24 * (int) $days ) ) )
					{
						@unlink( $file->getPathname() );
					}
				}
			}
		} catch ( Exception $e ) {}
	}
	
	
	/**
	 * Log
	 *
	 * @param		string			$message		The message
	 * @param		string			$suffix			Unique key for this log
	 * @return		void
	 */
	public function write( $message, $suffix=NULL )
	{
		$date       = date( 'r' );
		$ip         = $this->getIpAddress();
		$url        = ( isset( $_SERVER['argv'] ) ) ? "Command line" : \IPS\Request::i()->url();
		$fileSuffix = ( $suffix !== NULL ) ? '_' . $suffix : '';
		
		if ( is_array( $message ) )
		{
			$message = var_export( $message, TRUE );
		}
		#$debug      = @print_r( debug_backtrace(), true );
$message = <<<MSG
{$date} (Severity: {$this->severity})
{$ip} - {$url}
{$message}
------------------------------------------------------------------------

MSG;
		if ( ! is_dir( $this->logDir ) )
		{
			if ( ! @mkdir( $this->logDir ) )
			{
				if( $this->severity === LOG_DEBUG )
				{
					return;
				}

				throw new \RuntimeException( 'COULD_NOT_CREATE_LOG_DIR' );
			}

			@chmod( $this->logDir, \IPS\IPS_FOLDER_PERMISSION );
		}
		
		/* Latest */
		if ( ! @\file_put_contents( $this->logDir . '/latest' . $fileSuffix . '.cgi', $message ) )
		{
			if( $this->severity === LOG_DEBUG )
			{
				return;
			}

			throw new \RuntimeException( 'COULD_NOT_WRITE_FILE' );
		}

		@chmod( $this->logDir . '/latest' . $fileSuffix . '.cgi', \IPS\IPS_FILE_PERMISSION );

		/* Log */
		if ( ! @\file_put_contents( $this->logDir . '/' . date( 'Y' ) . '_' . date( 'm' ) . '_' . date('d') . $fileSuffix . '.cgi', $message, FILE_APPEND ) )
		{
			if( $this->severity === LOG_DEBUG )
			{
				return;
			}

			throw new \RuntimeException( 'COULD_NOT_WRITE_FILE' );
		}

		@chmod( $this->logDir . '/' . date( 'Y' ) . '_' . date( 'm' ) . '_' . date('d') . $fileSuffix . '.cgi', \IPS\IPS_FILE_PERMISSION );
	}
}