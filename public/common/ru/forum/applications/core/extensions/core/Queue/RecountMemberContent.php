<?php
/**
 * @brief		Background Task: Recount Member Content
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
 * Background Task: Recount Member Content
 */
class _RecountMemberContent
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
		$data['count'] = (int) \IPS\Db::i()->select( 'count(*)', 'core_members' )->first();

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
		$parsed	= 0;

		/* Loop over members to fix them */
		foreach( \IPS\Db::i()->select( '*', 'core_members', NULL, 'member_id ASC', array( $offset, $this->rebuild ) ) as $member )
		{
			try
			{
				$member	= \IPS\Member::constructFromData( $member );
				$member->recountContent();
				$parsed++;
			}
			catch( \Exception $ex ) { }
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'recounting_posts' ), 'complete' => $data['count'] ? ( round(  100 / $data['count'] * $offset, 2 ) ) : 100 );
	}	
}