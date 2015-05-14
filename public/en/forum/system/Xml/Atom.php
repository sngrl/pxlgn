<?php
/**
 * @brief		Class for managing Atom documents
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		5 Feb 2014
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
 * Class for managing Atom documents
 */
class _Atom extends SimpleXML
{
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public function title()
	{
		return $this->title;
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
		
		foreach ( $this->entry as $item )
		{			
			$link = NULL;
			if ( isset( $item->link ) )
			{
				try
				{
					$link = \IPS\Http\Url::external( $item->link );
				}
				catch ( \Exception $e ) {  }
			}
			
			$articles[ md5( $guidKey . ( (string) $item->id ) ) ] = array(
				'title'		=> (string) $item->title,
				'content'	=> isset( $item->content ) ? (string) $item->content : (string) $item->title,
				'date'		=> \IPS\DateTime::ts( strtotime( $item->updated ) ),
				'link'		=> $link
			);
		}
		return $articles;
	}
}