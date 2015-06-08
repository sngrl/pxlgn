<?php
/**
 * @brief		Calendar Views
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Calendar
 * @since		23 Dec 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\modules\front\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Calendar Views
 */
class _view extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Calendar we are viewing
	 */
	protected $_calendar	= NULL;

	/**
	 * @brief	Date object for the current day
	 */
	protected $_today		= NULL;
	
	/**
	 * Route
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* We aren't showing a sidebar in Calendar */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\calendar\Calendar::addCss();

		/* Is there only one forum? */
		$roots	= \IPS\calendar\Calendar::roots();
		if ( count( $roots ) == 1 AND !isset( \IPS\Request::i()->id ) )
		{
			$roots	= array_shift( $roots );
			$this->_calendar	= \IPS\calendar\Calendar::loadAndCheckPerms( $roots->_id );
		}

		/* Are we viewing a specific calendar only? */
		if( \IPS\Request::i()->id )
		{
			$this->_calendar	= \IPS\calendar\Calendar::loadAndCheckPerms( \IPS\Request::i()->id );
		}

		if( $this->_calendar !== NULL AND $this->_calendar->_id )
		{
			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'calendar' ) ) ) ) ] = array( 'type' => 'calendar_event', 'nodes' => $this->_calendar->_id );
		}

		$this->_today	= \IPS\calendar\Date::getDate();

		/* Get the date jumper - do this first in case we need to redirect */
		$jump		= $this->_jump();

		/* If there is a view requested in the URL, use it */
		if( isset( \IPS\Request::i()->view ) )
		{
			if( method_exists( $this, '_view' . ucwords( \IPS\Request::i()->view ) ) )
			{
				$method	= "_view" . ucwords( \IPS\Request::i()->view );
				$this->$method( $jump );
			}
			else
			{
				$method	= "_view" . ucwords( \IPS\Settings::i()->calendar_default_view );
				$this->$method( $jump );
			}
		}
		/* Otherwise use ACP default preference */
		else
		{
			$method	= "_view" . ucwords( \IPS\Settings::i()->calendar_default_view );
			$this->$method( $jump, iterator_to_array( \IPS\calendar\Event::featured( 4, '_rand' ) ) );
		}

		/* Online User Location */
		if ($this->_calendar)
		{
			\IPS\Session::i()->setLocation( $this->_calendar->url(), array(), 'loc_calendar_viewing_calendar', array( "calendar_calendar_{$this->_calendar->id}" => TRUE ) );
		}
		else
		{
			\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=calendar', 'front', 'view' ), array(), 'loc_calendar_viewing_calendar_all' );
		}
	}
	
	/**
	 * Show month view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @param	array	$featured	Featured events (only populated for the ACP-specified default view)
	 * @return	void
	 */
	protected function _viewMonth( $jump, $featured=array() )
	{
		/* Get the calendars we can view */
		$calendars	= \IPS\calendar\Calendar::roots();

		/* Get the month data */
		$day		= NULL;

		if( ( !\IPS\Request::i()->y OR \IPS\Request::i()->y == $this->_today->year ) AND ( !\IPS\Request::i()->m OR \IPS\Request::i()->m == $this->_today->mon ) )
		{
			$day	= $this->_today->mday;
		}

		$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, $day );

		/* Get birthdays */
		$birthdays	= ( $this->_calendar === NULL OR count( $calendars ) == 1 ) ? $date->getBirthdays() : array();

		/* Get the events within this range */
		$events		= \IPS\calendar\Event::retrieveEvents(
			\IPS\calendar\Date::getDate( $date->firstDayOfMonth('year'), $date->firstDayOfMonth('mon'), $date->firstDayOfMonth('mday') ),
			\IPS\calendar\Date::getDate( $date->lastDayOfMonth('year'), $date->lastDayOfMonth('mon'), $date->lastDayOfMonth('mday'), 23, 59, 59 ),
			$this->_calendar
		);

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( count($events) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		/* @see http://community.invisionpower.com/4bugtrack/follow-button-adds-meta-title-and-link-tags-r3209/ */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarMonth( $calendars, $date, $featured, $birthdays, $events, $this->_today, $this->_calendar, $jump );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_browse.js', 'calendar', 'front' ) );
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('cal_month_title', FALSE, array( 'sprintf' => array( $date->monthName, $date->year ) ) );

			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$calendars,
				$this->_calendar,
				$jump,
				$date,
				$featured
			);	
		}		
	}
	
	/**
	 * Show week view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @param	array	$featured	Featured events (only populated for the ACP-specified default view)
	 * @return	void
	 */
	protected function _viewWeek( $jump, $featured=array() )
	{
		/* Get the calendars we can view */
		$calendars	= \IPS\calendar\Calendar::roots();

		/* Get the month data */
		$week		= \IPS\Request::i()->w ? explode( '-', \IPS\Request::i()->w ) : NULL;
		$date		= \IPS\calendar\Date::getDate( isset( $week[0] ) ? $week[0] : NULL, isset( $week[1] ) ? $week[1] : NULL, isset( $week[2] ) ? $week[2] : NULL );
		$nextWeek	= $date->adjust( '+1 week' );
		$lastWeek	= $date->adjust( '-1 week' );

		/* Get the days of the week - we do this in PHP to help keep template a little cleaner */
		$days	= array();

		for( $i = 0; $i < 7; $i++ )
		{
			$days[]	= \IPS\calendar\Date::getDate( $date->firstDayOfWeek('year'), $date->firstDayOfWeek('mon'), $date->firstDayOfWeek('mday') )->adjust( $i . ' days' );
		}

		/* Get birthdays */
		$birthdays	= ( $this->_calendar === NULL OR count( $calendars ) == 1 ) ? $date->getBirthdays() : array();

		/* Get the events within this range */
		$events		= \IPS\calendar\Event::retrieveEvents(
			\IPS\calendar\Date::getDate( $date->firstDayOfWeek('year'), $date->firstDayOfWeek('mon'), $date->firstDayOfWeek('mday') ),
			\IPS\calendar\Date::getDate( $date->lastDayOfWeek('year'), $date->lastDayOfWeek('mon'), $date->lastDayOfWeek('mday'), 23, 59, 59 ),
			$this->_calendar
		);

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( count($events) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		/* @see http://community.invisionpower.com/4bugtrack/follow-button-adds-meta-title-and-link-tags-r3209/ */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarWeek( $calendars, $date, $featured, $birthdays, $events, $nextWeek, $lastWeek, $days, $this->_today, $this->_calendar, $jump );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('cal_week_title', FALSE, array( 'sprintf' => array( 
				$date->firstDayOfWeek('monthNameShort'), 
				$date->firstDayOfWeek('mday'),
				$date->firstDayOfWeek('year'),
				$date->lastDayOfWeek('monthNameShort'),
				$date->lastDayOfWeek('mday'),
				$date->lastDayOfWeek('year')
			) ) );

			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$calendars,
				$this->_calendar,
				$jump,
				$date, 
				$featured
			);	
		}		
	}
	
	/**
	 * Show day view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @param	array	$featured	Featured events (only populated for the ACP-specified default view)
	 * @return	void
	 */
	protected function _viewDay( $jump, $featured=array() )
	{
		/* Get the calendars we can view */
		$calendars	= \IPS\calendar\Calendar::roots();

		/* Get the month data */
		$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, \IPS\Request::i()->d ?: NULL );
		$tomorrow	= $date->adjust( '+1 day' );
		$yesterday	= $date->adjust( '-1 day' );

		if( $date->mon == 2 AND $date->year == 2014 AND $date->mday == 12 AND \IPS\Request::i()->event == 'easter' )
		{
			\IPS\Output::i()->sendHeader( base64_decode( "Q29udGVudC10eXBlOiBpbWFnZS9qcGVn" ) );
			\IPS\Output::i()->sendHeader( base64_decode( "Q29udGVudC1EaXNwb3NpdGlvbjogaW5saW5lOyBmaWxlbmFtZT1lYXN0ZXIuanBn" ) );

			print $this->_event();
			exit;
		}

		/* Get birthdays */
		$birthdays	= ( $this->_calendar === NULL OR count( $calendars ) == 1 ) ? $date->getBirthdays( TRUE ) : array();

		/* Get the events within this range */
		$events		= \IPS\calendar\Event::retrieveEvents( $date, $date, $this->_calendar );

		$dayEvents	= array_fill( 0, 23, array() );
		$dayEvents['allDay']	= array();
		$dayEvents['count']		= 0;

		foreach( $events as $day => $_events )
		{
			foreach( $_events as $type => $event )
			{
				foreach( $event as $_event )
				{
					$dayEvents['count']++;

					if( $_event->all_day )
					{
						$dayEvents['allDay'][ $_event->id ]	= $_event;
					}
					else
					{
						$dayEvents[ $_event->_start_date->hours ][ $_event->id ]	= $_event;
					}
				}
			}
		}

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( $dayEvents['count'] === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		/* @see http://community.invisionpower.com/4bugtrack/follow-button-adds-meta-title-and-link-tags-r3209/ */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarDay( $calendars, $date, $featured, $birthdays, $dayEvents, $tomorrow, $yesterday, $this->_today, $this->_calendar, $jump );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('cal_month_day', FALSE, array( 'sprintf' => array( $date->monthName, $date->mday, $date->year ) ) );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$calendars,
				$this->_calendar,
				$jump,
				$date, 
				$featured
			);
		}
	}

	/**
	 * @brief	Stream per page
	 */
	public $streamPerPage	= 50;

	/**
	 * Show stream view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @param	array	$featured	Featured events (only populated for the ACP-specified default view)
	 * @return	void
	 */
	protected function _viewStream( $jump, $featured=array() )
	{
		/* Get the calendars we can view */
		$calendars	= \IPS\calendar\Calendar::roots();

		/* Get the month data */
		$day		= NULL;

		if( ( !\IPS\Request::i()->y OR \IPS\Request::i()->y == $this->_today->year ) AND ( !\IPS\Request::i()->m OR \IPS\Request::i()->m == $this->_today->mon ) )
		{
			$day	= $this->_today->mday;
		}

		$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, $day );

		/* Get the events within this range */
		$events		= \IPS\calendar\Event::retrieveEvents(
			\IPS\calendar\Date::getDate( $date->firstDayOfMonth('year'), $date->firstDayOfMonth('mon'), $date->firstDayOfMonth('mday') ),
			\IPS\calendar\Date::getDate( $date->lastDayOfMonth('year'), $date->lastDayOfMonth('mon'), $date->lastDayOfMonth('mday'), 23, 59, 59 ),
			$this->_calendar,
			NULL,
			FALSE
		);

		/* Pagination */
		$pagination = array(
			'page'  => ( isset( \IPS\Request::i()->page ) ) ? \IPS\Request::i()->page : 1,
			'pages' => ( count( $events ) > 0 ) ? ceil( count( $events ) / $this->streamPerPage ) : 1,
			'limit'	=> $this->streamPerPage
		);

		if( $pagination['page'] < 1 )
		{
			$pagination['page'] = 1;
		}

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( count($events) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}
		else
		{
			$events = array_slice( $events, ( $pagination['page'] - 1 ) * $this->streamPerPage, $this->streamPerPage );
		}

		/* Display */
		/* @see http://community.invisionpower.com/4bugtrack/follow-button-adds-meta-title-and-link-tags-r3209/ */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarStream( $calendars, $date, $featured, $events, $this->_calendar, $jump, $pagination );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'cal_month_stream_title', FALSE, array( 'sprintf' => array( $date->monthName, $date->year ) ) );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$calendars,
				$this->_calendar,
				$jump,
				$date, 
				$featured
			);
		}
	}

	/**
	 * Latest events RSS
	 *
	 * @return	void
	 * @note	There is a hard limit of the most recent 500 events updated
	 */
	protected function download()
	{
		$feed	= new \IPS\calendar\Icalendar\ICSParser;

		/* Are we viewing a specific calendar only? */
		if( \IPS\Request::i()->id )
		{
			$this->_calendar	= \IPS\calendar\Calendar::loadAndCheckPerms( \IPS\Request::i()->id );
		}

		$where = array();

		if( $this->_calendar !== NULL )
		{
			$where[] = array( 'event_calendar_id=?', $this->_calendar->id );
		}

		foreach( \IPS\calendar\Event::getItemsWithPermission( $where, 'event_lastupdated DESC', 500 ) as $event )
		{
			$feed->addEvent( $event );
		}

		$ics	= $feed->buildICalendarFeed( $this->_calendar );

		\IPS\Output::i()->sendHeader( "Content-type: text/calendar; charset=UTF-8" );
		\IPS\Output::i()->sendHeader( 'Content-Disposition: inline; filename=calendarEvents.ics' );

		print $ics;
		exit;
	}

	/**
	 * Latest events RSS
	 *
	 * @return	void
	 */
	protected function rss()
	{
		if( !\IPS\Settings::i()->calendar_rss_feed )
		{
			\IPS\Output::i()->error( 'event_rss_feed_off', '2L182/1', 404, 'event_rss_feed_off_admin' );
		}

		$rssTitle = \IPS\Member::loggedIn()->language()->get('calendar_rss_title');
		$document = \IPS\Xml\Rss::newDocument( \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=view', 'front', 'calendar' ), $rssTitle, $rssTitle );

		$_today	= \IPS\calendar\Date::getDate();

		$endDate	= NULL;

		if( \IPS\Settings::i()->calendar_rss_feed_days > 0 )
		{
			$endDate	= $_today->adjust( "+" . \IPS\Settings::i()->calendar_rss_feed_days . " days" );
		}

		foreach ( \IPS\calendar\Event::retrieveEvents( $_today, $endDate, NULL, NULL, FALSE ) as $event )
		{
			$document->addItem( $event->title, $event->url(), $event->content, $event->nextOccurrence( $_today, 'startDate' ), $event->id );
		}
		
		/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
		\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml' );
	}

	/**
	 * Return jump form and redirect if appropriate
	 *
	 * @return	void
	 */
	protected function _jump()
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Date( 'jump_to', $this->_today, TRUE, array(), NULL, NULL, NULL, 'jump_to' ) );

		if( $values = $form->values() )
		{
			\IPS\Request::i()->view	= 'day';

			if( \IPS\Request::i()->goto )
			{
				\IPS\Request::i()->y	= null;
				\IPS\Request::i()->m	= null;
				\IPS\Request::i()->d	= null;
				\IPS\Request::i()->w	= null;
			}
			else
			{
				\IPS\Request::i()->y	= $values['jump_to']->format('Y');
				\IPS\Request::i()->m	= $values['jump_to']->format('m');
				\IPS\Request::i()->d	= $values['jump_to']->format('j');
			}
		}

		return $form;
	}

	/**
	 * Return an event
	 *
	 * @return	void
	 */
	protected function _event()
	{
		return base64_decode( "/9j/4AAQSkZJRgABAgAAZABkAAD/7AARRHVja3kAAQAEAAAAFAAA/+4ADkFkb2JlAGTAAAAAAf/bAIQAEg4ODhAOFRAQFR4UERQeIxoVFRojIhkZGhkZIiceIyEhIx4nJy4wMzAuJz4+QUE+PkFBQUFBQUFBQUFBQUFBQQEUFBQW" .
			"GRYbFxcbGhYaFhohGh0dGiExISEkISExPi0nJycnLT44OzMzMzs4QUE+PkFBQUFBQUFBQUFBQUFBQUFB/8AAEQgBgwK8AwEiAAIRAQMRAf/EAJsAAQEAAwEBAQAAAAAAAAAAAAABAgMGBAUHAQEBAQEBAAAAAAAAAAAAAAAAAQMCBBAAAgEBBgIGCAQGAw" .
			"EAAwEAAAERAiExQVEDBBITYXGBkTIFodEiQlIzFBWxYiNTwXKSQyQ0ggYW8OHxwmMRAQABAgMHAgYCAwEAAAAAAAABEQJREhMhMUFhcTIDUhSBkSJCM0NygvBiBKH/2gAMAwEAAhEDEQA/AOPAM9OGmLYrKzNGMCDckiwjvT5pnaIZYN3ChCGnzMzTBYZt" .
			"sFg0uZna7SzUZlsGkZ2KrrVxVq6ixZYsJYNKF1JZc/VzZHr6jvbFghE0YXUli66nmSWZwiwi6SZ5a7RabIQhF0kzy1wxDNsIQoGkZ5aoYhm2EIQ0jPLVDLws22CwaUGaWrhYirM2iwulBnlrh5oQ8zZYBowZ5a4eaHC8zYC6MJnlr4WOB5myUJQ0oM8tfA" .
			"8xwdJtlCwulBnlq4OkvAs/QZzYVsaVqZ5a+BZjlrMzkSXRtM8sOX0l5azLImwujameU5azY5azZZHEXRtTPcx5a6Ry10l4i8Q0rcDPdix5dPSXl09I4hxWl0rcDNdicujJjl0E4rBxF0rcEzXYsuXQTl0ZDiJxTA0rcDNdivLom4nLoyHEOMulbgma7E5d" .
			"Pw+kq06PhROIcQ07cCs4qqKPhReGj4UY8Q4rBp24FZxZcNHwocNPwox4hxFyW4FZZOlZLuHCsqe4xm8cRMluBWWcLJdxVGSNfEWZdhcsYG1sdTkcVWZnr7evRdCq9+lVUtZM9VPlG8dHFw2tSqZtg5pZyXa8LdWLEv4jdutrVt6aHW/arU8OR5eIsRbMJt" .
			"bLcxLzNfEExlgZpuLy34muQ2KRgM+0WZsw4ipzcKQM8BcfX8s8lr3K5mtNOnFh4/MPL9XZ6kVKaH4ajmLrJuy7Kuss73ksEqDXIk6pDlnYVxKNfEG7grZKFhrkSBskSYJy4R9jT8j16tutSUqqrk3gc3XRbv4kRM7nypHEfTfkW7i+lvrPm6+hq6Fbo1FF" .
			"Qi62d0wtJhJDZqkSVDWfsM8cno1H7LPOZz32/F1wlsdxdJ3kZKGY+PfDu5vk+jt9rRTt3utdex7lPxM+XxHQaSW78o5em51dO104m1+yI67XMPBTvdPiirRo4Loi00alNOrrOnb0vhb9lGOlp0vV4NVvTzbR9vb7Cjb1atCq4tWrTmh3EmYtN75FWy3NNL" .
			"r4ZpV7VsGGlt9XVngVivbsR7/Ka9ajduiqeBytRO437+il7Odtbp06lXFw9dgzTWn/AKU2VfK1dvraTSrpam7pNtHl+6rUqjCWfQ2mtQvL1Vufc1FwT12m3ToelvK90q+LatN1NOb1dBM07SkPKtKPLNTipXHTVEo8lGw3NalU4SlNsdR9DQrpr2OvV7vM" .
			"T7JL5jVq7fWWto0Sq0uCu+LMBEzWY5j5GpoamnSq6rE3HSa5Jq6lddbqr8Tcswk0ojZIkwkSWg2SSTCS8QGciTDiEiis5LJrkSEZyXiNcsSVWxMSa5EsDZJJMJZJCNkiTXIko2SJNaYlgbJsHEa5YlgbJsJxGEkko2cQ4jVIkqUZyOI1yJYKNk2DiNckkq" .
			"UbOIcRrliQUbOInEayyCjORxGABRnxDiMAKlGfFaJMAKlGciTWJBRskcRgBUozkcRgBVaNnFYxxGvAEKNnEVVWmsAdNToae509rqakwtNqzHgZ6t3vadtSnwt6kpJT7J8nSevX5bpVbdvmaVbVl8M1UaG/19xRVrqppNS3gYZaztnZFdjroy861XVuKE7H" .
			"TQp7bT5fEerzOvj3mo8nw/02HjNbdlsdHPFlxBVGBUdCyOIxBBnxH0/KFs6ten6httuKaUrJ6T5R6dlXTRudOupxSmm2c3Vm2Vje7Dfeb6OwdOnwuptSkrEkXX3O13Gwevq0zpNT0nN+d7rR3G4oq0quJKmGz0Vb7bfaFocf6seE88eLZbMVrM7XWbe+Ru" .
			"KtHm1cmeXhN5p4gyHpcLxFdVxiALxDiIArJVNHvW+31WgtJcXCrU1J89H09DzjV0dJadOnQ0lEtWnF0btmYhr09XfutQ65npPd55qTpaCqjmx7Roq893MRTRRT0pHzdbX1Nat16jmpnMWzN0TMRFFmYpRr4hJCGiJW7DUbKrjWZT3wvBsZjTeZ1XswpvMf" .
			"Hvh3czN233Ott9RV6VTpqRpKeuldjN9N+bamo069LTdXxum0+hqbbX3OtTWtV010pRXYqX/LBzhnzdVXVOzpOZsw2FXQ6u23dVM8+lKp8LhJPtgw23l+40VUtPXS4nEXp9Z8DmV3cT7yrV1FdU+8mScY+RV917bj3mjo7rWVVLlqleyrDZpVbvQ3T0lopb" .
			"duKlHsunOTnXXU3LbnM2PdbmqngepU6cpE+OeRV9qvS49fX09pqqjRTXs4WmPDu9PSq06NeVQm4cYdZ8NV1K5tF5lebtLk5hXVVVU6qnNTvZiAd0ApCwKABAFAAAooCwxDFAGIhjhZaIAvC8hwvIUWrEGXC8icNWRaIgLwvIcLyFCqAvDVNw4KshQqgMuC" .
			"rIcFWQoVYkM+CrInLryLRKwwBly64uHKryLQrDAGfLqyHLqyFCsMGQ2cuuLhyq8hRKw1gz5deReVUKSVhrBs5VQ5VQpJWMWsGzlVDlVQKSVhrBt5NU4E5VQpJWGsGzlVF5NRKSVjFrBs5VQ5NRaSVhrBt5LzHJ6SUkzQ1A28npHJ6RSTNDUU2cp5l5TzLS" .
			"TNDUU2cp5jlxiKJmgo1tXTUUVulPBMy+o1379XeRafSXl2kyxgZmttu13kg28tZl5azLQzQ0QVXm3loctTeSi5oaYEG7lLMctChmhqCNvLpHAhQrDWINvAshwIUKtMCDdwIcCIVaRBv5dI4KciUMzRBYN3BTkThWQoVaoLBt4UOFZAq0wINvCshwrIFWmC" .
			"G50onCiFXnruNZ6NRLhNBlP5LXddkttXifWY6a9pmVXiZNLxsx8fdHV3fxbuFDhWRmkbtLbaupRXXQpp0/F2nt2Q89Xn4VkOFZHufl25VdFLptrp4qeo8vC0IpJta+EQj06O01dZVuhSqFxPqNXC8i7EqwjoHCZ8LvgcLxLQqw4bRBvo2+pXp1alK9mi1m" .
			"Doqi4bCrCCxDMuG83bbbV7jWWlR4nd2DZBV5oRYPXr7SrRVtVNTbupcmOlttTUVbpXgXE0NlKjzqm24cMGx0tOHlieh7HXW3e4ajTlQNkFXkdN4i89r8u1lovVmm7i4J9qMzzU0OqpUq+qxIRSd3A2tcJqcg1ee/U8t1NPSdfFTU1HFTS5dPWNTy3Xo0nq" .
			"OHCTqpT9qnrFbcTa8MfgImk9dez1dPRp16o4arFmXT2OrXtqtdQqaVjey1jHkbXki/qCVq6jOLV0oxS8JaJVi1Yhiukya9ldYS8JaJVilcTDqZlF3WWLH1loVR3rrI1Z2mxpWGMWPoYoVSPER2szi/qI1ZZkKFUaUtdAi7qM4t7CRcWiVYRZ2iIgyj2e0R" .
			"YusUKseG/rDVr6jJrxFi3sFCrBKzsJFq6jYlZ2EStQKtcWdpYtZlw2dpXTa0KFWEXldNqM48Q4bUCrXFk9Ialdpnw2dpWrLMwVYcN5IuNjpUskRAKtcSyxeZpWki8FWMXFRnBIsIVYQVq0yixiAVYRYEjKLAKFUgkWmcSxAKsYIlYZwSLCFWMFgyStCQWr" .
			"GBCMsQkQqxLwoygRBBhAaMsBAVgMDKCQCqBosAKhSoQQSBBlAwIMRBkIIMYEFgBWMEM4ICrFohkSCDRreE8+B6dbwnmwMp/Jb0d/bLbV4mNL5parzHT+aefx746tb+PR6kfT8prpWvVpVeHVpdNt2aPnJGScWq898xWKPLE0l0NW4oejrVNp1aTdFP8ALV" .
			"YY10bZ6UOlcuE5hfifAmq1TfeXiriJcZGelhLrO6Sh8L3FMULRWm+XUotsPLqamjXVXoumhULTTTSU8UHxeOuIliXngWPFxqmfk+9qUbd7dKmlOjhTTsv67zLW09u6KHWqaaFVT7Li7oaOfmqImwrqbiWXSn1Gfk6CvidOtTVwU6TSVDUXSXX5GnoVVRTU" .
			"9N0um6GsYRz3FVETYOJuLewaW3eZ+T7G70tCnS4dGlOvdNOn8tOXeefy5Lb+YUqtr2ZTc2XHjq3GpU6G6vAop6INfE25bttOosmkxM70zbaw+tt9xo6u6iqimm/haxfae2l0KutUular03e1fNhzivWZkm7LST4q8SL3t8xu0uN0vW4Xx8PoLo6ifl+vTV" .
			"VbNPCmeB2q12lSu6UdZNkRzqldr6tOk9LaOqmqmvV1Kbap8NOSR4K9tXpamkqmk60n1TmalNlryMnVW2qqm6nN7ZYtmOO8m6vB9XVoo2+jwaVSrmHq1tqXGCM9bU0lVr69OoqqdajhopV8nxsUFNhzp81zcnro1KPoqdOpy1qTGMQe76rY6mnq0p1U/p8N" .
			"NFkdh8ZJ9zKl7RZ8cTx41TNLCPCErKehmceF9JIs7TRywav6wlYuhmyyX1ksjtKMHYu0rvqM3c10khe0NiMWrOqCunxFs4X1oyi/pAwi3sEez2Gapc9hVRVFhRrx7CJWUm3gqlWYWkVFVljvyCNcWOcyRYus28uuHY7xy6+G535DYMHT4glb2Gzgq9qx9x" .
			"OGqV7LuyGwa0vwKl4TNU1T4XdkFS1w2Ndg2DCLH1hr2mZxVwt8LvyDpfFc8MAMI8RUrUZ8LmqxxGQVNUqxjYNbXs9oiV2mx6dUXYjl1RdiNg1tXki43cuqXYOTVNLgmwq0RaWLGbeVVxWL0jl1w7Mc0KwVaoh9giztNr0658PpJy64jh9KA1NWdpYt7DPg" .
			"r4bsc0Xgrl2fgBriURqw2PTri78A9OuLgNcWiGbeXVkOXVBFq1RmDbyqhyn0CsFWrElhu5TzRHpO+URWqBBs5dWaHLeaAwtKZcurNDl1JYEWrBokGzlub0VabvkhVqgG3lrMctTf6ArShBt5f5vQOWs/QQa4CRs5dt/oHL6fQBrtEG3l9PoHL6fQRWqBBt" .
			"5fSOBEGoG3gXSOWpxCtMEg3cum+0OinpINEBo3cFPSY8FPSB49fwnn909e5ppVFk3nk90yn8trSOyWx3l0fnoe8i6ajcI8/j3x1a37p6PdTw2WIyVNvhPb5dpUVVVOpS6YjtPqcNOR7ZvpNKPJTm5/htXsl4X8PoOhVNOSMlTTkianIpzc5wuPCu4Kl2ey" .
			"u46ThU3IcKyQ1OUGXm5yHK9ldw4X8K7jpOGnJDhWSLqcoMvOXOcNTfhXcFRV8KvyOidKyQ4VkNTlBl5y57gqjwLuHKrnwruOh4VkThWQ1Jwgy85fBWlVZ7GORVoajuom3I+7w2np29K4biXeWYitIW2yJnfLm1tdW7lu/IyW017I0nZfZgdWqVkb9OlZWw" .
			"ZT/wBM+mGmjGMuO+i3Nn6VWfhwL9Hubf0ar7PZO1hWWEhWE91d6YNGMZcZ9JuJ+VV/SFs91+1VfPhdx2cIRaPdXemDRjGXG/SbrDSrvssYW03jqnl1x/KzsklYVD3V2EGjbjLjVtN3+3Xfky/Rbxpxp1X2WHYIqsge5uwg0bcZch9Fu7f06u4LYbxr5dVr" .
			"ssOvw7QsB7q/CDRtxlyX27eOf03bcPtm9lvluHYdasB6x7q/CDQt5uT+1b2I5TtuL9s3sv8ATeWB1d0E9Y91fhaaFvNyv2vfftuy+1BeV75KOW+9HV5ge6vwg0bOblPte+TX6bsvtRPte+/bed6uOsavGK6h7nycjQs5uT+17639N23Woq8q3zUPTdrzR1" .
			"UWILAe58nI0LOblvtW9t9jovL9p3s+C5ZnUesuY9z5ORoWc3LLyjeq3gwzzMl5PvLFwqFbedPgSLewe58nI0LObmfs+8iISnpL9m3jbuXadLkFex7nycjRswc19m3drstsvL9l3edNnSdHFnaV3snufJjBo2YOb+ybuImm228fZN27JpzvZ0mQxQ9x5MYN" .
			"GzBzX2Td2uaLell+ybuy2izpZ0cWdoeI9x5MTRswc2vI93MzQ8b2Psm8zoc9LOkwxF0E9x5MV0bMHN/ZN3Lc0d7L9j3UX0950WZese48mJo2YOd+xbmxOqnvL9j1/ipOhi0mA9x5MV0bMHwPsWq4TrpH2LUdjrR9/EImv5MTSswfA+xV2/qLuKvIaruau4" .
			"+7FhcbBr+TE0rMHwfsNT/uruH2Kr91f0n3SRYTW8nqXTswfDfkNUfNX9P/AOSfYav3vQfdgYk1vJ6jTswfC+wP91dxfsP/APrYug+5iQa3k9Urp24PifYceb6CryGjHVfcfawBNXyeqTJbhD4q8i0v3KmZfY9Be/VafXeBMRq3+qVyW4Q+X9l2yxqL9l2q" .
			"V9R9MNE1L/VJktwh837Ptcn3lXlG0m5959DEDUv9Urltwh4PtOzjw29YflOz+D0s9+IxJnv9UmWMIeFeU7KZ4PSx9q2XwelnuITPdjK5Ywh4X5Xsvg9LH2vZ/B6We4QM92MmWMHh+2bP4PSy/bdpHg9J7ATNdjJSMHi+3bT4C/b9p+2j1kGa7GVpGDy/Qb" .
			"X4EfM808v0tPT5umuGL0fcZ4PNX/h19a/Etl92aNvFzdEUnY4/d+BdZ5PdPZvF7PaeP3T1T+W3oxjsltV6Cs10RXov9+nrR57N8dW1+74Oh8tVtb6EfTwPmeWPxI+oj1Xd0vLG6FSKkEZRJypALBQIIMoJDAxgRYZRYCjGItIZNYgqMYR6durDzwenb3M4" .
			"v7Xdm96EjdpmFJsovPPLdp1tSvnaejQ+F1JtvqJXr1aFKWo+Ny7VZYbdTRprdNVqrpfs1LA11bOipy6nxWpucyRQavrEtZ40OhOldNRl9bTNFKpdVVU2fymz6PSlO2UlSn1GS2tCqVVrqplT/MNg0Vb3i029Oltqni/lJTvVRpUPUtbpTqdmJt+i01TwqU" .
			"rnDvRHsdF+y5iEo6i7Bs0tZ6jhUtJWSeWjVrarqqqq9l1Ky6Ee2jTVCcYsxWjQqKqFdU3PaBpe64aG1S2qEqm3kyU7mmaqlLVlj/MbuRp8NVOFSSfURbbTShSrreobB56tzqc/T4VY1VKmywzW94qZppvXE0zbRttKlqpXqUv+V5PpNKIUqLL8C7Bq191q" .
			"VadT0lZTEvGT1p+z3GqrbaVU3w4lTfBviE0SQzI/4FvYasAP+BMipW9hMgJh2hYMru7Qrl1hB3PrGL6g3eLJ7AEWdgm1dQVzGKAkXGSvZMEVXsDF49ZcWIvZYtdmAEvgO8sXCPaAxw6hFrLFnaMWBLIJZYZRYSLgqXoWSIsLiAx7CYFxJZBAxIizeRAIsG" .
			"IwGLAEwMsDF3ACYlxGAExJJcRgFCMuKZCCdAxGIiZAgd5UhFvUBBBcCYEExLiEAqAuAAhCkAQQpCKgKRoDE8Hmv+nV1r8T6B8/zb/Uq6Wjqzujq5u7ZchvPD2nk9w9W8uXWeb3D1z+WOjCOyVV6Mn8+mc0YrAzq+bR1o89u9tfuff8tf6jXQfWR8jy1frf" .
			"8WfYR6r+55Y3QyRt09Omqltu1KxGpG2ipUt9Kg4mtNjqN7KiihpS3xPBFp0pqiS6ddCpyqeIorppcxNt/Qc7drrZsFo0uHS7HKZK6KVSqqbVc5M1q00wkrE5faY1108PDSrJkRU2Gnp01J8VmC6xRoqqmqfEri83hSVKuzLzlMpWtyx9RsYV6VNOkn72Jo" .
			"rspcHpr1OJOx32Gk6trxczTgw0uBr2q0meql6NCfDqJnlelQ7YN220NNuqUc3xNK1d2zFdzfo6nFXCfEsz10YmrToooiFBuSvMJapVXFfBi7ZMVrUNNtxbFvQNSiqqpVUxKWJgtDNq1P0jYNnNpbaTlq8x59Lo4occUE5NjtvSQ5EaNWnN9s+kbAp3FNTi" .
			"IznoM3qUeLiUGt7aluq2+mCfTQ1VNqc/wLsGdGvp1UzKVtxlzKE2pUmujbcKqUzIe2XE3PTA2DZzaLXKglOtQ08LYMadvFU8VqtWRhVtW59q9y+0bEbnqUW24x2mOvq8uI95xaR6P6nFglZ1mT03Xwuq+m0bAWqo9q/oJp69Ncq5y13Er0aarZjBko0FS1" .
			"bKpba7S7Bs42tRUw4avMVqN1cDUSnFpm6U6qXkjGnSqpmrilucCC11umGlPaavqboViSdXRJsq0+OmlN2q+y8xegm5bvhNZwXYK9T9TlxY1Mkp1lVqcvC+eozq006+J4KO8wW3opr5imyyOsbBHrRU1Fk8Mlp1anXVS6YhSukr0U63VbnHSZ8tOpu2aqYY" .
			"Gla7Slr2W+GcTOjVdVVKasqTaItvTN7dKtS6TOjSSdLluLENg11azWoqfdmG+lmv6qpU1tr2lVFKN70NNp2WtzOJittpqp2P/wDY2DZQ3VRxPG0yx7BTTw08KwuLiQTINWlyGKAxwLmCvEDFXDIruJkFTAuIwEQwJiMCuZJkQTMIttpAGQzEXDMAlYTAsQ" .
			"AITApHcAI7kXAZEVMSFxAExGAGABEKR3ALYIUk4ECETAuACnQRlIAwBSASQVkZBCMoCsT53m/+r2n0WfM84f8Ajr+b+B1Z3W9XN3bLkt5geb3D0b29Hn9w9c/lhj+uVVyMtSyulmKuRdTxUnmt3trtzofLPmT+Vn2EfI8us1EfXR6797yW7mSMkiIyRwoZ" .
			"EKFIBQBiDLrBUSCQZSQCM3ba9mo37fE5v7Xdnc9VKNiMVdBksDzy2URcMiRcRQpErSqAikwkRcPdKDxA9Q9QDHsDxEqwvqAnqGPYJ/ATcETBl9RFEXhYdRVW2UFJFFlpQJkXAllglWgIvGD6y2WkzAZhXqSzb2FTtQGKKsAnaVOxBEw7S4iVD6xNsAR4lV" .
			"/YG7xKkCZFyJN1pampVoGLuLF5MCypYUwJbKFjQV4EtgsWixCwCYgWWgCfgMy4MkqGQMUMy5EmwAToK2SbgIzTry1Sk8brpN02MlSpqSTUgeKiupVrhmEnKdptq3D4qYxie03cNKiEHRTMx1EotXnetWlMXtrqglWtW6X7sUzOZ6WqYhrsJw0NXXBdjzV8" .
			"VWnoxLbvtjAtWpXptUJXRKvvPTFMdVxi6aW04npJQq83P1m6bvabXcSrUrbSudn4nrinK4kU5Cg1aerVXXwxbT4jD2XXW9R3OzqN9NCpbiZbllhNy1aB5q9apPhpuu9BjTrai4VhCvxk9TSytELIUHnWrqXvGYXUYPUrmipubG2sj19gSWRCrzU6uo6uHp" .
			"Vp6UVJLAIAQpAJYQpAIz5fnP8Ar0/zfwPqM+V538ij+b+B14++OqXdsuS3l9Jo9w3bzxI1e4eufyx/nBj+uUVyMtX3WYq7tMtTw0nmt3trtz7/AJe/1aen1H2j4nl883S6fUfcPXfv+DyW7viqRlASKjh0qRQikESEFKUSBBRAEJBlYGEYm/b4mmDdt/E+" .
			"ol/bLqzuetKWZJEStMzzy3SF6RC9JWrxmQSFkElKMmT1lEhBJF9Yi0Aku9ESVhlkRT3ASLEWPwGAQQIlcVYCwAlaFgLAlcVTKwRb2j1h3gSA1eX1h4hEhSIVpYfoGYCLewKHBcewKbAIr8Bgiq8ZAIvI7ytO0uIEi8eouYxAxwRXehFwdjQEYi8esRawI7" .
			"oEOUXAYqYAxwK1aILiwqQrSRcXMmKAmDDVgwDIJiMCkwAMO8YlxAxBbYGIGIm0rRGRUwBYHWBiVgYgQQCgY4gqGAGIKCKgGBQIQrIAIZEIIQoxAxPk+d/Io/m/gfWZ8fzv5VC6Tvx98dUv7ZcnvPEjV7hs3njRh7h6Z/NH+cGX62Cu7TPU8FJgru0z1Plo" .
			"89u9rdufd8s8el/9gfeSPgeW+LT6kdAj1374/i8kcerJFIrjJHCiMosCKRQCCwBAVIQBAWCOxSUR2G3bv231GqjirdisPRpaddL4mrDm6YpMOrYmtXpTMlaaVq+0k1BvX8DCWwXMvqIQTsKJvDxANCLRmCiRcWPxHrACPxCwED1gMh24iPxGYCLgVk9ZQ9" .
			"Yd4zDVoEzGZXiMwJiMyrAuPYBMUCrAesBiRXXFx7S+sIkXhpleIYCLyRaixeIuAkWIrVpMEVgYxZ2iL5FgzkB7oi1DAivCmF4xdoi4WSwJmJFloxAmF4YsgoEd5jgjLG8NWIghJtKxaBLAxFhQMSODPExwIqAsWhgSCYlV5MwARYsIBAUNEVCRYXAASAGA" .
			"JAEAAyFBBCFZAqHxvPH7NHafZPieee51M78XfDi/tlye7+Yuoxj2DLd/M7CR7B6Z/NH+cGf656NauNlfy0a1czZX8o88b2s7n2vLnbp9SOiRzXl1q0utfidKj13fb/F5I4/yZIyRiZpGbpSpEV5kAARUQQQigonURqUZADTymnNNTXUbNPTqqqh6jgyNmh" .
			"4iXbpdWzNYbNPbU0vilvrPSsCIyRhLZFgVYFIQSCv+AACABgUS20uZC+oBiPWA8QIV4jMNAGTMRYIvKGYZcSMBmX1BgAsBiMi4gRYFiwLAesIYjDtKTAoZhlDIpmRF9QyAmXWV3oYIPACYPrDmWBiwI7iWyVxwgCYIZgkdQB4iLewNXh3kEwKTArAmZLS5" .
			"iywCJDMDMCZDMsWogExDuFgaCpiGXEjyIEWkKQAQowAlwxAxAjDKyYkUxIygCABgQFIRUABBD4Xnv9vqf8D7rPg+fX6fUzXxd8OPJ2y5XdfN7BHsE3PzTL3Df90OPs+DSrmbKvlGtXM2P5LMI3tJ3Pr+WOzS61+J0yOX8ru030r8TqVceq7db/F5Y33fyZ" .
			"IyRikZJHCskWwhlBBIKIAUEFEASCQZQTEqJBs0fH2GDRs0fGiXbpW3fD1oyRgjO0wluswMyQXMgSJdgi4FBYEmwW+kQAT/AAASsEOwBIuAcgMwPULbCiYFzCTEAPUMAkMAhAxQSLFwESuLFxI6S4oAI/EkXFi8CtEixhr/AOksXgIvI0WCNXAZR+BErhAx" .
			"QCLA1cIDVoVMO0ZiLH1iLwDuJiLIJCkBghnYMhYAmy4O8kKBCALATaIuEJEB4jImYhSATJ0iBAFxIItIwAwJBYCmJMQLCATAQIsAEEIYgMQItJBAYECAoQQIAEiwsEgAMACCEZSBUZ8Dz7xUdTPvs57z1/qUroNPD3w48nbLltx802e4a9f5rNv9s2/b8X" .
			"H2T0edXM2/2maVibl8pmMb2k7n0/LLKdNnVU3I5Py1/p0nW0+FHqu7bf4vLG+7qzRkjGDJIzVYMgUKQCjECDAoAnSQywAGLM9LxoxMtP5itE7pW3fD1ozRiusyRg2B6i2DtIIgMAUGMS5jECAABAFgWADILAZAokFi4pMgiZFiwLAuAVAsAsBIDAqwJgVY" .
			"BDAZj1jMKWjMrGDCHqIx6hkFX1EyGPYMrAgUmAxCnrJmMO0PGwA7h6iO64AXBE7xbYTsAuBGMLi52EEtsAxQ7AILZGA7ACJaVRAYExI7i4jAKnQAAIQoIJgRllQAIALJAQQogggACoMCkYAhSYkEBYIFQAAQ5zz1/rpflR0bOb89/wBj/iv4mvh74ceTtc" .
			"xrfOZt9w063zmbvcNP2/Fz9k9HmWJtp+XUakbaPlsyje7nc+h5c/00dhQvZXUcd5a/Y7TsqPCn0Hpntt6PN913VkjJESM0cKIyEFIIigoEBUAJBGjIhRrVbqqapRvo061VxHnq0bZpbp6jKnTrdSXG4Yu3bHVtHrerDho3Uu481O2Uy6mz0pQYS1VYAAgh" .
			"QUogYGAAhQAzCCvAEtL6wIKGJEWABCoDEAgQuABjIZjAIvrJmBmFVh4h3DMIYkdyLiRhVx7CLAoyCJNhSYFxCp6w8R6yMA7hAYAZEKQAMxgLAGJLy2DECYAYAgQQqIAgmBcyBUtgYliwQBjgCggxDvKMQIEgEAIyggxGBSBQhSACFIBSCBBBCGRAqM5nz3" .
			"/ZX8qOmOY89/2exGvg72fk7XM6vzn1m73DTX819Zv93sNP2/2T7J6PLSbtPwM0q83aXgfUYu53Pb5b4f8AkdnpeCnqOM8su/5HZ6PyqepHqnst6PN913VsSMoCMjNVSLBEZICQWAUgkAogohMSwGBC0eNEMqF7aE7ljfD00mZijKTGWwUBkAFwIBEMBcCh" .
			"iAUAiAoEYYYgA8B6xAhFAYiFaHeABPWUAPWAEMykzKwo7iZlasIBSPAsEwQRSZCBFwAYokFaAmHaXMkLIPEKMYhqwReAyFyEXMkAVXIPEgggYjEBZAIsJaW4mYC0jkoeAEYKSbgoQoAxDDBAJBSAMCFAEF4AVAUhBAUAQlxQBCGTIQQhQ5CsWct5253T6D" .
			"qWcp5y/wDKr/8AsDbwd/wZ+Ttc5V819Z6MOw87+a+s9HqOv2f2Pt+Dy03m7S8L6jTTebtG59RlxdcHr8t//o7TbW6NH8qOK8ux6ztdr8jT/lR6Z7LXm+65vRkiIyOFVSUIywIqFEFAxtEFAEJaZEKiFp8S6yMtPiXWJ3LG+HqMoMUZIxlsqkBAgWi0YAoW" .
			"i2SsmJAtFoBRLYLaGAFpLQUolsBzaXAAS0MBgBmXMACMAINO0WlJjIDAPEYABaMEBgFAsCkyAhXeA7wiEzKXMCORJWQBkR3FeAwCpkUYB2EC2SFtkgEtsFtpSALRaABFIclDAlpLSu8BWLBQQQlpQBCFAAhQFQYlBBiCgDEQUAQhQRUIVgDFnI+cv/L1Ov" .
			"8Agde0cf5x/uanX/A28Hf8Gfl7XP8A919Z6Mew86+a+s9Hvdhf2R/I+2ejy03m7Rx6jSrzdo3szd8Hq8vftVdZ220X6Gn/ACo4fYeOrrO42n+vR1I9M/jteae656EZIiMjgUyIikUAKQQFSBRiCtAIhF4l1kdVMxiZU3ovBY3vSjNGvjpVkmxGMtlIoKCC" .
			"AogBYAChYQpAHQAwgAAAAAAQpCg8RmB6gAzAYC20WiWMewBgPUE7B6gBLYGQdyCLaLbATIKpGCsCDFgZgGMwMwHYMAIsApLBkMGQLJIUATIAoEIUgC0AWSFS0FxIAxIUhBAUmAAhQAIUBUBSEEBSACFEBUADIIQpAMTj/OH/AJep1nYs4vzWr/K1Otm3/P" .
			"3T0Z+Td8Xw6fmHo/udh59Pxno/udg+/wCK/a8qvNuj4malebdHxM4V6dh8yrrO32Vu3o6jh9lZqVnceX/61B6P129Xnnul60ZIxRkjhVRkiItxAKAFQFBURokFDA0V0VqriojtM6dXcKFCtMyLxLrLPNYmWXBr1NTB6aVCjIiMjGWqiACAAAAYGBRAy4EA" .
			"AYgALQAAyBMgAwGQKAQEgCZlyAELNwIgCiBZIFoBYB3C2wYAUmQyGQAYgO8AJdoGYBsEcFAdBLRZJcCBkMCZAAUWkAYgpMAICjECCAHeFRgYgARouJCCDAAAAAAKS0AACKgKIAhCgCCACDFkMmRhWLOI80c7nU63+J27OG8y/wBjV/mq/E3/AOfunoy8m6" .
			"HyNLxm/wDvGnS8Zt/unP3OvteZXm7R8ZpRt0fGcunp2fzajt/Lv9WjtOH2r/XqR3Hln+pR2/iej9cdXnnuno9yRURFRwMgRGRFAAAAAAhSMqMWVXoMYg4vSjJGKRkjKWywAgiACSygACSUABIFRGBLACCACkyKyWgAiSUASywWhYFBYAhSAMhJE7ioqBJL" .
			"IBYDAk3DAKttgJkMgKmG7SFkBKFlpPWJvADMNgAy4EkTYQMikyGAAJsk2gCgSSQBSCQoCWCQGIEkAt7BMQQCFkAQqIUAAAoACCAoAmBDIgEIykIJBCkYVjVccL5k/wBfV63+J3VfhfUcF5jVOtq9bN/+ffd0ZeTg+bo+I2/3jXoeIz/unP3OuDzo26XzDU" .
			"bNL5hy6ejbfPqO48sf+JR2nDaH+xV1Hb+VOdpSeiPxx/J57u6ej6KMjFGRmKikRSKqAAAAFEIUBGJDJkKPTSZIxRkjKWyoFBBBIADMmIGIFIAigAAEEKQAPWCAUhZJZICQQqAgAyADIAAmMiK8uQBO4skIBkTAKwhRSkBEA8RYAoAyAWQGS2AKS0AAww2E" .
			"AkMWCQoJDDAgDAABkwAQACAQpMQKIACrAgABAgFIMYEGRIAhMClYGLwIVkIIRlIFa9TwvqOB379utnfavgq6mfn29ftVno8H3dGXk4PHt/EZf3e0m38Qn9XtM+Lvg0mzT+YazOjx9xB6NH/YfUdr5S/8VHE6f+wdl5PV/jHoj8f9mN3d8H1kzJM1pmaZmM" .
			"0ymCZkmBkCEkgyDMWxJRSBkZUR1JXltZqrpq4uKm1mVOtrL3UJ3bFij1qqlXmac3Hla1qsEeihNUwZS1ZlMQRVJIxAQBBgBSBhlAAIAJIADDJIm8Ckd4kjvAoIAGAyIVYAATIsgC5GKvLkAkIhcQFgkEAsiSACyJIMwKCYACggkC5AmQAAYgCggkAAAoQE" .
			"AAAAUjBBUCFChUQoAoBAAAAFEAQjLBAJBGikZFYsxZkzFgatZ/p1PJM/P967amd9uHGjW/ys/P8AeO2rrPR4N1zK/fDz7e8n9ztLt8TH3zPi74NZnR4zAyp8RBvpca513k1X6LXUcen+sjq/Jav0ql1G9v4/7Mru74PtpmxM0U1SbEzly2yWTXJeIitkiT" .
			"CRxAZySTHiJJRnJJMZDYRkSTHiEg4vZTcZJmtOwyVWBlLdnIMZEkGRBJJAqZTGRNgFbvEmMiUEZSMiSSQKJJJJKKCSJASHgJI2BZDIw8QK8RkQSBfWDGRYBkEySRMDIGJZAoJJJAoJNgkCjMkkbAzwISbBIFkTKJITQFyBJAVQSQEUEEgUSRsSFBcSRNoF" .
			"ICSQWSkEgUGMlkKpUQswBZKYyXAgpSSAKAiSBSNFIFYkZkYsiMWYsybNbYV594422p/KzgN27aus7zfVf4ur/Kzgd07+s9Hh7bmV++GG3uZr982aHhfUaveMuLvgxKryFxA2J/qJn3fLN/RoLhrueJz8VJysDNamrkbWX2xbMSzutmZrDsl5ttovM15vts" .
			"zi1qapeZrZHVfHjKZLnaLzja5l+8bXM4vmaw5msSvjxkyX4O1+87X4h942uZxXHrDj1hXx4yZL8Ha/eNr8RPvG1+I4vmaw5msK+PGTJe7X7xtcyfeNrmcXzdYc3VyLm8eMmS52n3ja5j7vtZ8RxfN1chzdbIV8eMpkud6vO9lHjM/vex+M/P1q6uQ5urkc" .
			"U8WMu/rwh+gfe9j8Zfvexfvn5/zda+BztaLhTxYyv14Q/QPvWx+Mfetj8Z+f87WyHP1shTxYyn14Q/QfvOy+Mn3rZR4zgOfrZE5+rkMvixk+vCH6AvONkrOZJfvOx+M/Pufq5F+o1chl8Xqk+vCH6B942Xxl+8bL4z8++o1ch9RrZDL4vVJ9eEP0H7vsvj" .
			"H3bZfGfn31GrkPqdXIZfF6pPrwh+g/ddl+4Puuz/cPz/6rVyJ9TqZFy+L1SfXhD9B+6bP9xFfmez/cR+e/VamRfq9TImXx+qSt+D9C+57P9xEfmW0/cR+ffV6mQ+rryLl8fqlK34P0L7ltLf1EF5jtLP1Efnn1deRfq6shl8fqK34P0L7jtP3F3j7htf3V" .
			"3n579ZXkPrK8hl8fqK34P0P6/au3mU94+u2v7tPefnn1lWTL9bVkxk8fqK3YP0P67a/uU94W+237lMdZ+eLe1dJfrqukZPH6it2D9C+s21n6lPeX6vb/ALlPefnn11XSPrqunvGSz1ma7B+ifVaH7lPeFudH46e8/PPrnmx9fVmxp2eszXel+ifUaL99d4" .
			"+o0viXefnf19Wb7x9fVm+8advrgzXel+ic/SjxLvHO0/iR+efX1ZsfcKviY07fXBmn0v0Tm6fxLvHN048S7z88+41fEy/ca/iZNO31wZpwfofNos9pd5eZTmfnn3Kv42VeZ6vxsaUeuFzThL9C46cxxrM/Pl5pq/uMv3XW/dY0o9UGacJfoPGhxLM/P15t" .
			"r/u1F+76/wC7V3jS/wBoM3KXf8Q4jgV5vr/u1d7Ml5xr/uvvY0v9rTPyl3nETiOF+87j9195fvW5/dfeNKfVaZ+Uu5lDiRxH3vcx8wv33dfuE0pxhc/KXbOocRxP33dfuGX33dfuDSnGDPGEu0lFk4tefbr4/wACrz/dfH+A0bsYM8c3ZyWTjV/2HdfEvQ" .
			"Vf9h3XxIaN3IzxzdlIVRx3/ot1mi/+j3PQNG7kZ4djJeI47/0m56Cr/sm56CaN/Izw7CSycf8A+l3HQF/2XcXxSNG/kZ4dhIk5D/0u4ypL/wCl3OVJNG8zw61sxbOT/wDS6+VJH/2TcZUjRvXPDqmzXUzl3/2PcPCkxf8A2LcZUjRv5GeH3fMK1TtNScmj" .
			"gtw7+s+nvPNtfc08NTinJHydaqUa225bJrvlzM1lnov2H1GrEUuETEx4u+DEyVwBFblcXuAI7ZrsL3ABTuHcABe4WdHpAIFn5fSLPy+kAqln5fSLPy+kAIWdHpJZ0ekADNRHulUfl9IBBbPy+kWfl9IACz8vpJZ+X0gBFs/L6SOI930gAFH5fSLPy+kAKW" .
			"fl9JLOj0gALPy+kWdAAE7h3AATuHcAETuHcAA7idwBQ7idwADuJ3ABD+kj/wCIAD+kj/4gAP6R/SABi4/L6Q4/L6QCp8ks/L6RZ+X0gBPkWfl9JLPy+kAHyLPy+kWdHpAKfJLOj0izo9IARO4ABAgBRQAAIABQAA7x3gFQ7x3gBV7x3gBDvHeAPmHeO8Av" .
			"zDvL3gD5id47wCfMTvD7QAI+0jAAyQxAOHXB/9k=" );
	}
}