<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		5 Mar 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\setup\upg_100018;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 5 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Rebuild reputation received
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\Content::routedClasses( FALSE, TRUE, TRUE ) as $class )
		{
			try
			{
				\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $class ), 4 );

				if ( isset( $class::$containerNodeClass ) )
				{
					try
					{
						\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => $class::$containerNodeClass, 'count' => 0 ), 5, array( 'class' ) );
					}
					catch( \OutOfRangeException $ex ) { }
				}
			}
			catch( \OutOfRangeException $ex ) { }
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating reputation received";
	}
	
	/**
	 * Step 2
	 * Rebuild search index
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( !\IPS\Settings::i()->search_engine )
		{
			\IPS\Settings::i()->search_engine	= 'mysql';
		}

		\IPS\Content\Search\Index::i()->rebuild();

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Building Search index";
	}

	/**
	 * Step 3
	 * Clean up guest followers
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_member_id=?', 0 ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Clean up guest followers";
	}

	/**
	 * Step 4
	 * Fix broken attachments map
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		if( \IPS\Db::i()->select( 'count(*)', 'core_attachments_map', "location_key=1" ) )
		{
			foreach ( \IPS\Content::routedClasses( FALSE, TRUE, TRUE ) as $class )
			{
				try
				{
					\IPS\Task::queue( 'core', 'RestoreBrokenAttachments', array( 'class' => $class ), 3 );
				}
				catch( \OutOfRangeException $ex ) { }
			}

			\IPS\Db::i()->delete( 'core_attachments_map', "location_key=1" );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Fixing broken attachment maps";
	}
}