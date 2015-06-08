<?php
/**
 * @brief		Database File Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		07 May 2013
 * @version		SVN_VERSION_NUMBER
 */

require_once str_replace( 'applications/core/interface/file/index.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

try
{	
	$file = \IPS\Db::i()->select( '*', 'core_files', array( 'id=? AND salt=?', \IPS\Request::i()->id, \IPS\Request::i()->salt ) )->first();

	if ( $file['id'] )
	{
		$headers	= array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'inline', $file['filename'] ), "X-Content-Type-Options" => "nosniff" ) );
		\IPS\Output::i()->sendOutput( $file['contents'], 200, \IPS\File::getMimeType( $file['filename'] ), $headers );
	}
}
catch ( \UnderflowException $e )
{
	\IPS\Output::i()->sendOutput( '', 404 );
}