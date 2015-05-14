<?php
/**
 * @brief		Admin CP Member Form
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		15 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\MemberForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Member Form
 */
class _Restrictions
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Form\Tabbed		$form	The form
	 * @param	\IPS\Member				$member	Existing Member
	 * @return	void
	 */
	public function process( &$form, $member )
	{
		/* Moderation */
		$form->addHeader( 'member_moderation' );
		$form->add( new \IPS\Helpers\Form\Number( 'member_warnings', $member->warn_level, FALSE ) );	
		$form->add( new \IPS\Helpers\Form\Date( 'restrict_post', $member->restrict_post, FALSE, array( 'time' => TRUE, 'unlimited' => -1, 'unlimitedLang' => 'indefinitely' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('until') ) );
		$form->add( new \IPS\Helpers\Form\Date( 'mod_posts', $member->mod_posts, FALSE, array( 'time' => TRUE, 'unlimited' => -1, 'unlimitedLang' => 'indefinitely' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('until') ) );
		
		/* Restrictions */
		$form->addHeader( 'member_restrictions' );
		if ( \IPS\Settings::i()->tags_enabled )
		{
			if ( !$member->group['gbw_disable_tagging'] )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'bw_disable_tagging', !$member->members_bitoptions['bw_disable_tagging'] ) );
			}
			if ( !$member->group['gbw_disable_prefixes'] )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'bw_disable_prefixes', !$member->members_bitoptions['bw_disable_prefixes'] ) );
			}
		}
		if ( $member->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'bw_no_status_update', !$member->members_bitoptions['bw_no_status_update'] ) );
		}
		if ( $member->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging', 'front' ) ) )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'members_disable_pm', $member->members_disable_pm, FALSE, array( 'options' => array(
				0 => 'members_disable_pm_on',
				1 => 'members_disable_pm_member_disable',
				2 => 'members_disable_pm_admin_disable',
			) ) ) );
		}
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member			$member	The member
	 * @return	void
	 */
	public function save( $values, &$member )
	{
		$member->warn_level		= $values['member_warnings'];
		$member->mod_posts		= is_object( $values['mod_posts'] ) ? $values['mod_posts']->getTimestamp() : ( $values['mod_posts'] ?: 0 );
		$member->restrict_post	= is_object( $values['restrict_post'] ) ? $values['restrict_post']->getTimestamp() : ( $values['restrict_post'] ?: 0 );
		
		if ( \IPS\Settings::i()->tags_enabled )
		{
			if ( !$member->group['gbw_disable_tagging'] )
			{
				$member->members_bitoptions['bw_disable_tagging'] = !$values['bw_disable_tagging'];
			}
			if ( !$member->group['gbw_disable_prefixes'] )
			{
				$member->members_bitoptions['bw_disable_prefixes'] = !$values['bw_disable_prefixes'];
			}
		}
		if ( $member->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
		{
			$member->members_bitoptions['bw_no_status_update'] = !$values['bw_no_status_update'];
		}
		if ( $member->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging', 'front' ) ) )
		{
			$member->members_disable_pm = $values['members_disable_pm'];
		}
	}
}