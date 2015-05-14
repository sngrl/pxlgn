<?php
/**
 * @brief		Sockets REST Class
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
 * Sockets REST Class
 */
class _Sockets
{	
	/**
	 * @brief	URL
	 */
	protected $url = NULL;
	
	/**
	 * @brief	HTTP Version
	 */
	protected $httpVersion = '1.1';
	
	/**
	 * @brief	Timeout
	 */
	protected $timeout = 5;
		
	/**
	 * @brief	Headers
	 */
	protected $headers = array();
	
	/**
	 * @brief	Follow redirects?
	 */
	protected $followRedirects = TRUE;
	
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
		$this->url = $url;
		$this->httpVersion = $httpVersion ?: '1.1';
		$this->timeout = $timeout;
		$this->followRedirects = $followRedirects;
		$this->contextOptions['http']['ignore_errors'] = TRUE;
	}
	
	/**
	 * Login
	 *
	 * @param	string	Username
	 * @param	string	Password
	 * @retrun	\IPS\Http\Request\Socket (for daisy chaining)	
	 */
	public function login( $username, $password )
	{
		$this->setHeaders( array( 'Authorization' => 'Basic ' . base64_encode( "{$username}:{$password}" ) ) );
		return $this;
	}
	
	/**
	 * Set Headers
	 *
	 * @param	array	Key/Value pair of headers
	 * @return	\IPS\Http\Request\Socket
	 */
	public function setHeaders( $headers )
	{
		$this->headers = array_merge( $this->headers, $headers );	
		return $this;
	}
	
	/**
	 * Magic Method: __call
	 *
	 * @param	string	$method
	 * @param	array	Params
	 * @return	\IPS\Http\Response
	 */
	public function __call( $method, $params )
	{
		/* The data (string or array) will be the first parameter */
		if ( isset( $params[0] ) && is_array( $params[0] ) )
		{
			$this->setHeaders( array( 'Content-Type' => 'application/x-www-form-urlencoded' ) );
			$data = http_build_query( $params[0] );
		}
		else
		{
			$data = ( isset( $params[0] ) ? $params[0] : NULL );
		}

		/* Set the method */
		$this->contextOptions['http']['method'] = mb_strtoupper( $method );

		/* Set the Content-Length header automatically if this is a POST */
		if( $this->contextOptions['http']['method'] == 'POST' )
		{
			$this->setHeaders( array( 'Content-Length' => \strlen( $data ) ) );
		}
		
		/* Parse URL */		
		if ( isset( $this->url->data['user'] ) or isset( $this->url->data['pass'] ) )
		{
			$this->login( isset( $this->url->data['user'] ) ? $this->url->data['user'] : NULL, isset( $this->url->data['pass'] ) ? $this->url->data['pass'] : NULL );
		}

		/* Open connection */
		$resource = @\fsockopen( ( $this->url->data['scheme'] === 'https' ? 'ssl://' : '' ) . $this->url->data['host'], isset( $this->url->data['port'] ) ? $this->url->data['port'] : ( $this->url->data['scheme'] === 'http' ? 80 : 443 ), $errno, $errstr, $this->timeout );
		if ( $resource === FALSE )
		{
			throw new SocketsException( $errstr, $errno );
		}
		
		/* Send request */		
		$request  = mb_strtoupper( $method ) . ' ' . ( isset( $this->url->data['path'] ) ? $this->url->data['path'] : '' ) . ( !empty( $this->url->queryString ) ? ( '?' . http_build_query( $this->url->queryString ) ) : '' ) . ( isset( $this->url->data['fragment'] ) ? "#{$this->url->data['fragment']}" : '' ) . " HTTP/{$this->httpVersion}\r\n";
		$request .= "Host: {$this->url->data['host']}\r\n";
		foreach ( $this->headers as $k => $v )
		{
			$request .= "{$k}: {$v}\r\n";
		}
		$request .= "Connection: Close\r\n";
		$request .= "\r\n";
		if ( $data )
		{
			$request .= $data;
		}
		\fwrite( $resource, $request );
								
		/* Read response */
		stream_set_timeout( $resource, $this->timeout );
		$status = stream_get_meta_data( $resource );
		$response = '';
		while( !feof($resource) and !$status['timed_out'] )		
		{
			$response .= \fgets( $resource, 8192 );
			$status = stream_get_meta_data( $resource );
		}
		
		/* Close connection */
		\fclose( $resource );

		/* Interpret response */
		$response = new \IPS\Http\Response( $response );

		/* Either return it or follow it */
		if ( $this->followRedirects and ( $response->httpResponseCode == 302 OR $response->httpResponseCode == 301 ) )
		{
			$newRequest = \IPS\Http\Url::external( $response->httpHeaders['Location'] )->request( $this->timeout, $this->httpVersion, $this->followRedirects );
			return call_user_func_array( array( $newRequest, $method ), $params );
		}
		return $response;
	}

}

/**
 * Sockets Exception Class
 */
class SocketsException extends \IPS\Http\Request\Exception { }