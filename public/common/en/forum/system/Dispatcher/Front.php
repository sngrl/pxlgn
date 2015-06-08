<?php
/**
 * @brief		Front-end Dispatcher
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front-end Dispatcher
 */
class _Front extends \IPS\Dispatcher\Standard
{
	/**
	 * Controller Location
	 */
	public $controllerLocation = 'front';
	
	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{		
		/* Get cached page if available */
		$this->checkCached();
		
		/* Perform some legacy URL conversions*/
		static::convertLegacyParameters();
		
		/* Sync stuff when in developer mode */
		if ( \IPS\IN_DEV )
		{
			 \IPS\Developer::sync();
		}
		
		/* Base CSS */
		static::baseCss();

		/* Base JS */
		static::baseJs();

		/* FURLs only apply when calling to index.php */
		$_calledScript	= str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] );
		$_scriptParts	= explode( '/', $_calledScript );
		array_pop( $_scriptParts );
		$_calledScript	= implode( '/', $_scriptParts );

		/* Handle friendly URLs */
		if ( \IPS\Settings::i()->use_friendly_urls and $_calledScript == str_replace( '\\', '/', \IPS\ROOT_PATH ) )
		{
			try
			{
				try
				{
					foreach ( \IPS\Request::i()->url()->getFriendlyUrlData( \IPS\Settings::i()->seo_r_on and !\IPS\Request::i()->isAjax() and mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and !\IPS\ENFORCE_ACCESS ) as $k => $v )
					{
						if( $k == 'module' )
						{
							$this->_module	= NULL;
						}
						else if( $k == 'controller' )
						{
							$this->_controller	= NULL;
						}
								
						\IPS\Request::i()->$k = $v;
					}
				}
				catch ( \OutOfRangeException $e )
				{
					if( \IPS\Settings::i()->seo_r_on and !\IPS\Request::i()->isAjax() and mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and !\IPS\ENFORCE_ACCESS )
					{
						$defaultApplication = \IPS\Db::i()->select( 'app_directory', 'core_applications', 'app_default=1' )->first();
						$furlDefinitionFile = \IPS\ROOT_PATH . "/applications/{$defaultApplication}/data/furl.json";
						if ( file_exists( $furlDefinitionFile ) )
						{
							$furlDefinition = json_decode( preg_replace( '/\/\*.+?\*\//s', '', file_get_contents( $furlDefinitionFile ) ), TRUE );
							if ( isset( $furlDefinition['topLevel'] ) and $furlDefinition['topLevel'] )
							{
								$baseUrl = parse_url( \IPS\Settings::i()->base_url );
								$url = \IPS\Request::i()->url();
								$query = \IPS\Settings::i()->htaccess_mod_rewrite ? ( isset( $url->data['path'] ) ? $url->data['path'] : '' ) : ( isset( $url->data['query'] ) ? ltrim( $url->data['query'], '/' )  : '' );
								$query = preg_replace( '#^(' . preg_quote( rtrim( $baseUrl['path'], '/' ), '#' ) . ')/(index.php)?(?:(?:\?/|\?))?(.+?)?$#', '$3', $query );
								
								if ( mb_substr( $query, 0, mb_strlen( $furlDefinition['topLevel'] ) ) === $furlDefinition['topLevel'] )
								{
									$target = preg_replace( '/(' . preg_quote( \IPS\Settings::i()->base_url, '/' ) . '(index.php\?\/)?)(' . preg_quote( $furlDefinition['topLevel'], '/' ) . ')\/?/', '$1', (string) $url );

									\IPS\Output::i()->redirect( new \IPS\Http\Url( $target ) );
								}
							}
						}
					}
					
					if ( !\IPS\Request::i()->isAjax() )
					{
						throw $e;
					}
				}
				catch ( \DomainException $e )
				{
					\IPS\Output::i()->redirect( $e->getMessage() );
				}
			}
			catch ( \Exception $e )
			{
				$this->application = \IPS\Application::load('core');
				
				if ( \IPS\Member::loggedIn()->isBanned() )
				{
					\IPS\Output::i()->sidebar = FALSE;
					\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
				}
				
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'app.js' ) );
				
				\IPS\Output::i()->error( 'requested_route_404', '1S160/2', 404, '' );
			}
		}

		/* Run global init */
		try
		{
			parent::init();
		}
		catch ( \DomainException $e )
		{	
			// If this is a "no permission", and they're validating - show the validating screen instead
			if( $e->getCode() === 6 and \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->members_bitoptions['validating'] )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=validating', 'front', 'register' ) );
			}
			// Otherwise show the error
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '2S100/' . $e->getCode(), $e->getCode() === 4 ? 403 : 404, '' );
			}
		}
		
		/* Enable sidebar by default (controllers can turn it off if needed) */
		\IPS\Output::i()->sidebar['enabled'] = ( \IPS\Request::i()->isAjax() ) ? FALSE : TRUE;
		
		/* Are we online? */
		if ( !( $this->application->directory == 'core' and $this->module->key == 'system' and ( $this->controller == 'login' /* Because you can login when offline */ or $this->controller == 'embed' /* Because the offline message can contain embedded media */ or $this->controller == 'lostpass' or $this->controller == 'register' ) ) AND !\IPS\Settings::i()->site_online AND $this->controllerLocation == 'front' AND !\IPS\Member::loggedIn()->group['g_access_offline'] )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( \IPS\Member::loggedIn()->language()->addToStack( 'offline_unavailable', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->board_name ) ) ), 503 );
			}
			
			\IPS\Output::i()->showOffline();
		}
		
		/* Member Ban? */
		if ( $banEnd = \IPS\Member::loggedIn()->isBanned() )
		{
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				if ( $this->notAllowedBannedPage() )
				{
					$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' );
					if ( \IPS\Request::i()->url() != \IPS\Settings::i()->base_url )
					{
						$url = $url->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
					}
					\IPS\Output::i()->redirect( $url );
				}
			}
			else
			{

				\IPS\Output::i()->sidebar = FALSE;
				\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
				if( $this->controller !== 'contact' )
				{
					$message = 'member_banned';
					if ( $banEnd instanceof \IPS\DateTime )
					{
						$message = \IPS\Member::loggedIn()->language()->addToStack( 'member_banned_temp', FALSE, array( 'htmlsprintf' => array( $banEnd->html() ) ) );
					}
					\IPS\Output::i()->error( $message, '2S160/4', 403, '' );
				}
			}
		}
		
		/* Do we need more info from the member or do they need to validate? */
		if( \IPS\Member::loggedIn()->member_id )
		{
			if( ( !\IPS\Member::loggedIn()->real_name or !\IPS\Member::loggedIn()->email ) and $this->controller !== 'register' )
			{ 
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=complete' ) );
			}
			elseif( \IPS\Member::loggedIn()->members_bitoptions['validating'] and $this->controller !== 'register' and $this->controller !== 'login' and $this->controller != 'redirect' and $this->controller !== 'contact' )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=validating' ) );
			}
		}
		
		/* Permission Check */
		if ( !\IPS\Member::loggedIn()->canAccessModule( $this->module ) )
		{
			\IPS\Output::i()->error( ( \IPS\Member::loggedIn()->member_id ? 'no_module_permission' : 'no_module_permission_guest' ), '2S100/2', 403, 'no_module_permission_admin' );
		}
		
		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			/* Base Navigation. We only add the module not the app as most apps don't have a global base (for example, in Nexus, you want "Store" or "Client Area" to be the base). Apps can override themselves in their controllers. */
			foreach( \IPS\Application::applications() as $directory => $application )
			{
				if( $application->default )
				{
					$defaultApplication	= $directory;
					break;
				}
			}

			if( !isset( $defaultApplication ) )
			{
				$defaultApplication = 'core';
			}
			
			if ( $this->module->key != 'system' AND $this->application->directory != $defaultApplication )
			{
				\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=' . $this->application->directory . '&module=' . $this->module->key . '&controller=' . $this->module->default_controller, 'front', $this->module->key ), $this->module->_title );
			}
		}
	}

	/**
	 * Define that the page should load even if the user is banned and not logged in
	 *
	 * @return	bool
	 */
	protected function notAllowedBannedPage()
	{
		return ( !\IPS\Member::loggedIn()->group['g_view_board'] and !( $this->application->directory == 'core' and ( ( $this->module->key == 'system' and in_array( $this->controller, array( 'login', 'register', 'lostpass', 'terms', 'ajax', 'privacy', 'editor', 'language', 'theme' ) ) ) or ( $this->module->key == 'contact' AND $this->controller == 'contact' ) ) ) );
	}

	/**
	 * Check cache for this page
	 *
	 * @return	void
	 */
	protected function checkCached()
	{
		/* If this is a guest and there's a full cached page, we can serve that */
		if( mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and !\IPS\Session\Front::loggedIn() and !isset( \IPS\Request::i()->cookie['noCache'] ) )
		{
			$_key	= 'page_' . md5( $_SERVER['REQUEST_URI'] );
			$cache	= ( isset( \IPS\Data\Cache::i()->$_key ) ) ? \IPS\Data\Cache::i()->$_key : NULL;

			if( $cache )
			{
				$_data	= json_decode( $cache, TRUE );
				
				if( count( $_data ) )
				{
					/* Is it expired? */
					if( $_data['expires'] AND time() < $_data['expires'] )
					{
						/* Get the cache and output it */
						if( $_data['output'] )
						{
							\IPS\Output::i()->sendOutput( str_replace( '{{csrfKey}}', \IPS\Session::i()->csrfKey, $_data['output'] ), $_data['code'], $_data['contentType'], $_data['httpHeaders'], FALSE, TRUE );
						}
					}
					else
					{
						unset( \IPS\Data\Cache::i()->$_key );
					}
				}
			}
		}
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public static function convertLegacyParameters()
	{
		/* Convert &section= to &controller= */
		if ( isset( \IPS\Request::i()->section ) AND !isset( \IPS\Request::i()->controller ) )
		{
			\IPS\Request::i()->controller = \IPS\Request::i()->section;
		}

		/* Convert &showtopic= */
		if ( isset( \IPS\Request::i()->showtopic ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=forums&module=forums&controller=topic&id=' . \IPS\Request::i()->showtopic ) );
		}

		/* Convert &showforum= */
		if ( isset( \IPS\Request::i()->showforum ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=forums&module=forums&controller=forums&id=' . \IPS\Request::i()->showforum ) );
		}

		/* Convert &showuser= */
		if ( isset( \IPS\Request::i()->showuser ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=profile&id=' . \IPS\Request::i()->showuser ) );
		}
	}

	/**
	 * Finish
	 *
	 * @return	void
	 */
	public function finish()
	{
		/* Sidebar Widgets */
		if( !\IPS\Request::i()->isAjax() )
		{
			$widgets = array();
			
			if ( ! isset( \IPS\Output::i()->sidebar['widgets'] ) OR ! is_array( \IPS\Output::i()->sidebar['widgets'] ) )
			{
				\IPS\Output::i()->sidebar['widgets'] = array();
			}
			
			try
			{
				$widgetConfig = \IPS\Db::i()->select( '*', 'core_widget_areas', array( 'app=? AND module=? AND controller=?', $this->application->directory, $this->module->key, $this->controller ) );
				foreach( $widgetConfig as $area )
				{
					$widgets[$area['area']] = json_decode( $area['widgets'], TRUE );
				}
			}
			catch ( \UnderflowException $e ) {}
				
			if( !count( $widgets ) )
			{
				foreach( \IPS\Widget::appDefaults( $this->application ) as $widget )
				{
					/* If another app has already defined this area, don't overwrite it */
					if ( isset( \IPS\Output::i()->sidebar['widgets'][ $widget['default_area'] ] ) )
					{
						continue;
					}

					$widget['unique']	= $widget['key'];
					
					$widgets[$widget['default_area']][] = $widget;
				}
			}
			
			if( count( $widgets ) )
			{
				foreach ( $widgets as $areaKey => $area )
				{
					foreach ( $area as $widget )
					{
						try
						{
							$_widget = \IPS\Widget::load( isset( $widget['plugin'] ) ? \IPS\Plugin::load( $widget['plugin'] ) : \IPS\Application::load( $widget['app'] ), $widget['key'], ( ! empty($widget['unique'] ) ? $widget['unique'] : uniqid() ), ( isset( $widget['configuration'] ) ) ? $widget['configuration'] : array(), ( isset( $widget['restrict'] ) ? $widget['restrict'] : null ), ( $areaKey == 'sidebar' ) ? 'vertical' : 'horizontal' );
							
							/* @note - we always need to send widget to output, otherwise if you try to manage the page later on you can't remove the widget */
							//if( trim( $_widget ) !== '' )
							//{
								\IPS\Output::i()->sidebar['widgets'][ $areaKey ][] = $_widget;
							//}						
						}
						catch ( \Exception $e )
						{
							\IPS\Log::i( LOG_ERR )->write( $e->getMessage() );
						}
					}
				}
			}
		}
		
		/* Meta tags */
		\IPS\Output::i()->buildMetaTags();
		
		/* Finish */
		parent::finish();
	}

	/**
	 * Output the basic javascript files every page needs
	 *
	 * @return void
	 */
	protected static function baseJs()
	{
		parent::baseJs();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->globalControllers[] = 'core.front.core.app';
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front.js' ) );

			if ( \IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] AND ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) AND \IPS\Request::i()->cookie['vseThemeId'] ) and \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_vse.js', 'core', 'front' ) );
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'vse/vsedata.js', 'core', 'interface' ) );
				\IPS\Output::i()->globalControllers[] = 'core.front.vse.window';
			}

			/* Can we edit widget layouts? */
			if( \IPS\Member::loggedIn()->modPermission('can_manage_sidebar') )
			{
				\IPS\Output::i()->globalControllers[] = 'core.front.widgets.manager';
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_widgets.js', 'core', 'front' ) );
			}

			/* Are we editing meta tags? */
			if( isset( $_SESSION['live_meta_tags'] ) and $_SESSION['live_meta_tags'] and \IPS\Member::loggedIn()->isAdmin() )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_system.js', 'core', 'front' ) );
			}
		}
	}

	/**
	 * Base CSS
	 *
	 * @return	void
	 */
	public static function baseCss()
	{
		parent::baseCss();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core.css', 'core', 'front' ) );
			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core_responsive.css', 'core', 'front' ) );
			}
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'flags.css', 'core', 'global' ) );
			
			if ( \IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] AND ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) AND \IPS\Request::i()->cookie['vseThemeId'] ) and \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/vse.css', 'core', 'front' ) );
			}

			/* Are we editing meta tags? */
			if( isset( $_SESSION['live_meta_tags'] ) and $_SESSION['live_meta_tags'] and \IPS\Member::loggedIn()->isAdmin() )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/meta_tags.css', 'core', 'front' ) );
			}
			
			/* Query log? */
			if ( \IPS\QUERY_LOG )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/query_log.css', 'core', 'front' ) );
			}
			if ( \IPS\CACHING_LOG )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/caching_log.css', 'core', 'front' ) );
			}
		}
	}
}