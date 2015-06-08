<?php
/**
 * @brief		Register
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		3 July 2013
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
 * Register
 */
class _register extends \IPS\Dispatcher\Controller
{
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		if ( \IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->_fromLogin ) )
		{
			\IPS\Output::i()->redirect( \IPS\Settings::i()->base_url );
		}
		
		if( !\IPS\Settings::i()->allow_reg )
		{
			\IPS\Output::i()->error( 'reg_disabled', '2S129/5', 403, '' );
		}
		
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
	}
	
	/**
	 * Register
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if( isset( $_SESSION['coppa_user'] ) )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('reg_awaiting_validation');
			return \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->notCoppaValidated();
		}
		
		/* Set up the step array */
		$steps = array();
				
		/* If coppa is enabled we need to add a birthday verification */
		if ( \IPS\Settings::i()->use_coppa )
		{
			$steps['coppa'] = function( $data )
			{
				/* Build the form */
				$form = new \IPS\Helpers\Form( 'coppa', 'register_button' );
				$form->add( new \IPS\Helpers\Form\Date( 'bday', NULL, TRUE, array( 'max' => \IPS\DateTime::create() ) ) );
				
				if( $values = $form->values() )
				{
					if( ( $values['bday']->diff( \IPS\DateTime::create() )->y < 13 ) )
					{
						$_SESSION['coppa_user'] = TRUE;
						return \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->notCoppaValidated();
					}
								
					return $values;
				}
				
				return \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->coppa( $form );
			};
		}
		
		$steps['basic_info'] = function ( $data )
		{
			$form = \IPS\core\modules\front\system\register::buildRegistrationForm();

			/* Handle submissions */
			if ( $values = $form->values() )
			{
				/* Create Member */
				$member = \IPS\core\modules\front\system\register::_createMember( $values );
				
				/* Custom Fields */
				$profileFields = array();

				foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\REG ) as $group => $fields )
				{
					foreach ( $fields as $id => $field )
					{
						$profileFields[ "field_{$id}" ] = $field::stringValue( $values[ $field->name ] );

						if ( $fields instanceof \IPS\Helpers\Form\Editor )
						{
							$field->claimAttachments( $this->id );
						}
					}
				}
				\IPS\Db::i()->replace( 'core_pfields_content', array_merge( array( 'member_id' => $member->member_id ), $profileFields ) );
				
				/* Email - We don't want to send if the new member is banned or we're not using email validation */
				if( \IPS\Settings::i()->reg_auth_type != 'none' AND \IPS\Settings::i()->reg_auth_type != 'admin' AND !$member->members_bitoptions['bw_is_spammer'] )
				{
					\IPS\Email::buildFromTemplate( 'core', 'registration_validate', array( $member, \IPS\Db::i()->select( 'vid', 'core_validating', array( 'member_id=?', $member->member_id ) )->first() ) )->send( $member );
				}
				
				/* Notify the incoming mail address except if they're a spammer */
				if( \IPS\Settings::i()->new_reg_notify && !$member->members_bitoptions['bw_is_spammer'] )
				{
					\IPS\Email::buildFromTemplate( 'core', 'registration_notify', array( $member, $profileFields ) )->send( \IPS\Settings::i()->email_in );
				}
				
				/* Log them in */
				\IPS\Session::i()->setMember( $member );

				$redirectUrl	= \IPS\Login::getRegistrationDestination( $member );
				
				/* Redirect */
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( array( 'redirect' => (string) $redirectUrl ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $redirectUrl );
				}
			}
				
			return \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->register( $form, new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login', NULL, \IPS\Settings::i()->logins_over_https ) ) );
		};
		
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', NULL, 'register' ), array(), 'loc_registering' );
		
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('registration');
		\IPS\Output::i()->output = (string) new \IPS\Helpers\Wizard( $steps,	\IPS\Http\Url::internal( 'app=core&module=system&controller=register' ), FALSE );
	}
	
	/**
	 * Build Registration Form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public static function buildRegistrationForm()
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form( 'form', 'register_button', NULL, array( 'data-controller' => 'core.front.system.register') );
		$form->add( new \IPS\Helpers\Form\Text( 'username', NULL, TRUE, array( 'accountUsername' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array( 'accountEmail' => TRUE, 'maxLength' => 150 ) ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE, array() ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password_confirm', NULL, TRUE, array( 'confirm' => 'password' ) ) );
	
		/* Profile fields */
		foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\REG ) as $group => $fields )
		{
			foreach ( $fields as $field )
			{
				$form->add( $field );
			}
		}
		$form->addSeperator();
		
		$question = FALSE;
		try
		{
			$question = \IPS\Db::i()->select( '*', 'core_question_and_answer', NULL, "RAND()" )->first();
		}
		catch ( \UnderflowException $e ) {}
		
		/* Random Q&A */
		if( $question )
		{
			$form->hiddenValues['q_and_a_id'] = $question['qa_id'];
	
			$form->add( new \IPS\Helpers\Form\Text( 'q_and_a', NULL, TRUE, array(), function( $val )
			{
				$qanda  = intval( \IPS\Request::i()->q_and_a_id );
				$pass = true;
			
				if( $qanda )
				{
					$question = \IPS\Db::i()->select( '*', 'core_question_and_answer', array( 'qa_id=?', $qanda ) )->first();
					$answers = json_decode( $question['qa_answers'] );

					if( $answers )
					{
						$answers = is_array( $answers ) ? $answers : array( $answers );
						$pass = FALSE;
					
						foreach( $answers as $answer )
						{
							$answer = trim( $answer );

							if( mb_strlen( $answer ) AND mb_strtolower( $answer ) == mb_strtolower( $val ) )
							{
								$pass = TRUE;
							}
						}
					}
				}
				else
				{
					$questions = \IPS\Db::i()->select( 'count(*)', 'core_question_and_answer', 'qa_id > 0' )->first();
					if( $questions )
					{
						$pass = FALSE;
					}
				}
				
				if( !$pass )
				{
					throw new \DomainException( 'q_and_a_incorrect' );
				}
			} ) );
			
			/* Set the form label */
			\IPS\Member::loggedIn()->language()->words['q_and_a'] = \IPS\Member::loggedIn()->language()->addToStack( 'core_question_and_answer_' . $question['qa_id'], FALSE );
		}
		
		$captcha = new \IPS\Helpers\Form\Captcha;
		
		if ( (string) $captcha !== '' )
		{
			$form->add( $captcha );
		}
		
		if ( $question OR (string) $captcha !== '' )
		{
			$form->addSeperator();
		}
		
		$form->add( new \IPS\Helpers\Form\Checkbox( 'reg_admin_mails', TRUE, FALSE ) );
		
		\IPS\Member::loggedIn()->language()->words[ "reg_agreed_terms" ] = sprintf( \IPS\Member::loggedIn()->language()->get("reg_agreed_terms"), \IPS\Http\Url::internal( 'app=core&module=system&controller=terms', 'front', 'terms' ) );
		
		/* Build the appropriate links for registration terms & privacy policy */
		if ( \IPS\Settings::i()->privacy_type == "internal" )
		{
			\IPS\Member::loggedIn()->language()->words[ "reg_agreed_terms" ] .= sprintf( \IPS\Member::loggedIn()->language()->get("reg_privacy_link"), \IPS\Http\Url::internal( 'app=core&module=system&controller=privacy', 'front', 'privacy' ), 'data-ipsDialog data-ipsDialog-size="wide" data-ipsDialog-title="' . \IPS\Member::loggedIn()->language()->get("privacy") . '"' );
		}
		else if ( \IPS\Settings::i()->privacy_type == "external" )
		{
			\IPS\Member::loggedIn()->language()->words[ "reg_agreed_terms" ] .= sprintf( \IPS\Member::loggedIn()->language()->get("reg_privacy_link"), \IPS\Http\Url::external( \IPS\Settings::i()->privacy_link ), 'target="_blank"' );
		}
		
		$form->add( new \IPS\Helpers\Form\Checkbox( 'reg_agreed_terms', NULL, TRUE, array(), function( $val )
		{
			if ( !$val )
			{
				throw new \InvalidArgumentException('reg_not_agreed_terms');
			}
		} ) );
		
		
		return $form;
	}
	
	/**
	 * Create Member
	 *
	 * @param	array	$values	Values from form
	 * @return	\IPS\Member
	 */
	public static function _createMember( $values )
	{
		/* Create */
		$member = new \IPS\Member;
		$member->name	   = $values['username'];
		$member->email		= $values['email_address'];
		$member->members_pass_salt  = $member->generateSalt();
		$member->members_pass_hash  = $member->encryptedPassword( $values['password'] );
		$member->allow_admin_mails  = $values['reg_admin_mails'];
		$member->member_group_id	= \IPS\Settings::i()->member_group;
		
		/* Query spam service */
		if( \IPS\Settings::i()->spam_service_enabled )
		{
			$member->spamService();
		}

		/* Will we be validating? */
		if ( \IPS\Settings::i()->reg_auth_type != 'none' )
		{
			$member->members_bitoptions['validating'] = TRUE;
		}

		/* Save and return */
		$member->save();
		return $member;
	}
	
	/**
	 * A printable coppa form
	 *
	 * @return	void
	 */
	protected function coppaForm()
	{
		$output = \IPS\Theme::i()->getTemplate( 'system' )->coppaConsent();
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );
		
		require_once \IPS\ROOT_PATH . '/system/3rd_party/tcpdf/tcpdf.php';
		$pdf = new \TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, TRUE, 'UTF-8', FALSE );
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);
		$pdf->AddFont('freeserif');
		$pdf->SetFont('freeserif');
		$pdf->AddPage();
		$pdf->writeHTML( $output );
		$pdf->Output( 'coppa.pdf', 'I' );
		exit;
	}

	/**
	 * Awaiting Validation
	 *
	 * @return	void
	 */
	protected function validating()
	{
		if( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
		}
		
		/* Fetch the validating record to see what we're dealing with */
		try
		{
			$validating = \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=? AND new_reg=? OR email_chg=?', \IPS\Member::loggedIn()->member_id, 1, 1 ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'validate_no_record', '2S129/4', 404, '' );
		}
		
		/* They're not validated but in what way? */
		if( $validating['user_verified'] )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->notAdminValidated();
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->notValidated( $validating );
		}
		
		/* Display */
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('reg_awaiting_validation');
	}
	
	/**
	 * Resend validation email
	 *
	 * @return	void
	 */
	protected function resend()
	{
		$validating = \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=?', \IPS\Member::loggedIn()->member_id ) );
	
		if ( !count( $validating ) )
		{
			\IPS\Output::i()->error( 'validate_no_record', '2S129/3', 404, '' );
		}
	
		foreach( $validating as $reg )
		{
			\IPS\Email::buildFromTemplate( 'core', 'registration_validate', array( \IPS\Member::loggedIn(), $reg['vid'] ) )->send( \IPS\Member::loggedIn() );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=validating' ), 'reg_email_resent' );
	}
	
	/**
	 * Validate
	 *
	 * @return	void
	 */
	protected function validate()
	{
		if( \IPS\Request::i()->vid AND \IPS\Request::i()->mid )
		{
			try
			{
				$record = \IPS\Db::i()->select( '*', 'core_validating', array( 'vid=? AND member_id=? AND new_reg=? or email_chg=?', \IPS\Request::i()->vid, \IPS\Request::i()->mid, 1, 1 ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'validate_no_record', '3S129/2', 404, '' );
			}
			
			/* If we're using user validation, or this was anything other than a new registraion (like an email change) we can go ahead and validate */
			if( \IPS\Settings::i()->reg_auth_type == 'user' or $record['email_chg'] )
			{
				\IPS\Member::load( \IPS\Request::i()->mid )->validate();
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ), 'validate_confirmation' );
			}
			/* Otherwise admin still needs to validate so we flag user validated */
			else
			{
				\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => TRUE ), array( 'member_id=?', \IPS\Request::i()->mid ) );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ), 'validate_email_confirmation' );
			}
		}
	}
	
	/**
	 * Complete Profile
	 *
	 * @return	void
	 */
	protected function complete()
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form( 'form', 'register_button' );
		if( !\IPS\Member::loggedIn()->real_name OR \IPS\Member::loggedIn()->name === \IPS\Member::loggedIn()->language()->get('guest') )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'username', NULL, TRUE, array( 'accountUsername' => \IPS\Member::loggedIn() ) ) );
		}
		if( !\IPS\Member::loggedIn()->email )
		{
			$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array( 'accountEmail' => TRUE ) ) );
		}
		$form->addButton( 'cancel', 'link', \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=cancel', 'front', 'register' )->csrf() );
			
		/* Handle the submission */
		if ( $values = $form->values() )
		{
			if( isset( $values['username'] ) )
			{
				\IPS\Member::loggedIn()->name = $values['username'];
			}
			if( isset( $values['email_address'] ) )
			{
				\IPS\Member::loggedIn()->email = $values['email_address'];
			}

			/* Save */
			\IPS\Member::loggedIn()->save();
	
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
		}
	
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('reg_complete_details');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->completeProfile( $form );
	}
		
	/**
	 * Change Email
	 *
	 * @return	void
	 */
	protected function changeEmail()
	{
		/* Are we logged in and pending validation? */
		if( !\IPS\Member::loggedIn()->member_id OR !\IPS\Member::loggedIn()->members_bitoptions['validating'] )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C223/2', 403, '' );
		}

		/* Do we have any pending validation emails? */
		try
		{
			$pending = \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=? AND ( new_reg=1 or email_chg=1 )', \IPS\Member::loggedIn()->member_id ), 'entry_date DESC' )->first();
		}
		catch( \UnderflowException $e )
		{
			$pending = null;
		}
				
		/* Build the form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Email( 'new_email', '', TRUE, array( 'accountEmail' => TRUE ) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Change the email */
			foreach ( \IPS\Login::handlers( TRUE ) as $handler )
			{
				try
				{
					$handler->changeEmail( \IPS\Member::loggedIn(), \IPS\Member::loggedIn()->email, $values['new_email'] );
				}
				catch( \BadMethodCallException $e ) {}
			}
		
			/* Delete any pending validation emails */
			if ( $pending['vid'] )
			{
				\IPS\Db::i()->delete( 'core_validating', array( 'member_id=? AND ( new_reg=1 or email_chg=1 )', \IPS\Member::loggedIn()->member_id ) );
			}
		
			$vid = md5( uniqid() );
	
			\IPS\Db::i()->insert( 'core_validating', array(
				'vid'			=> $vid,
				'member_id'		=> \IPS\Member::loggedIn()->member_id,
				'entry_date'	=> time(),
				'new_reg'		=> !$pending or $pending['new_reg'],
				'email_chg'		=> $pending and $pending['email_chg'],
				'user_verified'	=> ( \IPS\Settings::i()->reg_auth_type == 'admin' ) ?: FALSE,
				'ip_address'	=> \IPS\Request::i()->ipAddress()
			) );
				
			\IPS\Member::loggedIn()->save();
	
			\IPS\Email::buildFromTemplate( 'core', 'registration_validate', array( \IPS\Member::loggedIn(), $vid ) )->send( \IPS\Member::loggedIn() );
				
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}
	
	/**
	 * Cancel Registration
	 *
	 * @return	void
	 */
	protected function cancel()
	{
		/* This bit is kind of important */
		\IPS\Session::i()->csrfCheck();
		if ( \IPS\Member::loggedIn()->name and \IPS\Member::loggedIn()->email and !\IPS\Db::i()->select( 'COUNT(*)', 'core_validating', array( 'member_id=? AND new_reg=1', \IPS\Member::loggedIn()->member_id ) )->first() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C223/1', 403, '' );
		}

		/* Delete Member */
		\IPS\Member::loggedIn()->delete();
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ), 'reg_canceled' );
	}
}