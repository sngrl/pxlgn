<?php
/**
 * @brief		Manage Posting Settings
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 Jun 2013
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
 * Manage Posting Settings
 */
class _posting extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Get tab content */
		$this->activeTab = \IPS\Request::i()->tab ?: 'general';
		
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage' );
		parent::execute();
	}

	/**
	 * Manage Posting Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		$activeTabContents = call_user_func( array( $this, '_manage'.ucfirst( $this->activeTab  ) ) );
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Build tab list */
		$tabs = array();
		$tabs['general']			= 'posting_general';
		$tabs['acronymExpansion']	= 'acronym_expansion';
		$tabs['polls']				= 'polls';
		$tabs['profanityFilters']	= 'profanity';
		$tabs['tags']				= 'tags';
		$tabs['urlFilters']			= 'url_settings';

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_posting');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $this->activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=settings&controller=posting" ) );
	}
		
	/**
	 * Manage general posting settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageGeneral()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage' );
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$current = ( isset( \IPS\Settings::i()->attachment_image_size ) ) ? explode( 'x', \IPS\Settings::i()->attachment_image_size ) : array( 1000, 750 );
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'attachment_image_size', $current, FALSE, array( 'resizableDiv' => FALSE ), NULL, NULL, NULL, 'attachment_image_dimensions' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'strip_quotes', !\IPS\Settings::i()->strip_quotes, FALSE, array(), NULL, NULL, NULL, 'strip_quotes' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'edit_log', \IPS\Settings::i()->edit_log, FALSE, array(
			'options' => array(
				0	=> 'edit_log_none',
				1	=> 'edit_log_simple',
				2	=> 'edit_log_full'
			),
			'toggles'	=> array(
				2	=> array( 'edit_log_public', 'edit_log_prune' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'edit_log_public', \IPS\Settings::i()->edit_log_public, FALSE, array(), NULL, NULL, NULL, 'edit_log_public' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'edit_log_prune', \IPS\Settings::i()->edit_log_prune, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'never' ), function( $value ){ if( $value < 1 AND $value != -1 ) { throw new \InvalidArgumentException('form_required'); } }, NULL, \IPS\Member::loggedIn()->language()->addToStack('days'), 'edit_log_prune' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'flood_control', \IPS\Settings::i()->flood_control, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'none' ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('seconds') ) );
		$form->add( new \IPS\Helpers\Form\Number( 'topic_redirect_prune', \IPS\Settings::i()->topic_redirect_prune, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), \IPS\Member::loggedIn()->language()->addToStack('days') ) );
		
		$hasReviewableApps = false;
		
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', NULL, NULL, NULL, TRUE, TRUE ) as $router )
		{
			foreach ( $router->classes as $class )
			{
				$classes[]	= $class;

				if ( isset( $class::$commentClass ) )
				{
					$hasReviewableApps = true;
					break 2;
				}
			}
		}
		
		if ( $hasReviewableApps )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'reviews_rating_out_of', \IPS\Settings::i()->reviews_rating_out_of, FALSE, array(
				'options' => array(
					5	=> 'reviews_rating_out_of_5',
					10	=> 'reviews_rating_out_of_10'
				)
			) ) );
		}
		
		/* Save values */
		if ( $values = $form->values() )
		{
			$values['strip_quotes'] = $values['strip_quotes'] ? FALSE : TRUE;
			$values['attachment_image_size'] = implode( 'x', $values['attachment_image_size'] );			

			$form->saveAsSettings( $values );

			\IPS\Session::i()->log( 'acplogs__posting_general_settings' );
		}
	
		return $form;
	}
	
	/**
	 * Show profanity filters
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageProfanityFilters()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_profanity' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_profanity_filters', \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=profanityFilters' ) );
		$table->langPrefix = 'profanity_';
		$table->mainColumn = 'type';
		
		/* Columns we need */
		$table->include = array( 'type', 'swop', 'm_exact' );

		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'type';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Filters */
		$table->filters = array(
			'profanity_filter_exact'		=> 'm_exact=1',
			'profanity_filter_loose'		=> 'm_exact=0',
		);
		
		/* Search */
		$table->quickSearch = 'type';
		
		/* Custom parsers */
		$table->parsers = array(
			'm_exact'				=> function( $val, $row )
			{
				return ( $val ) ? \IPS\Member::loggedIn()->language()->addToStack('profanity_filter_exact') : \IPS\Member::loggedIn()->language()->addToStack('profanity_filter_loose');
			}
		);
		
		/* Specify the buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'profanity', 'filter_add' ) )
		{
			$table->rootButtons['add'] = array(
				'icon'		=> 'plus',
				'title'		=> 'profanity_add',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=profanity' ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('profanity_add') )
			);
		}
		$table->rootButtons['download'] = array(
			'icon'		=> 'download',
			'title'		=> 'download',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=downloadProfanity' ),
			'data'		=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('profanity_download'), 'confirmIcon' => 'info', 'confirmButtons' => json_encode( array( 'ok' => \IPS\Member::loggedIn()->language()->addToStack('download'), 'cancel' => \IPS\Member::loggedIn()->language()->addToStack('cancel') ) ) )
		);

		$table->rowButtons = function( $row )
		{
			$return = array();

			$return['edit'] = array(
				'icon'		=> 'pencil',
				'title'		=> 'edit',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=profanity&id=' ) . $row['wid'],
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
			);

			$return['delete'] = array(
				'icon'		=> 'times',
				'title'		=> 'delete',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=deleteProfanityFilters&id=' ) . $row['wid'],
				'data'		=> array( 'delete' => '' ),
			);
				
			return $return;
		};
		
		return (string) $table;
	}

	/**
	 * Show url filters
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageUrlFilters()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_url' );
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'ipb_url_filter_option', \IPS\Settings::i()->ipb_url_filter_option, FALSE, array(
			'options' => array(
				'none' => 'url_none',
				'black' => 'url_blacklist',
				'white' => "url_whitelist" ),
			'toggles' => array(
				'black'	=> array( 'ipb_url_blacklist' ),
				'white'	=> array( 'ipb_url_whitelist' ),
				'none'		=> array(),
			)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Stack( 'ipb_url_whitelist', \IPS\Settings::i()->ipb_url_whitelist ? explode( ",", \IPS\Settings::i()->ipb_url_whitelist ) : array(), FALSE, array(), NULL, NULL, NULL, 'ipb_url_whitelist' ) );
 		$form->add( new \IPS\Helpers\Form\Stack( 'ipb_url_blacklist', \IPS\Settings::i()->ipb_url_blacklist ? explode( ",", \IPS\Settings::i()->ipb_url_blacklist ) : array(), FALSE, array(), NULL, NULL, NULL, 'ipb_url_blacklist' ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'links_external', \IPS\Settings::i()->links_external ) );
 		$form->add( new \IPS\Helpers\Form\YesNo( 'posts_add_nofollow', \IPS\Settings::i()->posts_add_nofollow, FALSE, array(), NULL, NULL, NULL, 'posts_add_nofollow' ) );
 		
		/* Save values */
		if ( $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplogs__url_filter_settings' );
		}

		return $form;
	}
	
	/**
	 * Acronym expansion
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageAcronymExpansion()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_acronym' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_acronyms', \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=acronymExpansion' ) );
		$table->langPrefix = 'acronym_';
		$table->mainColumn = 'a_short';
	
		/* Columns we need */
		$table->include = array( 'a_short', 'a_long', 'a_casesensitive' );
	
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'a_short';
		$table->sortDirection = $table->sortDirection ?: 'asc';
	
		/* Search */
		$table->quickSearch = 'a_short';
	
		/* Custom parsers */
		$table->parsers = array(
			'a_casesensitive'=> function( $val, $row )
			{
				return ( $val ) ? "<i class='fa fa-check'></i>" : "<i class='fa fa-times'></i>";
			},
		);
	
		/* Specify the buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'acronym', 'acronym_add' ) )
		{
			$table->rootButtons = array(
					'add'	=> array(
							'icon'		=> 'plus',
							'title'		=> 'acronym_add',
							'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=acronym' ),
							'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acronym_add') )
					)
			);
		}
	
		$table->rowButtons = function( $row )
		{
			$return = array();
	
			$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=acronym&id=' ) . $row['a_id'],
			);
	
			$return['delete'] = array(
					'icon'		=> 'times',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=deleteAcronym&id=' ) . $row['a_id'],
					'data'		=> array( 'delete' => '' ),
			);
	
			return $return;
		};
	
		return $table;
	}
	
	/**
	 * Manage poll settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _managePolls()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_polls' );
		
		$form = new \IPS\Helpers\Form();
		$form->addHeader('poll_creation');
		$form->add( new \IPS\Helpers\Form\Number( 'max_poll_questions', \IPS\Settings::i()->max_poll_questions ) );
		$form->add( new \IPS\Helpers\Form\Number( 'max_poll_choices', \IPS\Settings::i()->max_poll_choices ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'poll_allow_public', \IPS\Settings::i()->poll_allow_public ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_poll_only', \IPS\Settings::i()->ipb_poll_only ) );
		$form->add( new \IPS\Helpers\Form\Number( 'startpoll_cutoff', \IPS\Settings::i()->startpoll_cutoff, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'always' ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('hours') ) );
		$form->addHeader('poll_voting');
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_creator_vote', \IPS\Settings::i()->allow_creator_vote ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'poll_allow_vdelete', \IPS\Settings::i()->poll_allow_vdelete, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_result_view', \IPS\Settings::i()->allow_result_view, FALSE, array(), NULL, NULL, NULL, 'allow_result_view' ) );
		
		if ( $form->saveAsSettings() )
		{
			\IPS\Session::i()->log( 'acplogs__poll_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=polls' ), 'saved' );
		}
		
		return \IPS\Theme::i()->getTemplate( 'forms' )->blurb( 'polls_blurb' ) . $form;
	}
	
	/**
	 * Manage tag settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageTags()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_tags' );
		
		$form = new \IPS\Helpers\Form();
				
		$form->add( new \IPS\Helpers\Form\YesNo( 'tags_enabled', \IPS\Settings::i()->tags_enabled, FALSE, array( 'togglesOn' => array( 'tags_can_prefix', 'tags_open_system', 'tags_predefined', 'tags_force_lower', 'tags_min', 'tags_max', 'tags_len_min', 'tags_len_max', 'tags_clean' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'tags_can_prefix', \IPS\Settings::i()->tags_can_prefix, FALSE, array(), NULL, NULL, NULL, 'tags_can_prefix' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'tags_open_system', \IPS\Settings::i()->tags_open_system, FALSE, array( 'options' => array( 1 => 'tags_open_system_open', 0 => 'tags_open_system_closed' ) ), NULL, NULL, NULL, 'tags_open_system' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'tags_predefined', \IPS\Settings::i()->tags_predefined, FALSE, array( 'autocomplete' => array( 'unique' => TRUE ) ), NULL, NULL, NULL, 'tags_predefined' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'tags_force_lower', \IPS\Settings::i()->tags_force_lower, FALSE, array(), NULL, NULL, NULL, 'tags_force_lower' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'tags_min', \IPS\Settings::i()->tags_min, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'tags_min_none', 'unlimitedToggles' => array( 'tags_min_req' ), 'unlimitedToggleOn' => FALSE ), NULL, NULL, NULL, 'tags_min' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'tags_min_req', \IPS\Settings::i()->tags_min_req, FALSE, array(), NULL, NULL, NULL, 'tags_min_req' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'tags_max', \IPS\Settings::i()->tags_max, FALSE, array( 'unlimited' => 0 ), NULL, NULL, NULL, 'tags_max' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'tags_len_min', \IPS\Settings::i()->tags_len_min, FALSE, array( 'unlimited' => 0 ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack( 'characters' ), 'tags_len_min' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'tags_len_max', \IPS\Settings::i()->tags_len_max, FALSE, array( 'unlimited' => 0 ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack( 'characters' ), 'tags_len_max' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'tags_clean', \IPS\Settings::i()->tags_clean, FALSE, array(), NULL, NULL, NULL, 'tags_clean' ) );
		
		if ( $form->saveAsSettings() )
		{
			\IPS\Session::i()->log( 'acplogs__tag_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=tags' ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * Add/Edit Profanity Filter
	 *
	 * @return	void
	 */
	public function profanity()
	{
		/* Permission check */
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_profanity' );
		
		/* Init */
		$current = NULL;
		if ( \IPS\Request::i()->id )
		{
			try
			{
				$current = \IPS\Db::i()->select( '*', 'core_profanity_filters', array( 'wid=?', \IPS\Request::i()->id ) )->first();
			}
			catch ( \UnderflowException $e ) { }
		}
	
		/* Build form */
		$form = new \IPS\Helpers\Form();
		if ( !$current )
		{
			$form->addTab('add');
		}
		$form->add( new \IPS\Helpers\Form\Text( 'profanity_type', ( $current ) ? $current['type'] : NULL, NULL, array() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'profanity_swop', ( $current ) ? $current['swop'] : NULL, NULL, array() ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'profanity_m_exact', ( $current ) ? $current['m_exact'] : NULL, FALSE, array(
			'options' => array(
				'1' => 'profanity_filter_exact',
				'0'	=> 'profanity_filter_loose' ) )
		) );
		if ( !$current )
		{
			$form->addTab('upload');
			$form->add( new \IPS\Helpers\Form\Upload( 'profanity_upload', NULL, NULL, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ) ) );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Upload */
			if ( isset( $values['profanity_upload'] ) and $values['profanity_upload'] )
			{
				/* Move it to a temporary location */
				$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
				move_uploaded_file( $values['profanity_upload'], $tempFile );
									
				/* Initate a redirector */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=importProfanity&file=' . urlencode( $tempFile ) . '&key=' . md5_file( $tempFile ) ) );
			}
			
			/* Normal */
			else
			{
				if ( $values['profanity_type'] and $values['profanity_swop'] )
				{
					$save = array(
						'type'		=> $values['profanity_type'],
						'swop'		=> $values['profanity_swop'],
						'm_exact'	=> $values['profanity_m_exact']
					);
					
					if( $current )
					{
						\IPS\Db::i()->update( 'core_profanity_filters', $save, array( 'wid=?', $current['wid'] ) );
					}
					else
					{
						\IPS\Db::i()->insert( 'core_profanity_filters', $save );
					}
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=profanityFilters' ), 'saved' );
				}
				else
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('profanity_add_error');
				}
			}
	
		}
	
		/* Display */
		\IPS\Output::i()->title	 		= \IPS\Member::loggedIn()->language()->addToStack('profanity');
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Output::i()->title );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Output::i()->title, $form, FALSE );
	}
	
	/**
	 * Download Profanity Filters
	 *
	 * @return	void
	 */
	public function downloadProfanity()
	{
		/* Permission Check */
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_profanity' );
		
		$xml = new \XMLWriter;
		$xml->openMemory();
		$xml->setIndent( TRUE );
		$xml->startDocument( '1.0', 'UTF-8' );
		$xml->startElement('badwordexport');
		$xml->startElement('badwordgroup');
		foreach ( \IPS\Db::i()->select( '*', 'core_profanity_filters' ) as $profanity )
		{
			$xml->startElement('badword');
			
			$xml->startElement('type');
			$xml->text( $profanity['type'] );
			$xml->endElement();
			
			$xml->startElement('swop');
			$xml->text( $profanity['swop'] );
			$xml->endElement();
			
			$xml->startElement('m_exact');
			$xml->text( $profanity['m_exact'] );
			$xml->endElement();
			
			$xml->endElement();
		}
		$xml->endElement();
		$xml->endElement();
		$xml->endDocument();
		
		\IPS\Output::i()->sendOutput( $xml->outputMemory(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', sprintf( \IPS\Member::loggedIn()->language()->get('profanity_download_name'),  \IPS\Settings::i()->board_name ) . '.xml' ) ) );
	}
	
	/**
	 * Import from upload
	 *
	 * @return	void
	 */
	protected function importProfanity()
	{
		if ( !file_exists( \IPS\Request::i()->file ) or md5_file( \IPS\Request::i()->file ) !== \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'generic_error', '3C256/1', 500, '' );
		}
		
		$url = \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=importProfanity&file=' . urlencode( \IPS\Request::i()->file ) . '&key=' .  \IPS\Request::i()->key );
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			$url,
			function( $data )
			{
				/* Open XML file */
				$xml = new \XMLReader;
				$xml->open( \IPS\Request::i()->file );
				$xml->read(); //badwordexport
				$xml->read(); //badwordexport
				$xml->read(); //badwordgroup
				$xml->read(); //badwordgroup
				$xml->read();
				
				/* Skip */
				for ( $i = 0; $i < $data; $i++ )
				{
					$xml->next();
					if ( !$xml->read() or $xml->name != 'badword' )
					{
						return NULL;
					}
				}
								
				/* Import */
				$save = array();
				$xml->read();
				$xml->read();
				$save['type'] = $xml->readString();
				$xml->next();
				$xml->read();
				$save['swop'] = $xml->readString();
				$xml->next();
				$xml->read();
				$save['m_exact'] = $xml->readString();
				try
				{
					$current = \IPS\Db::i()->select( 'wid', 'core_profanity_filters', array( 'type=?', $save['type'] ) )->first();
					\IPS\Db::i()->update( 'core_profanity_filters', $save, array( 'wid=?', $current ) );
				}
				catch ( \UnderflowException $e )
				{
					\IPS\Db::i()->insert( 'core_profanity_filters', $save );
				}
							
				/* Move to next */
				return array( ++$data, \IPS\Member::loggedIn()->language()->get('processing') );
			},
			function()
			{
				unset( \IPS\Data\Store::i()->languages );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=profanityFilters' ) );
			}
		);
	}
	
	/**
	 * Add/Edit Acronym
	 *
	 * @return	void
	 */
	public function acronym()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_acronym' );
		
		$current = NULL;
	
		if ( \IPS\Request::i()->id )
		{
			$current = \IPS\core\Acronym::load( \IPS\Request::i()->id );
		}
	
		/* Build form */
		$form = \IPS\core\Acronym::form( $current );
	
		if ( $values = $form->values() )
		{
			\IPS\core\Acronym::createFromForm( $values, $current );
	
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=acronymExpansion' ), 'saved' );
		}
	
		/* Display */
		\IPS\Output::i()->title	 		= \IPS\Member::loggedIn()->language()->addToStack('acronym_a_short');
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Output::i()->title );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Output::i()->title, $form, FALSE );
	}
	
	/**
	 * Delete Profanity Filter
	 *
	 * @return	void
	 */
	public function deleteProfanityFilters()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_profanity' );
		
		\IPS\Db::i()->delete( 'core_profanity_filters', array( 'wid=?', \IPS\Request::i()->id ) );
		
		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=posting&tab=profanityFilters" ) );
	}
	
	/**
	 * Delete Acronym
	 *
	 * @return	void
	 */
	public function deleteAcronym()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'posting_manage_acronym' );
	
		\IPS\core\Acronym::load( \IPS\Request::i()->id )->delete();
	
		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=posting&tab=acronymExpansion" ) );
	}
}