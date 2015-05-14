<?php
/**
 * @brief		Address input class for Form Builder
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		11 Jul 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Address input class for Form Builder
 */
class _Address extends FormAbstract
{	
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
			'minimize'	=> FALSE,			// Minimize the address field until the user focuses?
	 	);
	 * @encode
	 */
	protected $defaultOptions = array(
		'minimize' => FALSE
	);

	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		/* If we don't have a value, set their country based on the HTTP headers */
		if ( !$this->value OR ( $this->value instanceof \IPS\GeoLocation AND !$this->value->country ) )
		{
			$this->value = ( $this->value instanceof \IPS\GeoLocation ) ? $this->value : new \IPS\GeoLocation;
			if ( $defaultCountry = $this->_calculateDefaultCountry() )
			{
				$this->value->country = $defaultCountry;
			}
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->address( $this->name, $this->value, \IPS\Settings::i()->googleplacesautocomplete, $this->options['minimize'] );
	}
	
	/**
	 * Calculate default country
	 *
	 * @return	string|NULL
	 */
	protected function _calculateDefaultCountry()
	{
		if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
		{
			if( mb_strpos( $_SERVER['HTTP_ACCEPT_LANGUAGE'], ',' ) )
			{
				$exploded	= explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
				$exploded	= $exploded[0];
			}
			else
			{
				$exploded	= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}
			$exploded = explode( '-', $exploded );
			$country = mb_strtoupper( ( count( $exploded) > 1 ) ? $exploded[1] : $exploded[0] );
			if ( in_array( $country, \IPS\GeoLocation::$countries ) )
			{
				return $country;
			}
		}
		
		return NULL;
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		/* Create the object */
		$input = parent::getValue();
		$value = new \IPS\GeoLocation;		
		$value->addressLines = array_filter( $input['address'] );
		if ( empty( $value->addressLines ) )
		{
			$value->addressLines = array( NULL );
		}
		$value->city = $input['city'];
		$value->region = $input['region'];
		$value->postalCode = $input['postalCode'];
		$value->country = $input['country'];
		
		/* Work out what parts are filled in */
		$partiallyCompleted = FALSE;
		$fullyCompleted = TRUE;
		$addresslines = array_filter( $value->addressLines );
		if ( empty( $addresslines ) )
		{
			$fullyCompleted = FALSE;
		}
		else
		{
			$partiallyCompleted = TRUE;
		}
		if ( $value->city )
		{
			$partiallyCompleted = TRUE;
		}
		else
		{
			$fullyCompleted = FALSE;
		}
		if ( $value->postalCode )
		{
			$partiallyCompleted = TRUE;
		}
		else
		{
			$fullyCompleted = FALSE;
		}
		if ( $value->country )
		{
			if ( $value->country != $this->_calculateDefaultCountry() )
			{
				$partiallyCompleted = TRUE;
			}
			
			if ( array_key_exists( $value->country, \IPS\GeoLocation::$states ) )
			{
				if ( !$value->region )
				{
					$fullyCompleted = FALSE;
				}
			}
		}
		else
		{
			$fullyCompleted = FALSE;
		}
		if ( trim( $value->region ) )
		{
			$states = ( isset( \IPS\GeoLocation::$states[ $value->country ] ) ) ? \IPS\GeoLocation::$states[ $value->country ] : array();
			if ( !array_key_exists( $value->country, \IPS\GeoLocation::$states ) or $value->region != array_shift( $states ) )
			{
				$partiallyCompleted = TRUE;
			}
		}
		
		/* Validate, return NULL if we have nothing */
		if ( !$fullyCompleted )
		{
			if ( $partiallyCompleted )
			{
				if ( $this->required )
				{
					throw new \InvalidArgumentException('form_partial_address_req');
				}
				else
				{
					throw new \InvalidArgumentException('form_partial_address_opt');
				}
			}
			else
			{
				return NULL;
			}
		}
		
		/* Add in latitude and longitude if we can */
		try
		{
			$value->getLatLong();
		}
		catch( \BadFunctionCallException $e ){}
		
		/* Return */
		return $value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if( $this->value === NULL and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}
		
		return parent::validate();
	}
		
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public static function stringValue( $value )
	{
		return json_encode( $value );
	}
}