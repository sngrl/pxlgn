<?php
/**
 * @brief		Security Settings
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		11 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\overview;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Security Settings
 */
class _security extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'security_manage' );
		parent::execute();
	}

	/**
	 * Security Center
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		$content = array();
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'settings'	=> array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=security&do=settings' ),
				'title'	=> 'security_settings',
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('security_settings') )
			),
			'list_admins'	=> array(
				'icon'	=> 'key',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&filter=members_filter_administrators' ),
				'title'	=> 'security_list_admins',
			),
		);

		/* open_basedir */
		$dir = @dir( '/' );
		if ( $dir instanceof Directory )
		{
			$content[] = array(
				'title'			=> \IPS\Member::loggedIn()->language()->addToStack('open_basedir_title'),
				'description'	=> \IPS\Member::loggedIn()->language()->addToStack('open_basedir_desc'),
				'risk'			=> "high",
			);
		}		
		
		/* Htaccess Protection */
		$uploadSettings	= json_decode( \IPS\Settings::i()->upload_settings, TRUE );
		$storeSettings	= json_decode( \IPS\STORE_CONFIG, TRUE );
		if ( ( ( isset( $uploadSettings['FileSystem']['dir'] ) AND !is_file( $uploadSettings['FileSystem']['dir'] . '/.htaccess' ) ) OR
			( \IPS\STORE_METHOD == 'FileSystem' AND !is_file( str_replace( '{root}', \IPS\ROOT_PATH, $storeSettings['path'] ) . '/.htaccess' ) ) ) AND !\IPS\NO_WRITES )
		{
			$content[] = array(
				'title'			=> \IPS\Member::loggedIn()->language()->addToStack('htaccess_title'),
				'description'	=> \IPS\Member::loggedIn()->language()->addToStack('htaccess_desc'),
				'risk'			=> "high",
				'button'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('security_run_tool'), 'action' => "app=core&module=overview&controller=security&do=htaccess" ),
			);
		}
		
		/* Configuration File */
		if ( is_writeable( \IPS\ROOT_PATH . '/conf_global.php' ) AND !\IPS\NO_WRITES )
		{
			$content[] = array(
				'title'			=> \IPS\Member::loggedIn()->language()->addToStack('conf_writeable_title'),
				'description'	=> \IPS\Member::loggedIn()->language()->addToStack('conf_writeable_desc'),
				'risk'			=> "high",
				'button'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('security_run_tool'), 'action' => "app=core&module=overview&controller=security&do=conf" ),
			);
				
		}
		
		/* Disabled PHP Functions */
		$functionsToDisable = array( 'exec', 'system', 'passhtru', 'pcntl_exec', 'popen', 'proc_open', 'shell_exec' );
		$showingFunctionWarning = FALSE;
		
		foreach ( $functionsToDisable as $k => $function )
		{
			if ( function_exists( $function ) )
			{
				$showingFunctionWarning = TRUE;
			}
			else
			{
				unset( $functionsToDisable[ $k ] );
			}
		}
		
		if ( $showingFunctionWarning )
		{
			$content[] = array(
				'title'			=> \IPS\Member::loggedIn()->language()->addToStack('disable_functions_title'),
				'description'	=> \IPS\Member::loggedIn()->language()->addToStack('disable_functions_desc', FALSE, array( 'sprintf' => array( implode( ', ', $functionsToDisable ) ) ) ),
				'risk'			=> "high",
			);
		}
		
		/* Display Errors */
		if ( ini_get( 'display_errors' ) )
		{
			$content[] = array(
					'title'			=> \IPS\Member::loggedIn()->language()->addToStack('display_errors_title'),
					'description'	=> \IPS\Member::loggedIn()->language()->addToStack('display_errors_desc'),
					'risk'			=> "medium",
			);
		}
		
		/* ACP Directory Name */
		if ( \IPS\CP_DIRECTORY == 'admin' )
		{
			$content[] = array(
				'title'			=> \IPS\Member::loggedIn()->language()->addToStack('rename_admin_title'),
				'description'	=> \IPS\Member::loggedIn()->language()->addToStack('rename_admin_desc'),
				'risk'			=> "low",
				'button'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('security_learn_more'), 'action' => "app=core&module=overview&controller=security&do=renameAdmin" ),
			);
		}
		
		/* ACP Password Protection */
		if ( ! is_file( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/.htaccess' ) AND !\IPS\NO_WRITES )
		{
			$content[] = array(
					'title'			=> \IPS\Member::loggedIn()->language()->addToStack('admin_pass_title'),
					'description'	=> \IPS\Member::loggedIn()->language()->addToStack('admin_pass_desc'),
					'risk'			=> "low",
					'button'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('security_run_tool'), 'action' => "app=core&module=overview&controller=security&do=acpHtaccess" ),
			);
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('security_center');
		
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'overview' )->security( $content );
	}
	
	/**
	 * Security Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'security_remove_acp_link', !\IPS\Settings::i()->security_remove_acp_link, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'xforward_matching', \IPS\Settings::i()->xforward_matching, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'match_ipaddress', \IPS\Settings::i()->match_ipaddress, FALSE ) ); //
		
		if ( $values = $form->values() )
		{
			$values['security_remove_acp_link'] = $values['security_remove_acp_link'] ? FALSE : TRUE;
			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplogs__security_settings' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=security" ), 'saved' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('security_settings');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'storage_settings', $form );
	}
	
	/**
	 * Change conf global permissions
	 *
	 * @return	void
	 */
	protected function conf()
	{
		/* INIT */
		$done = FALSE;
	
		/* Try... */
		if ( !@chmod( \IPS\ROOT_PATH . '/conf_global.php', 0444 ) )
		{
			\IPS\Output::i()->error( 'conf_not_altered', '2C258/2', 500, '' );
		}
	
		/* All Done */
		\IPS\Session::i()->log( 'acplogs__security_conf' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=security" ), 'conf_altered' );
	}
	
	/**
	 * Add htaccess files to writeable directory
	 *
	 * @return	void
	 */
	protected function htaccess()
	{
		/* INIT */
		$errors = array();
		$deny = <<<EOF
#<ipb-protection>
<Files ~ "^.*\.(php|cgi|pl|php3|php4|php5|php6|phtml|shtml)">
    Order allow,deny
    Deny from all
</Files>
<ifModule mod_headers.c>
	Header set Content-Disposition attachment
</ifModule>
#</ipb-protection>
EOF;
		
		$uploadSettings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
		if ( isset( $uploadSettings['FileSystem']['dir'] ) )
		{
			$directory = $uploadSettings['FileSystem']['dir'];
			
			if ( !@\file_put_contents( $directory . '/.htaccess', $deny ) )
			{
				$errors[] = $directory;
			}
		}

		$storeSettings	= json_decode( \IPS\STORE_CONFIG, TRUE );
		if ( \IPS\STORE_METHOD == 'FileSystem' )
		{
			$directory = str_replace( '{root}', \IPS\ROOT_PATH, $storeSettings['path'] );
			
			if ( !@\file_put_contents( $directory . '/.htaccess', $deny ) )
			{
				$errors[] = $directory;
			}
		}

		if( count( $errors ) )
		{
			\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'htaccess_not_written', FALSE, array( 'sprintf' => \IPS\Member::loggedIn()->language()->formatList( $errors ) ) ), '2C258/1', 403, '', array(), $deny );
		}
			
		/* All Done */
		\IPS\Session::i()->log( 'acplogs__security_htaccess_writeable' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=security" ), 'htaccess_written' );
	}
	
	/**
	 * Rename ACP directory
	 *
	 * @return	void
	 */
	protected function renameAdmin()
	{
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('rename_admin_title');
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Output::i()->title );
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'overview' )->securityRenameAdmin();
	}
	
	/**
	 * ACP Htaccess Protection
	 *
	 * @return	void
	 */
	protected function acpHtaccess()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'username', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE ) );
		
		if ( $values = $form->values() )
		{
			$done = FALSE;
			$htaccess_auth = "ErrorDocument 401 \"Unauthorized\"\n"
							. "AuthType Basic\n"
							. "AuthName \"IPS Social Suite ACP\"\n"
							. "AuthUserFile " . \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/.htpasswd\n"
							. "Require valid-user\n";
			
			$htaccess_pw   = $values['username'] . ":" . 
				( ( mb_strtoupper( mb_substr( PHP_OS, 0, 3 ) ) === 'WIN' ) ? $values['password'] : crypt( $values['password'], base64_encode( $values['password'] ) ) );
			
			if ( $FH = @\fopen( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/.htpasswd', 'w' ) )
			{
				\fwrite( $FH, $htaccess_pw );
				\fclose( $FH );
					
				$FF = @\fopen( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/.htaccess', 'w' );
				\fwrite( $FF, $htaccess_auth );
				\fclose( $FF );
					
				$done = TRUE;
				
				\IPS\Session::i()->log( 'acplogs__security_acp_password' );
			}

			/* All Done */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=security" ), ( $done ) ? 'admin_pass_written' : 'admin_pass_not_written' );
		}
	
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'admin_pass_warning', 'warning' );
		
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('admin_pass_title');
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Output::i()->title, $form );
	}
}