<?php
/**
 * @brief		LinkedIn Account Login Handler Redirect URI Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

require_once str_replace( 'applications/core/interface/linkedin/auth.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
if ( \IPS\Request::i()->state == 'ucp' )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=profilesync&service=Linkedin&loginProcess=linkedin&code=" . urlencode( \IPS\Request::i()->code ), 'front', 'settings_LinkedIn' ) );
}
else
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login&loginProcess=linkedin&code=" . urlencode( \IPS\Request::i()->code ), \IPS\Request::i()->state ) );
}