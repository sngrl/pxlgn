<?php
/**
 * @brief		Recent event reviews Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Calendar
 * @since		19 Feb 2014
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
 * Recent event reviews Widget
 */
class _recentReviews extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'recentReviews';
	
	/**
	 * @brief	App
	 */
	public $app = 'calendar';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

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
 		
		$form->add( new \IPS\Helpers\Form\Number( 'review_count', isset( $this->configuration['review_count'] ) ? $this->configuration['review_count'] : 5, TRUE ) );

		return $form;
 	} 

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$reviews = \IPS\calendar\Event\Review::getItemsWithPermission( array(), NULL, ( isset( $this->configuration['review_count'] ) AND $this->configuration['review_count'] > 0 ) ? $this->configuration['review_count'] : 5 );

		return $this->output( $reviews );
	}
}