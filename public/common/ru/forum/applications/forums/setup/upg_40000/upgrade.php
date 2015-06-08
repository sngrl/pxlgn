<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Board
 * @since		15 Jan 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\setup\upg_40000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Move ratings to core_ratings and delete topics_ratings table
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= intval( \IPS\Request::i()->extra );
		
		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		if ( \IPS\Db::i()->checkForTable( 'topic_ratings' ) )
		{
			foreach( \IPS\Db::i()->select( '*', 'topic_ratings', null, 'rating_id ASC', array( $limit, $perCycle ) ) as $rating )
			{
				if( $cutOff !== null AND time() >= $cutOff )
				{
					return ( $limit + $did );
				}

				$did++;
	
				\IPS\Db::i()->replace( 'core_ratings', array(
					'class'		=> "IPS\\forums\\Topic",
					'item_id'	=> $rating['rating_tid'],
					'rating'	=> $rating['rating_value'],
					'member'	=> $rating['rating_member_id'],
					'ip'		=> $rating['rating_ip_address']
				) );
			}
		}
		
		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			try
			{
				\IPS\Db::i()->dropTable( 'topic_ratings' );
			}
			catch( \IPS\Db\Exception $e ) { }

			unset( $_SESSION['_step1Count'] );
			
			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('topic_ratings') )
		{
			if( !isset( $_SESSION['_step1Count'] ) )
			{
				$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'topic_ratings' )->first();
			}

			$message = "Upgrading topic ratings (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
		}
		else
		{
			$message = "Upgraded all topic ratings";
		}
		
		return $message;
	}

	/**
	 * Step 2
	 * Archive rules and misc
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\Db::i()->select( '*', 'forums_archive_rules' ) as $rule )
		{
			if ( ! empty( $rule['archive_text'] ) )
			{
				$test = unserialize( $rule['archive_text'] );
					
				if ( is_array( $test ) )
				{
					\IPS\Db::i()->update( 'forums_archive_rules', array( 'archive_text' => implode( ',', $test ) ), array( 'archive_key=?', $rule['archive_key'] ) );
				}
			}
			else
			{
				/* Empty archive_text causes problems in 4 as 4 only stores rules with a value, so we should remove these fields */
				\IPS\Db::i()->delete( 'forums_archive_rules', array( 'archive_key=?', $rule['archive_key'] ) );
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading forum archive rules";
	}

	/**
	 * Step 3
	 * Reformat forums data
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$perCycle	= 50;
		$did		= 0;
		$limit		= intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'forums_forums', NULL, 'id ASC', array( $limit, $perCycle ) ) as $forum )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			
			$did++;

			foreach ( array( 'name' => "forums_forum_{$forum['id']}", 'description' => "forums_forum_{$forum['id']}_desc", 'rules_title' => "forums_forum_{$forum['id']}_rulestitle", 'rules_text' => "forums_forum_{$forum['id']}_rules", 'permission_custom_error' => "forums_forum_{$forum['id']}_permerror" ) as $fieldKey => $langKey )
			{
				if ( isset( $forum[ $fieldKey ] ) )
				{
					if ( in_array( $fieldKey, array( 'description', 'rules_text', 'permission_custom_error' ) ) )
					{
						try
						{
							$forum[ $fieldKey ] = \IPS\Text\Parser::parseStatic( $forum[ $fieldKey ], TRUE, NULL, NULL, TRUE, TRUE, TRUE );
						}
						catch( \InvalidArgumentException $e )
						{
							if( $e->getcode() == 103014 )
							{
								$forum[ $fieldKey ] = preg_replace( "#\[/?([^\]]+?)\]#", '', $forum[ $fieldKey ] );
							}
						}
					}
					
					\IPS\Lang::saveCustom( 'forums', $langKey, trim( $forum[ $fieldKey ] ) );
				}
			}
			
			$update = array();
			
			if ( empty( $_SESSION['upgrade_options']['forums']['40000']['qa_forum'] ) )
			{
				if ( $forum['forums_bitoptions'] & 4 )
				{
					$update['forums_bitoptions'] = $forum['forums_bitoptions'];
					$update['forums_bitoptions'] &= ~4;
				}
			}
			
			if ( empty( $forum['password'] ) )
			{
				/* 4.0 specifically looks for NULL */
				$update['password'] = NULL;
			}
			
			if ( count( $update ) )
			{
				\IPS\Db::i()->update( 'forums_forums', $update, array( 'id=?', $forum['id'] ) );
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			\IPS\Db::i()->dropColumn( 'forums_forums', array( 'name', 'description', 'rules_text', 'permission_custom_error', 'rules_title' ) );

			unset( $_SESSION['_step3Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step3Count'] ) )
		{
			$_SESSION['_step3Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums' )->first();
		}

		return "Upgrading forums (Upgraded so far: " . ( ( $limit > $_SESSION['_step3Count'] ) ? $_SESSION['_step3Count'] : $limit ) . ' out of ' . $_SESSION['_step3Count'] . ')';
	}

	/**
	 * Step 4
	 * Adjust topics table
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
    public function step4()
    {
        /* Adjust table definition */
        $description	= \IPS\Db::i()->checkForColumn( 'forums_topics', 'description' ) ? "DROP COLUMN description," : '';
        $dropLastPost	= \IPS\Db::i()->checkForIndex( 'forums_topics', 'last_post' ) ? "DROP INDEX last_post," : '';
		$dropLastPostSorting	= \IPS\Db::i()->checkForIndex( 'forums_topics', 'last_post_sorting' ) ? ",\nDROP INDEX last_post_sorting" : '';
		$dropHasAttach	= \IPS\Db::i()->checkForColumn( 'forums_topics', 'topic_hasattach' ) ? "DROP COLUMN topic_hasattach," : '';

        $toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
            'table' => 'forums_topics',
            'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_topics {$description}
{$dropHasAttach}
DROP COLUMN topic_deleted_posts,
DROP COLUMN tdelete_time,
DROP COLUMN seo_last_name,
DROP COLUMN seo_first_name,
ADD COLUMN popular_time INT(10) NULL DEFAULT NULL COMMENT 'Timestamp of when this topic will stop being popular.',
ADD COLUMN featured TINYINT(1) UNSIGNED NULL DEFAULT '0' COMMENT 'Topic is featured?',
ADD COLUMN question_rating INT(10) NULL DEFAULT NULL COMMENT 'If this topic is a question, it\'s current rating',
CHANGE COLUMN views views INT(10) NULL DEFAULT '0',
ADD INDEX featured_topics (featured, start_date ),
{$dropLastPost}
ADD INDEX last_post (forum_id, pinned, last_real_post, state)
{$dropLastPostSorting}"
        ),
        array(
        	'table' => 'forums_topics',
        	'query' => "UPDATE " . \IPS\Db::i()->prefix . "forums_topics SET posts=posts+1, last_real_post=last_post"
        ) ) );

        if ( count( $toRun ) )
        {
            $mr = json_decode( \IPS\Request::i()->mr, TRUE );
            $mr['extra']['_upgradeData'] = 0; # this ends up mapped to \IPS\Request::i()->extra
            $mr['extra']['_upgradeStep'] = 5;

            \IPS\Request::i()->mr = json_encode( $mr );

            /* Queries to run manually */
            return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
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
		return "Updating topics";
	}

	/**
	 * Step 5
	 * Rebuild post content for saved actions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		foreach( \IPS\Db::i()->select( '*', 'forums_topic_mmod', NULL, 'mm_id ASC' ) as $mmod )
		{
			\IPS\Db::i()->update( 'forums_topic_mmod', array( 'topic_reply_content' => \IPS\Text\Parser::parseStatic( $mmod['topic_reply_content'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ) ), array( 'mm_id=?', $mmod['mm_id'] ) );
			\IPS\Lang::saveCustom( 'forums', 'forums_mmod_' . $mmod['mm_id'], $mmod['mm_title'] );
		}

		\IPS\Db::i()->dropColumn( 'forums_topic_mmod', 'mm_title' );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Upgrading multi-moderation actions";
	}

	/**
	 * Step 6
	 * Cleanup
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		if( \IPS\Db::i()->checkForTable( 'forums_recent_posts' ) )
		{
			\IPS\Db::i()->dropTable( 'forums_recent_posts' );
		}

		if( \IPS\Db::i()->checkForTable( 'forum_tracker' ) )
		{
			\IPS\Db::i()->dropTable( 'forum_tracker' );
		}

		if( \IPS\Db::i()->checkForTable( 'tracker' ) )
		{
			\IPS\Db::i()->dropTable( 'tracker' );
		}

		if( \IPS\Db::i()->checkForTable( 'topic_views' ) )
		{
			\IPS\Db::i()->dropTable( 'topic_views' );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		return "Removing unused forum database tables";
	}

	/**
	 * Step 7
	 * Potentially long query, run by itself
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'forums_archive_posts',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_archive_posts ADD COLUMN archive_field_int INT(10) NULL DEFAULT NULL"
		) ) );
		
		if ( count( $toRun ) )
		{
			$mr = json_decode( \IPS\Request::i()->mr, TRUE );
			$mr['extra']['_upgradeStep'] = 8;
			
			\IPS\Request::i()->mr = json_encode( $mr );
			
			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr, 'extra' => 0 ) ) ) );
		}
	
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Updating forum database table definitions";
	}
	
	/**
	 * Step 8
	 * Potentially long query, run by itself
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		require( \IPS\ROOT_PATH . '/conf_global.php' );
			
		if ( $INFO['archive_remote_sql_database'] )
		{
			$archiveDb = \IPS\Db::i('archive', array(
				'sql_host' 	     => $INFO['archive_remote_sql_host'],
				'sql_user' 	     => $INFO['archive_remote_sql_user'],
				'sql_pass'       => $INFO['archive_remote_sql_pass'],
				'sql_database' 	 => $INFO['archive_remote_sql_database'],
				'sql_tbl_prefix' => $INFO['sql_tbl_prefix']
			) );
			
			$query = "ALTER TABLE " . $archiveDb->prefix . "forums_archive_posts ADD COLUMN archive_field_int INT(10) NULL DEFAULT NULL";
		
			if ( $archiveDb->select( 'count(*)', 'forums_archive_posts' )->first() > \IPS\UPGRADE_MANUAL_THRESHOLD )
			{
				$mr = json_decode( \IPS\Request::i()->mr, TRUE );
				$mr['extra']['_upgradeStep'] = 9;
				\IPS\Request::i()->mr = json_encode( $mr );
				
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( array( $query ), \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr, 'extra' => 0 ) ), "You must run this query against your archive database, <strong>{$INFO['archive_remote_sql_database']}</strong> on {$INFO['archive_remote_sql_host']}, <strong>NOT</strong> the normal community database." ) );
			}
			else
			{
				$archiveDb->query( $query );
				return TRUE;
			}
			
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step8CustomTitle()
	{
		return "Updating forum database table definitions";
	}

	/**
	 * Step 9
	 * Potentially long query, run by itself
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		$queries	= array();

		if( \IPS\Db::i()->checkForIndex( 'forums_posts', 'post' ) )
		{
			$queries[] = array(
				'table' => 'forums_posts',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_posts DROP INDEX post"
			);
		}

		if( \IPS\Db::i()->checkForIndex( 'forums_topics', 'title' ) )
		{
			$queries[] = array(
				'table' => 'forums_topics',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_topics DROP INDEX title"
			);
		}

		if( count( $queries ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
			
			if ( count( $toRun ) )
			{
				$mr = json_decode( \IPS\Request::i()->mr, TRUE );
				$mr['extra']['_upgradeStep'] = 10;
				
				\IPS\Request::i()->mr = json_encode( $mr );
				
				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr, 'extra' => 0 ) ) ) );
			}
		}
	
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step9CustomTitle()
	{
		return "Optimizing posts table";
	}
	
	/**
	 * Step 10
	 * Update the hidden value. Need to do this here rather than in queries.json as the second one is a bit too complicated for it
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		\IPS\Db::i()->update( 'forums_posts', array( 'queued' => -1 ), 'queued=2' );
		\IPS\Db::i()->update( 'forums_posts', array( 'queued' => 2 ), array( 'topic_id IN(?)', \IPS\Db::i()->select( 'tid', 'forums_topics', 'approved=-1' ) ) );
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step10CustomTitle()
	{
		return "Fixing legacy post data...";
	}
}
