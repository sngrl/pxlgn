<?php
/**
 * @brief		cURL REST Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Http\Request;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * cURL REST Class
 */
class _Curl
{
	/**
	 * @brief	URL
	 */
	protected $url = NULL;
	
	/**
	 * @brief	Curl Handle
	 */
	protected $curl = NULL;
	
	/**
	 * @brief	Has the Content-Type header been set?
	 * @note	Because cURL will automatically set the Content-Type header to multipart/form-data if we send a POST request with an array, we need to change it to a string if we want to send a different Content-Type
	 * @see		<a href='http://www.php.net/manual/en/function.curl-setopt.php'>PHP: curl_setopt - Manual</a>
	 */
	protected $modifiedContentType = FALSE;

	/**
	 * Contructor
	 *
	 * @param	\IPS\Http\Url	$url				URL
	 * @param	int				$timeout			Timeout (in seconds)
	 * @param	string			$httpVersion		HTTP Version
	 * @param	bool			$followRedirects	Automatically follow redirects?
	 * @return	void
	 */
	public function __construct( $url, $timeout, $httpVersion, $followRedirects )
	{
		/* Init */
		$this->url = $url;
		$this->curl = curl_init();
		
		/* Need to adjust if this is FTP */
		$user	= null;
		$pass	= null;

		if( $this->url->data['scheme'] == 'ftp' )
		{
			if( isset( $this->url->data['user'] ) AND $this->url->data['user'] AND isset( $this->url->data['pass'] ) AND $this->url->data['pass'] )
			{
				$user	= $this->url->data['user'];
				$pass	= $this->url->data['pass'];

				$this->url->data['user']	= null;
				$this->url->data['pass']	= null;
				$this->url	= $this->url->setFragment( null );
			}

			/* Set our basic settings */
			curl_setopt_array( $this->curl, array(
				CURLOPT_HEADER			=> TRUE,								// Specifies that we want the headers
				CURLOPT_RETURNTRANSFER	=> TRUE,								// Specifies that we want the response
				CURLOPT_SSL_VERIFYPEER	=> FALSE,								// Specifies that we don't need to validate the SSL certificate, if applicable (causes issues with, for example, API calls to CPanel in Nexus)
				CURLOPT_TIMEOUT			=> $timeout,							// The timeout
				CURLOPT_URL				=> (string) $this->url,					// The URL we're requesting
				) );

			/* Need to set user and pass if this is FTP */
			if( $user !== null AND $pass !== null )
			{
				curl_setopt( $this->curl, CURLOPT_USERPWD, $user . ':' . $pass );
			}
		}
		else
		{
			/* Work out HTTP version */
			if( $httpVersion === null )
			{
				$version = curl_version();

				/* Before 7.36 there are some issues handling chunked-encoded data */
				if( version_compare( $version['version'], '7.36', '>=' ) )
				{
					$httpVersion = '1.1';
				}
				else
				{
					$httpVersion = '1.0';
				}
			}

			$httpVersion = ( $httpVersion == '1.1' ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0 );

			/* Set our basic settings */
			curl_setopt_array( $this->curl, array(
				CURLOPT_HEADER			=> TRUE,								// Specifies that we want the headers
				CURLOPT_HTTP_VERSION	=> $httpVersion,						// Sets the HTTP version
				CURLOPT_RETURNTRANSFER	=> TRUE,								// Specifies that we want the response
				CURLOPT_SSL_VERIFYPEER	=> FALSE,								// Specifies that we don't need to validate the SSL certificate, if applicable (causes issues with, for example, API calls to CPanel in Nexus)
				CURLOPT_TIMEOUT			=> $timeout,							// The timeout
				CURLOPT_URL				=> (string) $this->url,					// The URL we're requesting
				) );
		}
					
		if ( ! ini_get('open_basedir') AND ! ini_get('safe_mode') )
		{
			curl_setopt( $this->curl, CURLOPT_AUTOREFERER, $followRedirects ); // Specifies that we want to provide the referer when we follow redirects
			curl_setopt( $this->curl, CURLOPT_FOLLOWLOCATION, $followRedirects ); // Specifies that we want to follow redirects
		}
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		curl_close( $this->curl );
	}
	
	/**
	 * Login
	 *
	 * @param	string	$username		Username
	 * @param	string	$password		Password
	 * @return	\IPS\Http\Request\Curl
	 */
	public function login( $username, $password )
	{
		curl_setopt_array( $this->curl, array(
			CURLOPT_HTTPAUTH		=> CURLAUTH_BASIC,
			CURLOPT_USERPWD			=> "{$username}:{$password}"
			) );
		
		return $this;
	}
	
	/**
	 * Set Headers
	 *
	 * @param	array	$headers		Key/Value pair of headers
	 * @return	\IPS\Http\Request\Curl
	 */
	public function setHeaders( $headers )
	{
		$extra = array();
	
		foreach ( $headers as $k => $v )
		{
			switch ( $k )
			{
				case 'Cookie':
					curl_setopt( $this->curl, CURLOPT_COOKIE, $v );
					break;
				
				case 'Accept-Encoding':
					curl_setopt( $this->curl, CURLOPT_ENCODING, $v );
					break;
				
				case 'Referer':
					curl_setopt( $this->curl, CURLOPT_REFERER, $v );
					break;
					
				case 'User-Agent':
					curl_setopt( $this->curl, CURLOPT_USERAGENT, $v );
					break;
					
				default:
					if ( $k === 'Content-Type' )
					{
						$this->modifiedContentType = TRUE;
					}
					$extra[] = "{$k}: {$v}";
					break;
			}
		}
		
		if ( !empty( $extra ) )
		{
			curl_setopt( $this->curl, CURLOPT_HTTPHEADER, $extra );
		}
		
		return $this;
	}

	/**
	 * HTTP GET
	 *
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function get()
	{
		/* Specify that this is a GET request */
		curl_setopt( $this->curl, CURLOPT_HTTPGET, TRUE );
					
		/* Execute */
		return $this->_execute();
	}
	
	/**
	 * HTTP POST
	 *
	 * @param	mixed	$data	Data to post (can be array or string)
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function post( $data=NULL )
	{		
		/* Specify that this is a POST request */
		curl_setopt( $this->curl, CURLOPT_POST, TRUE );
		
		/* Set the data */
		if ( !$this->modifiedContentType and is_array( $data ) )
		{
			$data = http_build_query( $data );
		}
		curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $data );
		
		/* Execute */
		return $this->_execute();
	}
	
	/**
	 * Magic Method: __call
	 * Used for other HTTP methods (like PUT and DELETE)
	 *
	 * @param	string	$method	Method (A HTTP method)
	 * @param	array	$params	Parameters (a single parameter with data to post, which can be an array or a string)
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	public function __call( $method, $params )
	{		
		/* Specify the request method */
		curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, mb_strtoupper( $method ) );
		
		/* If we have any data to send, set it */
		if ( isset( $params[0] ) )
		{
			curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $params[0] );
		}
		
		/* Execute */
		return $this->_execute();
	}
	
	/**
	 * Execute the request
	 *
	 * @return	\IPS\Http\Response
	 * @throws	\IPS\Http\Request\CurlException
	 */
	protected function _execute()
	{
		/* Execute */
		$output = curl_exec( $this->curl );
		
		/* Errors? */
		if ( $output === FALSE )
		{
			throw new CurlException( curl_error( $this->curl ), curl_errno( $this->curl ) );
		}

		/* If this is FTP we need to fudge the headers a little */
		if( $this->url->data['scheme'] == 'ftp' )
		{
			$output = "HTTP/1.1 200 OK\nFTP: True\r\n\r\n" . $output;
		}
				
		/* Return response */
		return new \IPS\Http\Response( $output );
	}
}

/**
 * CURL Exception Class
 */
class CurlException extends \IPS\Http\Request\Exception { }