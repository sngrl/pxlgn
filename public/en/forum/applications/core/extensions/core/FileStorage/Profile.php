<?php
/**
 * @brief		File Storage Extension: Profile
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		23 Sep 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Profile
 */
class _Profile
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'MAX(member_id)', 'core_members', array( "NULLIF(pp_cover_photo, '') IS NOT NULL OR NULLIF(pp_main_photo, '') IS NOT NULL" ) )->first();
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\Underflowexception					When file record doesn't exist. Indicating there are no more files to move
	 * @return	int			New offset to be recorded
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		$memberData = \IPS\Db::i()->select( '*', 'core_members', array( "member_id > ? AND ( NULLIF(pp_cover_photo, '') IS NOT NULL OR NULLIF(pp_main_photo, '') IS NOT NULL )", $offset ), 'member_id', array( 0, 1 ) )->first();
		$member = \IPS\Member::constructFromData( $memberData );
		$update = array();
		
		if ( $member->pp_cover_photo )
		{
			try
			{
				/* Using $member->pp_xxxxxx_photo deletes original meaning that there is two attempts to delete the image post move */
				$update['pp_cover_photo'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Profile', $member->pp_cover_photo )->move( $storageConfiguration );;
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}
		
		try
		{
			$update['pp_main_photo'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Profile', $member->pp_main_photo )->move( $storageConfiguration );;
		}
		catch( \Exception $e )
		{
			/* Any issues are logged */
		}
			
		if ( $member->pp_thumb_photo )
		{
			try
			{
				$update['pp_thumb_photo'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Profile', $member->pp_thumb_photo )->move( $storageConfiguration );;
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}
		
		if ( count( $update ) )
		{
			\IPS\Db::i()->update( 'core_members', $update, array( 'member_id=?', $member->member_id ) );
		}
		
		return $member->member_id;
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	\IPS\Http\Url	$file		The file to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		try
		{
			$photo	= \IPS\Db::i()->select( '*', 'core_members', array( 'pp_cover_photo=? OR pp_main_photo=? OR pp_thumb_photo=?', (string) $file, (string) $file, (string) $file ) )->first();

			return TRUE;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_members', 'pp_cover_photo IS NOT NULL OR pp_main_photo IS NOT NULL OR pp_thumb_photo IS NOT NULL' ) as $member )
		{
			try
			{
				if( $member['pp_cover_photo'] )
				{
					\IPS\File::get( 'core_Profile', $member['pp_cover_photo'] )->delete();
				}

				if( $member['pp_main_photo'] )
				{
					\IPS\File::get( 'core_Profile', $member['pp_main_photo'] )->delete();
				}
				
				if( $member['pp_thumb_photo'] )
				{
					\IPS\File::get( 'core_Profile', $member['pp_thumb_photo'] )->delete();
				}
			}
			catch( \Exception $e ){}
		}
	}
}