<?php
/**
 * @brief		System Logs
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		13 Nov 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Error Logs
 */
class _systemLogs extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'system_logs_view' );
		parent::execute();
	}

	/**
	 * Manage Error Logs
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Fetch the data */
		$dataSource = array();
		
		foreach( \IPS\Log::getUsedMethods() as $method => $levels )
		{
			$logs = \IPS\Log::i( $method )->getMostRecentLogsTitles( 50 );
			
			foreach( $logs as $log )
			{
				$log['method'] = $method;
				$dataSource[ $log['date'] . '.' . $log['suffix'] ] = $log;
			}			
		}
		
		krsort( $dataSource );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Custom( array_slice( $dataSource, 0, 50 ), \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ) );
		$table->langPrefix = 'systemlogs_';
		$table->mainColumn = 'title';
		
		$table->widths = array( 'date' => 30, 'title' => 50, 'suffix' => 20 );
		$table->parsers = array(
			'suffix'=> function( $val )
			{
				return mb_strtoupper( $val );
			},
			'method'=> function( $val )
			{
				return ucfirst( $val );
			},
			'date'  => function( $val )
			{
				return \IPS\DateTime::ts( $val );
			},
			'title' => function( $val, $row )
			{
				return "<a href='" . \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs&do=view&title=" . urlencode( $val ) . '&method=' . $row['method'] ) . "'>{$val}</a>";
			}
		);
		$table->sortBy        = $table->sortBy        ?: 'date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'support', 'diagnostic_log_settings' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
					'settings'	=> array(
							'title'		=> 'prunesettings',
							'icon'		=> 'cog',
							'link'		=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=logSettings' ),
							'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
					),
			);
		}
		
		/* Display */		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('r__system_logs');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'r__system_logs', (string) $table );
	}
	
	/**
	 * View a log file
	 * 
	 * @return void
	 */
	protected function view()
	{
		/* Display */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/systemlogs.css', 'core', 'admin' ) );
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('r__system_logs');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->systemLogView( \IPS\Request::i()->title, \IPS\Log::i( \IPS\Request::i()->method )->getLog( urldecode( \IPS\Request::i()->title ) ) );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs" ), \IPS\Member::loggedIn()->language()->addToStack('r__system_logs') );
	}
	
	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function logSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'diagnostic_log_settings' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Number( 'prune_log_system', \IPS\Settings::i()->prune_log_system, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), \IPS\Member::loggedIn()->language()->addToStack('days'), 'prune_log_moderator' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__systemlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('systemlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'systemlogssettings', $form, FALSE );
	}
}