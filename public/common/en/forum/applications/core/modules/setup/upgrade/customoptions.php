<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 May 2014
 * @version		SVN_VERSION_NUMBER
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Custom Upgrade Options
 */
class _customoptions extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		$elements	= array();
		
		/* We need to store option_name => app reference for use later, otherwise all options get stored for the last app in the list. */
		$appOptions = array();
		
		/* Loop through all applications we are upgrading and all versions for those applications and see if any custom options are available.
			At the same time we look for any pre-upgrade checks, which completely halt everything. */
		foreach( $_SESSION['apps'] as $app => $upgrade )
		{
			$application	= \IPS\Application::load( $app );
			$steps			= $application->getUpgradeSteps( $application->long_version );

			foreach( $steps as $step )
			{
				if( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/setup/upg_{$step}/checks.php" ) )
				{
					$output = NULL;
					require_once( \IPS\ROOT_PATH . "/applications/{$app}/setup/upg_{$step}/checks.php" );
					
					if( $output !== NULL )
					{
						\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('admin');
						\IPS\Output::i()->output 	= $output;
						return;
					}
				}

				if( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/setup/upg_{$step}/options.php" ) )
				{
					require_once( \IPS\ROOT_PATH . "/applications/{$app}/setup/upg_{$step}/options.php" );
					$elements	= array_merge( $elements, $options );
					foreach( $options AS $option )
					{
						$appOptions[$option->name] = $app;
					}
				}
			}
		}
		
		/* If there are no options, no need to show an empty/blank form */
		if( !count( $elements ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=confirm" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
		}

		/* Otherwise, show the form */
		$form = new \IPS\Helpers\Form( 'options', 'continue' );

		foreach( $elements as $element )
		{
			$form->add( $element );
		}

		if( $values = $form->values() )
		{
			foreach( $values as $key => $value )
			{
				$app = $appOptions[$key];
				if ( preg_match( '#^(\d{5,6})_#', $key ) )
				{
					$version	= mb_substr( $key, 0, mb_strpos( $key, '_' ) );
					$key		= mb_substr( $key, mb_strpos( $key, '_' ) + 1 );
				}
				
				$_SESSION['upgrade_options'][ $app ][ $version ][ $key ] = $value;
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=confirm" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('admin');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'admin', $form );
	}
}