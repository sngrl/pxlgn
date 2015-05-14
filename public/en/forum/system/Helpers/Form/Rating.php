<?php
/**
 * @brief		Rating input Form Builder
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		3 Oct 2013
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
 * Rating input class for Form Builder
 */
class _Rating extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'max'		=> 5,		// Maximum number of stars
	 	);
	 * @encode
	 */
	protected $defaultOptions = array(
		'max'		=> 5,
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->rating( $this->name, $this->value, $this->required, $this->options['max'] );
	}
	
	/**
	 * Format Value
	 *
	 * @return	int|NULL
	 */
	public function formatValue()
	{
		return $this->value ? intval( $this->value ) : NULL;
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
}