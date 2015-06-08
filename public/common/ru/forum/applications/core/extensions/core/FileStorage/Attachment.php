<?php
/**
 * @brief		File Storage Extension: Attachment
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
 * File Storage Extension: Attachment
 */
class _Attachment
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments' )->first();
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\Underflowexception				    When file record doesn't exist. Indicating there are no more files to move
	 * @return	void
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array(), 'attach_id', array( $offset, 1 ) )->first();

		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'core_Attachment', $attachment['attach_location'] )->move( $storageConfiguration, \IPS\File::MOVE_DELAY_DELETE );
	
			$thumb = NULL;
			if ( $attachment['attach_thumb_location'] )
			{
				$thumb = \IPS\File::get( $oldConfiguration ?: 'core_Attachment', $attachment['attach_thumb_location'] )->move( $storageConfiguration, \IPS\File::MOVE_DELAY_DELETE );
			}
			
			\IPS\Db::i()->update( 'core_attachments', array( 'attach_location' => (string) $file, 'attach_thumb_location' => (string) $thumb ), array( 'attach_id=?', $attachment['attach_id'] ) );
		}
		catch( \Exception $e )
		{
			/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
		}
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
			$attachment	= \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_location=? OR attach_thumb_location=?', (string) $file, (string) $file ) )->first();

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
		foreach( \IPS\Db::i()->select( '*', 'core_attachments', 'attach_location IS NOT NULL' ) as $attachment )
		{
			try
			{
				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}