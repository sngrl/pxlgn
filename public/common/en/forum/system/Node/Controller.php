<?php
/**
 * @brief		Node Controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Node;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Node Controller
 */
class _Controller extends \IPS\Dispatcher\Controller
{
	/**
	 * Title can contain HTML?
	 */
	public $_titleHtml = FALSE;
	
	/**
	 * Description can contain HTML?
	 */
	public $_descriptionHtml = FALSE;
	
	/**
	 * @brief	If true, will prevent any item from being moved out of its current parent, only allowing them to be reordered within their current parent
	 */
	protected $lockParents = FALSE;
	
	/**
	 * @brief	If true, root cannot be turned into sub-items, and other items cannot be turned into roots
	 */
	protected $protectRoots = FALSE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Are we sortable? */
		$nodeClass = $this->nodeClass;
		$this->sortable = $nodeClass::$databaseColumnOrder and $nodeClass::$nodeSortable;
		
		/* Set the title */
		$title = $nodeClass::$nodeTitle;
		$this->title = $title;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $title );
				
		/* Do stuff */
		return parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$nodeClass = $this->nodeClass;
		
		if ( isset( \IPS\Request::i()->searchResult ) )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( sprintf( \IPS\Member::loggedIn()->language()->get('search_results_in_nodes'), mb_strtolower( \IPS\Member::loggedIn()->language()->get( $nodeClass::$nodeTitle . '_sg' ) ) ), 'information' );
		}
		
		if ( $nodeClass::$databaseColumnParent === NULL )
		{
			$this->protectRoots = TRUE;
		}
					
		$tree = new \IPS\Helpers\Tree\Tree( $this->url, $nodeClass::$nodeTitle, array( $this, '_getRoots' ), array( $this, '_getRow' ), array( $this, '_getRowParentId' ), array( $this, '_getChildren' ), array( $this, '_getRootButtons' ), TRUE, $this->lockParents, $this->protectRoots );
		\IPS\Output::i()->output .= $tree;
	}
	
	/**
	 * Get Root Rows
	 *
	 * @return	array
	 */
	public function _getRoots()
	{
		$nodeClass = $this->nodeClass;
		$rows = array();
		foreach( $nodeClass::roots( NULL ) as $node )
		{
			$rows[ $node->_id ] = $this->_getRow( $node );
		}
		
		return $rows;
	}
	
	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$nodeClass = $this->nodeClass;
		
		if ( $nodeClass::canAddRoot() )
		{
			return array( 'add' => array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> $this->url->setQueryString( 'do', 'form' ),
				'data'	=> ( $nodeClass::$modalForms ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') ) : array() )
				) );
		}
		return array();
	}

	/**
	 * Return the custom badge for each row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	public function _getRowBadge( $node )
	{
		return NULL;
	}
	
	/**
	 * Get Single Row
	 *
	 * @param	mixed	$id		May be ID number (or key) or an \IPS\Node\Model object
	 * @param	bool	$root	Format this as the root node?
	 * @param	bool	$noSort	If TRUE, sort options will be disabled (used for search results)
	 * @return	string
	 */
	public function _getRow( $id, $root=FALSE, $noSort=FALSE )
	{
		$nodeClass = $this->nodeClass;
		if ( $id instanceof \IPS\Node\Model )
		{
			$node = $id;
		}
		else
		{
			try
			{
				$node = $nodeClass::load( $id );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2S101/P', 404, '' );
			}
		}
		
		$id = ( $node instanceof $nodeClass ) ? $node->_id :  "s.{$node->_id}";
		$class = get_class( $node );
		
		$buttons = $node->getButtons( $this->url, !( $node instanceof $this->nodeClass ) );
		if ( isset( \IPS\Request::i()->searchResult ) and isset( $buttons['edit'] ) )
		{
			$buttons['edit']['link'] = $buttons['edit']['link']->setQueryString( 'searchResult', \IPS\Request::i()->searchResult );
		}
										
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row(
			$this->url,
			$id,
			$node->_title,
			$node->childrenCount( NULL ),
			$buttons,
			$node->_description,
			$node->_icon ? $node->_icon : NULL,
			( $noSort === FALSE and $class::$nodeSortable and $node->canEdit() ) ? $node->_position : NULL,
			$root,
			$node->_enabled,
			( $node->_locked or !$node->canEdit() ),
			( ( $node instanceof \IPS\Node\Model ) ? $node->_badge : $this->_getRowBadge( $node ) ),
			$this->_titleHtml,
			$this->_descriptionHtml,
			$node->canAdd()
		);
	}
	
	/**
	 * Get Row parent ID
	 *
	 * @param	int|string	$id		Row ID
	 * @return	int|string	Parent ID
	 */
	public function _getRowParentId( $id )
	{
		$nodeClass = $this->nodeClass;

		try
		{
			return $nodeClass::load( $id )->parent();
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/Q', 404, '' );
		}
	}
	
	/**
	 * Get Child Rows
	 *
	 * @param	int|string	$id		Row ID
	 * @return	array
	 */
	public function _getChildren( $id )
	{
		$rows = array();

		$nodeClass = $this->nodeClass;

		try
		{
			$node	= $nodeClass::load( $id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/R', 404, '' );
		}

		foreach ( $node->children( NULL ) as $child )
		{
			$id = ( $child instanceof $this->nodeClass ? '' : 's.' ) . $child->_id;
			$rows[ $id ] = $this->_getRow( $child );
		}
		return $rows;
	}

	/**
	 * Add/Edit Form
	 *
	 * @return void
	 */
	protected function form()
	{
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}
		$node = NULL;
		if ( \IPS\Request::i()->id )
		{
			try
			{
				$node = call_user_func( "{$nodeClass}::load", \IPS\Request::i()->id );
				\IPS\Output::i()->title = $node->_title;
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2S101/K', 404, '' );
			}
		}
		else
		{
			$node = new $nodeClass;
			
			if ( !$this->title )
			{
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle . '_add_child' );
			}
			else
			{
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $this->title );
			}
			
			if ( \IPS\Request::i()->parent )
			{
				$parentColumn = NULL;
				
				if ( \IPS\Request::i()->subnode )
				{
					if ( isset( $nodeClass::$parentNodeColumnId ) )
					{
						$parentColumn = $nodeClass::$parentNodeColumnId;
					}
				}
				elseif ( isset( $nodeClass::$databaseColumnParent ) )
				{
					$parentColumn = $nodeClass::$databaseColumnParent;
				}
				
				if ( $parentColumn !== NULL )
				{
					$node->$parentColumn = \IPS\Request::i()->parent;
				}
			}
		}
		
		/* Check permission */
		if( !$node->canEdit() )
		{
			\IPS\Output::i()->error( 'node_noperm_edit', '2S101/N', 403, '' );
		}
		
		/* Build form */
		$form = $this->_addEditForm( $node );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			if ( isset( \IPS\Request::i()->massChangeValue ) )
			{
				\IPS\Output::i()->json( (string) $values[ \IPS\Request::i()->massChangeValue ] );
			}
			
			try
			{
				$new = !$node->_id;
				if ( $new and isset( $node::$databaseColumnOrder ) )
				{
					$orderColumn = $node::$databaseColumnOrder;
					$node->$orderColumn = \IPS\Db::i()->select( 'MAX(' . $node::$databasePrefix . $orderColumn . ')', $node::$databaseTable  )->first() + 1;
				}
				
				$old = NULL;
				if ( !$new )
				{
					$node->skipCloneDuplication = TRUE;
					$old = clone $node;
				}
				$node->saveForm( $node->formatFormValues( $values ) );
								
				if ( $new )
				{
					\IPS\Session::i()->log( 'acplog__node_created', array( $this->title => TRUE, $node->_logKey => TRUE ) );
					
					if ( $node->canManagePermissions() )
					{
						\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'do' => 'permissions', 'id' => $node->_id, 'subnode' => ( isset( \IPS\Request::i()->subnode ) ? \IPS\Request::i()->subnode : 0 ) ) ) );
					}
				}
				else
				{
					if ( $node->parent() )
					{
						foreach( $node->parent()->children() AS $child )
						{
							$child->setLastComment();
							$child->setLastReview();
							$child->save();
						}
						
						if ( \IPS\Request::i()->subnode )
						{
							if ( isset( $nodeClass::$parentNodeColumnId ) )
							{
								$parentColumn = $nodeClass::$parentNodeColumnId;
							}
						}
						elseif ( isset( $nodeClass::$databaseColumnParent ) )
						{
							$parentColumn = $nodeClass::$databaseColumnParent;
						}
						
						$node->$parentColumn = $node->parent()->_id;
						$node->setLastComment();
						$node->setLastReview();
						$node->save();
					}
					
					\IPS\Session::i()->log( 'acplog__node_edited', array( $this->title => TRUE, $node->_logKey => TRUE ) );
				}
								
				$this->_afterSave( $old, $node );
				return;
			}
			catch ( \LogicException $e )
			{
				$form->error = $e->getMessage();
			}
		}

		/* Display */
		\IPS\Output::i()->output .= $form;
	}
	
	/**
	 * Get form
	 *
	 * @param	\IPS\Node\Model
	 * @return	\IPS\Helpers\Form
	 */
	protected function _addEditForm( \IPS\Node\Model $node )
	{
		$form = new \IPS\Helpers\Form( 'form_' . ( $node->_id ?: 'new' ) );
		if ( $node->_id AND !$node->noCopyButton )
		{
			$form->copyButton = $this->url->setQueryString( array( 'do' => 'massChange', 'from' => $node->_id ) );
		}
		$node->form( $form );
		
		return $form;
	}
	
	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model	$old	A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$node	The node now
	 * @return	void
	 */
	protected function _afterSave( \IPS\Node\Model $old = NULL, \IPS\Node\Model $new )
	{
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array() );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => ( $new->parent() ? $new->parent()->_id : '' ) ) ), 'saved' );
		}
	}
	
	/**
	 * Mass Change
	 *
	 * @return	void
	 */
	protected function massChange()
	{
		/* Check permission */
		$nodeClass = $this->nodeClass;
		try
		{
			$node = call_user_func( "{$nodeClass}::load", \IPS\Request::i()->from );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/S', 404, '' );
		}
		if( !$node->canEdit() )
		{
			\IPS\Output::i()->error( 'node_noperm_edit', '2S101/T', 403, '' );
		}
		
		/* Get the value */
		$key = \IPS\Request::i()->key;
		$dummyForm = new \IPS\Helpers\Form;
		$node->form( $dummyForm );
		$value = isset( \IPS\Request::i()->value ) ? \IPS\Request::i()->value : NULL;
		if ( $value === NULL )
		{
			foreach ( $dummyForm->elements as $tab => $elements )
			{
				if ( isset( $elements[ $key ] ) )
				{
					$value = $elements[ $key ]->value;
					break;
				}
			}
		}
		
		if ( is_array( $value ) )
		{
			$value = implode( ',', $value );
		}
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->ajaxOutput = TRUE;
		$field = new \IPS\Helpers\Form\Node( 'nodes', 0, TRUE, array( 'url' =>  $this->url->setQueryString( array( 'do' => 'massChange', 'key' => $key, 'from' => $node->_id ) ), 'class' => $nodeClass, 'zeroVal' => 'all', 'multiple' => TRUE ) );
		$field->label = \IPS\Member::loggedIn()->language()->get( 'copy_value_to' );
		$form->add( $field );

		/* Display */
		if ( $values = $form->values() )
		{			
			$url = $this->url;
			\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect( $this->url->setQueryString( array( 'do' => 'massChange', 'key' => \IPS\Request::i()->key, 'value' => $value, 'nodes' => \IPS\Request::i()->nodes ?: $values['nodes'], 'from' => \IPS\Request::i()->from, 'form_submitted' => 1, 'csrfKey' => \IPS\Request::i()->csrfKey ) ),
				function( $doneSoFar ) use ( $nodeClass )
				{
					$select = \IPS\Db::i()->select( '*', $nodeClass::$databaseTable, \IPS\Request::i()->nodes == 0 ? NULL : \IPS\Db::i()->in( $nodeClass::$databasePrefix . $nodeClass::$databaseColumnId, json_decode( \IPS\Request::i()->nodes, TRUE ) ), $nodeClass::$databasePrefix . $nodeClass::$databaseColumnId, array( $doneSoFar, 50 ), NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
					$count	= $select->count( TRUE );
					if ( !$count )
					{
						return NULL;
					}

					$did	= 0;
					foreach ( $select as $row )
					{
						$did++;
						$_node = $nodeClass::constructFromData( $row );
						$values = $_node->formatFormValues( array( \IPS\Request::i()->key => \IPS\Request::i()->value ) );

						foreach( $values as $k => $v )
						{
							$val = $_node->$k;

							if( is_array( $v ) )
							{
								foreach( $v as $_k => $_v )
								{
									$val[ $_k ]	= $_v;
								}

								$_node->$k	= $val;
							}
							else
							{
								$_node->$k	= $v;
							}
						}
						
						$_node->save();
					}

					if( !$did )
					{
						return NULL;
					}
					
					$doneSoFar += 50;
					return array( $doneSoFar, \IPS\Member::loggedIn()->language()->addToStack('copying'), 100 / $count * $doneSoFar );	
				}, 
				function() use( $url )
				{
					\IPS\Output::i()->redirect( $url->setQueryString( array( 'do' => 'form', 'id' => \IPS\Request::i()->from ) ) );
				},
				FALSE 
			);
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global', 'core')->block( '', $form );
		}
	}
				
	/**
	 * Toggle Enabled/Disable
	 *
	 * @return	void
	 */
	protected function enableToggle()
	{
		/* Work out which class we're using */
		$nodeClass = $this->nodeClass;
		if ( mb_substr( \IPS\Request::i()->id, 0, 2 ) === 's.' )
		{
			\IPS\Request::i()->id = mb_substr( \IPS\Request::i()->id, 2 );
			$nodeClass = $nodeClass::$subnodeClass;
		}
	
		/* Load Node */
		try
		{
			$node = $nodeClass::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '3S101/A', 404, '' );
		}
		
		/* Check we're not locked */
		if( $node->_locked or !$node->canEdit() )
		{
			\IPS\Output::i()->error( 'node_noperm_enable', '2S101/3', 403, '' );
		}
		
		/* Toggle */
		$node->_enabled = \IPS\Request::i()->status;
		$node->save();

		/* Recount if needed */
		if( $node->parent() )
		{
			$node->parent()->setLastComment();
			$node->parent()->setLastReview();
			$node->parent()->save();
		}
		
		$this->logToggleAndRedirect( $node );
	}

	/**
	 * Following a toggle, log the action and redirect. Abstracted so it can be called separately externally.
	 *
	 * @param	\IPS\Node\Model	$node	The node we are working with
	 * @return void
	 */
	public function logToggleAndRedirect( $node )
	{
		/* Log */
		if ( $node->_enabled )
		{
			\IPS\Session::i()->log( 'acplog__node_enabled', array( $this->title => TRUE, $node->_logKey => TRUE ) );
		}
		else
		{
			\IPS\Session::i()->log( 'acplog__node_disabled', array( $this->title => TRUE, $node->_logKey => TRUE ) );
		}
				
		/* If this is an AJAX request, just respond */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( $node->_enabled );
		}
		/* Otherwise, redirect */
		else
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => \IPS\Request::i()->root ) ) );
		}
	}
	
	/**
	 * Copy
	 *
	 * @return	void
	 */
	protected function copy()
	{
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}
		try
		{
			$node = call_user_func( "{$nodeClass}::load", \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/L', 404, '' );
		}

		/* Do we have any children? */
		if ( $node->hasChildren( NULL, NULL, FALSE ) and !isset( \IPS\Request::i()->skipChildren ) )
		{
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\YesNo( 'node_copy_children', NULL, FALSE, array(), function( $val ) use ( &$form )
			{
				if ( $val )
				{
					$form->ajaxOutput = TRUE;
				}
			} ) );
			if ( $values = $form->values() OR \IPS\Request::i()->node_copy_children )
			{
				/* Copy Children */
				if ( $values['node_copy_children'] OR \IPS\Request::i()->node_copy_children )
				{
					$ourObj = $this;

					$multipleRedirect = new \IPS\Helpers\MultipleRedirect(
						$this->url->setQueryString( array( 'do' => 'copy', 'id' => $node->_id, 'subnode' => \IPS\Request::i()->subnode, 'form_submitted' => 1, 'csrfKey' => \IPS\Request::i()->csrfKey, 'node_copy_children' => 1 ) ),
						/* Process */
						function( $data ) use ( $node, $nodeClass )
						{
							/* Init */
							if ( !is_array( $data ) )
							{
								return array( array( 'copy' => array( array( 'id' => $node->_id, 'subnode' => 0 ) ), 'ids' => array() ), \IPS\Member::loggedIn()->language()->addToStack('copying') );
							}
							/* Process */
							else
							{
								/* Have we finished? */
								if ( empty( $data['copy'] ) )
								{
									return NULL;
								}
								
								/* No, still going */
								foreach( $data['copy'] as $k => $itemData )
								{
									/* Load */
									if ( $itemData['subnode'] )
									{
										$nodeClass = $nodeClass::$subnodeClass;
									}
									$item = $nodeClass::load( $itemData['id'] );
									
									/* Copy it */
									$new = clone $item;
									$data['ids'][ get_class( $item ) ][ $item->_id ] = $new->_id;

									/* Update it's parent */
									if ( $item->parent() )
									{
										if ( array_key_exists( $item->parent()->_id, $data['ids'][ get_class( $item->parent() ) ] ) )
										{
											$parentColumn = $nodeClass::$databaseColumnParent;
											if ( $itemData['subnode'] )
											{
												$parentColumn = $nodeClass::$parentNodeColumnId;
											}
																						
											$new->$parentColumn = $data['ids'][ get_class( $item->parent() ) ][ $item->parent()->_id ];
											$new->save();
										}
									}
									
									/* Remove this one from our array */
									unset( $data['copy'][ $k ] );
									
									/* And add all it's children */
									foreach ( $item->children( NULL ) as $child )
									{
										$data['copy'][] = array( 'id' => $child->_id, 'subnode' => !( $child instanceof $nodeClass ) );
									}
									
									/* Return */
									return array( $data, \IPS\Member::loggedIn()->language()->addToStack('copying') );
								}
							}
						},
						/* Finish */
						function() use ( $node, $ourObj )
						{
							\IPS\Session::i()->log( 'acplog__node_copied_c', array( $node->title => TRUE, $node->_logKey => TRUE ) );
							\IPS\Output::i()->redirect( $ourObj->url->setQueryString( array( 'root' => ( $node->parent() ? $node->parent()->id : '' ) ) ), 'saved' );
						}
					);
					\IPS\Output::i()->output = $multipleRedirect;
					return;
				}
			}
			else
			{
				/* Show form */
				\IPS\Output::i()->output = $form;
				return;
			}
		}

		/* Copy it */
		$new = clone $node;
		\IPS\Session::i()->log( 'acplog__node_copied', array( $this->title => TRUE, $node->_logKey => TRUE ) );
		
		/* Boink */
		$url = $this->url->setQueryString( array( 'do' => 'form', 'id' => $new->_id ) );
		if ( isset( \IPS\Request::i()->subnode ) )
		{
			$url = $url->setQueryString( 'subnode', 1 );
		}
		\IPS\Output::i()->redirect( $url, 'copied' );
	}
		
	/**
	 * Reorder
	 *
	 * @return	void
	 */
	protected function reorder()
	{	
		/* Init */
		$nodeClass = $this->nodeClass;
		
		/* Normalise AJAX vs non-AJAX */
		if( isset( \IPS\Request::i()->ajax_order ) )
		{
			$order = array();
			$position = array();
			foreach( \IPS\Request::i()->ajax_order as $id => $parent )
			{
				if ( !isset( $order[ $parent ] ) )
				{
					$order[ $parent ] = array();
					$position[ $parent ] = 1;
				}
				$order[ $parent ][ $id ] = $position[ $parent ]++;
			}
		}
		/* Non-AJAX way */
		else
		{
			$order = array( \IPS\Request::i()->root ?: 'null' => \IPS\Request::i()->order );
		}

		/* Okay, now order */
		foreach( $order as $parent => $nodes )
		{
			foreach ( $nodes as $id => $position )
			{
				/* Load Node */
				try
				{
					if ( mb_substr( $id, 0, 2 ) === 's.' )
					{
						$node = call_user_func( array( $nodeClass::$subnodeClass, 'load' ), mb_substr( $id, 2 ) );
						$parentColumn = $node::$parentNodeColumnId;
					}
					else
					{
						$node = $nodeClass::load( $id );
						$parentColumn = $node::$databaseColumnParent;
					}
				}
				catch ( \OutOfRangeException $e )
				{
					\IPS\Output::i()->error( 'node_error', '3S101/B', 404, '' );
				}
				$orderColumn = $node::$databaseColumnOrder;
				
				/* Check permission */
				if( !$node->canEdit() )
				{
					continue;
				}
				if( !$node::$nodeSortable or $orderColumn === NULL )
				{
					continue;
				}
								
				/* Do it */
				if ( $parentColumn )
				{
					$node->$parentColumn = ( $parent === 'null' ) ? 0 : is_numeric( $parent ) ? $parent : $nodeClass::$databaseColumnParentRootValue;
				}
				$node->$orderColumn = $position;
				$node->save();
			}

			if( $parent !== 'null' )
			{
				$node = call_user_func( array( $nodeClass, 'load' ), $parent );
				$node->setLastComment();
				$node->setLastReview();
				$node->save();
			}
		}
				
		/* Log */
		\IPS\Session::i()->log( 'acplog__node_reorder', array( $this->title => TRUE ), TRUE );
				
		/* If this is an AJAX request, just respond */
		if( \IPS\Request::i()->isAjax() )
		{
			return;
		}
		/* Otherwise, redirect */
		else
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => \IPS\Request::i()->root ) ) );
		}
		\IPS\Output::i()->sendOutput();
	}
	
	/**
	 * Permissions
	 *
	 * @return	void
	 */
	protected function permissions()
	{
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}
		$node = NULL;
		
		if ( \IPS\Request::i()->id )
		{
			try
			{
				$node = call_user_func( "{$nodeClass}::load", \IPS\Request::i()->id );
				\IPS\Output::i()->title = $node->_title;
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2S101/M', 404, '' );
			}
		}
		
		/* Check permission */
		if( !$node->canManagePermissions() )
		{
			\IPS\Output::i()->error( 'node_noperm_edit', '2S101/O', 403, '' );
		}
		
		/* Get current permissions */
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', $nodeClass::$permApp, $nodeClass::$permType, $node->_id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			/* Recommended permissions */
			$current = array();
			foreach ( $nodeClass::$permissionMap as $k => $v )
			{
				switch ( $k )
				{
					case 'view':
					case 'read':
						$current["perm_{$v}"] = '*';
						break;
						
					case 'add':
					case 'reply':
					case 'review':
					case 'upload':
					case 'download':
					default:
						$current["perm_{$v}"] = implode( ',', array_keys( \IPS\Member\Group::groups( TRUE, FALSE ) ) );
						break;
				}
			}
		}
		
		/* Build Matrix */
		$matrix = new \IPS\Helpers\Form\Matrix;
		$matrix->manageable = FALSE;
		$matrix->langPrefix = $nodeClass::$permissionLangPrefix . 'perm__';
		$matrix->columns = array(
			'label'		=> function( $key, $value, $data )
			{
				return $value;
			},
		);
		foreach ( $node->permissionTypes() as $k => $v )
		{
			$matrix->columns[ $k ] = function( $key, $value, $data ) use ( $current, $k, $v )
			{
				$groupId = mb_substr( $key, 0, -( 2 + mb_strlen( $k ) ) );
				return new \IPS\Helpers\Form\Checkbox( $key, isset( $current[ "perm_{$v}" ] ) and ( $current[ "perm_{$v}" ] === '*' or in_array( $groupId, explode( ',', $current[ "perm_{$v}" ] ) ) ) );
			};
			$matrix->checkAlls[ $k ] = ( $current[ "perm_{$v}" ] === '*' );
		}
		$matrix->checkAllRows = TRUE;
		
		$rows = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$rows[ $group->g_id ] = array(
				'label'	=> $group->name,
				'view'	=> TRUE,
			);
		}
		$matrix->rows = $rows;
		
		/* Handle submissions */
		if ( $values = $matrix->values() )
		{
			$_perms = array();
			
			/* Check for "all" checkboxes */
			foreach ( $nodeClass::$permissionMap as $k => $v )
			{
				if ( isset( \IPS\Request::i()->__all[ $k ] ) )
				{
					$_perms[ $v ] = '*';
				}
				else
				{
					$_perms[ $v ] = array();
				}
			}
			
			/* Prepare insert */
			$insert = array( 'app' => $nodeClass::$permApp, 'perm_type' => $nodeClass::$permType, 'perm_type_id' => $node->_id );
			if ( isset( $current['perm_id'] ) )
			{
				$insert['perm_id'] = $current['perm_id'];
			}
			
			/* Loop groups */
			foreach ( $values as $group => $perms )
			{
				foreach ( $nodeClass::$permissionMap as $k => $v )
				{
					if ( isset( $perms[ $k ] ) and $perms[ $k ] and is_array( $_perms[ $v ] ) )
					{
						$_perms[ $v ][] = $group;
					}
				}
			}
			
			/* Finalise */
			foreach ( $_perms as $k => $v )
			{
				$insert[ "perm_{$k}" ] = is_array( $v ) ? implode( $v, ',' ) : $v;
			}
			
			/* Set the permissions */
			$node->setPermissions( $insert, $matrix );

			unset(\IPS\Data\Store::i()->modules);

			/* Log */
			\IPS\Session::i()->log( 'permissions_adjusted_node', array( $node->_logKey => TRUE ) );
			
			/* Clear out member's cached "Create Menu" contents */
			\IPS\Member::clearCreateMenu();
			
			/* Redirect */
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => ( $node->parent() ? $node->parent()->_id : '' ) ) ), 'saved' );
		}
		
		/* Display */
		\IPS\Output::i()->output .= $matrix;
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}
		
		try
		{
			$node = call_user_func( "{$nodeClass}::load", \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/J', 404, '' );
		}
		 
		/* Permission check */
		if( !$node->canDelete() )
		{
			\IPS\Output::i()->error( 'node_noperm_delete', '2S101/H', 403, '' );
		}

		/* Do we have any children or content? */
		$hasContent = FALSE;
		if ( isset( $nodeClass::$contentItemClass ) )
		{
			$hasContent	= (bool) $node->getContentItemCount();
		}
		if ( $node->hasChildren( NULL, NULL, TRUE ) or $hasContent )
		{			
			$form = $node->deleteOrMoveForm();
			if ( $values = $form->values() )
			{
				$node->deleteOrMoveFormSubmit( $values, \IPS\Request::i()->deleteNode );				
				\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => ( $node->parent() ? $node->parent()->_id : '' ) ) ), 'deleted' );
			}
			else
			{
				/* Show form */
				\IPS\Output::i()->output = $form;
				return;
			}
		}
		else
		{
			$form = $node->deleteForm();
			
			if ( ! isset( \IPS\Request::i()->form_submitted ) )
			{
				/* Show form */
				\IPS\Output::i()->output = $form;
				return;
			}
		}
		
		/* Delete it */
		\IPS\Session::i()->log( 'acplog__node_deleted', array( $this->title => TRUE, $node->_logKey => TRUE ) );
		$node->delete();

		/* Clear out member's cached "Create Menu" contents */
		\IPS\Member::clearCreateMenu();
		
		/* Boink */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( "OK" );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => ( $node->parent() ? $node->parent()->_id : '' ) ) ), 'deleted' );
		}
	}
	
	/**
	 * Search
	 *
	 * @return	void
	 */
	protected function search()
	{
		$rows = array();
		
		/* Get results */
		$nodeClass = $this->nodeClass;
		$results = $nodeClass::search( '_title', \IPS\Request::i()->input, '_title' );
		
		/* Get results of subnodes */
		if ( isset( $nodeClass::$subnodeClass ) )
		{
			$subnodeClass = $nodeClass::$subnodeClass;
			$results = array_merge( $results, array_values( $subnodeClass::search( '_title', \IPS\Request::i()->input, '_title' ) ) );
			
			usort( $results, function( $a, $b ) {
				return strnatcasecmp( $a->_title, $b->_title );
			} );
		}
		
		/* Convert to HTML */
		foreach ( $results as $result )
		{
			$id = ( $result instanceof $this->nodeClass ? '' : 's.' ) . $result->_id;
			$rows[ $id ] = $this->_getRow( $result, FALSE, TRUE );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->rows( $rows, '' );
	}
}