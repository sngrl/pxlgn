<?php
/**
 * @brief		Background Task: Rebuild reputation index
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
 * Background Task: Rebuild Reputation Index
 */
class _RebuildReputationIndex
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= 250;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];

		/* Make sure there's even content to parse */
		if( ! isset( $classname::$reputationType ) )
		{
			$data['count'] = 0;
		}
		else
		{
			try
			{
				$data['count'] = \IPS\Db::i()->select( 'MAX( id )', 'core_reputation_index', array( 'app=? and type=?', $classname::$application, $classname::$reputationType ) )->first();
			}
			catch( \Exception $ex )
			{
				throw new \OutOfRangeException;
			}
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
		if ( !class_exists( $classname ) )
		{
			throw new \OutOfRangeException;
		}

		/* Make sure there's even content to parse */
		if( ! isset( $classname::$reputationType ) )
		{
			throw new \OutOfRangeException;
		}

		$last     = NULL;
		foreach( \IPS\Db::i()->select( '*', 'core_reputation_index', array( 'app=? and type=? and id > ?', $classname::$application, $classname::$reputationType, $offset ), 'id asc', array( 0, $this->rebuild ) ) as $row )
		{
			try
			{
				$post = $classname::load( $row['type_id'] );

				if ( $post->mapped('author') )
				{
					\IPS\Db::i()->update( 'core_reputation_index', array( 'member_received' => $post->mapped('author') ), array( 'id=?', $row['id'] ) );
				}
			}
			catch( \OutOfRangeException $ex ) { }

			$last = $row['id'];
		}

		return $last;
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
		if ( !class_exists( $class ) )
		{
			throw new \OutOfRangeException;
		}
		
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_reputation', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}
}