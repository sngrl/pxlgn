<?php
/**
 * @brief		URL Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		10 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Http;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * URL Class
 */
class _Url
{
	const PROTOCOL_AUTOMATIC = 0;
	const PROTOCOL_HTTPS = 1;
	const PROTOCOL_HTTP = 2;
	const PROTOCOL_RELATIVE = 3;
	
	/**
	 * @brief	FURL Definition
	 */
	protected static $furlDefinition = NULL;
	
	/**
	 * Get FURL Definition
	 *
	 * @param	bool	$revert	If TRUE, ignores all customisations and reloads from json
	 * @return	array
	 */
	public static function furlDefinition( $revert=FALSE )
	{
		if ( static::$furlDefinition === NULL or $revert )
		{

			$furlCustomizations	= ( \IPS\Settings::i()->furl_configuration AND !$revert ) ? json_decode( \IPS\Settings::i()->furl_configuration, TRUE ) : array();
			$furlConfiguration = ( isset( \IPS\Data\Store::i()->furl_configuration ) AND \IPS\Data\Store::i()->furl_configuration ) ? json_decode( \IPS\Data\Store::i()->furl_configuration, TRUE ) : array();

			if ( ( \IPS\IN_DEV and !\IPS\DEV_USE_FURL_CACHE ) or !count( $furlConfiguration ) or $revert )
			{
				static::$furlDefinition = array();
				foreach ( \IPS\Application::applications() as $app )
				{
					if( file_exists( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json" ) )
					{
						$data = json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json" ) ), TRUE );
						$topLevel = $data['topLevel'];
												
						$definitions = $data['pages'];
						if ( $topLevel )
						{
							if ( $app->default )
							{
								$definitions = array_map( function( $definition ) use ( $topLevel )
								{
									$definition['with_top_level'] = $topLevel . ( $definition['friendly'] ? '/' . $definition['friendly'] : '' );
									return $definition;
								}, $definitions );
							}
							else
							{
								$definitions = array_map( function( $definition ) use ( $topLevel )
								{
									$definition['without_top_level'] = $definition['friendly'];
									$definition['friendly'] = $topLevel . ( $definition['friendly'] ? '/' . $definition['friendly'] : '' );
									return $definition;
								}, $definitions );
							}
						}
										 	
						static::$furlDefinition = array_merge( static::$furlDefinition, $definitions );
					}
				}

				\IPS\Data\Store::i()->furl_configuration	= json_encode( static::$furlDefinition );

				static::$furlDefinition	= array_merge( static::$furlDefinition, $furlCustomizations );
			}
			else
			{
				static::$furlDefinition = array_merge( $furlConfiguration, $furlCustomizations );
			}
		}
		
		return static::$furlDefinition;
	}

	/**
	 * Return the base URL
	 *
	 * @param	bool			$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	string
	 */
	public static function baseUrl( $protocol = 0 )
	{
		/* Get the base URL */
		$url = \IPS\Settings::i()->base_url;
		
		/* Adjust the protocol */
		if ( $protocol )
		{
			switch ( $protocol )
			{
				case static::PROTOCOL_HTTPS:
					$url = 'https://' . mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
					
				case static::PROTOCOL_HTTP:
					$url = 'http://' . mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
					
				case static::PROTOCOL_RELATIVE:
					$url = '//' . mb_substr( $url, mb_strpos( $url, '://' ) + 3 );
					break;
			}
		}
		
		/* Add a trailing slash */
		if ( mb_substr( $url, -1 ) !== '/' )
		{
			$url .= '/';
		}
		
		/* Return */
		return $url;
	}

	/**
	 * Build Internal URL
	 *
	 * @param	string			$queryString	The query string
	 * @param	string|null		$base			Key for the URL base. If NULL, defaults to current controller location
	 * @param	string			$seoTemplate	The key for making this a friendly URL
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @param	bool			$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	\IPS\Http\Url
	 */
	public static function internal( $queryString, $base=NULL, $seoTemplate=NULL, $seoTitles=array(), $protocol = 0 )
	{
		/* If we don't have a base, assume the template location */
		if ( $base === NULL )
		{
			$base = \IPS\Dispatcher::hasInstance() ? \IPS\Dispatcher::i()->controllerLocation : 'front';
		}
		
		/* We handle setup specially */
		if ( $base === 'setup' )
		{
			return new static( ( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . ( $_SERVER['QUERY_STRING'] ? rtrim( mb_substr( $_SERVER['REQUEST_URI'], 0, -mb_strlen( $_SERVER['QUERY_STRING'] ) ), '?' ) : $_SERVER['REQUEST_URI'] ) . '?' . $queryString, TRUE );
		}

		/* Get the base URL */
		$url = static::baseUrl( $protocol );
						
		/* Do our stuff */
		switch ( $base )
		{
			case 'admin':
				if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation !== 'admin' )
				{
					/* Never disclose adsess in front pages */
					return new static( $url . '?app=core&module=system&controller=redirect&do=admin&_data=' . base64_encode( $queryString ), TRUE );
				}
				else
				{
					return new static( $url . \IPS\CP_DIRECTORY . '/?adsess=' . session_id() . '&' . $queryString, TRUE );
				}
				break;
			
			default:
			case 'front':
				$obj = new static( $url . ( $queryString ? '?' . $queryString : '' ), TRUE );

				if ( $seoTemplate )
				{
					$obj->makeFriendly( $seoTemplate, $seoTitles, $protocol );
				}
				
				return $obj;
				
			case 'none':
				return new static( $url . $queryString, TRUE );
		}
	}
	
	/**
	 * Build External URL
	 *
	 * @param	string			$url
	 * @return	\IPS\Http\Url
	 * @throws	\InvalidArgumentException
	 */
	public static function external( $url )
	{
		return new static( $url, FALSE );
	}
	
	/**
	 * Build IPS-External URL
	 *
	 * @param	string			$url
	 * @return	\IPS\Http\Url
	 */
	public static function ips( $url )
	{
		return new static( "https://remoteservices.invisionpower.com/{$url}/?version=" . \IPS\Application::getAvailableVersion('core'), FALSE );
	}
	
	/**
	 * Convert a value into an "SEO Title" for friendly URLs
	 *
	 * @param	string	$value	Value
	 * @return	string
	 * @note	Many places require an SEO title, so we always need to return something, so when no valid title is available we return a dash
	 */
	public static function seoTitle( $value )
	{
		/* Always lowercase */
		$value = mb_strtolower( $value );
		
		/* Just for readability */
		$value = str_replace( ' ', '-', $value );
		
		/* Disallowed characters which browsers may try to automatically percent-encode */
		$value = str_replace( array( '!', '*', '\'', '(', ')', ';', ':', '@', '&', '=', '+', '$', ',', '/', '?', '#', '[', ']', '%', '\\', '"', '<', '>', '^', '{', '}', '|', '.', '`' ), '', $value );
		
		/* Trim */
		$value = preg_replace( '/\-+/', '-', $value );
		$value = trim( $value, '-' );
		$value = trim( $value );
		
		/* Return */
		return $value ?: '-';
	}
		
	/**
	 * @brief	URL
	 */
	protected $url = NULL;
	
	/**
	 * @brief	Data
	 */
	public $data = array(
		'scheme'	=> NULL,
		'host'		=> NULL,
		'port'		=> NULL,
		'user'		=> NULL,
		'pass'		=> NULL,
		'path'		=> NULL,
		'query'		=> NULL,
		'fragment'	=> NULL
	);
		
	/**
	 * @brief	Query String
	 */
	public $queryString = array();
	
	/** 
	 * @brief	Is internal?
	 */
	public $isInternal = FALSE;
	
	/** 
	 * @brief	Is friendly?
	 */
	public $isFriendly = FALSE;
	
	/**
	 * Constructor
	 *
	 * @param	string	$url		The URL
	 * @param	bool	$internal	Is internal? (NULL to auto-detect)
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( $url, $internal=NULL )
	{
		$this->setUrl( $url, $internal );
	}
	
	/**
	 * Adjust Query String
	 *
	 * @param	string|array	$keyOrArray	Key, or array of key/value paird
	 * @param	string|null		$value		Value, or NULL if $key is an array
	 * @return	\IPS\Http\Url
	 */
	public function setQueryString( $key, $value=NULL )
	{
		if ( is_array( $key ) )
		{
			$queryString = array_merge( $this->queryString, $key );
		}
		else
		{
			$queryString = array_merge( $this->queryString, array( $key => $value ) );
		}

		return $this->reconstruct( $this->data, $queryString );
	}
	
	/**
	 * Add CSRF check to query string
	 *
	 * @return	\IPS\Http\Url
	 */
	public function csrf()
	{
		return $this->setQueryString( 'csrfKey', \IPS\Session::i()->csrfKey );
	}

	/**
	 * Strip Query String
	 *
	 * @param	string|array	$keys	The key(s) to strip - if omitted, entire query string is wiped
	 * @return	\IPS\Http\Url
	 */
	public function stripQueryString( $keys=NULL )
	{
		$queryString	= array();

		if( $keys !== NULL )
		{
			if( !is_array( $keys ) )
			{
				$keys = array( $keys => $keys );
			}

			$queryString = array_diff_key( $this->queryString, $keys );
		}

		return $this->reconstruct( $this->data, $queryString );
	}

	/**
	 * Strip URL arguments
	 *
	 * @param	int		$position	Arguments from this position onward will be stripped. Value of 0 is equivalent to stripping the entire query string
	 * @return	\IPS\Http\Url
	 */
	public function stripArguments( $position=0 )
	{
		if( $position === 0 )
		{
			return $this->stripQueryString();
		}

		$queryString	= array();
		$_index			= 0;

		foreach( $this->queryString as $key => $value )
		{
			if( $_index >= $position )
			{
				break;
			}

			$queryString[ $key ]	= $value;
			$_index++;
		}

		return $this->reconstruct( $this->data, $queryString );
	}
	
	/**
	 * Adjust fragment
	 *
	 * @param	string	$value	New fragment
	 * @return	\IPS\Http\Url
	 */
	public function setFragment( $value )
	{
		return $this->reconstruct( array_merge( $this->data, array( 'fragment' => $value ) ), $this->queryString );
	}

	/**
	 * Create a URL object from data array
	 *
	 * @param	array	$data	URL data pieces
	 * @return	\IPS\Http\Url
	 */
	public static function createFromArray( $data )
	{
		return new static(
			( $data['scheme'] ? ( $data['scheme'] . '://' ) : '//' ) .
			( ( isset( $data['user'] ) or isset( $data['pass'] ) ) ? "{$data['user']}:{$data['pass']}@" : '' ) .
			$data['host'] .
			( isset( $data['port'] ) ? ":{$data['port']}" : '' ) .
			$data['path'] .
			( !empty( $data['query'] ) ? ( ( mb_strpos( $data['path'], '?' ) !== FALSE ? '&' : '?' ) . ( is_array( $data['query'] ) ? http_build_query( $data['query'], '', '&' ) : $data['query'] ) ) : '' ) .
			( isset( $data['fragment'] ) ? "#{$data['fragment']}" : '' ),
			FALSE
		);
	}
	
	/**
	 * Reconstruct
	 *
	 * @param	array	$data			URL data
	 * @param	array	$queryString	Query String
	 * @return	\IPS\Http\Url
	 */
	protected function reconstruct( $data, $queryString=array() )
	{
		return new static(
			( ( isset( $data['scheme'] ) AND $data['scheme'] ) ? ( $data['scheme'] . '://' ) : '//' ) .
			( ( isset( $data['user'] ) or isset( $data['pass'] ) ) ? "{$data['user']}:{$data['pass']}@" : '' ) .
			$data['host'] .
			( isset( $data['port'] ) ? ":{$data['port']}" : '' ) .
			$data['path'] .
			( !empty( $queryString ) ? ( ( mb_strpos( $data['path'], '?' ) !== FALSE ? '&' : '?' ) . http_build_query( $queryString, '', '&' ) ) : '' ) .
			( isset( $data['fragment'] ) ? "#{$data['fragment']}" : '' ),
			$this->isInternal
		);
	}
	
	/**
	 * Set URL
	 *
	 * @param	string	$url		The URL
	 * @param	bool	$internal	Is internal? (NULL to auto-detect)
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	protected function setUrl( $url, $internal=NULL )
	{
		/* Set it */
		$this->url = $url;
		
		/* Parse */
		$this->data = $this->utf8ParseUrl( $url );
		$this->queryString = array();
		if ( isset( $this->data['query'] ) )
		{
			/* If there are no ampersands and the query string starts with '/' then it is part of the path */
			if( mb_strpos( $this->data['query'], '&' ) === FALSE AND mb_strpos( $this->data['query'], '/' ) === 0 )
			{
				$this->data['path'] .= '?' . $this->data['query'];
			}
			else
			{
				/* We can't use parse_str because it replaces . with _ which can cause FURLs to get converted incorrectly */
				$values	= explode( '&', $this->data['query'] );
				foreach( $values as $value )
				{
					$pieces		= explode( '=', $value, 2 );

					/* If there's a parameter which is /something/ and has no value, that's actually part of the path */
					if( !isset( $pieces[1] ) and preg_match( '#^/(.*?)/$#i', $pieces[0] ) )
					{
						$this->data['path'] .= '?' . $pieces[0];
						continue;
					}

					$this->queryString[ urldecode( $pieces[0] ) ] = isset( $pieces[1] ) ? urldecode( $pieces[1] ) : '';
				}
			}
		}

		/* Is it internal? We have to ignore protocol because areas like force login over https and secure checkout in Nexus will use https urls for an otherwise http site and we end up with infinite redirects */
		if ( $internal === NULL )
		{
			$this->isInternal = ( mb_substr( str_replace( 'https://', 'http://', $this->url ), 0, mb_strlen( str_replace( 'https://', 'http://', \IPS\Settings::i()->base_url ) ) ) === str_replace( 'https://', 'http://', \IPS\Settings::i()->base_url ) );
		}
		else
		{
			$this->isInternal = $internal;
		}
				
		/* Is it friendly? */
		if ( $this->isInternal and ( mb_strpos( $url, 'index.php' ) === FALSE or mb_substr( str_replace( 'https://', 'http://', $this->url ), str_replace( 'https://', 'http://', mb_strlen( \IPS\Settings::i()->base_url ) ), 11 ) === 'index.php?/' ) )
		{	
			$this->isFriendly = TRUE;
		}
	}
	
	/**
	 * To String
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return (string) $this->url;
	}

	/**
	 * Return URL that conforms to RFC 3986
	 *
	 * @return	string
	 */
	public function rfc3986()
	{
		$pieces		= $this->utf8ParseUrl( (string) $this->url );
		$pathBits	= implode( "/", array_map( "rawurlencode", explode( "/", ltrim( $pieces['path'], '/' ) ) ) );

		return ( $pieces['scheme'] ? ( $pieces['scheme'] . '://' ) : '//' ) .
			( ( isset( $pieces['user'] ) AND $pieces['user'] ) ? $pieces['user'] : '' ) . 
			( ( isset( $pieces['pass'] ) AND $pieces['pass'] ) ? ':' . $pieces['pass'] : '' ) . 
			$pieces['host'] .
			( ( isset( $pieces['port'] ) AND $pieces['port'] ) ? $pieces['port'] : '' ) . 
			'/' . $pathBits .
			( ( isset( $pieces['query'] ) AND $pieces['query'] ) ? '?' . $pieces['query'] : '' ) . 
			( ( isset( $pieces['fragment'] ) AND $pieces['fragment'] ) ? '#' . $pieces['fragment'] : '' );
	}
	
	/**
	 * Get ACP query string without adsess
	 *
	 * @return	string
	 */
	public function acpQueryString()
	{
		$queryString = $this->queryString;
		unset( $queryString['adsess'] );
		unset( $queryString['csrf'] );
		return http_build_query( $queryString, '', '&' );
	}
		
	/**
	 * Make friendly
	 *
	 * @param	string|array	$seoTemplate	The key for making this a friendly URL; or a manual FURL definition
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @param	bool			$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	void
	 */
	public function makeFriendly( $seoTemplate, $seoTitles, $protocol = 0 )
	{		
		/* Enabled? */
		if ( !\IPS\Settings::i()->use_friendly_urls )
		{
			return;
		}
			
		/* Make SEO Titles an array if they're not already */
		if ( !is_array( $seoTitles ) )
		{
			$seoTitles = array( $seoTitles );
		}

		/* Get FURL definition */
		$definition = NULL;
		if ( is_array( $seoTemplate ) )
		{
			$definition = $seoTemplate;
		}
		else
		{
			$furlDefinition = static::furlDefinition();
			if ( isset( $furlDefinition[ $seoTemplate ] ) )
			{
				$definition = $furlDefinition[ $seoTemplate ];
			}
		}

		/* Find it */
		if ( isset( $definition ) )
		{
			$titleMatch = 0;
			$parsed = &$this->queryString;

			$url = preg_replace_callback( '/{(\#|\@|\?)([^}]+?)?}/i', function( $match ) use ( &$parsed, $titleMatch, $seoTitles, $seoTemplate )
			{
				if ( $match[1] === '?' )
				{
					if ( !isset( $match[2] ) )
					{
						$match[2] = $titleMatch++;
					}

					if ( !isset( $seoTitles[ $match[2] ] ) )
					{
						return '';
					}

					return $seoTitles[ $match[2] ];
				}
				else
				{
					$toReturn = ( !empty($parsed[ $match[2] ]) ) ? $parsed[ $match[2] ] : '';
					unset( $parsed[ $match[2] ] );
					return $toReturn;
				}
			}, $definition['friendly'] );

			$qs = $this->queryString;

			parse_str( $definition['real'], $ignore );
			foreach ( array_keys( $ignore ) as $i )
			{
				unset( $qs[ $i ] );
			}
			
			$trailingSlash = mb_strpos( $definition['friendly'], '.' ) !== FALSE ? '' : '/';
			if ( \IPS\Settings::i()->htaccess_mod_rewrite )
			{
				$this->setUrl( rtrim( static::baseUrl( $protocol ) . $url, '/' ) . ( $qs ? '/?' . http_build_query( $qs, '', '&' ) : $trailingSlash ) );
			}
			else
			{
				$this->setUrl( static::baseUrl( $protocol ) . 'index.php?/' . $url . ( $qs ? '/&' . http_build_query( $qs, '', '&' ) : ( $url ? $trailingSlash : '' ) ) );
			}
		}
		
		/* Set it */
		$this->isFriendly = TRUE;
	}
	
	/**
	 * Get friendly URL data
	 *
	 * @param	bool	$verify		If TRUE, will check URL uses correct SEO title
	 * @return	array	Parameters
	 * @throws	\OutOfRangeException	Invalid URL
	 * @throws	\DomainException		URL does not have correct SEO title (exception message will contain correct URL)
	 */
	public function getFriendlyUrlData( $verify=FALSE )
	{
		$set = array();
		
		/* Need to remember the template we use for FURL verification */
		$usedTemplate	= NULL;

		/* Get furl definition */
		$furlDefinition = static::furlDefinition();

		/* Determine our gateway */
		$gatewayFile	= explode( '/', str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ) );
		$gatewayFile	= array_pop( $gatewayFile );

		/* Work out what the FURL "query" is */
		$baseUrl = $this->utf8ParseUrl( \IPS\Settings::i()->base_url );
		if ( \IPS\Settings::i()->htaccess_mod_rewrite )
		{
			$query = ( isset( $this->data['path'] ) ? $this->data['path'] : '' );
		}
		else
		{
			if ( isset( $this->data['path'] ) and mb_strpos( $this->data['path'], $gatewayFile . '?' ) )
			{
				$query = ( isset( $this->data['query'] ) ? ltrim( $this->data['query'], '/' )  : '' );
			}		
			else
			{
				$query = '';
			}	
		}

		$query = preg_replace( '#^(' . preg_quote( rtrim( $baseUrl['path'], '/' ), '#' ) . ')/(' . $gatewayFile . ')?(?:(?:\?/|\?))?(.+?)?$#', '$3', $query );

		/* Examine our URLs */
		$this->examineFurl( $furlDefinition, $query, $set, $usedTemplate );
		
		/* What about an alias? */
		if ( $usedTemplate === NULL and $query )
		{
			$this->examineFurl( $furlDefinition, $query, $set, $usedTemplate, TRUE );
		}
		
		/* What about a different topLevel? */
		if ( $usedTemplate === NULL and $query )
		{
			$this->examineFurl( $furlDefinition, $query, $set, $usedTemplate, FALSE, TRUE );
		}

		/* Nothing? */
		if ( $usedTemplate === NULL and $query )
		{
			throw new \OutOfRangeException;
		}

		/* Redirect to correct FURL if necessary */
		if ( $verify )
		{			
			/* Build the requested URL as an object */
			$requestedUrls	= array( $this );

			/* Now...if we did not include a module or a controller, add the default ones on so we can check those too */
			if( isset( $requestedUrls[0]->data['query'] ) )
			{
				parse_str( $requestedUrls[0]->data['query'], $parameters );
			}

			if( isset( $parameters['app'] ) )
			{
				if( !isset( $parameters['module'] ) OR !isset( $parameters['controller'] ) )
				{
					$modules	= \IPS\Application::load( $parameters['app'] )->modules( 'front' );
				
					foreach( $modules as $moduleId => $module )
					{
						if( !isset( $parameters['module'] ) AND $module->default )
						{
							$requestedUrls[]	= $requestedUrls[0]->setQueryString( 'module', $module->key );

							if( !isset( $parameters['controller'] ) )
							{
								$requestedUrls[]	= $requestedUrls[0]->setQueryString( array( 'module' => $module->key, 'controller' => $module->default_controller ) );
							}
						}
						else if( isset( $parameters['module'] ) AND !isset( $parameters['controller'] ) AND $module->key == $parameters['module'] )
						{
							$requestedUrls[]	= $requestedUrls[0]->setQueryString( 'controller', $module->default_controller );
						}
					}
				}
			}

			foreach( $requestedUrls as $requestedUrl )
			{
				$correctUrl = $requestedUrl;
				
				/* If this was a FURL request and we have a template to use, great! */
				if( $usedTemplate !== NULL )
				{
					/* Is there a callback, or should we just build the URL ourselves based on the information available? */
					if( !empty( $usedTemplate['verify'] ) )
					{
						try
						{							
							$correctUrl	= $usedTemplate['verify']::loadFromUrl( \IPS\Http\Url::internal( $usedTemplate['_rebuiltUrl'], 'front' ) )->url();
						}
						catch ( \OutOfRangeException $e )
						{
							break;
						}
					}
					else
					{
						$correctUrl	= \IPS\Http\Url::internal( $usedTemplate['_rebuiltUrl'], 'front', $usedTemplate['_template'], $usedTemplate['_dynamicInfo'] );
					}
																									
					/* Strip the trailing slash? */
					if ( mb_strpos( $usedTemplate['friendly'], '.' ) !== FALSE )
					{
						$correctUrl = new \IPS\Http\Url( rtrim( $correctUrl, '/' ) );
					}
					
					/* Merge query string back in */
					$correctUrl = $correctUrl->setQueryString( $requestedUrl->queryString );
				}
				/* Not a FURL request...should it have been? */
				else
				{
					if( !empty( $requestedUrl->data['query'] ) )
					{
						foreach ( $furlDefinition as $_key => $data )
						{
							if( mb_stripos( $requestedUrl->data['query'], $data['real'] ) !== FALSE )
							{
								/* Figure out if this FURL definition requires extra data.
									Example: messenger_convo and messenger have the same $data['real'] definition, but messenger_convo requires an 'id' parameter too */
								$params	= array();
								preg_match_all( '/{(.+?)}/', $data['friendly'], $matches );

								foreach ( $matches[1] as $tag )
								{
									switch ( mb_substr( $tag, 0, 1 ) )
									{
										case '#':
											$params[] = mb_substr( $tag, 1 );
											break;
										
										case '@':
											$params[] = mb_substr( $tag, 1 );
											break;
									}
								}

								/* If this definition requires a parameter, see if we have it.  If not, skip to next definition to check. */
								if( count( $params ) )
								{
									parse_str( $requestedUrl->data['query'], $set );
									
									foreach( $params as $param )
									{
										if( !isset( $set[ $param ] ) )
										{
											continue 2;
										}
									}
								}

								/* Now try to check URL */
								try
								{
									/* Is there a callback, or should we just build the URL ourselves based on the information available? */
									if( !empty( $data['verify'] ) )
									{
										parse_str( $data['real'], $paramsInCorrectUrl );
										$paramsInCorrectUrl = array_merge( array_keys( $paramsInCorrectUrl ), $params );
										
										$correctUrl	= $data['verify']::loadFromUrl( \IPS\Http\Url::internal( $requestedUrl->data['query'], 'front' ) )->url();
										
										$paramsToSet = array();
										foreach ( $requestedUrl->queryString as $k => $v )
										{
											if ( !in_array( $k, $paramsInCorrectUrl ) )
											{
												$paramsToSet[ $k ] = $v;
											}
										}
										
										if ( count( $paramsToSet ) )
										{
											$correctUrl = $correctUrl->setQueryString( $paramsToSet );
										}
									}
									else
									{
										$seoTitles = array();
										if ( isset( $data['seoTitles'] ) )
										{
											foreach ( $data['seoTitles'] as $seoTitleData )
											{
												try
												{
													$class = $seoTitleData['class'];
													$queryParam = $seoTitleData['queryParam'];
													$property = $seoTitleData['property'];
																										
													$seoTitles[] = $class::load( \IPS\Request::i()->$queryParam )->$property;
												}
												catch ( \OutOfRangeException $e ) {}
											}
										}
										
										$correctUrl	= \IPS\Http\Url::internal( $requestedUrl->data['query'], 'front', $_key, $seoTitles );
									}
										
									break;
								}
								catch ( \Exception $e ) {}
							}
						}
					}
				}

				/* If the URL was wrong, redirect - we compare without query strings */
				/* @note The urldecode() is needed for http://community.invisionpower.com/resources/bugs.html/_/4-0-0/109-problem-with-seo-names-categories-forums-r47585 */
				/* @note We have to ignore protocol because areas like force login over https and secure checkout in Nexus will use https urls for an otherwise http site and
					we end up with infinite redirects */
				if( str_replace( 'https://', 'http://', (string) $correctUrl->stripQueryString() ) != str_replace( 'https://', 'http://', urldecode( (string) $requestedUrl->stripQueryString() ) ) or $correctUrl->isFriendly and !$requestedUrl->isFriendly )
				{
					/* IP.Board 3.x used /page-x in the path rather than a query string argument - we support this so as not to break past links */
					/* @link http://community.invisionpower.com/resources/bugs.html/_/4-0-0/url-part-page4-r47961 */
					if( mb_strpos( (string) $requestedUrl, '/page-' ) )
					{
						preg_match( "/\/page\-(\d+?)/", (string) $requestedUrl, $matches );

						if( isset( $matches[1] ) )
						{
							$correctUrl = $correctUrl->setQueryString( 'page', $matches[1] );
						}
					}

					throw new \DomainException( $correctUrl );
				}
			}
		}
		
		return $set;
	}
	
	/**
	 * Examine friendly URL
	 *
	 * @return	array
	 */
	protected function examineFurl( &$furlDefinition, &$query, &$set, &$usedTemplate, $checkAlias=FALSE, $checkWithoutTopLevel=FALSE )
	{
		foreach ( $furlDefinition as $_key => $data )
		{
			/* What are we looking at? */
			$check = $data['friendly'];
			if ( $checkAlias and isset( $data['alias'] ) )
			{
				$check = $data['alias'];
			}
			elseif ( $checkWithoutTopLevel )
			{
				if ( isset( $data['without_top_level'] ) )
				{
					$check = $data['without_top_level'];
				}
				elseif ( isset( $data['with_top_level'] ) )
				{
					$check = $data['with_top_level'];
				}
			}
						
			/* If this is just for the default page, skip it, otherwise it'll match everything */
			if ( !$check )
			{
				continue;
			}
						
			/* Start with what we have for the friendly URL */
			$regex = preg_quote( $check, '/' );
				
			/* Parse out variables */
			$params = array();
			preg_match_all( '/{(.+?)}/', $check, $matches );
			foreach ( $matches[1] as $tag )
			{
				switch ( mb_substr( $tag, 0, 1 ) )
				{
					case '#':
						$regex = str_replace( '\{#' . mb_substr( $tag, 1 ) . '\}', '(\d+?)', $regex );
						$params[] = mb_substr( $tag, 1 );
						break;
							
					case '@':
						$regex = str_replace( '\{@' . mb_substr( $tag, 1 ) . '\}', '(.+?)', $regex );
						$params[] = mb_substr( $tag, 1 );
						break;
			
					case '?':
						$regex = str_replace( '\{\?\}', '(.+?)', $regex );
						$params[]	= '';
						break;
				}
			}
						
			/* Now see if it matches */
			if ( preg_match( '/^' . $regex . '(?:$|\/$|\?|\/\?|&|\/&)(.+?$)?/i', $query, $matches ) )
			{				
				/* This will be used for FURL checks later */
				$data['_dynamicInfo']	= array();
				$data['_rebuiltUrl']	= '';
			
				/* Get the variables we need to set from the "real" URL */
				parse_str( $data['real'], $set );
				
				/* Grab any other variables from the requested URL */
				if( isset( $matches[1] ) AND count( $matches[1] ) AND ( mb_substr_count( $matches[1], '=' ) === 1 OR mb_strpos( $matches[1], '&' ) !== FALSE ) )
				{
					$morePieces = explode( '&', $matches[1] );
			
					foreach( $morePieces as $_argument )
					{
						if( !\strpos( $_argument, '=' ) )
						{
							continue;
						}

						list( $k, $v )	= explode( '=', $_argument );
						$set[ $k ]		= $v;
					}
				}
				
				/* Add variables from the friendly URL */
				foreach ( $params as $k => $v )
				{
					if( $v )
					{
						$set[ $v ] = $matches[ $k + 1 ];
					}
					else
					{
						$data['_dynamicInfo'][]	= \IPS\Settings::i()->htaccess_mod_rewrite ? $matches[ $k + 1 ] : urldecode( $matches[ $k + 1 ] );
					}
				}
				
				/* Set them */
				foreach ( $set as $k => $v )
				{
					$data['_rebuiltUrl']	.= $k . '=' . $v . '&';
				}
				
				$data['_rebuiltUrl']	= mb_substr( $data['_rebuiltUrl'], 0, -1 );
			
				/* Query string might override */
				if ( isset( $this->data['query'] ) )
				{
					parse_str( $this->data['query'], $qs );
					foreach ( $qs as $k => $v )
					{
						$set[ $k ] = $v;
					}
				}

				/* Figure out which template we used */
				$usedTemplate				= $data;
				$usedTemplate['_template']	= $_key;
			}
			if( $usedTemplate )
			{
				break;
			}			
		}

		/* We need to explicitly do this so it is not carried over to the FURL checking below */
		unset($data);
		
		/* Some urls pick up lonely amps, lets remove those before we check */
		if ( mb_strstr( $query, '&' ) )
		{
			while( mb_substr( $query, -1 ) === '&' )
			{
				$query = mb_substr( $query, 0, -1 );
			}
		}
	}
		
	/**
	 * Make a HTTP Request
	 *
	 * @param	int|null	$timeout			Timeout
	 * @param	string		$httpVersion		HTTP Version
	 * @param	bool		$followRedirects	Automatically follow redirects?
	 * @return	\IPS\Http\Request
	 */
	public function request( $timeout=null, $httpVersion=null, $followRedirects=TRUE )
	{
		if( $timeout === null )
		{
			$timeout = \IPS\DEFAULT_REQUEST_TIMEOUT;
		}

		if ( function_exists( 'curl_init' ) and function_exists( 'curl_exec' ) and \IPS\BYPASS_CURL === false )
		{
			/* Older versions of curl can't handle chunked encoding properly.. */
			$version = curl_version();

			if( \IPS\FORCE_CURL or version_compare( $version['version'], '7.36', '>=' ) )
			{
				$requestObj	= new \IPS\Http\Request\Curl( $this, $timeout, $httpVersion, $followRedirects );
			}
		}

		if( !isset( $requestObj ) )
		{
			$requestObj	= new \IPS\Http\Request\Sockets( $this, $timeout, $httpVersion, $followRedirects );
		}

		/* Set a default user-agent since many services block requests without one, i.e. spotify */
		$requestObj->setHeaders( array( 'User-Agent' => 'IPS Community Suite 4.0' ) );

		return $requestObj;
	}
	
	/**
	 * Import as file
	 *
	 * @param	string	$storageExtension	The extension which specified the storage location to use
	 * @return	\IPS\File
	 * @throws	\RuntimeException
	 */
	public function import( $storageExtension )
	{
		$parts = $this->utf8ParseUrl( $this->url );
		return \IPS\File::create( $storageExtension, basename( $parts['path'] ), $this->request()->get() );
	}

	/**
	 * Make safe for ACP
	 *
	 * @param	bool	$resource	If TRUE, will redirect silently
	 * @return	\IPS\Http\Url
	 */
	public function makeSafeForAcp( $resource=FALSE )
	{
		return static::internal( "app=core&module=system&controller=redirect", 'front' )->setQueryString( array(
			'url'		=> (string) $this,
			'key'		=> hash_hmac( "sha256", (string) $this, md5( \IPS\Settings::i()->sql_pass . \IPS\Settings::i()->board_url . \IPS\Settings::i()->sql_database ) ),
			'resource'	=> $resource
		) );
	}

	/**
	 * UTF-8 safe parse URL
	 *
	 * @param	string	$url	URL to parse
	 * @return	array
	 */
	public function utf8ParseUrl( $url )
	{
		/* parse_url doesn't work with relative protocols */
		$relativeProtocol = FALSE;
		if ( mb_substr( $url, 0, 2 ) === '//' )
		{
			$relativeProtocol = TRUE;
			$url = 'http:' . $url;
		}
		
		/* Encode UTF8 characters */
		$encodedUrl	= preg_replace_callback( '%[^:/@?&=#a-zA-Z0-9_\.,\-\%]+%usD', function( $matches ){ return urlencode( $matches[0] ); }, $url );
			
		/* Parse */
		$parts		= parse_url( $encodedUrl );
			
		if( is_array( $parts ) )
		{
			foreach( $parts as $name => $value )
			{
				/* @link http://community.invisionpower.com/4bugtrack/ampersand-in-link-text-stripped-r2686/ */
				if ( $name === 'query' )
				{
					$value = str_replace( '%26', '%2526', $value );
				}
				
				$parts[ $name ]	= urldecode($value);
			}
		}
		else
		{
			$parts	= parse_url( $url );
			$parts	= ( is_array( $parts ) ) ? $parts : array();
		}
		
		/* Take the schema out again if it's relative */
		if ( $relativeProtocol )
		{
			$parts['scheme'] = '';
		}
		
		/* Return */
		return $parts;
	}
}