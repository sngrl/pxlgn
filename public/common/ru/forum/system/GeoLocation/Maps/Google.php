<?php
/**
 * @brief		Google Maps
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		19 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\GeoLocation\Maps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Google Maps
 */
class _Google
{	
	/**
	 * @brief	GeoLocation
	 */
	public $geoLocation;
	
	/**
	 * @brief	Use http or https
	 */
	protected $protocol	= 'http://';

	/**
	 * Constructor
	 *
	 * @param	\IPS\GeoLocation	$geoLocation	Location
	 * @return	void
	 */
	public function __construct( \IPS\GeoLocation $geoLocation )
	{
		$this->geolocation	= $geoLocation;
		$this->protocol		= ( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' );
	}
	
	/**
	 * Render
	 *
	 * @param	int	$width	Width
	 * @param	int	$heigth	Height
	 * @return	string
	 */
	public function render( $width, $height, $zoom=5 )
	{
		if ( $this->geolocation->lat and $this->geolocation->long )
		{
			$location = "{$this->geolocation->lat},{$this->geolocation->long}";
			return "<span itemscope itemtype='http://schema.org/GeoCoordinates'><meta itemprop='latitude' content='{$this->geolocation->lat}'><meta itemprop='longitude' content='{$this->geolocation->long}'><a href='{$this->protocol}maps.google.com/?q={$location}' target='_blank' itemprop='url'><img src='{$this->protocol}maps.googleapis.com/maps/api/staticmap?center={$location}&zoom={$zoom}&size={$width}x{$height}&sensor=false&markers={$location}' alt='' class='ipsImage'></a></span>";
		}
		else
		{
			$zoom = 1;
			$location = array();
			foreach ( array( 'country', 'region', 'city', 'addressLines' ) as $k )
			{
				if ( $this->geolocation->$k )
				{
					$zoom++;
					if ( is_array( $this->geolocation->$k ) )
					{
						foreach ( array_reverse( $this->geolocation->$k ) as $v )
						{
							$location[] = $v;
						}
					}
					else
					{
						$location[] = $this->geolocation->$k;
					}
				}
			}
			$location = implode( ', ', array_reverse( $location ) );

			return "<a href='{$this->protocol}maps.google.com/?q={$location}' target='_blank'><img src='{$this->protocol}maps.googleapis.com/maps/api/staticmap?center={$location}&zoom={$zoom}&size={$width}x{$height}&sensor=false&markers={$location}' alt='' class='ipsImage'></a>";
		}
	}
}