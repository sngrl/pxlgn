<?php
/**
 * @brief		Queue Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		04 Dec 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Queue Task
 */
class _queue extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		try
		{			
			$queueData = \IPS\Db::i()->select( '*', 'core_queue', NULL, 'priority ASC, RAND()', 1 )->first();
			
			try
			{
				$extensions = \IPS\Application::load( $queueData['app'] )->extensions( 'core', 'Queue', FALSE );
				if ( !isset( $extensions[ $queueData['key'] ] ) )
				{
					throw new \OutOfRangeException;
				}
				
				$class = new $extensions[ $queueData['key'] ];
				$newOffset = $class->run( json_decode( $queueData['data'], TRUE ), $queueData['offset'] );
				
				if ( is_null( $newOffset ) )
				{
					\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $queueData['id'] ) );
				}
				else
				{
					\IPS\Db::i()->update( 'core_queue', array( 'offset' => $newOffset ), array( 'id=?', $queueData['id'] ) );
				}
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $queueData['id'] ) ); 
			}
		}
		catch ( \UnderflowException $e )
		{
			$this->enabled = FALSE;
			$this->save();
		}
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}