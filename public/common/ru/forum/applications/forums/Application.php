<?php
/**
 * @brief		Forums Application Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2014 Invision Power Services, Inc.
 * @package		IPS Social Suite
 * @subpackage	Forums
 * @since		07 Jan 2014
 * @version		
 */
 
namespace IPS\forums;

/**
 * Forums Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * Init
	 *
	 * @param	void
	 */
	public function init()
	{
		/* If the viewing member cannot view the board (ex: guests must login first), then send a 404 Not Found header here, before the Login page shows in the dispatcher */
		if ( !\IPS\Member::loggedIn()->group['g_view_board'] and ( \IPS\Request::i()->module == 'forums' and \IPS\Request::i()->controller == 'forums' and isset( \IPS\Request::i()->rss ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2F219/1', 404, '' );
		}
	}
	
	/**
	 * Archive Query
	 *
	 * @param	array	$rules	Rules
	 * @return	array
	 */
	public static function archiveWhere( $rules )
	{
		$where = array();
		foreach ( $rules as $rule )
		{
			$clause = NULL;
			
			switch ( $rule['archive_field'] )
			{
				case 'lastpost':
					$clause = array( 'last_post' . $rule['archive_value'] . '?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $rule['archive_text'] . mb_strtoupper( $rule['archive_unit'] ) ) )->getTimestamp() );
					break;
				
				case 'forum':
					$clause = array( 'forum_id ' . ( $rule['archive_value'] == '+' ? 'IN' : 'NOT IN' ) . '(' . $rule['archive_text'] . ')' );
					break;
					
				case 'pinned':
				case 'featured':
				case 'state':
				case 'approved':
					$clause = array( $rule['archive_field'] . '=?', $rule['archive_value'] );
					break;
				
				case 'poll':
					if ( $rule['archive_value'] )
					{
						$clause = array( 'poll_state>0' );
					}
					else
					{
						$clause = array( 'poll_state=0' );
					}
					break;
					
				case 'post':
				case 'view':
					$clause = array( $rule['archive_field'] . 's' . $rule['archive_value'] . '?', $rule['archive_text'] );
					break;
				
				case 'rating':
					$clause = array( 'ROUND(topic_rating_total/topic_rating_hits)' . $rule['archive_value'] . '?', $rule['archive_text'] );
					break;
				
				case 'member':
					$clause = array( 'starter_id ' . ( $rule['archive_value'] == '+' ? 'IN' : 'NOT IN' ) . '(' . $rule['archive_text'] . ')' );
					break;
				
			}
			
			if ( $clause )
			{
				if ( $rule['archive_skip'] )
				{
					$clause[0] = ( '!(' . $clause[0] . ')' );
					$where[] = $clause;
				}
				else
				{
					$where[] = $clause;
				}
			}
		}
		
		return $where;
	}
	
	/**
	 * Install 'other' items.
	 *
	 * @return void
	 */
	public function installOther()
	{
		\IPS\Content\Search\Index::i()->index( \IPS\forums\Topic::load( 1 ) );
		\IPS\Content\Search\Index::i()->index( \IPS\forums\Topic\Post::load( 1 ) );
	}
}