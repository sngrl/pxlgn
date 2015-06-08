<?php
/**
 * @brief		Abstract Search Index
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 Aug 2014
 * @version		SVN_VERSION_NUMBER
*/

namespace IPS\Content\Search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Search Index
 */
abstract class _Index extends \IPS\Patterns\Singleton
{
	/**
	 * @brief	Singleton Instances
	 */
	protected static $instance = NULL;
	
	/**
	 * Get instance
	 *
	 * @return	static
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$classname = '\\IPS\\Content\\Search\\' . ucfirst( \IPS\Settings::i()->search_engine ) . '\\Index';
			static::$instance = new $classname;
		}
		
		return static::$instance;
	}
	
	/**
	 * Clear and rebuild search index
	 *
	 * @return	void
	 */
	public function rebuild()
	{
		/* Delete everything currently in it */
		$this->prune();		
		
		/* If the queue is already running, clear it out */
		\IPS\Db::i()->delete( 'core_queue', array( "`key`=?", 'RebuildSearchIndex' ) );
		
		/* And set the queue in motion to rebuild */
		foreach ( \IPS\Content::routedClasses( FALSE, TRUE ) as $class )
		{
			try
			{
				if( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => $class ) );
				}
			}
			catch( \OutOfRangeException $ex ) {}
		}
	}
	
	/**
	 * Get index data
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	array|NULL
	 */
	public function indexData( \IPS\Content\Searchable $object )
	{
		/* Init */
		$class = get_class( $object );
		$idColumn = $class::$databaseColumnId;
		$tags = ( $object instanceof \IPS\Content\Tags ) ? implode( ',', array_filter( array_merge( array( $object->prefix() ), $object->tags() ) ) ) : NULL;
		
		/* If this is an item where the first comment is required, don't index because the comment will serve as both */
		if ( $object instanceof \IPS\Content\Item and $class::$firstCommentRequired )
		{
			return NULL;
		}

		/* Don't index if this is an item to be published in the future */
		if ( $object->isFutureDate() )
		{
			return NULL;
		}

		/* Or if this *is* the first comment, add the title and replace the tags */
		$title = $object->mapped('title');
		if ( $object instanceof \IPS\Content\Comment )
		{
			$itemClass = $class::$itemClass;
			if ( $itemClass::$firstCommentRequired and $object->isFirst() )
			{
				$title = $object->item()->mapped('title');
				$tags = ( $object->item() instanceof \IPS\Content\Tags ) ? implode( ',', array_filter( array_merge( array( $object->item()->prefix() ), $object->item()->tags() ) ) ) : NULL;
			}
		}
		
		/* Get the last updated date */
		$dateUpdated = ( $object instanceof \IPS\Content\Item ) ? $object->mapped('last_comment') : $object->mapped('edit_time');
		
		/* Take the HTML out of the content */
		$content = trim( strip_tags( preg_replace( "#<blockquote(.*?)>(.*)</blockquote>#ui", "", ' ' . str_replace( ">", "> ", $object->mapped('content') ) . ' ' ) ) );

		/* Work out the hidden status */
		$hiddenStatus = $object->hidden();
		if ( $hiddenStatus === 0 and method_exists( $object, 'item' ) and $object->item()->hidden() )
		{
			$hiddenStatus = 2;
		}

		if ( $hiddenStatus !== 0 and method_exists( $object, 'item' ) and $object->item()->isFutureDate() )
		{
			$hiddenStatus = 0;
		}

		/* Return */
		return array(
			'index_class'			=> $class,
			'index_object_id'		=> $object->$idColumn,
			'index_item_id'			=> ( $object instanceof \IPS\Content\Item ) ? $object->$idColumn : $object->mapped('item'),
			'index_container_id'	=> ( $object instanceof \IPS\Content\Item ) ? (int) $object->mapped('container') : (int) $object->item()->mapped('container'),
			'index_title'			=> $title,
			'index_content'			=> $content,
			'index_permissions'		=> $object->searchIndexPermissions(),
			'index_date_created'	=> $object->mapped('date'),
			'index_date_updated'	=> $dateUpdated ?: $object->mapped('date'),
			'index_author'			=> (int) $object->mapped('author'),
			'index_tags'			=> $tags,
			'index_hidden'			=> $hiddenStatus
		);
	}
	
	/**
	 * Index an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	abstract public function index( \IPS\Content\Searchable $object );
	
	/**
	 * Remove item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to remove
	 * @return	void
	 */
	abstract public function removeFromSearchIndex( \IPS\Content\Searchable $object );
	
	/**
	 * Removes all content for a classs
	 *
	 * @param	string		$class 	The class
	 * @param	int|NULL	$containerId		The container ID to update, or NULL
	 * @return	void
	 */
	abstract public function removeClassFromSearchIndex( $class, $containerId=NULL );
	
	/**
	 * Removes all content for a specific application from the index (for example, when uninstalling).
	 *
	 * @param	\IPS\Application	$application The application
	 * @return	void
	 */
	public function removeApplicationContent( \IPS\Application $application )
	{
		foreach ( $application->extensions( 'core', 'ContentRouter' ) as $router )
		{
			foreach( $router->classes AS $class )
			{
				if ( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					$this->removeClassFromSearchIndex( $class );
					
					if ( isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						if ( is_subclass_of( $commentClass, 'IPS\Content\Searchable' ) )
						{
							$this->removeClassFromSearchIndex( $commentClass );
						}
					}
					
					if ( isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						if ( is_subclass_of( $reviewClass, 'IPS\Content\Searchable' ) )
						{
							$this->removeClassFromSearchIndex( $reviewClass );
						}
					}
				}
			}
		}
	}
		
	/**
	 * Mass Update (when permissions change, for example)
	 *
	 * @param	string				$class 				The class
	 * @param	int|NULL			$containerId		The container ID to update, or NULL
	 * @param	int|NULL			$itemId				The item ID to update, or NULL
	 * @param	string|NULL			$newPermissions		New permissions (if applicable)
	 * @param	int|NULL			$newHiddenStatus	New hidden status (if applicable) special value 2 can be used to indicate hidden only by parent
	 * @param	int|NULL			$newContainer		New container ID (if applicable)
	 * @return	void
	 */
	abstract public function massUpdate( $class, $containerId = NULL, $itemId = NULL, $newPermissions = NULL, $newHiddenStatus = NULL, $newContainer = NULL );
	
	/**
	 * Prune search index
	 *
	 * @param	\IPS\DateTime|NULL	$cutoff	The date to delete index records from, or NULL to delete all
	 * @return	void
	 */
	abstract public function prune( \IPS\DateTime $cutoff = NULL );
}