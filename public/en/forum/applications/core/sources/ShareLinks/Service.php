<?php
/**
 * @brief		Share Links Node
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\ShareLinks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Share Link Node
 */
class _Service extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_share_links';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'share_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'share_key' );
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'sharelinks';
	
	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;
	
	/**
	 * @brief	[Node] ACP Restrictions
	 */
	protected static $restrictions = array(
		'app'		=> 'core',
		'module'	=> 'settings',
		'prefix'	=> 'sharelinks_',
	);

	/**
	 * [Node] Reset our roots so they can be reloaded
	 *
	 * @return	void
	 */
	public static function resetRootResult()
	{
		static::$rootsResult	= NULL;
	}
	
	/**
	 * Fetch All Services
	 *
	 * @return	array
	 */
	public static function shareLinks()
	{
		if ( !isset( \IPS\Data\Store::i()->shareLinks ) )
		{
			\IPS\Data\Store::i()->shareLinks = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, NULL, static::$databasePrefix . static::$databaseColumnOrder ) );
		}
		
		$return = array();
		foreach ( \IPS\Data\Store::i()->shareLinks as $service )
		{
			$return[] = static::constructFromData( $service );
		}
		
		return $return;
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	 	array(
	 		array(
	 			'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	 			'title'	=> 'foo',		// Language key to use for button's title parameter
	 			'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	 			'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	 		),
	 		...							// Additional buttons
	 	);
	 * @encode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{		
		$buttons = array();
		
		if ( $subnode )
		{
			$url = $url->setQueryString( array( 'subnode' => 1 ) );
		}

		if( $this->canEdit() )
		{
			$buttons['edit'] = array(
				'icon'	=> 'pencil',
				'title'	=> 'edit',
				'link'	=> $url->setQueryString( array( 'do' => 'form', 'id' => $this->_id ) ),
				'data'	=> ( static::$modalForms ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ) : array() ),
				'hotkey'=> 'e return'
				);
		}

		return $buttons;
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'share_title', $this->title, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Select( 'share_groups', ( $this->groups != '*' ) ? explode( ",", $this->groups ) : $this->groups, FALSE, array( 'options' => \IPS\Member\Group::groups(), 'parse' => 'normal', 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'everyone' ) ) );

		/* Find the service and see if it has any additional settings... */
		$className	= "\\IPS\\Content\\ShareServices\\" . ucwords( $this->key );

		$className::modifyForm( $form );
		
		if ( $className::canAutoshare() )
		{
			$form->add( new \IPS\Helpers\Form\Checkbox( 'share_autoshare', $this->autoshare, false ) );
		}
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( count( $values ) )
		{
			foreach ( $values as $k => $v )
			{
				if( !in_array( $k, array( 'share_title', 'share_groups', 'share_autoshare' ) ) )
				{
					if ( $v instanceof \IPS\GeoLocation )
					{
						$v = json_encode( $v );
					}
					if ( is_array( $v ) )
					{
						$v = implode( ',', $v );
					}
					
					\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $v ), array( 'conf_key=?', $k ) );
					\IPS\Settings::i()->$k	= $v;

					unset( $values[ $k ] );
				}
			}

			unset( \IPS\Data\Store::i()->settings );

			/* Remove prefix */
			$_values = $values;
			$values = array();
			foreach ( $_values as $k => $v )
			{
				if( mb_substr( $k, 0, 6 ) === 'share_' )
				{
					$values[ mb_substr( $k, 6 ) ] = $v;
				}
				else
				{
					$values[ $k ]	= $v;
				}
			}
		}

		return $values;
	}
	
	/**
	 * [Node] Get Node Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		if ( !$this->id )
		{
			return '';
		}
		
		return $this->title;
	}
	
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return ( $this->enabled ) ? TRUE : FALSE;
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		$this->enabled	= $enabled;
	}

	/**
	 * [Node] Does the currently logged in user have permission to add a child node to this node?
	 *
	 * @return	bool
	 */
	public function canAdd()
	{
		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to copy this node?
	 *
	 * @return	bool
	 */
	public function canCopy()
	{
		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to edit permissions for this node?
	 *
	 * @return	bool
	 */
	public function canManagePermissions()
	{
		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		return FALSE;
	}

	/**
	 * Search
	 *
	 * @param	string		$column	Column to search
	 * @param	string		$query	Search query
	 * @param	string|null	$order	Column to order by
	 * @param	mixed		$where	Where clause
	 * @return	array
	 */
	public static function search( $column, $query, $order=NULL, $where=array() )
	{	
		if ( $column === '_title' )
		{
			$column = 'share_title';
		}
		if ( $order === '_title' )
		{
			$order = 'share_title';
		}
		return parent::search( $column, $query, $order, $where );
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		unset( \IPS\Data\Store::i()->shareLinks );
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		unset( \IPS\Data\Store::i()->shareLinks );
	}
}