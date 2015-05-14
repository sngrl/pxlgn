<?php
/**
 * @brief		Background Task: Delete or move content
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		27 May 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Delete or move content
 */
class _DeleteOrMoveContent
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];
		$node = $classname::load( $data['id'] );
		$data['originalCount'] = (int) $node->getContentItemCount();

		if ( !$data['originalCount'] )
		{
			if ( isset( $data['deleteWhenDone'] ) and $data['deleteWhenDone'] )
			{
				$node->delete();
			}
			return NULL;
		}
		
		return $data;
	}
	
	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int|null				New offset or NULL if complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$classname = $data['class'];
		if ( !class_exists( $classname ) )
		{
			throw new \OutOfRangeException;
		}
		
		$node = $classname::load( $data['id'] );
		
		$moveTo = NULL;
		if ( isset( $data['moveTo'] ) )
		{
			$moveToClass = isset( $data['moveToClass'] ) ? $data['moveToClass'] : $classname;
			$moveTo = $moveToClass::load( $data['moveTo'] );
		}
		
		$contentItemClass = $node::$contentItemClass;

		$where = array();
		if ( isset( $data['additional'] ) AND count( $data['additional'] ) )
		{
			if ( isset( $data['additional']['author'] ) )
			{
				$where[]			= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['author'] . '=?', $data['additional']['author'] );
			}
			
			if ( isset( $data['additional']['no_comments'] ) AND $data['additional']['no_comments'] > 0 )
			{
				$lastCommentField	= $contentItemClass::$databaseColumnMap['last_comment'];
				$field				= is_array( $lastCommentField ) ? array_pop( $lastCommentField ) : $lastCommentField;
				$where[]			= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['num_comments'] . '<=? AND ' . $contentItemClass::$databasePrefix . $field . '<?', $contentItemClass::$firstCommentRequired ? 1 : 0, $data['additional']['no_comments'] );
			}
			
			if ( isset( $data['additional']['num_comments'] ) AND $data['additional']['num_comments'] > 0 )
			{
				$where[]			= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['num_comments'].'<?', $data['additional']['num_comments'] );
			}
			
			if ( isset( $data['additional']['state'] ) )
			{
				if ( isset( $contentItemClass::$databaseColumnMap['locked'] ) )
				{
					$where[]		= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['locked'].'=?', $data['additional']['state'] == 'locked' ? 1 : 0 );
				}
				else
				{
					$where[]		= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['status'].'=?', $data['additional']['state'] == 'locked' ? 'closed' : 'open' );
				}
			}
			
			if ( isset( $data['additional']['pinned'] ) AND $data['additional']['pinned'] === TRUE )
			{
				$where[]			= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['pinned'].'!=?', 1 );
			}
			
			if ( isset( $data['additional']['featured'] ) AND $data['additional']['featured'] === TRUE )
			{
				$where[]			= array( $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnMap['featured'].'!=?', 1 );
			}
		}
		
		$select = $node->getContentItems( 100, 0, $where );
		
		if ( count( $select ) )
		{
			foreach ( $select as $item )
			{
				if ( $moveTo )
				{
					$item->move( $moveTo );
				}
				else
				{
					$item->delete();
				}
			}
			
			$return = $offset + 100;
		}
		else
		{
			$return = NULL;
		}
		
		if ( $return === NULL and isset( $data['deleteWhenDone'] ) and $data['deleteWhenDone'] )
		{
			$node->delete();
		}
		
		return $return;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaning task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		$classname = $data['class'];
		if ( !class_exists( $classname ) )
		{
			throw new \OutOfRangeException;
		}
		
		$node = $classname::load( $data['id'] );
		if ( isset( $data['moveTo'] ) )
		{
			$moveTo = $classname::load( $data['moveTo'] );
			$text = \IPS\Member::loggedIn()->language()->addToStack('backgroundQueue_move_content', FALSE, array( 'htmlsprintf' => array( "<a href='{$node->url()}' target='_blank'>{$node->_title}</a>", "<a href='{$moveTo->url()}' target='_blank'>{$moveTo->_title}</a>" ) ) );
		}
		else
		{
			$text = \IPS\Member::loggedIn()->language()->addToStack('backgroundQueue_deleting', FALSE, array( 'htmlsprintf' => array( "<a href='{$node->url()}' target='_blank'>{$node->_title}</a>" ) ) );
		}
		
		return array( 'text' => $text, 'complete' => $data['originalCount'] ? ( round( 100 / $data['originalCount'] * $offset, 2 ) ) : 100 );
	}
}