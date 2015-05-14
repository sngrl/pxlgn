<?php
/**
 * @brief		Background Task: Rebuild content images following storage method change
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		13 Jun 2014
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
 * Background Task: Rebuild content images
 */
class _RebuildContentImages
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= 50;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];
		
		\IPS\Log::i( LOG_DEBUG )->write( "Getting preQueueData for " . $classname, 'rebuildContentImages' );
		
		try
		{			
			$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', $classname::$databaseTable )->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		if( $data['count'] == 0 )
		{
			return null;
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

		/* Make sure there's even content to parse */
		if( !isset( $classname::$databaseColumnMap['content'] ) )
		{
			throw new \OutOfRangeException;
		}

		$parsed	= 0;
		
		\IPS\Log::i( LOG_DEBUG )->write( "Running " . $classname . ", with an offset of " . $offset, 'rebuildPosts' );
		
		$dateColumn = $classname::$databaseColumnMap['date'];
		
		$iterator = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $classname::$databaseTable, NULL, $classname::$databasePrefix . $dateColumn . ' DESC', array( $offset, $this->rebuild ) ), $classname );
		
		foreach( $iterator as $item )
		{
			$contentColumn	= $classname::$databaseColumnMap['content'];

			$rebuilt = \IPS\Text\Parser::rebuildAttachmentUrls( $item->$contentColumn );

			if( $rebuilt )
			{
				$item->$contentColumn = $rebuilt;
				$item->save();
			}

			$parsed++;
		}

		return ( $parsed == $this->rebuild ) ? ( $offset + $this->rebuild ) : null;
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
		$class = $data['class'];
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_content_images', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}	
}