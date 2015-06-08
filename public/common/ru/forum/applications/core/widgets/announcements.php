<?php
/**
 * @brief		Announcements Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		19 Nov 2013
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
 * Announcements Widget
 */
class _announcements extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'announcements';
	
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
 		
		$form->add( new \IPS\Helpers\Form\Number( 'toshow', isset( $this->configuration['toshow'] ) ? $this->configuration['toshow'] : 5, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Select( 'sort_by', isset( $this->configuration['sort_by'] ) ? $this->configuration['sort_by'] : 'asc', TRUE, array( 'options' => array( 'asc' => 'ascending', 'desc' => 'descending' ) ) ) );

		return $form;
 	}
 	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$announcements = array();
		$where = array();
		
		$where = array();
		$where[] = array( 'announce_active=?', 1 );
		$where[] = array( 'announce_start<? AND ( announce_end=0 OR announce_end>? )', time(), time() );
		$where[] = array( '( announce_app=? OR announce_app=? )', \IPS\Dispatcher::i()->application->directory, "*" );
		foreach ( \IPS\Dispatcher::i()->application->extensions( 'core', 'Announcements' ) as $key => $extension )
		{
			if( in_array( \IPS\Dispatcher::i()->controller, $extension::$controllers ) )
			{
				$id = $extension::$idField;
				$where[] = array( '( announce_location=? OR announce_location=? )', $key, '*' );
			
				if ( isset( \IPS\Request::i()->$id ) )
				{
					/* Are we viewing content? */
					if ( \IPS\Dispatcher::i()->dispatcherController instanceof \IPS\Content\Controller )
					{
						foreach( \IPS\Dispatcher::i()->application->extensions( 'core', 'ContentRouter' ) AS $k => $contentRouter )
						{
							foreach( $contentRouter->classes AS $class )
							{
								/* Whew, finally */
								$where[] = array( '( ' . \IPS\Db::i()->findInSet( 'announce_ids', array( $class::load( \IPS\Request::i()->$id )->mapped('container') ) ) . ' OR announce_ids IS NULL )' );
								break 2;
							}
						}
					}
					else
					{
						$where[] = array( '( ' . \IPS\Db::i()->findInSet( 'announce_ids', array( \IPS\Request::i()->$id ) ) . ' OR announce_ids IS NULL )' );
					}
				}
				else
				{
					$where[] = array( '( announce_ids IS NULL )' );
				}
			}
		}
		
		$direction = isset( $this->configuration['sort_by'] ) ? $this->configuration['sort_by'] : 'asc';
		$limit     = isset( $this->configuration['toshow'] ) ? $this->configuration['toshow'] : 5;
		
		foreach( \IPS\Db::i()->select( '*' ,'core_announcements', $where, 'announce_start ' . $direction, array( 0, $limit ) ) as $row )
		{
			$announcements[] = \IPS\core\Announcements\Announcement::constructFromData($row);
		}
		
		if ( !count( $announcements ) )
		{
			return '';
		}

		return $this->output( $announcements );
	}
}