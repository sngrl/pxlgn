<?php
/**
 * @brief		Twitter share link
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		11 Sept 2013
 * @version		SVN_VERSION_NUMBER
 * @see			<a href='https://dev.twitter.com/docs/tweet-button'>Tweet button documentation</a>
 */

namespace IPS\Content\ShareServices;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Twitter share link
 */
class _Twitter
{
	/**
	 * @brief	URL to the content item
	 */
	protected $url		= NULL;
	
	/**
	 * @brief	Title of the content item
	 */
	protected $title	= NULL;
		
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	URL to the content [optional - if omitted, some services will figure out on their own]
	 * @param	string			$title	Default text for the content, usually the title [optional - if omitted, some services will figure out on their own]
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL, $title=NULL )
	{
		$this->url		= $url;
		$this->title	= $title;
	}
	
	/**
	 * Determine whether the logged in user has the ability to autoshare
	 *
	 * @return	boolean
	 */
	public static function canAutoshare()
	{
		return (boolean) \IPS\Member::loggedIn()->twitter_token AND \IPS\Member::loggedIn()->twitter_secret;
	}
	
	/**
	 * Publish text or a URL to this service
	 *
	 * @param	string	$content	Text to publish
	 * @param	string	$url		[URL to publish]
	 * @return	@void
	 */
	public static function publish( $content, $url=null )
	{
		if ( static::canAutoshare() )
		{
			$loginHandler = \IPS\Login\LoginAbstract::load('twitter');
			
			if ( isset( \IPS\Data\Store::i()->twitter_config ) AND isset( \IPS\Data\Store::i()->twitter_config['time'] ) AND ( \IPS\Data\Store::i()->twitter_config['time'] <= time() - 86400 ) )
			{
				$maxUrlLen = \IPS\Data\Store::i()->twitter_config['short_url_length'];
			}
			else
			{
				try
				{
					$response = $loginHandler->sendRequest( 'get', "https://api.twitter.com/1.1/help/configuration.json", array(), \IPS\Member::loggedIn()->twitter_token, \IPS\Member::loggedIn()->twitter_secret )->decodeJson();
					
					if ( isset( $response['short_url_length'] ) )
					{
						\IPS\Data\Store::i()->twitter_config = array_merge( $response, array( 'time' => time() ) );
						
						$maxUrlLen = $response['short_url_length'];
					}
					else
					{
						throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_exception') );
					}
				}
				catch ( \IPS\Http\Request\Exception $e )
				{
					\IPS\Log::i( LOG_WARNING )->write( \IPS\Member::loggedIn()->id . ': '. $e->getMessage(), 'twitter' );
					
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_exception') );
				}
			}
			
			if ( $url !== null )
			{
				$content = mb_substr( $content, 0, ( 140 - ( $maxUrlLen + 1 ) ) ) . ' ' . $url;
			}
			
			try
			{
				$response = $loginHandler->sendRequest( 'post', "https://api.twitter.com/1.1/statuses/update.json", array( 'status' => $content ), \IPS\Member::loggedIn()->twitter_token, \IPS\Member::loggedIn()->twitter_secret )->decodeJson();
				
				if ( isset( $response['id_str'] ) )
				{
					return $response['id_str'];
				}
				else
				{
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_exception') );
				}
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				\IPS\Log::i( LOG_WARNING )->write( \IPS\Member::loggedIn()->id . ': '. $e->getMessage(), 'twitter' );
				
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_exception') );
			}
		}
		else
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_no_user') );
		}
	}

	/**
	 * Add any additional form elements to the configuration form. These must be setting keys that the service configuration form can save as a setting.
	 *
	 * @param	\IPS\Helpers\Form	$form	Configuration form for this service
	 * @return	void
	 */
	public static function modifyForm( \IPS\Helpers\Form &$form )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'twitter_hashtag', \IPS\Settings::i()->twitter_hashtag, FALSE ) );
	}

	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			$title = $this->title ?: NULL;
			if ( \IPS\Settings::i()->twitter_hashtag !== '')
			{
				$title .= ' ' . \IPS\Settings::i()->twitter_hashtag;
			}
			return \IPS\Theme::i()->getTemplate( 'sharelinks', 'core' )->twitter( urlencode( $this->url ), urlencode( $title ) );
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
}