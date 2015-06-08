<?php
/**
 * @brief		Calendar Application Class
 * @author		<a href=''>Invision Power Services, Inc.</a>
 * @copyright	(c) 2013 Invision Power Services, Inc.
 * @package		IPS Social Suite
 * @subpackage	Calendar
 * @since		18 Dec 2013
 * @version		
 */
 
namespace IPS\calendar;

/**
 * Core Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		/* If the viewing member cannot view the board (ex: guests must login first), then send a 404 Not Found header here, before the Login page shows in the dispatcher */
		if ( !\IPS\Member::loggedIn()->group['g_view_board'] and ( \IPS\Request::i()->module == 'calendar' and \IPS\Request::i()->controller == 'view' and in_array( \IPS\Request::i()->do, array( 'rss', 'download' ) ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2L217/1', 404, '' );
		}

		/* Reset first day of week */
		if( \IPS\Settings::i()->ipb_calendar_mon )
		{
			\IPS\Output::i()->jsVars['date_first_day'] = 1;
		}
	}
}