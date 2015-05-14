<?php
/**
 * @brief		upcomingEvents Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	calendar
 * @since		18 Dec 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * upcomingEvents Widget
 */
class _upcomingEvents extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'upcomingEvents';
	
	/**
	 * @brief	App
	 */
	public $app = 'calendar';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * @brief	Cache Expiration
	 * @note	We allow this cache to be valid for 12 hours
	 */
	public $cacheExpiration = 43200;
	
	/**
	 * Initialize this widget
	 *
	 * @return	void
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar.css', 'calendar' ) );
		
		parent::init();
	}
	
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
 		if ( $form === null )
 		{
	 		$form = new \IPS\Helpers\Form;
 		} 
 		
		$form->add( new \IPS\Helpers\Form\YesNo( 'auto_hide', isset( $this->configuration['auto_hide'] ) ? $this->configuration['auto_hide'] : FALSE, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'days_ahead', isset( $this->configuration['days_ahead'] ) ? $this->configuration['days_ahead'] : NULL, TRUE, array( 'unlimited' => -1 ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'maximum_count', isset( $this->configuration['maximum_count'] ) ? $this->configuration['maximum_count'] : 5, TRUE, array( 'unlimited' => -1 ) ) );
		return $form;
 	} 

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$_today	= \IPS\calendar\Date::getDate();

		/* Do we have a days ahead cutoff? */
		$endDate	= NULL;

		if( isset( $this->configuration['days_ahead'] ) AND  $this->configuration['days_ahead'] > 0 )
		{
			$endDate	= $_today->adjust( "+" . $this->configuration['days_ahead'] . " days" );
		}

		$events = \IPS\calendar\Event::retrieveEvents( $_today, $endDate, NULL, ( isset( $this->configuration['maximum_count'] ) AND $this->configuration['maximum_count'] > 0 ) ? $this->configuration['maximum_count'] : 5, FALSE );

		/* Auto hiding? */
		if( !count($events) AND isset( $this->configuration['auto_hide'] ) AND $this->configuration['auto_hide'] )
		{
			return '';
		}

		return $this->output( $events, $_today );
	}
}