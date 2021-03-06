<?php
/**
 * @brief		email
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * email
 */
class _email extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_manage' );
		parent::execute();
	}

	/**
	 * Email Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Build the settings form */
		$settingsForm = new \IPS\Helpers\Form( 'settings_form' );
		$settingsForm->addHeader( 'basic_settings' );
		$settingsForm->add( new \IPS\Helpers\Form\Email( 'email_out', \IPS\Settings::i()->email_out, TRUE ) );
		$settingsForm->add( new \IPS\Helpers\Form\Email( 'email_in', \IPS\Settings::i()->email_in, TRUE ) );
		$settingsForm->add( new \IPS\Helpers\Form\Color( 'email_color', \IPS\Settings::i()->email_color, TRUE ) );
		$settingsForm->add( new \IPS\Helpers\Form\Upload( 'email_logo', \IPS\Settings::i()->email_logo ? \IPS\File::get( 'core_Theme', \IPS\Settings::i()->email_logo ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Theme' ) ) );
		
		$settingsForm->addHeader( 'advanced_settings' );
		$settingsForm->add( new \IPS\Helpers\Form\Select( 'mail_method', \IPS\Settings::i()->mail_method, TRUE, array(
			'options' => array( 'mail' => 'mail_method_php', 'smtp' => 'mail_method_smtp' ),
			'toggles' => array(
				'mail'	=> array( 'php_mail_extra' ),
				'smtp'	=> array( 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_helo' )
			)
		) ) );
		$settingsForm->add( new \IPS\Helpers\Form\Text( 'smtp_host', \IPS\Settings::i()->smtp_host, FALSE, array(), NULL, NULL, NULL, 'smtp_host' ) );
		$settingsForm->add( new \IPS\Helpers\Form\Number( 'smtp_port', \IPS\Settings::i()->smtp_port, FALSE, array(), NULL, NULL, NULL, 'smtp_port' ) );
		$settingsForm->add( new \IPS\Helpers\Form\Text( 'smtp_user', \IPS\Settings::i()->smtp_user, FALSE, array(), NULL, NULL, NULL, 'smtp_user' ) );
		$settingsForm->add( new \IPS\Helpers\Form\Password( 'smtp_pass', \IPS\Settings::i()->smtp_pass, FALSE, array(), NULL, NULL, NULL, 'smtp_pass' ) );
		$settingsForm->add( new \IPS\Helpers\Form\Text( 'php_mail_extra', \IPS\Settings::i()->php_mail_extra, FALSE, array(), NULL, NULL, NULL, 'php_mail_extra' ) );
		if ( $settingsForm->values() )
		{
			$settingsForm->saveAsSettings();
			\IPS\Session::i()->log( 'acplogs__email_settings' );
		}
		
		
		/* Build the test form */
		$messageHtml = '';
		$testFormHtml = '';
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'email_test' ) )
		{
			\IPS\Output::i()->sidebar['actions']['test'] = array(
				'title'		=> 'email_test',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal( '#testForm' ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('email_test'), 'ipsDialog-content' => '#testForm' )
			);
			
			$testForm = new \IPS\Helpers\Form( 'test_form' );
			$testForm->add( new \IPS\Helpers\Form\Email( 'from', \IPS\Settings::i()->email_out, TRUE ) );
			$testForm->add( new \IPS\Helpers\Form\Email( 'to', \IPS\Member::loggedIn()->email, TRUE ) );
			$testForm->add( new \IPS\Helpers\Form\Text( 'subject', \IPS\Member::loggedIn()->language()->addToStack('email_test_subject'), TRUE ) );
			$testForm->add( new \IPS\Helpers\Form\TextArea( 'message', \IPS\Member::loggedIn()->language()->addToStack('email_test_message'), TRUE ) );
			if ( $values = $testForm->values() )
			{
				try
				{
					$email			= \IPS\Email::buildFromContent( $values['subject'], $values['message'], $values['message'] );
					$email->from	= $values['from'];
					$result = $email->send( $values['to'] );

					if ( $result === TRUE )
					{
						$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'email_test_okay', 'success' );
					}
				}
				catch( \IPS\Email\Outgoing\Exception $result )
				{
					$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'email_test_error', 'error', $result->getMessage() );
				}
				/* If php mail fails it throws a regular PHP error */
				catch( \ErrorException $result )
				{
					$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'email_test_error', 'error', $result->getMessage() );
				}
			}

			$testFormHtml = \IPS\Theme::i()->getTemplate( 'global' )->block( 'email_test', $testForm, FALSE, 'ipsJS_hide', 'testForm' );
		}
		
		/* Add a button for logs */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'email_errorlog' ) )
		{
			\IPS\Output::i()->sidebar['actions']['errorLog'] = array(
				'title'		=> 'emailerrorlogs',
				'icon'		=> 'exclamation-triangle',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLog' ),
			);
		}
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('email_settings');
		\IPS\Output::i()->output	= $messageHtml;
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'email_settings', $settingsForm );
		\IPS\Output::i()->output	.= $testFormHtml;
	}
	
	/**
	 * Error Log
	 *
	 * @return	void
	 */
	protected function errorLog()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_mail_error_logs', \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLog' ) );
		$table->langPrefix = 'emailerrorlogs_';
		
		/* Columns we need */
		$table->include	= array( 'mlog_to', 'mlog_subject', 'mlog_date', 'mlog_msg' );
		$table->widths	= array( 'mlog_date' => '15', 'mlog_to' => '20', 'mlog_subject' => '20', 'mlog_msg' => '35' );

		$table->sortBy	= $table->sortBy ?: 'mlog_date';
		$table->sortDirection	= $table->sortDirection ?: 'DESC';
		$table->noSort	= array( 'mlog_msg' );
		
		/* Search */
		$table->quickSearch = 'mlog_to';
		$table->advancedSearch = array(
				'mlog_to'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_from'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_subject'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_msg'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_content'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
		);
		
		/* Custom parsers */
		$table->parsers = array(
			'mlog_date'				=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			},
			'mlog_msg'				=> function( $val, $row )
			{
				if( !$val )
				{
					$val = \IPS\Member::loggedIn()->language()->get('phpmail_not_sent');
				}

				return \IPS\Theme::i()->getTemplate( 'logs' )->emailErrorLog( nl2br( htmlspecialchars( $val, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) ), $row );
			},
			'mlog_content'				=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'logs' )->emailErrorBody( $val, $row );
			},
		);

		/* Row buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();

			$return['view'] = array(
				'icon'		=> 'search',
				'title'		=> 'view_email_error_body',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogView&id=' ) . $row['mlog_id'],
				'hotkey'	=> 'Return',
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('emailerrorlogs_mlog_content') )
			);

			$return['resend'] = array(
				'icon'		=> 'refresh',
				'title'		=> 'resend_email_error',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogResend&id=' ) . $row['mlog_id'],
			);

			return $return;
		};

		/* Add a button for settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'email_errorlog_prune' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'prunesettings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogSettings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
			);
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('emailerrorlogs');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'title', (string) $table );
	}

	/**
	 * Attempt to resend the email that failed
	 *
	 * @return void
	 */
	public function errorLogResend()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog' );
		
		$id		= (int) \IPS\Request::i()->id;
		$log	= \IPS\Db::i()->select( '*', 'core_mail_error_logs', array( 'mlog_id=?', $id ) )->first();
		$email	= json_decode( $log['mlog_resend_data'], true );

		try
		{
			$emailHandler	= \IPS\Email::buildFromRaw( $email['headers'], $email['body'] );
			$emailHandler->subject	= $log['mlog_subject'];
			$emailHandler->to		= explode( ',', $log['mlog_to'] );
			$emailHandler->from		= $log['mlog_from'];
			$emailHandler->forceSend( $email['boundary'] );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '4C143/1', 500, '' );
		}

		\IPS\Db::i()->delete( 'core_mail_error_logs', array( 'mlog_id=?', $id ) );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=email&do=errorLog" ), 'emailerror_resent' );
	}

	/**
	 * View a failed email details
	 *
	 * @return void
	 */
	public function errorLogView()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog' );
		
		$id		= (int) \IPS\Request::i()->id;
		$log	= \IPS\Db::i()->select( '*', 'core_mail_error_logs', array( 'mlog_id=?', $id ) )->first();

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('logs')->emailErrorBody( $log );
	}

	/**
	 * Error log Prune Settings
	 *
	 * @return	void
	 */
	protected function errorLogSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog_prune' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Number( 'prune_log_email_error', \IPS\Settings::i()->prune_log_email_error, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), \IPS\Member::loggedIn()->language()->addToStack('days'), 'prune_log_email_error' ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__emailerrorlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLog' ), 'saved' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('emailerrorlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'emailerrorlogssettings', $form, FALSE );
	}
}