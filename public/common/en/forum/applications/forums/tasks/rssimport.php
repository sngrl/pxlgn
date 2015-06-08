<?php
/**
 * @brief		rssimport Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Board
 * @since		05 Feb 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * rssimport Task
 */
class _rssimport extends \IPS\Task
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
			$feed = \IPS\forums\Feed::constructFromData( \IPS\Db::i()->select( '*', 'forums_rss_import', array( 'rss_import_enabled=1' ), 'rss_import_last_import ASC', 1 )->first() );
			$feed->run();
		}
		/* UnderflowException means there's no feed, so we can disable the task */
		catch ( \UnderflowException $e )
		{
			\IPS\DB::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 'rssimport' ) );
		}
		/* Any other exception means an error which should be logged */
		catch ( \Exception $e )
		{
			return $e->getMessage();
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