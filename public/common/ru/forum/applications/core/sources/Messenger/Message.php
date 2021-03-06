<?php
/**
 * @brief		Personal Conversation Message Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		8 Jul 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\Messenger;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Personal Conversation Message
 */
class _Message extends \IPS\Content\Comment implements \IPS\Content\ReportCenter
{
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_message_posts';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'msg_';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Content\Comment]	Title
	 */
	public static $title = 'personal_conversation_message';
	
	/**
	 * @brief	[Content\Comment]	Icon
	 */
	public static $icon = 'envelope';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\core\Messenger\Conversation';
	
	/**
	 * @brief	[Content\Comment]	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'		=> 'topic_id',
		'date'		=> 'date',
		'content'	=> 'post',
		'author'	=> 'author_id',
		'ip_address'=> 'ip_address',
		'first'		=> 'is_first_post'
	);
	
	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = 'messenger_';
	
	/**
	 * @brief	[Content\Comment]	The ignore type
	 */
	public static $ignoreType = 'messages';
	
	/**
	 * Should this comment be ignored?
	 * Override so that the person who starts the conversation sees all messages - if you send a
	 * message to someone, you're always going to want to be able to see their replies.
	 *
	 * @param	\IPS\Member|null	$member	The member to check for - NULL for currently logged in member
	 * @return	bool
	 */
	public function isIgnored( $member=NULL )
	{
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		if ( $this->item()->author() == $member )
		{
			return FALSE;
		}
		
		return parent::isIgnored( $member );
	}
	
	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item		The content item just created
	 * @param	string					$comment	The comment
	 * @param	bool					$first		Is the first comment?
	 * @param	string					$guestName	If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @return	\IPS\Content\Comment
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL )
	{
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		$comment = call_user_func_array( 'parent::create', func_get_args() );
		
		/* Mark unread for this person */
		\IPS\Db::i()->update( 'core_message_topic_user_map', array( 'map_has_unread' => FALSE, 'map_read_time' => time() ), array( 'map_user_id=? AND map_topic_id=?', $member->member_id, $item->id ) );
		
		return $comment;
	}
	
	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{
		/* Update topic maps for other participants */
		\IPS\Db::i()->update( 'core_message_topic_user_map', array( 'map_has_unread' => 1, 'map_last_topic_reply' => time() ), array( 'map_topic_id=? AND map_user_id!=?', $this->item()->id, $this->author()->member_id ) );
		
		/* Update topic map for this author */
		\IPS\Db::i()->update( 'core_message_topic_user_map', array( 'map_has_unread' => 0, 'map_last_topic_reply' => time(), 'map_read_time' => time() ), array( 'map_topic_id=? AND map_user_id=?', $this->item()->id, $this->author()->member_id ) );
			
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'new_private_message', $this->item(), array( $this ) );
		foreach ( $this->item()->maps( TRUE ) as $map )
		{
			if ( $map['map_user_id'] !== $this->author()->member_id and $map['map_user_active'] and !$map['map_ignore_notification'] )
			{
				$member = \IPS\Member::load( $map['map_user_id'] );
				\IPS\core\Messenger\Conversation::rebuildMessageCounts( $member );
				
				$notification->recipients->attach( $member );
				
				if ( $member->members_bitoptions['show_pm_popup'] )
				{
					$member->msg_show_notification = TRUE;
					$member->save();
				}
			}
		}

		$notification->send();
	}
}