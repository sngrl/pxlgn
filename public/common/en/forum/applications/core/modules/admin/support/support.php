<?php
/**
 * @brief		Support Wizard
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 May 2014
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
 * Support Wizard
 */
class _support extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'get_support' );
		parent::execute();
	}

	/**
	 * Support Wizard
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('get_support');
		\IPS\Output::i()->output = \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->support( new \IPS\Helpers\Wizard( array( 'type_of_problem' => array( $this, '_typeOfProblem' ), 'self_service' => array( $this, '_selfService' ), 'contact_support' => array( $this, '_contactSupport' ) ), \IPS\Http\Url::internal('app=core&module=support&controller=support') ) );
		
		\IPS\Output::i()->sidebar['actions']['systemcheck'] = array(
			'icon'	=> 'search',
			'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=support&do=systemCheck' ),
			'title'	=> 'requirements_checker',
		);
	}
	
	/**
	 * phpinfo
	 *
	 * @return	void
	 */
	protected function phpinfo()
	{
		phpinfo();
		exit;
	}
	
	/**
	 * System Check
	 *
	 * @return	void
	 */
	protected function systemCheck()
	{
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('requirements_checker');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'support' )->healthcheck( \IPS\core\Setup\Upgrade::systemRequirements() );
	}
	
	/**
	 * Step 1: Type of problem
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	string|array
	 */
	public function _typeOfProblem( $data )
	{
		$form = new \IPS\Helpers\Form( 'form', 'continue' );
		$form->class = 'ipsForm_horizontal ipsPad';
		$form->add( new \IPS\Helpers\Form\Radio( 'type_of_problem_select', NULL, TRUE, array(
			'options' 	=> array( 'advice' => 'type_of_problem_advice', 'issue' => 'type_of_problem_issue' ),
			'toggles'	=> array( 'advice' => array( 'support_advice_search' ) )
		) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'support_advice_search', NULL, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->type_of_problem_select === 'advice' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'support_advice_search' ) );
		if ( $values = $form->values() )
		{
			return array( 'type' => $values['type_of_problem_select'], 'keyword' => $values['support_advice_search'] );
		}
		return (string) $form;
	}
	
	/**
	 * Step 2: Self Service
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	string|array
	 */
	public function _selfService( $data )
	{
		/* Advice */
		if ( $data['type'] === 'advice' )
		{
			if ( isset( \IPS\Request::i()->next ) )
			{
				return $data;
			}
			
			$searchResults = array();
			if ( $data['keyword'] )
			{
				$search = new \IPS\core\extensions\core\LiveSearch\Settings;
				$searchResults = $search->getResults( $data['keyword'] );
			}
			
			$guides = array();
			try
			{
				$guides = \IPS\Http\Url::ips( 'guides/' . urlencode( $data['keyword'] ) )->request()->get()->decodeJson();
			}
			catch ( \Exception $e ) { }
			
			if ( count( $searchResults ) or count( $guides ) )
			{
				return \IPS\Theme::i()->getTemplate( 'support' )->advice( $searchResults, $guides );
			}
			else
			{
				return $data;
			}
		}
		
		/* Issue */
		else
		{			
			if ( isset( \IPS\Request::i()->serviceDone ) )
			{
				return $data;
			}
			
			$baseUrl = \IPS\Http\Url::internal('app=core&module=support&controller=support&_step=self_service');

			$overrideThingToTry = NULL;
			if ( isset( \IPS\Request::i()->next ) )
			{
				$overrideThingToTry = \IPS\Request::i()->next;
				$baseUrl = $baseUrl->setQueryString( 'next', $overrideThingToTry );
			}

			$possibleSolutions = array( '_requirementsChecker', '_databaseChecker', '_clearCaches', '_connectionChecker', '_whitespaceChecker', '_upgradeCheck', '_defaultTheme', '_disableThirdParty', '_knowledgebase' );
			$self = $this;

			return new \IPS\Helpers\MultipleRedirect(
				$baseUrl,
				function( $thingToTry ) use ( $self, $possibleSolutions, $overrideThingToTry )
				{					
					if ( !is_null( $overrideThingToTry ) and $overrideThingToTry > $thingToTry )
					{
						$thingToTry = $overrideThingToTry;
					}

					if ( isset( $possibleSolutions[ $thingToTry ] ) )
					{						
						$test = call_user_func( array( $self, $possibleSolutions[ $thingToTry ] ), $thingToTry );
						if ( is_string( $test ) )
						{
							return array( $test );
						}
						else
						{
							return array( $thingToTry + 1, \IPS\Member::loggedIn()->language()->addToStack( 'looking_for_problems' ) );
						}
					}
					else
					{
						return NULL;
					}
				},
				function() use ( $baseUrl )
				{
					\IPS\Output::i()->redirect( $baseUrl->setQueryString( 'serviceDone', 1 ) );
				}
			);
		}
	}
	
	/**
	 * Step 2: Self Service - Clear Caches
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _clearCaches( $id )
	{
		/* Clear JS Maps first */
		\IPS\Output::clearJsFiles();
		
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();
		
		return \IPS\Theme::i()->getTemplate( 'support' )->tryNow( ++$id, 'support_caches_cleared' );
	}
	
	/**
	 * Step 2: Self Service - Database Checker
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _databaseChecker( $id )
	{
		$changesToMake = array();
		$db = \IPS\Db::i();

		/* Loop Apps */
		foreach ( \IPS\Application::applications() as $app )
		{
			/* Loop the tables in the schema */
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/schema.json" ), TRUE ) as $tableName => $tableDefinition )
			{
				/* Get our local definition of this table */
				try
				{
					$localDefinition	= \IPS\Db::i()->getTableDefinition( $tableName );
					$localDefinition	= \IPS\Db::i()->normalizeDefinition( $localDefinition );
					$compareDefinition	= \IPS\Db::i()->normalizeDefinition( $tableDefinition );
					$tableDefinition	= \IPS\Db::i()->updateDefinitionIndexLengths( $tableDefinition );

					if ( $compareDefinition != $localDefinition )
					{
						/* Normalise it a little to prevent unnecessary conflicts */
						foreach ( $tableDefinition['columns'] as $k => $c )
						{
							foreach ( array( 'length', 'decimals' ) as $i )
							{
								if ( isset( $c[ $i ] ) )
								{
									$tableDefinition['columns'][ $k ][ $i ] = intval( $c[ $i ] );
								}
								else
								{
									$tableDefinition['columns'][ $k ][ $i ] = NULL;
								}
							}
							
							if ( !isset( $c['values'] ) )
							{
								$tableDefinition['columns'][ $k ]['values'] = array();
							}
							
							if ( $c['type'] === 'BIT' )
							{
								$tableDefinition['columns'][ $k ]['default'] = ( is_null($c['default']) ) ? NULL : "b'{$c['default']}'";
							}
							
							ksort( $tableDefinition['columns'][ $k ] );
						}
						
						/* Loop the columns */
						foreach ( $tableDefinition['columns'] as $columnName => $columnData )
						{
							/* If it doesn't exist in the local database, create it */
							if ( !isset( $localDefinition['columns'][ $columnName ] ) )
							{
								$changesToMake[] = "ALTER TABLE `{$db->prefix}{$db->escape_string( $tableName )}` ADD COLUMN {$db->compileColumnDefinition( $columnData )};";
							}
							/* Or if it's wrong, change it */
							elseif ( $columnData != $localDefinition['columns'][ $columnName ] )
							{
								$changesToMake[] = "ALTER TABLE `{$db->prefix}{$db->escape_string( $tableName )}` CHANGE COLUMN `{$db->escape_string( $columnName )}` {$db->compileColumnDefinition( $columnData )};";
							}
						}
						
						/* Loop the index */
						foreach ( $compareDefinition['indexes'] as $indexName => $indexData )
						{
							if ( !isset( $localDefinition['indexes'][ $indexName ] ) )
							{
								$changesToMake[] = "ALTER TABLE `{$db->prefix}{$db->escape_string( $tableName )}` {$db->buildIndex( $tableName, $tableDefinition['indexes'][ $indexName ] )};";
							}
							elseif ( $indexData != $localDefinition['indexes'][ $indexName ] )
							{
								$changesToMake[] = "ALTER IGNORE TABLE `{$db->prefix}{$db->escape_string( $tableName )}` " . ( ( $indexName == 'PRIMARY KEY' ) ? "DROP " . $indexName . ", " : "DROP INDEX `" . $db->escape_string( $indexName ) . "`, " ) . $db->buildIndex( $tableName, $tableDefinition['indexes'][ $indexName ] ) . ';';
							}
						}
					}
				}
				/* If the table doesn't exist, create it */
				catch ( \OutOfRangeException $e )
				{
					$changesToMake[] = $db->_createTableQuery( $tableDefinition );
				}
			}
		}
		
		/* Display */
		if ( $changesToMake )
		{
			if ( isset( \IPS\Request::i()->run ) )
			{
				$erroredQueries = array();
				$errors         = array();
				foreach ( $changesToMake as $query )
				{
					try
					{
						\IPS\Db::i()->query( $query );
					}
					catch ( \Exception $e )
					{
						$erroredQueries[] = $query;
						$errors[] = $e->getMessage();
					}
				}
				if ( count( $erroredQueries ) )
				{
					return \IPS\Theme::i()->getTemplate( 'support' )->databaseChecker( $id, ++$id, $erroredQueries, $errors );
				}
				else
				{
					return \IPS\Theme::i()->getTemplate( 'support' )->tryNow( ++$id, 'database_changes_made' );
				}
			}
			else
			{
				return \IPS\Theme::i()->getTemplate( 'support' )->databaseChecker( $id, ++$id, $changesToMake );
			}
		}
		elseif ( isset( \IPS\Request::i()->recheck ) )
		{
			return \IPS\Theme::i()->getTemplate( 'support' )->tryNow( ++$id, 'database_changes_made' );
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Step 2: Self Service - Whitespace Checker
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _whitespaceChecker( $id )
	{		
		$files = $this->_whitespaceCheckerIterator( \IPS\ROOT_PATH );
		if ( count( $files ) )
		{
			return \IPS\Theme::i()->getTemplate( 'support' )->whitespace( $files, $id );
		}
		return NULL;
	}
	
	/**
	 * Step 2: Whitespace Checker Iterator
	 *
	 * @param	string	$directory	Directory to look through
	 * @return	array
	 */
	public function _whitespaceCheckerIterator( $directory )
	{
		$return = array();

		if ( !in_array( $directory, array( \IPS\ROOT_PATH . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . '3rd_party', \IPS\ROOT_PATH . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'interface' . DIRECTORY_SEPARATOR . 'ckeditor' ) ) )
		{		
			foreach( new \DirectoryIterator( $directory ) as $file )
			{
				if ( mb_substr( $file, 0, 1 ) === '.' or mb_substr( $file, 0, 1 ) === '_' )
				{
					continue;
				}
				
				if ( $file->isDir() )
				{
					$return = array_merge( $return, $this->_whitespaceCheckerIterator( $file->getPathname() ) );
				}
				elseif ( mb_substr( $file, -4 ) === '.php' )
				{
					$fullPath = $file->getPathname();
					
					if ( !is_readable( $fullPath ) )
					{
						continue;
					}
					
					$contents = file_get_contents( $fullPath );
					if ( ( mb_substr( ltrim( $contents ), 0, 3 ) == '<?php' and mb_substr( $contents, 0, 3 ) != '<?php' ) or ( ( mb_substr( rtrim( $contents ), -2 ) == '?>' AND mb_substr( $contents, -2 ) != '?>' ) and ( mb_substr( rtrim( $contents ), -2 ) == '?>' AND mb_substr( $contents, -3 ) != "?>\n" ) ) )
					{
						$return[] = $fullPath;
					}
				}
			}
		}
		return $return;
	}
	
	/**
	 * Step 2: Self Service - Requirements Checker (Includes File Permissions)
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _requirementsChecker( $id )
	{		
		$check = \IPS\core\Setup\Upgrade::systemRequirements();
		foreach ( $check['requirements'] as $group => $requirements )
		{
			foreach ( $requirements as $requirement )
			{
				if ( !$requirement['success'] )
				{
					return \IPS\Theme::i()->getTemplate( 'support' )->tryNow( $id, $requirement['message'], '', FALSE );
				}
			}
		}
		
		return NULL;
	}
	
	/**
	 * Step 2: Self Service - Connection Checker
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _connectionChecker( $id )
	{	
		try
		{
			\IPS\Http\Url::ips( 'connectionCheck' )->request()->get();
			return NULL;
		}
		catch ( \Exception $e )
		{
			return \IPS\Theme::i()->getTemplate( 'support' )->connectionChecker( ++$id );
		}
	}
	
	/**
	 * Step 2: Self Service - Check for Uprade
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _upgradeCheck( $id )
	{
		try
		{
			$response = \IPS\Http\Url::ips('updateCheck')->request()->get()->decodeJson();
			if ( $response['longversion'] > \IPS\Application::load('core')->long_version )
			{
				return \IPS\Theme::i()->getTemplate( 'support' )->upgrade( ++$id, $response['updateurl'] );
			}
		}
		catch ( \Exception $e ) { }

		return NULL;
	}
	
	/**
	 * Step 2: Self Service - Instruct user to try using default theme
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _defaultTheme( $id )
	{
		if ( isset( \IPS\Request::i()->deleteTheme ) )
		{
			try
			{
				\IPS\Theme::load( \IPS\Request::i()->deleteTheme )->delete();
			}
			catch ( \Exception $e ) {}
			
			return NULL;
		}
		
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_templates', 'template_set_id>0' )->first() or \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_css', 'css_set_id>0' )->first() )
		{
			$newTheme = new \IPS\Theme;
			$newTheme->permissions = \IPS\Member::loggedIn()->member_group_id;
			$newTheme->save();
			$newTheme->installThemeSettings();
			$newTheme->copyResourcesFromSet();
			
			\IPS\Lang::saveCustom( 'core', "core_theme_set_title_" . $newTheme->id, "IPS Support" );
			
			\IPS\Member::loggedIn()->skin = $newTheme->id;

			if( \IPS\Member::loggedIn()->acp_skin !== NULL )
			{
				\IPS\Member::loggedIn()->acp_skin = $newTheme->id;
			}

			\IPS\Member::loggedIn()->save();
			
			return \IPS\Theme::i()->getTemplate( 'support' )->tryNow( $id, 'try_default_theme', '&deleteTheme=' . $newTheme->id );
		}
		
		return NULL;
	}
	
	/**
	 * Step 2: Self Service - Disable 3rd party apps/plugins
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _disableThirdParty( $id )
	{
		if ( isset( \IPS\Request::i()->enableApps ) or isset( \IPS\Request::i()->enablePlugins ) )
		{
			foreach ( explode( ',', \IPS\Request::i()->enableApps ) as $app )
			{			
				try
				{
					$app = \IPS\Application::load( $app );
					$app->enabled = TRUE;
					$app->save();
				}
				catch ( \Exception $e ) {}
			}
			
			foreach ( explode( ',', \IPS\Request::i()->enablePlugins ) as $plugin )
			{			
				try
				{
					$plugin = \IPS\Plugin::load( $plugin );
					$plugin->enabled = TRUE;
					$plugin->save();
				}
				catch ( \Exception $e ) {}
			}
			
			return NULL;
		}
		
		$disabledApps = array();
		$disabledPlugins = array();
		
		/* Loop Apps */
		foreach ( \IPS\Application::applications() as $app )
		{
			if ( $app->enabled and !in_array( $app->directory, \IPS\Application::$ipsApps ) )
			{
				$app->enabled = FALSE;
				$app->save();
				
				$disabledApps[] = $app->directory;
			}
		}
		
		/* Look Plugins */
		foreach ( \IPS\Plugin::plugins() as $plugin )
		{
			if ( $plugin->enabled )
			{
				$plugin->enabled = FALSE;
				$plugin->save();
				
				$disabledPlugins[] = $plugin->id;
			}
		}
		
		/* Do any? */
		if ( count( $disabledApps ) or count( $disabledPlugins ) )
		{
			return \IPS\Theme::i()->getTemplate( 'support' )->tryNow( $id, 'third_party_stuff_disabled', '&enableApps=' . implode( ',', $disabledApps ) . '&enablePlugins=' . implode( ',', $disabledPlugins ) );
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Step 2: Self Service - Knowledgebase
	 *
	 * @param	int	$id	The current ID
	 * @return	string|NULL
	 */
	public function _knowledgebase( $id )
	{
		$kb = array();
		try
		{
			$kb = \IPS\Http\Url::ips('kb')->request()->get()->decodeJson();
		}
		catch ( \Exception $e ) { }
		
		if ( count( $kb ) )
		{
			return \IPS\Theme::i()->getTemplate( 'support' )->knowledgebase( ++$id, $kb );
		}
		return NULL;
	}
	
	/**
	 * Step 3: Contact Support
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	string|array
	 */
	public function _contactSupport( $data )
	{
		$licenseData = \IPS\IPS::licenseKey();
		if ( !$licenseData or strtotime( $licenseData['expires'] ) < time() )
		{
			return \IPS\Theme::i()->getTemplate( 'global' )->message( 'get_support_no_license', 'warning' );
		}
		
		try
		{
			$supportedVerions = \IPS\Http\Url::ips('support/versions')->request()->get()->decodeJson();
			
			if ( \IPS\Application::load('core')->long_version > $supportedVerions['max'] )
			{
				return \IPS\Theme::i()->getTemplate( 'global' )->message( 'get_support_unsupported_prerelease', 'warning' );
			}
			if ( \IPS\Application::load('core')->long_version < $supportedVerions['min'] )
			{
				return \IPS\Theme::i()->getTemplate( 'global' )->message( 'get_support_unsupported_obsolete', 'warning' );
			}
		}
		catch ( \Exception $e ) {}
		
		$form = new \IPS\Helpers\Form( 'contact_support', 'contact_support' );
		$form->class = 'ipsForm_vertical ipsPad';
		$form->add( new \IPS\Helpers\Form\Text( 'support_request_title', NULL, TRUE, array( 'maxLength' => 128 ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'support_request_body', NULL, TRUE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'acp-support-request' ) ) );
		if ( $values = $form->values() )
		{
			$key = md5( \IPS\Http\Url::internal('app=core&module=support&controller=support') );
			unset( $_SESSION["wizard-{$key}-step"] );
			unset( $_SESSION["wizard-{$key}-data"] );
			
			$response = \IPS\Http\Url::ips('support')->request()->login( \IPS\Settings::i()->ipb_reg_number, '' )->post( array(
				'title'		=> $values['support_request_title'],
				'message'	=> $values['support_request_body']
			) );
			
			switch ( $response->httpResponseCode )
			{
				case 200:
				case 201:
					return \IPS\Theme::i()->getTemplate( 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack( 'get_support_done', FALSE, array( 'pluralize' => array( intval( (string) $response ) ) ) ), 'success' );
				
				case 401:
				case 403:
					return \IPS\Theme::i()->getTemplate( 'global' )->message( 'get_support_no_license', 'warning' );
				
				case 429:
					return \IPS\Theme::i()->getTemplate( 'global' )->message( 'get_support_duplicate', 'error' );
				
				case 502:
				default:
					return \IPS\Theme::i()->getTemplate( 'global' )->message( 'get_support_error', 'error' );
			}
		}
		return (string) $form;
	}
}