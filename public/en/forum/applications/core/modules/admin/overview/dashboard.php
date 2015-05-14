<?php
/**
 * @brief		ACP Dashboard
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		2 July 2013
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
 * ACP Dashboard
 */
class _dashboard extends \IPS\Dispatcher\Controller
{
	/**
	 * Show the ACP dashboard
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_dashboard.js', 'core') );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/dashboard.css', 'core', 'admin' ) );

		/* Figure out which blocks we should show */
		$toShow	= $this->current( TRUE );
		
		/* Now grab dashboard extensions */
		$blocks	= array();
		$info	= array();
		foreach ( \IPS\Application::allExtensions( 'core', 'Dashboard', TRUE, 'core' ) as $key => $extension )
		{
			$info[ $key ]	= array(
				'name'	=> \IPS\Member::loggedIn()->language()->addToStack('block_' . $key ),
				'key'	=> $key,
				'app'	=> \substr( $key, 0, \strpos( $key, '_' ) )
			);

			if( method_exists( $extension, 'getBlock' ) )
			{
				foreach( $toShow as $row )
				{
					if( in_array( $key, $row ) )
					{
						$blocks[ $key ]	= $extension->getBlock();
						break;
					}
				}
			}
		}
		
		/* ACP Bulletin */
		$bulletin = isset( \IPS\Data\Store::i()->acpBulletin ) ? \IPS\Data\Store::i()->acpBulletin : NULL;
		if ( !$bulletin or $bulletin['time'] < ( time() - 86400 ) )
		{
			try
			{
				$bulletins = \IPS\Http\Url::ips('bulletin')->request()->get()->decodeJson();
				\IPS\Data\Store::i()->acpBulletin = array(
					'time'		=> time(),
					'content'	=> $bulletins
				);
			}
			catch( \RuntimeException $e )
			{
				$bulletins = array();
			}
		}
		else
		{
			$bulletins = $bulletin['content'];
		}
		foreach ( $bulletins as $k => $data )
		{
			if ( count( $data['files'] ) )
			{
				$skip = TRUE;
				foreach ( $data['files'] as $file )
				{
					if ( filemtime( \IPS\ROOT_PATH . '/' . $file ) < $data['timestamp'] )
					{
						$skip = FALSE;
					}
				}
				if ( $skip )
				{
					unset( $bulletins[ $k ] );
				}
			}
		}

		/* Warnings */
		$warnings = array();

		$tasks = \IPS\Db::i()->select( '*', 'core_tasks', 'lock_count >= 3' );

		$keys = array();
		foreach( $tasks as $task )
		{
			$keys[] = $task['key'];
		}

		if ( !empty( $keys ) )
		{
			$warnings[] = array(
				'title' => \IPS\Member::loggedIn()->language()->addToStack( 'dashboard_tasks_broken' ),
				'description' => \IPS\Member::loggedIn()->language()->addToStack( 'dashboard_tasks_broken_desc', TRUE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $keys ) ) ) )
			);
		}

		/* Get new core update available data */
		$system	= \IPS\Application::load( 'core' );
		$update	= array();
		if( $system->update_version )
		{
			$data = json_decode( $system->update_version, TRUE );

			if( !empty($data['longversion']) AND $data['longversion'] > $system->long_version )
			{
				if( $data['released'] AND intval($data['released']) == $data['released'] AND \strlen($data['released']) == 10 )
				{
					$data['released']	= (string) \IPS\DateTime::ts( $data['released'] )->localeDate();
				}

				$update	= $data;
			}
		}
		
		/* Don't show the ACP header bar */
		\IPS\Output::i()->hiddenElements[] = 'acpHeader';

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('dashboard');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'dashboard' )->dashboard( $update, $toShow, $blocks, $info, $bulletins, $warnings );
	}

	/**
	 * Return a json-encoded array of the current blocks to show
	 *
	 * @param	bool	$return	Flag to indicate if the array should be returned instead of output
	 * @return	void
	 */
	public function current( $return=FALSE )
	{
		$dbKey	= "dashboard_blocks_" . \IPS\Member::loggedIn()->member_id;
		$toShow	= isset( \IPS\Data\Store::i()->$dbKey ) ? \IPS\Data\Store::i()->$dbKey : array();

		if( !$toShow OR !isset( $toShow['main'] ) OR !isset( $toShow['side'] ) )
		{
			$toShow	= array(
				'main' => array( 'core_BackgroundQueue', 'core_Registrations' ),
				'side' => array( 'core_AdminNotes', 'core_OnlineUsers' ),
			);

			\IPS\Data\Store::i()->$dbKey	= $toShow;
		}

		if( $return === TRUE )
		{
			return $toShow;
		}

		\IPS\Output::i()->output		= json_encode( $toShow );
	}

	/**
	 * Return an individual block's HTML
	 *
	 * @return	void
	 */
	public function getBlock()
	{
		$output		= '';

		/* Loop through the dashboard extensions in the specified application */
		foreach( \IPS\Application::load( \IPS\Request::i()->appKey )->extensions( 'core', 'Dashboard', 'core' ) as $key => $_extension )
		{
			if( \IPS\Request::i()->appKey . '_' . $key == \IPS\Request::i()->blockKey )
			{
				if( method_exists( $_extension, 'getBlock' ) )
				{
					$output	= $_extension->getBlock();
				}

				break;
			}
		}

		\IPS\Output::i()->output	= $output;
	}

	/**
	 * Update our current block configuration/order
	 *
	 * @return	void
	 * @note	When submitted via AJAX, the array should be json-encoded
	 */
	public function update()
	{
		$dbKey	= "dashboard_blocks_" . \IPS\Member::loggedIn()->member_id;
		$blocks = \IPS\Request::i()->blocks;
		
		if( !isset( $blocks['main'] ) )
		{
			$blocks['main'] = array();
		}
		if( !isset( $blocks['side'] ) )
		{
			$blocks['side'] = array();
		}
		
		\IPS\Data\Store::i()->$dbKey	= $blocks;

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = 1;
			return;
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=dashboard" ), 'saved' );
	}
	
	/**
	 * Download latest version
	 *
	 * @return	void
	 */
	public function upgrade()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('ips_suite_upgrade');

		$form = new \IPS\Helpers\Form( 'download', 'download' );
		$form->addMessage('download_upgrade_blurb');
		$form->add( new \IPS\Helpers\Form\Email( 'ips_email_address', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'ips_password', NULL, TRUE ) );
		if ( $values = $form->values() )
		{
			$key = \IPS\IPS::licenseKey();
						
			$url = \IPS\Http\Url::ips( 'build/' . $key['key'] )->setQueryString( 'ip', \IPS\Request::i()->ipAddress() );
			if ( \IPS\USE_DEVELOPMENT_BUILDS )
			{
				$url = $url->setQueryString( 'development', 1 );
			}
			if ( \IPS\CP_DIRECTORY !== 'admin' )
			{
				$url = $url->setQueryString( 'cp_directory', \IPS\CP_DIRECTORY );
			}
			
			try
			{	
				$response = $url->request( 30 )->login( $values['ips_email_address'], $values['ips_password'] )->get();
				switch ( $response->httpResponseCode )
				{
					case 200:
						if ( !preg_match( '/^ips_[a-z0-9]{5}$/', (string) $response ) )
						{
							\IPS\Log::i( LOG_DEBUG )->write( (string) $response );
							$form->error = \IPS\Member::loggedIn()->language()->addToStack('download_upgrade_error');
							break;
						}
						else
						{
							\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'overview' )->deltaUpgradeInstructions( \IPS\Http\Url::ips( 'download/' . $response ), $response );
							return;
						}
					
					case 304:
						if ( \IPS\Db::i()->select( 'MIN(app_long_version)', 'core_applications', \IPS\Db::i()->in( 'app_directory', \IPS\Application::$ipsApps ) )->first() < \IPS\Application::getAvailableVersion('core') )
						{
							\IPS\Output::i()->redirect( 'upgrade' );
						}
						$form->error = \IPS\Member::loggedIn()->language()->addToStack('download_upgrade_nothing');
						break;
					
					default:
						$form->error = (string) $response;
				}
				
			}
			catch ( \Exception $exception )
			{
				\IPS\Log::i( LOG_DEBUG )->write( get_class( $exception ) . "\n" . $exception->getCode() . ": " . $exception->getMessage() . "\n" . $exception->getTraceAsString() );
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('download_upgrade_error');
			}
		}
		
		\IPS\Output::i()->output = $form;
	}
}