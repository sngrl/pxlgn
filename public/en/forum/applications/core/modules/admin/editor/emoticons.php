<?php
/**
 * @brief		Emoticons
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		02 May 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\editor;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Emoticons
 */
class _emoticons extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_manage' );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/emoticons.css', 'core', 'admin' ) );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_editor_emoticons');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_editor_emoticons', \IPS\Theme::i()->getTemplate( 'customization' )->emoticons( $this->_getEmoticons() ) );
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'editor', 'emoticons_edit' ) )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_customization.js', 'core', 'admin') );
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'editor', 'emoticons_add' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'add'	=> array(
					'icon'	=> 'plus-circle',
					'title'	=> 'emoticons_add',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons&do=add' ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('emoticons_add') )
				),
			);
		}
	}
	
	/**
	 * Add
	 *
	 * @return	void
	 */
	protected function add()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_add' );
	
		$groups = iterator_to_array( \IPS\Db::i()->select( "emo_set, CONCAT( 'core_emoticon_group_', emo_set ) as emo_set_name", 'core_emoticons', null, null, null, 'emo_set' )->setKeyField('emo_set')->setValueField('emo_set_name') );

		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Upload( 'emoticons_upload', NULL, TRUE, array( 'multiple' => TRUE, 'image' => TRUE, 'storageExtension' => 'core_Emoticons' ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'emoticons_add_group', 'create', TRUE, array(
			'options'	=> array( 'create' => 'emoticons_add_create', 'existing' => 'emoticons_add_existing' ),
			'toggles'	=> array( 'create' => array( 'emoticons_add_newgroup' ), 'existing' => array( 'emoticons_add_choosegroup' ) ),
			'disabled'	=> empty($groups)
		) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'emoticons_add_newgroup', NULL, FALSE, array(), NULL, NULL, NULL, 'emoticons_add_newgroup' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'emoticons_add_choosegroup', NULL, FALSE, array( 'options' => $groups ), NULL, NULL, NULL, 'emoticons_add_choosegroup' ) );
		
		if ( $values = $form->values() )
		{
			if ( $values['emoticons_add_group'] === 'create' )
			{
				foreach ( \IPS\Lang::languages() as $lang )
				{
					if ( $lang->default )
					{
						if( !$values['emoticons_add_newgroup'][ $lang->id ] )
						{		
							$form->elements['']['emoticons_add_newgroup']->error	= \IPS\Member::loggedIn()->language()->addToStack( 'emoticon_group_name_error' );
								
							\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'emoticons_add', $form, FALSE );
							return;
						}
						
					}
				}
				
				$position = 0;
				$setId = md5( uniqid() );
				\IPS\Lang::saveCustom( 'core', "core_emoticon_group_{$setId}", $values['emoticons_add_newgroup'] );
			}
			else
			{
				$setId = $values['emoticons_add_choosegroup'];
				$position = \IPS\Db::i()->select( 'MAX(emo_position)', 'core_emoticons', array( 'emo_set=?', $setId ) )->first( );
				$position = $position['pos'];
			}
					
			if ( !is_array( $values['emoticons_upload'] ) )
			{
				$values['emoticons_upload'] = array( $values['emoticons_upload'] );
			}
			
			$inserts = array();
			foreach ( $values['emoticons_upload'] as $file )
			{
				$filename	= preg_replace( "/^(.+?)\.[0-9a-f]{32}(?:\..+?)$/i", "$1", $file->filename );

				$inserts[] = array(
					'typed'			=> ':' . mb_substr( $filename, 0, mb_strrpos( $filename, '.' ) ) . ':',
					'image'			=> (string) $file,
					'clickable'		=> TRUE,
					'emo_set'		=> $setId,
					'emo_position'	=> ++$position,
				);
			}
			
			\IPS\Db::i()->insert( 'core_emoticons', $inserts );
			unset( \IPS\Data\Store::i()->emoticons );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons' ), 'saved' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'emoticons_add', $form, FALSE );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_edit' );		
		
		$position = 0;
		$set = NULL;
		
		if ( \IPS\Request::i()->isAjax() )
		{
			$emoticons = $this->_getEmoticons( TRUE );
			$i = 1;

			foreach ( $emoticons as $group => $emos )
			{
				if( isset( \IPS\Request::i()->$group ) AND is_array( \IPS\Request::i()->$group ) )
				{
					foreach( \IPS\Request::i()->$group as $id )
					{
						\IPS\Db::i()->update( 'core_emoticons', array( 'emo_position' => $i, 'emo_set' => str_replace( 'core_emoticon_group_', '', $group ) ), array( 'id=?', $id ) );
						$i++;
					}
				}
			}
			unset( \IPS\Data\Store::i()->emoticons );
			\IPS\Output::i()->json( 'OK' );
			return;
		}

		$emoticons = $this->_getEmoticons( FALSE );
		
		foreach ( \IPS\Request::i()->emo as $id => $data )
		{
			if ( isset( $data['__set__'] ) )
			{
				$set = mb_substr( $data['__set__'], \strlen( 'core_emoticon_group_' ) );
				continue;
			}
		
			if ( isset( $emoticons[ $id ] ) )
			{
				if ( !isset( $data['order'] ) )
				{
					if ( !isset( $orders[ $set ] ) )
					{
						$orders[ $set ] = 0;
					}
					$data['order'] = ++$orders[ $set ];
				}
			
				if ( $emoticons[ $id ]['typed'] !== $data['name'] or $data['order'] != $emoticons[ $id ]['emo_position'] )
				{
					$save = array( 'typed' => $data['name'] );
					if ( isset( $data['order'] ) )
					{
						$save['emo_position'] = $data['order'];
					}
					if ( $set !== NULL )
					{
						$save['emo_set'] = $set;
					}
					
					\IPS\Db::i()->update( 'core_emoticons', $save, array( 'id=?', $id ) );
				}
			}
		}
		
		unset( \IPS\Data\Store::i()->emoticons );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons' ), 'saved' );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_delete' );
		
		try
		{
			$emoticon = \IPS\Db::i()->select( '*', 'core_emoticons', array( 'id=?', \IPS\Request::i()->id ) )->first();
			if ( $emoticon['id'] )
			{
				\IPS\File::get( 'core_Emoticons', $emoticon['image'] )->delete();
			}
		}
		catch ( \UnderflowException $e ) { }
		
		\IPS\Db::i()->delete( 'core_emoticons', array( 'id=?', (int) \IPS\Request::i()->id ) );

		/* delete the group name, if there are no other emoticons in this group */
		$emoticons = \IPS\Db::i()->select( 'COUNT(*) as count', 'core_emoticons', array( 'emo_set =?', $emoticon['emo_set'] ) )->first();

		if ( $emoticons == 0 )
		{
			\IPS\Lang::deleteCustom( 'core', 'core_emoticon_group_'. $emoticon['emo_set'] );
		}


		unset( \IPS\Data\Store::i()->emoticons );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons' ), 'saved' );
	}

	/**
	 * Edit group title
	 *
	 * @return	void
	 */
	protected function editTitle()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'emoticons_edit' );

		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Translatable( 'emoticons_add_newgroup', NULL, FALSE, array( 'app' => 'core', 'key' => \IPS\Request::i()->key ), NULL, NULL, NULL, 'emoticons_add_newgroup' ) );
		
		if ( $values = $form->values() )
		{
			\IPS\Lang::saveCustom( 'core', \IPS\Request::i()->key, $values['emoticons_add_newgroup'] );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=emoticons' ), 'saved' );
		}
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $form;
			return;
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'emoticons_edit_groupname', $form, FALSE );
	}

	/**
	 * Get Emoticons
	 *
	 * @param	bool	$group	Group by their group?
	 * @return	array
	 */
	protected function _getEmoticons( $group=TRUE )
	{
		$emoticons = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_emoticons', NULL, 'emo_set,emo_position' ) as $row )
		{			
			if ( $group )
			{
				$emoticons[ 'core_emoticon_group_' . $row['emo_set'] ][ $row['id'] ] = $row;
			}
			else
			{
				$emoticons[ $row['id'] ] = $row;
			}
		}
		
		return $emoticons;
	}
}