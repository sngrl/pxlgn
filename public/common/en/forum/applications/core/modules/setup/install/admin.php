<?php
/**
 * @brief		Installer: Admin Account
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		2 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */
 
namespace IPS\core\modules\setup\install;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Installer: Admin Account
 */
class _admin extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 */
	public function manage()
	{
		$form = new \IPS\Helpers\Form( 'admin', 'continue' );
		
		$form->add( new \IPS\Helpers\Form\Text( 'admin_user', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'admin_pass1', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'admin_pass2', NULL, TRUE, array( 'confirm' => 'admin_pass1' ) ) );
		$form->add( new \IPS\Helpers\Form\Email( 'admin_email', NULL, TRUE ) );
		
		if ( $values = $form->values() )
		{
			$INFO = NULL;
			require \IPS\ROOT_PATH . '/conf_global.php';
			$INFO = array_merge( $INFO, $values );
			
			$toWrite = "<?php\n\n" . '$INFO = ' . var_export( $INFO, TRUE ) . ";";
			
			try
			{
				if ( \file_put_contents( \IPS\ROOT_PATH . '/conf_global.php', $toWrite ) )
				{
					/* PHP 5.6 - clear opcode cache or details won't be seen on next page load */
					if( function_exists('opcache_reset') )
					{
						/* Avoid throwing an exception Zend OPcache API is restricted by "restrict_api" configuration directive */
						@opcache_reset();
					}

					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=install' ) );
				}
			}
			catch( \Exception $ex )
			{
				$errorform = new \IPS\Helpers\Form( 'admin', 'continue' );
				$errorform->add( new \IPS\Helpers\Form\TextArea( 'conf_global_error', $toWrite, FALSE ) );
				
				foreach( $values as $k => $v )
				{
					$errorform->hiddenValues[ $k ] = $v;
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->confWriteError( $errorform, \IPS\ROOT_PATH );
				return;
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('admin');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'admin', $form );
	}
}