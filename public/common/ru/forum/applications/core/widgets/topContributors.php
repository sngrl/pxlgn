<?php
/**
 * @brief		topContributors Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		02 Jul 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * topContributors Widget
 */
class _topContributors extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'topContributors';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
 		if ( $form === null )
 		{
	 		$form = new \IPS\Helpers\Form;
 		} 
 		
		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, TRUE ) );
		return $form;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		foreach ( array( 'week' => 'P1W', 'month' => 'P1M', 'year' => 'P1Y', 'all' => NULL ) as $time => $interval )
		{
			$select = \IPS\Db::i()->select( 'core_members.*, core_reputation_index.member_received as member, SUM(rep_rating) as rep', 'core_reputation_index', $interval ? array( 'member_received > 0 AND rep_date>?', \IPS\DateTime::create()->sub( new \DateInterval( $interval ) )->getTimestamp() ) : array('member_received > 0'), 'rep DESC', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, 'member' )->join( 'core_members', 'core_reputation_index.member_received=core_members.member_id' );

			${$time} = array();
			
			foreach( $select as $key => $values )
			{
				${$time}[$key]['member'] = \IPS\Member::constructFromData( $values );
				${$time}[$key]['rep']  = $values['rep'];
			}
		}

		return $this->output( $week, $month, $year, $all );
	}
}