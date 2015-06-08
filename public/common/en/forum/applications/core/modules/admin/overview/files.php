<?php
/**
 * @brief		File Settings
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		06 May 2013
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
 * File Settings
 */
class _files extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		parent::execute();
	}

	/**
	 * Manage Attachment Types
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_view' );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('uploaded_files');
		
		\IPS\Output::i()->sidebar['actions'] = array();

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'files_settings' ) )
		{
			\IPS\Output::i()->sidebar['actions']['settings'] = array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings' ),
				'title'	=> 'storage_settings',
			);

			\IPS\Output::i()->sidebar['actions']['images'] = array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=imagesettings' ),
				'title'	=> 'image_settings',
			);
		}

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'orphaned_files' ) )
		{
			\IPS\Output::i()->sidebar['actions']['orphaned'] = array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=orphaned' ),
				'title'	=> 'orphaned_files',
				'data'	=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('orphaned_files_confirm') )
			);
		}
		
		$table = new \IPS\Helpers\Table\Db( 'core_attachments', \IPS\Http\Url::internal( 'app=core&module=overview&controller=files' ) );
		$table->filters = array(
			'images'	=> "attach_is_image=1",
			'files'		=> "attach_is_image=0",
		);
		$table->include = array( 'attach_file', 'attach_filesize', 'attach_date', 'attach_member_id' );
		if ( $table->filter !== 'images' )
		{
			$table->include[] = 'attach_hits';
		}
		$table->noSort = array( 'attach_type' );
		$table->quickSearch = 'attach_file';
		$table->parsers = array(
			'attach_file'	=> function( $val, $row ) use ( $table )
			{
				$url = \IPS\Http\Url::external( $row['attach_location'] )->makeSafeForAcp( $row['attach_is_image'] );
				if ( $row['attach_is_image'] and $table->filter === 'images' )
				{
					return "<a href='{$url}' target='_blank'><img src='{$url}' style='max-height:200px'></a>";
				}

				$val = htmlentities( $val, \IPS\HTMLENTITIES, 'UTF-8', FALSE );
				
				return "<a href='{$url}' target='_blank'>{$val}</a>";
			},
			'attach_filesize' => function( $val )
			{
				if ( $val < 1024 )
				{
					return "{$val}B";
				}
				elseif ( $val < 1048576 )
				{
					return round( ( $val / 1024 ), 2 ) . 'KB';
				}
				elseif ( $val < 1073741824 )
				{
					return round( ( $val / 1048576 ), 2 ) . 'MB';
				}
				else
				{
					return round( ( $val / 1073741824 ), 2 ) . 'GB';
				}
			},
			'attach_date' => function( $val )
			{
				return \IPS\DateTime::ts( $val );
			},
			'attach_hits' => function( $val, $row )
			{
				return $row['attach_is_image'] ? '' : $val;
			},
			'attach_member_id' => function( $val )
			{
				return "<a href='" . \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=edit&id=' . $val ) . "'>" . htmlentities( \IPS\Member::load( $val )->name, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) . '</a>';
			}
		);
		$table->advancedSearch = array(
			'attach_file'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'attach_ext'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'attach_hits'		=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			'attach_date'		=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'attach_member_id'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
			'attach_filesize'	=> \IPS\Helpers\Table\SEARCH_NUMERIC,
		);
		$table->rowButtons = function( $row )
		{
			$buttons = array();
			$buttons['view'] = array(
				'icon'	=> 'search',
				'title'	=> 'attach_view_locations',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=lookup&id={$row['attach_id']}" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => $row['attach_file'] )
			);
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'files_delete' ) )
			{
				$buttons['delete'] = array(
					'icon'	=> 'times-circle',
					'title'	=> 'delete',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=delete&id={$row['attach_id']}" ),
					'data'		=> array( 'delete' => '' ),
				);
			}
			
			return $buttons;
		};
		
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Lookup attachment locations
	 *
	 * @return	void
	 */
	public function lookup()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_view' );
		
		$loadedExtensions = array();
		$locations = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_attachments_map', array( 'attachment_id=?', intval( \IPS\Request::i()->id ) ) ) as $map )
		{
			if ( !isset( $loadedExtensions[ $map['location_key'] ] ) )
			{
				$exploded = explode( '_', $map['location_key'] );
				try
				{
					$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'EditorLocations' );
					if ( isset( $extensions[ $exploded[1] ] ) )
					{
						$loadedExtensions[ $map['location_key'] ] = $extensions[ $exploded[1] ];
					}
				}
				catch ( \OutOfRangeException $e ){ }
			}
			
			if ( isset( $loadedExtensions[ $map['location_key'] ] ) )
			{
				try
				{
					if ( $url = $loadedExtensions[ $map['location_key'] ]->attachmentLookup( $map['id1'], $map['id2'], $map['id3'] ) )
					{
						$locations[] = $url;
					}
				}
				catch ( \LogicException $e ) { }
			}
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( NULL, \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->attachmentLocations( $locations, FALSE ) );
	}
	
	/**
	 * Delete attachment
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_delete' );
		
		try
		{
			$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', \IPS\Request::i()->id ) )->first();
			
			try
			{
				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
				\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
			}
			catch ( \Exception $e ) { }
			
			\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', \IPS\Request::i()->id ) );
			
			\IPS\Session::i()->log( 'acplogs__file_deleted', array( $attachment['attach_file'] => FALSE ) );
		}
		catch ( \UnderflowException $e ) { }
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=files" ) );
	}

	/**
	 * Image Settings
	 * 
	 * @return	void
	 */
	protected function imagesettings()
	{
		/* Init */
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_settings' );

		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\Radio( 'image_suite', \IPS\Settings::i()->image_suite, TRUE, array(
			'options' => array( 'gd' => 'imagesuite_gd', 'imagemagick' => 'imagesuite_imagemagick' ),
			'toggles' => array( 'imagemagick' => array(), 'gd' => array( 'image_jpg_quality', 'image_png_quality_gd' ) ),
			'disabled'=> class_exists( 'Imagick', FALSE ) ? array() : array( 'imagemagick' )
		) ) );

		$form->add( new \IPS\Helpers\Form\Number( 'image_jpg_quality', \IPS\Settings::i()->image_jpg_quality, FALSE, array( 'min' => 0, 'max' => 100, 'range' => TRUE ), NULL, NULL, NULL, 'image_jpg_quality' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'image_png_quality_gd', \IPS\Settings::i()->image_png_quality_gd, FALSE, array( 'min' => 0, 'max' => 9, 'range' => TRUE ), NULL, NULL, NULL, 'image_png_quality_gd' ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings();

			\IPS\Session::i()->log( 'acplogs__image_settings_updated' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=imagesettings' ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('image_settings');
		\IPS\Output::i()->output = $form;
	}

	/**
	 * Upload Settings
	 * 
	 * @return	void
	 */
	protected function settings()
	{
		/* Init */
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_settings' );
		$activeTab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : 'settings';
				
		/* Settings form */
		if ( $activeTab === 'settings' )
		{
			$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
						
			$configurations = array();
			foreach ( \IPS\Db::i()->select( '*', 'core_file_storage' ) as $row )
			{
				$classname = 'IPS\File\\' . $row['method'];
				$configurations[ $row['id'] ] = $classname::displayName( json_decode( $row['configuration'], TRUE ) );
			}
			
			$form = new \IPS\Helpers\Form;
			$form->addMessage( 'filestorage_move_info' );
			foreach ( \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE, NULL, NULL, TRUE, FALSE ) as $name => $obj )
			{
				$disabled = ( isset( $settings[ "filestorage__{$name}" ] ) and is_array( $settings[ "filestorage__{$name}" ] ) ) ? TRUE : FALSE;
				$form->add( new \IPS\Helpers\Form\Select( 'filestorage__' . $name, isset( $settings[ "filestorage__{$name}" ] ) ? $settings[ "filestorage__{$name}" ] : NULL, TRUE, array( 'options' => $configurations, 'disabled' => $disabled ) ) );
				
				if ( $disabled )
				{
					\IPS\Member::loggedIn()->language()->words[ 'filestorage__' . $name . '_warning' ] = \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_move_in_progress' );
				}
			}
						
			if ( $values = $form->values() )
			{
				$rebuild = FALSE;

                /* Queue theme first */
                if( isset( $values['filestorage__core_Theme'] ) and $settings['filestorage__core_Theme'] != $values['filestorage__core_Theme'] )
                {
                    $rebuild = TRUE;
                    $extension = new \IPS\core\extensions\core\FileStorage\Theme;
                    
                    \IPS\Task::queue( 'core', 'MoveFiles', array( 'storageExtension' => 'filestorage__core_Theme', 'oldConfiguration' => $settings[ 'filestorage__core_Theme' ], 'newConfiguration' => $values[ 'filestorage__core_Theme' ], 'count' => $extension->count() ), 1 );
					
					/* Add to allowed storage methods so when moving files, we can accept old config or new config if move is in progress. Important: order is array(x, y) x is the new location (pos 0), y is the old location (pos 1)*/
					$values['filestorage__core_Theme'] = array( $values['filestorage__core_Theme'], $settings['filestorage__core_Theme'] );
                }

				foreach ( $values as $k => $v )
				{
					if ( isset( $settings[$k] ) )
					{
						if ( $settings[ $k ] != $v and $k != 'filestorage__core_Theme' )
						{
							$rebuild = TRUE;
							
							$exploded = explode( '_', $k );
							$classname = "IPS\\{$exploded[2]}\\extensions\\core\\FileStorage\\{$exploded[3]}";
							$extension = new $classname;
							
							\IPS\Task::queue( 'core', 'MoveFiles', array( 'storageExtension' => $k, 'oldConfiguration' => $settings[ $k ], 'newConfiguration' => $values[ $k ], 'count' => $extension->count() ), 2 );
							
							/* Add to allowed storage methods so when moving files, we can accept old config or new config if move is in progress. Important: order is array(x, y) x is the new location (pos 0), y is the old location (pos 1)*/
							$values[ $k ] = array( $v, $settings[ $k ] );
						}
					}
				}
				
				if( $rebuild )
				{
					$this->queueRebuild();
					
					\IPS\Task::queue( 'core', 'DeleteMovedFiles', array( 'delete' => true ), 5, array( 'delete' ) ); /* We use a key in the data array just to trigger the code that deletes duplicate tasks */
				}
				
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => json_encode( $values ) ), array( 'conf_key=?', 'upload_settings' ) );
				unset( \IPS\Data\Store::i()->settings );
				\IPS\Session::i()->log( 'acplogs__files_config_moved', array() );
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=overview&controller=files&do=settings&tab=settings'), 'saved' );
			}
			
			$activeTabContents = $form;
		}
		/* Or configurations table */
		else
		{
			$table = new \IPS\Helpers\Table\Db( 'core_file_storage', \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=settings&tab=configurations" ) );
			$table->include = array( 'filestorage_method' );
			$table->noSort = array( 'filestorage_method' );
			$table->parsers = array( 'filestorage_method' => function( $val, $row )
			{
				$classname = 'IPS\File\\' . $row['method'];
				return $classname::displayName( json_decode( $row['configuration'], TRUE ) );
			} );
			
			$table->rootButtons = array( 'add' => array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationForm" )
			) );
			
			$table->rowButtons = function( $row )
			{
				return array(
					'edit'	=> array(
						'icon'	=> 'pencil',
						'title'	=> 'edit',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationForm&id={$row['id']}" )
					),
					'log'	=> array(
						'icon'	=> 'search',
						'title'	=> 'file_config_log_title',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationLog&id={$row['id']}" )
					),
					'delete'	=> array(
						'icon'	=> 'times-circle',
						'title'	=> 'delete',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationForm&id={$row['id']}&delete=1" )
					),
				);
			};
			
			$activeTabContents = $table;
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() and !isset( \IPS\Request::i()->ajaxValidate ) )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('storage_settings');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( array( 'settings' => 'filestorage_settings', 'configurations' => 'filestorage_configurations' ), $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=settings" ) );
		}
	}
	
	/**
	 * Add/Edit Configuration
	 *
	 * @return	void
	 */
	protected function configurationForm()
	{
		/* Get existing */
		$current = NULL;
		$currentHandlerSettings = array();
		$createNewAndMove = FALSE;
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$current = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'id=?', intval( \IPS\Request::i()->id ) ) )->first();
				$currentHandlerSettings = json_decode( $current['configuration'], TRUE );
				
				if ( in_array( intval( \IPS\Request::i()->id ), json_decode( \IPS\Settings::i()->upload_settings, TRUE ) ) )
				{
					if ( isset( \IPS\Request::i()->delete ) )
					{
						\IPS\Output::i()->error( 'file_storage_in_use', '1C158/2', 403, '' );
					}
					else
					{
						$createNewAndMove = TRUE;
					}
				}
				else
				{
					foreach ( \IPS\Db::i()->select( 'data', 'core_queue', array( '`key`=?', 'MoveFiles' ) ) as $data )
					{
						$data = json_decode( $data, TRUE );
						if ( $data['oldConfiguration'] == \IPS\Request::i()->id )
						{
							\IPS\Output::i()->error( 'file_storage_move_out', '1C158/3', 403, '' );
						}
						elseif ( $data['newConfiguration'] == \IPS\Request::i()->id )
						{
							\IPS\Output::i()->error( 'file_storage_move_in', '1C158/4', 403, '' );
						}
					}
					
					if ( isset( \IPS\Request::i()->delete ) )
					{
						\IPS\Db::i()->delete( 'core_file_storage', array( 'id=?', intval( \IPS\Request::i()->id ) ) );
						unset( \IPS\Data\Store::i()->storageConfigurations );
	
						\IPS\Session::i()->log( 'acplogs__files_config_removed', array() );
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings&tab=configurations' ) );
					}
				}
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C158/1', 404, '' );
			}
		}
		
		/* Get handlers */
		$handlers = array();
		$handlerSettings = array();
		$toggles = array();
		foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . '/system/File' ) as $f )
		{
			if ( !$f->isDot() and mb_substr( $f, -4 ) === '.php' and $f != 'File.php' and $f != 'Iterator.php' )
			{				
				$key = mb_substr( $f, 0, -4 );
				
				$handlers[ $key ] = 'filehandler__' . $key;
				
				$class = 'IPS\File\\' . $key;
				foreach ( $class::settings() as $k => $v )
				{
					if ( is_array( $v ) )
					{
						$settingClass = '\IPS\Helpers\Form\\' . $v['type'];
						
						$default = isset( $currentHandlerSettings[ $k ] ) ? str_replace( '{root}', \IPS\ROOT_PATH, $currentHandlerSettings[ $k ] ) : NULL;
						if ( isset( $v['default'] ) and !$default )
						{
							$default = str_replace( '{root}', \IPS\ROOT_PATH, $v['default'] );
						}
						
						$handlerSettings[ $key ][ $k ] = new $settingClass( "filehandler__{$key}_{$k}", $default, FALSE, isset( $v['options'] ) ? $v['options'] : array(), isset( $v['validate'] ) ? $v['validate'] : NULL, isset( $v['prefix'] ) ? $v['prefix'] : NULL, isset( $v['suffix'] ) ? $v['suffix'] : NULL, "{$key}_{$k}" );
					}
					else
					{
						$settingClass = '\IPS\Helpers\Form\\' . $v;
						$handlerSettings[ $key ][ $k ] = new $settingClass( "filehandler__{$key}_{$k}", isset( $currentHandlerSettings[ $k ] ) ? $currentHandlerSettings[ $k ] : NULL, FALSE, array(), NULL, NULL, NULL, "{$key}_{$k}" );
					}
					$toggles[ $key ][ $k ] = "{$key}_{$k}";
				}
			}
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;

		if ( isset( \IPS\Request::i()->id ) AND in_array( intval( \IPS\Request::i()->id ), json_decode( \IPS\Settings::i()->upload_settings, TRUE ) ) )
		{
			$form->addMessage( 'files_edit_existing_and_used', 'ipsMessage ipsMessage_info' );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'filestorage_method', $current ? $current['method'] : 'FileSystem', TRUE, array( 'options' => $handlers, 'toggles' => $toggles ) ) );
		foreach ( $handlerSettings as $handlerKey => $settings )
		{
			foreach ( $settings as $setting )
			{
				$form->add( $setting );
			}
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			try
			{
				if ( isset( $toggles[ $values['filestorage_method'] ] ) )
				{
					foreach ( $toggles[ $values['filestorage_method'] ] as $k => $v )
					{
						$currentHandlerSettings[ $k ] = str_replace( \IPS\ROOT_PATH, '{root}', $values[ 'filehandler__' . $v ] );
					}
				}

				$classname = 'IPS\File\\' . $values['filestorage_method'];
				if ( method_exists( $classname, 'testSettings' ) )
				{
					$classname::testSettings( $currentHandlerSettings );
				}

				/* Do we really need to create and move? */
				if ( $current AND $createNewAndMove )
				{
					$currentConf      = json_decode( $current['configuration'], TRUE );
					$createNewAndMove = $classname::moveCheck( $currentHandlerSettings, $currentConf );
				}

				if ( $current === NULL or $createNewAndMove )
				{
					$insertId = \IPS\Db::i()->insert( 'core_file_storage', array(
						'method'		=> $values['filestorage_method'],
						'configuration'	=> json_encode( $currentHandlerSettings ),
					) );
					unset( \IPS\Data\Store::i()->storageConfigurations );

					if ( $createNewAndMove )
					{
						$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
						foreach ( $settings as $k => $v )
						{
							if ( $v == $current['id'] )
							{
								$exploded = explode( '_', $k );
								$classname = "IPS\\{$exploded[2]}\\extensions\\core\\FileStorage\\{$exploded[3]}";

								if( class_exists( $classname ) )
								{
									$extension = new $classname;
																	
									\IPS\Task::queue( 'core', 'MoveFiles', array( 'storageExtension' => $k, 'oldConfiguration' => $v, 'newConfiguration' => $insertId, 'count' => $extension->count() ), 2 );
								}
								
								$settings[ $k ] = $insertId;
							}
						}
						
						\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => json_encode( $settings ) ), array( 'conf_key=?', 'upload_settings' ) );
						unset( \IPS\Data\Store::i()->settings );
						
						$this->queueRebuild();
						
						\IPS\Task::queue( 'core', 'DeleteMovedFiles', array( 'delete' => true ), 5, array( 'delete' ) ); /* We use a key in the data array just to trigger the code that deletes duplicate tasks */
					}
				}
				else
				{
					\IPS\Db::i()->update( 'core_file_storage', array( 'configuration' => json_encode( $currentHandlerSettings ) ), array( 'id=?', $current['id'] ) );
					unset( \IPS\Data\Store::i()->storageConfigurations );
				}

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings&tab=configurations' ), 'saved' );
			}
			catch ( \LogicException $e )
			{
				$msg = $e->getMessage();
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( $msg );
			}
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('storage_settings');
		\IPS\Output::i()->output = $form;
	}

	/**
	 * View logs for this configuration
	 *
	 * @return	void
	 */
	protected function configurationLog()
	{
		$method = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'id=?', intval( \IPS\Request::i()->id ) ) )->first();
		$title  = \IPS\Member::loggedIn()->language()->addToStack( 'file_config_log', NULL, array( 'sprintf' => array( $method['method'] ) ) );

		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_file_logs', \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=configurationLog&id=' . \IPS\Request::i()->id ), array( array( 'log_configuration_id=?', \IPS\Request::i()->id ) ) );
		$table->langPrefix  = 'files_';
		$table->title       = $title;
		$table->quickSearch = 'log_filename';
		$table->sortBy      = 'log_date';

		$table->filters		= array(
			'files_log_filter_error'   => array('log_type=?', 'error' ),
			'files_log_filter_move'    => array('log_type=?', 'move' )
		);

		$table->include = array( 'log_date', 'log_type', 'log_action', 'log_msg', 'log_filename' );

		$table->parsers = array(
			'log_filename' => function( $val, $row )
			{
				return ( ! empty( $row['log_container'] ) ? $row['log_container'] . '/' : '' ) . $val;
			},
			'log_date' => function( $val )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'log_type' => function( $val )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'files_type_' . $val );
			},
			'log_action' => function( $val )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'files_action_' . $val );
			}
		);

		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "&app=core&module=overview&controller=files&do=settings&tab=configurations" ), 'filestorage_settings' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( $title, (string) $table );
		\IPS\Output::i()->title  = $title;
	}

	/**
	 * Remove orphaned files
	 *
	 * @return	void
	 */
	protected function orphaned()
	{
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=orphaned" ),
			function( $data )
			{
				/* Initiate the data array */
				if ( !is_array( $data ) )
				{
					$data = array( 'count' => 0, 'configuration' => 0, 'fileIndex' => 0 );
				}

				/* If there is no configuration ID, grab the first one */
				if( !$data['configuration'] )
				{
					$configuration	= \IPS\Db::i()->select( '*', 'core_file_storage', array(), 'id', array( 0, 1 ) )->first();

					$data['configuration']	= $configuration['id'];
				}
				else
				{
					/* Otherwise we load this id or the next one found - when a configuration is 'done' we simply increment the ID later so we use >= here to grab the next one */
					try
					{
						$configuration	= \IPS\Db::i()->select( '*', 'core_file_storage', array( 'id >= ?', $data['configuration'] ), 'id', array( 0, 1 ) )->first();

						$data['configuration']	= $configuration['id'];
					}
					catch( \UnderflowException $e )
					{
						/* If there is no record found, we have run through all configurations */
						\IPS\Session::i()->log( 'acplogs__orphaned_files_tool', array( $data['count'] => TRUE ) );
						return NULL;
					}
				}

				/* Decode any settings, if necessary */
				$configuration['_settings']	= json_decode( $configuration['configuration'], TRUE );

				/* Check the configuration location and loop through x files looking for any that aren't mapped in any storage locations */
				$results	= \IPS\File::orphanedFiles( $configuration, $data['fileIndex'] );

				/* Increment count */
				$data['count']			+= $results['count'];

				/* If this configuration is done now, move to the next, otherwise increment the offset values for the next loop */
				if( $results['_done'] )
				{
					$data['configuration']	+= 1;
					$data['fileIndex']		= 0;
				}
				else
				{
					$data['fileIndex']		= $results['fileIndex'];
				}
				
				return array( $data, \IPS\Member::loggedIn()->language()->addToStack('removing_orphaned_files') );
			},
			function()
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=files" ), 'orphaned_files_removed' );
			}
		);
	}
	
	/**
	 * Queue content rebuild
	 *
	 * @return	void
	 */
	protected function queueRebuild()
	{
		/* Rebuild content to update images */
		foreach ( \IPS\Content::routedClasses( FALSE, FALSE, TRUE ) as $class )
		{
			try
			{
				\IPS\Task::queue( 'core', 'RebuildContentImages', array( 'class' => $class ), 3, array( 'class' ) );
			}
			catch( \OutOfRangeException $ex ) { }
		}

		/* Rebuild attachment images everywhere else */
		foreach( \IPS\Application::allExtensions( 'core', 'EditorLocations', FALSE, NULL, NULL, TRUE, FALSE ) as $_key => $extension )
		{
			if( method_exists( $extension, 'rebuildAttachmentImages' ) )
			{
				\IPS\Task::queue( 'core', 'RebuildNonContentImages', array( 'extension' => $_key ), 3, array( 'extension' ) );
			}
		}
	}
}