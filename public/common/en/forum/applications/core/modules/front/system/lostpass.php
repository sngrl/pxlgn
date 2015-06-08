<?php
/**
 * @brief		Lost Password
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		26 Aug 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Lost Password
 */
class _lostpass extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Build the form */
		$form =  new \IPS\Helpers\Form( "lostpass", 'request_password' );
		$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array(), function( $val ){
			
			/* Check email exists */
			$inUse = FALSE;
			foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
			{
				if ( $handler->emailIsInUse( $val ) === TRUE )
				{
					$inUse = TRUE;
					break;
				}
			}
			
			if( !$inUse )
			{
				throw new \LogicException( 'lost_pass_no_email' );
			}
		}) );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('lost_password');
		
		/* Handle the reset */
		if ( $values = $form->values() )
		{
			/* Load the member */
			$member = \IPS\Member::load( $values['email_address'], 'email' );
			
			/* Make a validation key */
			$vid = md5( $member->members_pass_hash . uniqid( mt_rand(), TRUE ) );
			
			/* Get rid of old entries for this member */
			\IPS\DB::i()->delete( 'core_validating', array( 'member_id=? AND lost_pass=1', $member->member_id ) );
			
			/* Update the DB for this member. */
			$validating = array(
				'vid'         => $vid,
				'member_id'   => $member->member_id,
				'entry_date'  => time(),
				'lost_pass'   => 1,
				'ip_address'  => $member->ip_address,
			);
				
			\IPS\Db::i()->insert( 'core_validating', $validating );
			
			/* Send email */
			\IPS\Email::buildFromTemplate( 'core', 'lost_password_init', array( $member, $vid ) )->send( $member );
			
			/* Show confirmation page with further instructions */
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->lostPassConfirm();
		}
		else
		{
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->lostPass( $form );
		}
	}
	
	/**
	 * Validate
	 *
	 * @return	void
	 */
	protected function validate()
	{
		try
		{
			$record = \IPS\Db::i()->select( '*', 'core_validating', array( 'vid=? AND member_id=? AND lost_pass=1', \IPS\Request::i()->vid, \IPS\Request::i()->mid ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'no_validation_key', '2S151/1', 410, '' );
		}
		
		/* Show form for new password */
		$form =  new \IPS\Helpers\Form( "resetpass", 'save' );
		$form->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE, array() ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password_confirm', NULL, TRUE, array( 'confirm' => 'password' ) ) );

		/* Set new password */
		if ( $values = $form->values() )
		{
			$member = \IPS\Member::load( $record['member_id'] );
			
			$member->members_pass_salt	= $member->generateSalt();
			$member->members_pass_hash	= $member->encryptedPassword( $values['password'] );
			$member->failed_logins		= array();
			$member->save();
			
			/* Delete validating record and log in */
			\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND lost_pass=1', $member->member_id ) );
			
			/* If the member doesn't have any other validating rows validate them */
			try
			{
				$record = \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=?', $member->member_id ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				$member->validate();
			}

			\IPS\Session::i()->setMember( $member );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
		}

		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->resetPass( $form );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'lost_password' );
	}
}