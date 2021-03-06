<?php
/**
 * @brief		Template Plugin - URL
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Output\Plugin;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - URL
 */
class _Url
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = TRUE;
	
	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options    Array of options
	 * @return	string		Code to eval
	 */
	public static function runPlugin( $data, $options )
	{
		$csrf = '';
		
		if ( isset( $options['csrf'] ) )
		{
			$csrf = ' . "&csrfKey=" . \IPS\Session::i()->csrfKey';
		}
		
		$location = ( in_array( 'base', array_keys( $options ) ) ) ? '"' . $options['base'] . '"' : 'null';
		
		if ( !isset( $options['seoTemplate'] ) )
		{
			$options['seoTemplate'] = '';
		}
		if ( isset( $options['seoTitle'] ) )
		{
			$options['seoTitles'] = "array( {$options['seoTitle']} )";
		}
		elseif( !isset( $options['seoTitles'] ) )
		{
			$options['seoTitles'] = 'array()';
		}
		
		if ( !isset( $options['protocol'] ) )
		{
			$options['protocol'] = \IPS\Http\Url::PROTOCOL_AUTOMATIC;
		}
		
		$fragment = "";
		
		if ( isset( $options['fragment'] ) )
		{
			$fragment = "->setFragment(\"{$options['fragment']}\")";
		}
		
		if ( isset( $options['plain'] ) )
		{
			$url = "\IPS\Http\Url::internal( \"$data\"{$csrf}, {$location}, \"{$options['seoTemplate']}\", {$options['seoTitles']}, {$options['protocol']} )".$fragment;
		}
		elseif ( isset( $options['ips'] ) )
		{
			$url = "\IPS\Http\Url::ips( \"docs/$data\" )";
		}
		else
		{
			$url = "str_replace( '&', '&amp;', \IPS\Http\Url::internal( \"$data\"{$csrf}, {$location}, \"{$options['seoTemplate']}\", {$options['seoTitles']}, {$options['protocol']} ){$fragment} )";
		}

		if( isset( $options['noprotocol'] ) AND $options['noprotocol'] )
		{
			$url = "str_replace( array( 'http://', 'https://' ), '//', {$url} )";
		}

		return $url;
	}
}