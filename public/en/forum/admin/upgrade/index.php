<?php
/**
 * @brief		Upgrader bootstrap
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		20 May 2014
 * @version		SVN_VERSION_NUMBER
 */

require_once '../../init.php';

if( \IPS\IN_DEV )
{
	die( "You must disable developer mode (IN_DEV) in order to run the upgrader" );
}

\IPS\Dispatcher\Setup::i()->setLocation('upgrade')->run();