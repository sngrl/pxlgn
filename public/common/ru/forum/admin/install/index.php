<?php
/**
 * @brief		Installer bootstrap
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		2 Apr 2013
 * @version		SVN_VERSION_NUMBER
 */

require_once '../../init.php';
\IPS\Dispatcher\Setup::i()->setLocation('install')->run();