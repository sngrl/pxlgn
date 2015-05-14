<?php
/**
 * @brief		Topic Feed Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	forums
 * @since		16 Oct 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * latestTopics Widget
 */
class _postFeed extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'postFeed';
	
	/**
	 * @brief	App
	 */
	public $app = 'forums';
		
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

		$form->add( new \IPS\Helpers\Form\Node( 'tfb_forums', isset( $this->configuration['tfb_forums'] ) ? $this->configuration['tfb_forums'] : 0, FALSE, array(
			'class'           => '\IPS\forums\Forum',
			'zeroVal'         => 'tfb_all_forums',
			'permissionCheck' => 'view',
			'multiple'        => true
		) ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'tfb_use_perms', isset( $this->configuration['tfb_use_perms'] ) ? $this->configuration['tfb_use_perms'] : TRUE, FALSE ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'tfb_topic_status', isset( $this->configuration['tfb_topic_status'] ) ? $this->configuration['tfb_topic_status'] : array( 'open', 'pinned', 'notpinned', 'visible', 'featured', 'notfeatured' ), FALSE, array(
			'options' => array(
				'open'        => 'tfb_open_status_open',
			    'closed'      => 'tfb_open_status_closed',
			    'pinned'      => 'tfb_open_status_pinned',
			    'notpinned'   => 'tfb_open_status_notpinned',
			    'visible'     => 'tfb_open_status_visible',
			    'hidden'      => 'tfb_open_status_hidden',
			    'featured'    => 'tfb_open_status_featured',
			    'notfeatured' => 'tfb_open_status_notfeatured'
			)
		) ) );

		$author = NULL;

		try
		{
			if ( isset( $this->configuration['tfb_author'] ) and is_array( $this->configuration['tfb_author'] ) )
			{
				foreach( $this->configuration['tfb_author']  as $id )
				{
					$author[ $id ] = \IPS\Member::load( $id );
				}
			}
		}
		catch( \OutOfRangeException $ex ) { }

		$form->add( new \IPS\Helpers\Form\Member( 'tfb_author', $author, FALSE, array( 'multiple' => true ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'tfb_min_posts', isset( $this->configuration['tfb_min_posts'] ) ? $this->configuration['tfb_min_posts'] : 0, FALSE, array( 'unlimitedLang' => 'tfb_any_posts', 'unlimited' => 0 ) ) );

 		$form->add( new \IPS\Helpers\Form\Number( 'tfb_show', isset( $this->configuration['tfb_show'] ) ? $this->configuration['tfb_show'] : 5, TRUE ) );

		$form->add( new \IPS\Helpers\Form\Select( 'tfb_sort_dir', isset( $this->configuration['tfb_sort_dir'] ) ? $this->configuration['tfb_sort_dir'] : 'desc', FALSE, array(
            'options' => array(
	            'desc'   => 'tfb_sort_dir_dsc',
	            'asc'    => 'tfb_sort_dir_asc'
            )
        ) ) );

 		return $form;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$where = array();
		$order = NULL;
		$limit = isset( $this->configuration['tfb_show'] ) ? $this->configuration['tfb_show'] : 5;
		$permissionKey = ( !isset( $this->configuration['tfb_use_perms'] ) or $this->configuration['tfb_use_perms'] ) ? 'read' : NULL;
		$includeHidden = false;

		if ( isset( $this->configuration['tfb_forums'] ) and is_array( $this->configuration['tfb_forums'] ) )
		{
			$where[] = array( 'forum_id IN (' . implode( ",", array_keys( $this->configuration['tfb_forums'] ) ) . ')' );
		}

		if ( isset( $this->configuration['tfb_topic_status'] ) and is_array( $this->configuration['tfb_topic_status'] ) )
		{
			$status = $this->configuration['tfb_topic_status'];

			if ( ! in_array( 'open', $status ) or ! in_array( 'closed', $status ) )
			{
				if ( ! in_array( 'open', $status ) )
				{
					$where[] = array( "state='closed'" );
				}
				else if ( ! in_array( 'closed', $status ) )
				{
					$where[] = array( "state='open'" );
				}
			}

			if ( in_array( 'hidden', $status ) )
			{
				$includeHidden = true;
			}

			if ( ! in_array( 'featured', $status ) or ! in_array( 'notfeatured', $status ) )
			{
				if ( ! in_array( 'featured', $status ) )
				{
					$where[] = array( 'featured=0' );
				}
				else if ( ! in_array( 'notfeatured', $status ) )
				{
					$where[] = array( 'featured=1' );
				}
			}

			if ( ! in_array( 'pinned', $status ) or ! in_array( 'notpinned', $status ) )
			{
				if ( ! in_array( 'pinned', $status ) )
				{
					$where[] = array( 'pinned=0' );
				}
				else if ( ! in_array( 'notpinned', $status ) )
				{
					$where[] = array( 'pinned=1' );
				}
			}
		}

		if ( isset( $this->configuration['tfb_author'] ) and is_array( $this->configuration['tfb_author'] ) )
		{
			$where[] = array( "starter_id IN(" . implode( ',', $this->configuration['tfb_author'] ) . ")" );
		}

		if ( isset( $this->configuration['tfb_sort_dir'] ) )
		{
			$order = 'post_date ' . $this->configuration['tfb_sort_dir'];
		}

		$posts = \IPS\forums\Topic\Post::getItemsWithPermission( $where, $order, $limit, $permissionKey ?: NULL, $includeHidden );

		if ( count( $posts ) )
		{
			return $this->output( $posts );
		}
		else
		{
			return '';
		}
	}
}