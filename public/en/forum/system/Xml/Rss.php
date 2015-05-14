<?php
/**
 * @brief		Class for managing RSS documents
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Xml;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Class for managing RSS documents
 */
class _Rss extends SimpleXML
{	
	/**
	 * Create RSS document
	 *
	 * @param	\IPS\Http\Url	$url			URL to document
	 * @param	string			$title			Channel Title
	 * @param	string			$description	Channel Description
	 * @return	void
	 * @see		<a href='http://cyber.law.harvard.edu/rss/languages.html'>Allowable values for language in RSS</a>
	 */
	public static function newDocument( \IPS\Http\Url $url, $title, $description )
	{
		$xml = new self( '<rss version="2.0" />' );
		
		$channel = $xml->addChild( 'channel' );
		$channel->addChild( 'title', $title );
		$channel->addChild( 'link', (string) $url );
		$channel->addChild( 'description', $description );
		
		$locale = mb_strtolower( \IPS\Member::loggedIn()->language()->short );
		foreach ( array( preg_replace( '/^(.+?)_(.+?)(\..+?)$/', '$1-$2', $locale ), preg_replace( '/^(.+?)_(.+?)(\..+?)$/', '$1', $locale ) ) as $langCodeToTry )
		{
			if ( in_array( $langCodeToTry, array( 'af', 'sq', 'eu', 'be', 'bg', 'ca', 'zh-cn', 'zh-tw', 'hr', 'cs', 'da', 'nl', 'nl-be', 'nl-nl', 'en', 'en-au', 'en-bz', 'en-ca', 'en-ie', 'en-jm', 'en-nz', 'en-ph', 'en-za', 'en-tt', 'en-gb', 'en-us', 'en-zw', 'et', 'fo', 'fi', 'fr', 'fr-be', 'fr-ca', 'fr-fr', 'fr-lu', 'fr-mc', 'fr-ch', 'gl', 'gd', 'de', 'de-at', 'de-de', 'de-li', 'de-lu', 'de-ch', 'el', 'haw', 'hu', 'is', 'in', 'ga', 'it', 'it-it', 'it-ch', 'ja', 'ko', 'mk', 'no', 'pl', 'pt', 'pt-br', 'pt-pt', 'ro', 'ro-mo', 'ro-ro', 'ru', 'ru-mo', 'ru-ru', 'sr', 'sk', 'sl', 'es', 'es-ar', 'es-bo', 'es-cl', 'es-co', 'es-cr', 'es-do', 'es-ec', 'es-sv', 'es-gt', 'es-hn', 'es-mx', 'es-ni', 'es-pa', 'es-py', 'es-pe', 'es-pr', 'es-es', 'es-uy', 'es-ve', 'sv', 'sv-fi', 'sv-se', 'tr', 'uk' ) ) )
			{
				$channel->addChild( 'language', $langCodeToTry );
				break;
			}
		}
				
		return $xml;
	}
	
	/**
	 * Add Item
	 *
	 * @param	string				$title			Item title
	 * @param	\IPS\Http\Url|NULL	$link			Item link
	 * @param	string|NULL			$description	Item description/content
	 * @param	\IPS\DateTime|NULL	$date			Item date
	 * @param	string				$guid			Item ID
	 * @return	void
	 * @todo	[Future] The feed will validate now, but unrecognized attribute values cause warnings when validating. Also, the validator recommends using an Atom feed with the atom:link attribute.
	 */
	public function addItem( $title = NULL, \IPS\Http\Url $link = NULL, $description = NULL, \IPS\DateTime $date = NULL, $guid = NULL )
	{
		if ( $title === NULL and $description === NULL )
		{
			throw new \InvalidArgumentException;
		}
		
		$item = $this->channel->addChild( 'item' );
		
		if ( $title !== NULL )
		{
			$item->addChild( 'title', $title );
		}
		
		$item->addChild( 'link', $link->rfc3986() );
		
		if ( $description !== NULL )
		{
			$item->addChild( 'description', $description );
		}
		
		if ( $guid !== NULL )
		{
			$item->addChild( 'guid', $guid )->addAttribute( 'isPermaLink', 'false' );
		}
		
		if ( $date !== NULL )
		{
			$item->addChild( 'pubDate', $date->format('r') );
		}
	}
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public function title()
	{
		return $this->channel->title;
	}
	
	/**
	 * Get articles
	 *
	 * @param	mixed	$guidKey	In previous versions, we encoded a key with the GUID. For legacy purposes, this can be passed here.
	 * @return	array
	 */
	public function articles( $guidKey=NULL )
	{
		$articles = array();
		foreach ( $this->channel->item as $item )
		{
			$link = NULL;
			if ( $item->link )
			{
				try
				{
					$link = \IPS\Http\Url::external( $item->link );
				}
				catch ( \Exception $e ) {  }
			}
			
			if ( isset( $item->guid ) )
			{
				$guid = $item->guid;
			}
			else
			{
				$guid = '';
				foreach ( array( 'title', 'link', 'description' ) as $k )
				{
					if ( isset( $item->$k ) )
					{
						$guid .= $item->$k;
					}
				}
				$guid = preg_replace( "#\s|\r|\n#is", "", $guid );
			}
			$guid = md5( $guidKey . $guid );

			$articles[ $guid ] = array(
				'title'		=> ( (string) $item->title ) ?: ( mb_substr( $item->description, 0, 47 ) . '...' ),
				'content'	=> (string) $item->description,
				'date'		=> $item->pubDate ? \IPS\DateTime::ts( strtotime( $item->pubDate ), TRUE ) : \IPS\DateTime::ts( strtotime( $this->channel->pubDate ), TRUE ),
				'link'		=> $link
			);
		}
		return $articles;
	}
}