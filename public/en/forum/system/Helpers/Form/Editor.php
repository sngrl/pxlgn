<?php
/**
 * @brief		Editor class for Form Builder
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		5 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor class for Form Builder
 */
class _Editor extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'app'			=> 'core',		// The application that owns this type of editor (as defined by an extension)
	 		'key'			=> 'Example',	// The key for this type of editor (as defined by an extension)
	 		'autoSaveKey'	=> 'abc',		// Pass a string which identifies this editor's purpose. For example, if the editor is for replying to a topic with ID 5, you could use "topic-reply-5". Make sure you pass the same key every time, but a different key for different editors.
	 		'attachIds'		=> array(		// ID numbers to idenfity content for attachments if the content has been saved - the first two must be int or null, the third must be string or null. If content has not been saved yet, you must claim attachments after saving.
	 			1,
	 			2,
	 			'foo'
	 		),
	 		'minimize'		=> 'clickme',	// Language string to use for mimimized view. NULL will mean editor is not minimized.
	 		'minimizeIcon'	=> 'flag-us',	// Icon to use for minimized view
	 		'allButtons'	=> FALSE,		// Only used for the customisation ACP page. Do not use.
	 		'tags'			=> array(),		// An array of extra insertable tags in key => value pair with key being what is inserted and value serving as a description
	 		'autoGrow'		=> FALSE,		// Used to specify if editor should grow in size as content is added. Defaults to TRUE.
	 	);
	 * @encode
	 */
	protected $defaultOptions = array(
		'app'				=> NULL,
		'key'				=> NULL,
		'autoSaveKey'		=> NULL,
		'attachIds'			=> NULL,
		'minimize'			=> NULL,
		'minimizeIcon'		=> 'fa fa-comment-o',
		'allButtons'		=> FALSE,
		'tags'				=> array(),
		'autoGrow'			=> TRUE,
	);
	
	/**
	 * @brief	The extension that owns this type of editor
	 */
	protected $extension;
	
	/**
	 * @brief	The uploader helper
	 */
	protected $uploader = NULL;

	/**
	 * @brief	Editor identifier
	 */
	protected $postKey;
		
	/**
	 * Constructor
	 * Sets that the field is required if there is a minimum length and vice-versa
	 *
	 * @see		\IPS\Helpers\Form\Abstract::__construct
	 * @return	void
	 * @throws	\OutOfBoundsException
	 */
	public function __construct()
	{
		$args = func_get_args();
		$this->postKey = md5( $args[3]['autoSaveKey'] . ':' . session_id() );
		
		/* Get our extension */		
		if ( !isset( $args[3]['allButtons'] ) or !$args[3]['allButtons'] )
		{
			$extensions = \IPS\Application::load( $args[3]['app'] )->extensions( 'core', 'EditorLocations' );
			if ( !isset( $extensions[ $args[3]['key'] ] ) )
			{
				throw new \OutOfBoundsException( $args[3]['key'] );
			}
			
			$this->extension = $extensions[ $args[3]['key'] ];
		}
		
		/* Don't mimize if we have a value */
		if ( $args[1] or \IPS\Request::i()->$args[0] or ( isset( \IPS\Request::i()->cookie['vle_editor'] ) and \IPS\Request::i()->cookie['vle_editor'] ) )
		{
			$args[3]['minimize'] = NULL;
		}
		
		/* Create the upload helper - if the form has been submitted, this has to be done before parent::__construct() as we need the uploader present for getValue(), but for views, we won't load until the editor is clicked */
		$this->options = array_merge( $this->defaultOptions, $args[3] );
		if ( $this->canAttach() )
		{
			if ( isset( \IPS\Request::i()->getUploader ) and \IPS\Request::i()->getUploader === $args[0] )
			{
				if ( $uploader = $this->getUploader( $args[0]) )
				{
					\IPS\Output::i()->sendOutput( $uploader->html() );
				}
				else
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->editorAttachmentsPlaceholder( $args[0], $this->postKey ) );
				}
			}
			elseif( mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'post' or !$this->options['minimize'] )
			{
				$this->uploader = $this->getUploader( $args[0] );
			}
			else
			{
				$this->uploader = FALSE;
			}
		}
		
		/* Go */
		call_user_func_array( 'parent::__construct', $args );
				
		/* Include editor JS - but not if loaded via ajax. JS loader will handle that on demand */
		if( !\IPS\Request::i()->isAjax() )
		{
			if ( \IPS\IN_DEV )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, array( (string) \IPS\Http\Url::internal( 'applications/core/dev/ckeditor/ckeditor.js', 'none' ) ) );
			}
			else
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, array( \IPS\Http\Url::internal( 'applications/core/interface/ckeditor/ckeditor/ckeditor.js', 'none' ) ) );
			}
		}
		\IPS\Output::i()->jsVars['strip_quotes'] = \IPS\Settings::i()->strip_quotes;
	}
	
	/**
	 * Get HTML
	 *
	 * @param	bool	$raw	If TRUE, will return without HTML any chrome
	 * @return	string
	 */
	public function html( $raw=FALSE )
	{
		/* What buttons should we show? */
		$allowed = NULL;
		if ( !$this->options['allButtons'] )
		{
			$toolbars	= json_decode( \IPS\Settings::i()->ckeditor_toolbars, TRUE );
			$allowed	= array();
			
			foreach ( $toolbars as $device => $rows )
			{
				foreach ( $rows as $rowId => $data )
				{
					if ( is_array( $data ) )
					{
						$allowed[ $device ][ $rowId ]['name'] = $data['name'];
						foreach ( $data['items'] as $k => $v )
						{
							if ( \IPS\Text\Parser::canUse( \IPS\Member::loggedIn(), $v, "{$this->options['app']}_{$this->options['key']}" ) )
							{
								$allowed[ $device ][ $rowId ]['items'][] = $v;
							}
						}
					}
					else
					{
						$allowed[ $device ][ $rowId ] = $data;
					}
				}
			}
			
			/* Can we use HTML? */
			if ( $this->canUseHtml() === TRUE )
			{
				array_unshift( $allowed['desktop'][0]['items'], 'Source' );
				array_unshift( $allowed['tablet'][0]['items'], 'Source' );
				array_unshift( $allowed['phone'][0]['items'], 'Source' );
			}
		}
		
		/* Clean resources in ACP */
		$value = $this->value;
		if( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation == 'admin' )
		{
			$value	= \IPS\Text\Parser::mungeResources( $value );
		}

		/* Show full uploader */
		if ( $this->uploader )
		{
			$attachmentArea = $this->uploader->html();
		}
		/* Or show a loading icon where the uploader will go if the editor is minimized */
		elseif ( $this->uploader === FALSE )
		{
			$attachmentArea = \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->editorAttachmentsMinimized( $this->name );
			
			/* We still need to include plupload otherwise it won't work when they click in */
			if ( \IPS\IN_DEV )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/moxie.js', 'core', 'interface' ) );
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.dev.js', 'core', 'interface' ) );
			}
			else
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.full.min.js', 'core', 'interface' ) );
			}
		}
		/* Or if the user can't attach, just show a bar */
		else
		{
			$attachmentArea = \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->editorAttachmentsPlaceholder( $this->name, $this->postKey );
		}
		
		/* Display */
		$template = $raw ? 'editorRaw' : 'editor';
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->$template( $this->name, $value, $this->options, $allowed, md5( $this->options['autoSaveKey'] . ':' . session_id() ), $attachmentArea, json_encode( static::getEmoticons() ) );
	}
	
	/**
	 * Convert the value to something that will look okay for the no-JS fallback
	 *
	 * @param	string	$value	Valie
	 * @return	string
	 */
	public static function valueForNoJsFallback( $value )
	{		
		$value = preg_replace( "/\<br(\s*)?\/?\>(\s*)?/i", "\n", html_entity_decode( $value ) );
		
		$value = trim( $value );
		
		$value = preg_replace( '/<\/p>\s*<p>/', "\n\n", $value );
		
		if ( mb_substr( $value, 0, 3 ) === '<p>' )
		{
			$value = mb_substr( $value, 3 );
		}
		if ( mb_substr( $value, -4 ) === '</p>' )
		{
			$value = mb_substr( $value, 0, -4 );
		}
		
		return $value;
	}
		
	/**
	 * Get Value
	 *
	 * @return	string
	 */
	public function getValue()
	{
		$error = NULL;
		$value = parent::getValue();
		
		/* If it was made without JS, convert linebreaks to <br>s */
		$noJsKey = $this->name . '_noscript';
		if ( isset( \IPS\Request::i()->$noJsKey ) AND !$value )
		{
			$value = nl2br( \IPS\Request::i()->$noJsKey, FALSE );
		}
		
		/* Or remove any invisible spaces used by the editor JS */
		else
		{
			$value = str_replace( json_decode('"\u2063"'), '', $value );
		}
						
		/* Parse value */
		if ( $value )
		{
			$parser = $this->_getParser();
			$parsedValue = $parser->parse( $value );
			if( count( $parser->errors ) )
			{
				$error = new \DomainException( implode( '; ', array_unique( $parser->errors ) ) );
			}
			
			$value = $parsedValue;
		}
													
		/* Add any attachments that weren't inserted in the content */
		if ( $this->uploader )
		{
			$inserts = array();
			foreach ( static::getAttachments( NULL, $this->postKey ) as $attachment )
			{
				if ( !isset( $parser ) or !in_array( $attachment['attach_id'], $parser->mappedAttachments ) )
				{
					if ( $attachment['attach_is_image'] )
					{
						$value .= \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedImage( $attachment['attach_location'], $attachment['attach_thumb_location'] ? $attachment['attach_thumb_location'] : $attachment['attach_location'], $attachment['attach_file'], $attachment['attach_id'] );
					}
					else
					{
						$value .= \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedFile( \IPS\Settings::i()->base_url . "applications/core/interface/file/attachment.php?id=" . $attachment['attach_id'], $attachment['attach_file'] );
					}

					$inserts[] = array(
						'attachment_id'	=> $attachment['attach_id'],
						'location_key'	=> "{$this->options['app']}_{$this->options['key']}",
						'id1'			=> ( is_array( $this->options['attachIds'] ) and isset( $this->options['attachIds'][0] ) ) ? $this->options['attachIds'][0] : NULL,
						'id2'			=> ( is_array( $this->options['attachIds'] ) and isset( $this->options['attachIds'][1] ) ) ? $this->options['attachIds'][1] : NULL,
						'id3'			=> ( is_array( $this->options['attachIds'] ) and isset( $this->options['attachIds'][2] ) ) ? $this->options['attachIds'][2] : NULL,
						'temp'			=> is_string( $this->options['attachIds'] ) ? $this->options['attachIds'] : ( $this->options['attachIds'] === NULL ? md5( $this->options['autoSaveKey'] ) : $this->options['attachIds'] )
					);
				}
			}
			if( count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_attachments_map', $inserts, TRUE );
			}
		}

		/* Remove abandoned attachments */
		foreach ( \IPS\Db::i()->select( '*', 'core_attachments', array( array( 'attach_id NOT IN(?)', \IPS\Db::i()->select( 'DISTINCT attachment_id', 'core_attachments_map' ) ), array( 'attach_member_id=? AND attach_date<?', \IPS\Member::loggedIn()->member_id, time() - 86400 ) ) ) as $attachment )
		{
			try
			{
				\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', $attachment['attach_id'] ) );
				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
				if ( $attachment['attach_thumb_location'] )
				{
					\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
				}
			}
			catch ( \Exception $e ) { }
		}
							
		/* Throw any errors */
		if ( $error )
		{
			$this->value = $value;
			throw $error;
		}

		/* Return */
		return $value;
	}

	/**
	 * Get parser object
	 *
	 * @return	\IPS\Text\Parser
	 */
	protected function _getParser()
	{
		return new \IPS\Text\Parser( TRUE, ( $this->options['attachIds'] === NULL ? md5( $this->options['autoSaveKey'] ) : $this->options['attachIds'] ), NULL, "{$this->options['app']}_{$this->options['key']}", TRUE, !$this->canUseHtml(), method_exists( $this->extension, 'htmlPurifierConfig' ) ? array( $this->extension, 'htmlPurifierConfig' ) : NULL );
	}
		
	/**
	 * Can use HTML?
	 *
	 * @return	bool
	 */
	protected function canUseHtml()
	{
		$canUseHtml = (bool) \IPS\Member::loggedIn()->group['g_dohtml'];
		if ( $this->extension )
		{
			$extensionCanUseHtml = $this->extension->canUseHtml( \IPS\Member::loggedIn(), $this );
			if ( $extensionCanUseHtml !== NULL )
			{
				$canUseHtml = $extensionCanUseHtml;
			}
		}
		return $canUseHtml;
	}
	
	/**
	 * Can Attach?
	 *
	 * @return	bool
	 */
	protected function canAttach()
	{
		$canAttach = ( \IPS\Member::loggedIn()->group['g_attach_max'] == '0' ) ? FALSE : TRUE;

		if ( $this->extension )
		{
			$extensionCanAttach = $this->extension->canAttach( \IPS\Member::loggedIn(), $this );
			if ( $extensionCanAttach !== NULL )
			{
				$canAttach = $extensionCanAttach;
			}
		}
		return $canAttach;
	}
	
	/**
	 * Get uploader
	 *
	 * @param	string	$name	Form name
	 * @return	\IPS\Helpers\Form\Uploader
	 */
	protected function getUploader( $name )
	{
		/* Load existing attachments */
		$existingAttachments = '';
		$counts = array();
		$where = array();
		if ( isset( $this->options['attachIds'] ) and is_array( $this->options['attachIds'] ) and count( $this->options['attachIds'] ) )
		{
			$where = array( array( 'location_key=?', "{$this->options['app']}_{$this->options['key']}" ) );
			$i = 1;
			foreach ( $this->options['attachIds'] as $id )
			{
				$where[] = array( "id{$i}=?", $id );
				$i++;
			}
			$_existingAttachments = iterator_to_array( \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', $where ) );
			if ( !empty( $_existingAttachments ) )
			{
				$existingAttachments = \IPS\Db::i()->in( 'attach_id', $_existingAttachments ) . ' OR ';
			}
		}
		$existing = array();
		foreach ( static::getAttachments( $existingAttachments, $this->postKey ) as $attachment )
		{
			try
			{
				$file = \IPS\File::get( 'core_Attachment', $attachment['attach_location'] );
				$file->attachmentThumbnailUrl = $attachment['attach_thumb_location'] ?: $attachment['attach_location'];
				$existing[ $attachment['attach_id'] ] = $file;
			}
			catch ( \Exception $e ) { }
		}
	
		/* Can we upload more? */
		$maxTotalSize = array();
		if ( \IPS\Member::loggedIn()->group['g_attach_max'] > 0 and \IPS\Member::loggedIn()->member_id )
		{
			$maxTotalSize[] = ( ( \IPS\Member::loggedIn()->group['g_attach_max'] * 1024 ) - \IPS\Db::i()->select( 'SUM(attach_filesize)', 'core_attachments', array( 'attach_member_id=?', \IPS\Member::loggedIn()->member_id ) )->first() ) / 1048576;
		}
		if ( \IPS\Member::loggedIn()->group['g_attach_per_post'] )
		{
			$maxTotalSize[] = \IPS\Member::loggedIn()->group['g_attach_per_post'] / 1024;
		}
		$maxTotalSize = count( $maxTotalSize ) ? min( $maxTotalSize ) : NULL;
		
		/* Create the uploader */
		if ( $maxTotalSize === NULL or $maxTotalSize > 0 )
		{
			$maxTotalSize = ( !is_null( $maxTotalSize ) ) ? round( $maxTotalSize, 2 ) : NULL;
			
			$postKey = $this->postKey;
			$uploader = new \IPS\Helpers\Form\Upload( str_replace( array( '[', ']' ), '_', $name ) . '_upload', $existing, FALSE, array( 'template' => 'core.attachments.fileItem', 'multiple' => TRUE, 'postKey' => $this->postKey, 'storageExtension' => 'core_Attachment', 'retainDeleted' => TRUE, 'totalMaxSize' => $maxTotalSize, 'callback' => function( $file ) use ( $postKey )
			{
				\IPS\Db::i()->delete( 'core_files_temp', array( 'contents=?', (string) $file ) );
				$attachment = $file->makeAttachment( $postKey );
				return $attachment['attach_id'];
			} ) );
			$uploader->template = array( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' ), 'editorAttachments' );
			
			/* Handle delete calls */
			if ( isset( \IPS\Request::i()->postKey ) and \IPS\Request::i()->postKey == $this->postKey and isset( \IPS\Request::i()->deleteFile ) )
			{
				try
				{				
					$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', \IPS\Request::i()->deleteFile ) )->first();
					\IPS\Db::i()->delete( 'core_attachments_map', array_merge( $where, array( array( 'attachment_id=?', $attachment['attach_id'] ) ) ) );
					$otherMaps = \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( 'attachment_id=?', $attachment['attach_id'] ) )->first();
					if ( !$otherMaps )
					{
						\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', $attachment['attach_id'] ) );
						\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
						if ( $attachment['attach_thumb_location'] )
						{
							\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
						}
					}
				}
				catch ( \Exception $e ) {	}
				\IPS\Output::i()->json( 'OK' );
			}
		}
		else
		{
			$uploader = NULL;
		}
		
		/* Return */
		return $uploader;
	}

	/**
	 * Fetch the emoticons - abstracted to a static method for caching
	 *
	 * @return	array
	 */
	public static function getEmoticons()
	{
		if( !isset( \IPS\Data\Store::i()->emoticons ) )
		{
			\IPS\Data\Store::i()->emoticons = iterator_to_array( \IPS\Db::i()->select( '*', 'core_emoticons', array( 'typed<>?', '' ) )->setKeyField('typed')->setValueField('image') );
		}

		return \IPS\Data\Store::i()->emoticons;
	}

	/**
	 * Fetch existing attachments - abstracted  to a static method for caching
	 *
	 * @param	string	$existing	Existing attachments limiter
	 * @param	string	$postKey	Post key
	 * @return	array
	 */
	public static function getAttachments( $existing, $postKey )
	{
		static $attachments = array();

		$_key	= md5( $existing . $postKey );

		if( isset( $attachments[ $_key ] ) )
		{
			return $attachments[ $_key ];
		}

		$attachments[ $_key ] = \IPS\Db::i()->select( '*', 'core_attachments', array( $existing . 'attach_post_key=?', $postKey ) );

		return $attachments[ $_key ];
	}
}
