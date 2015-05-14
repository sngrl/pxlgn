<?php
/**
 * @brief		View Event Controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Calendar
 * @since		14 Jan 2014
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
 * View Event Controller
 */
class _event extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\calendar\Event';

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\calendar\Calendar::addCss();

		try
		{
			$this->event = \IPS\calendar\Event::load( \IPS\Request::i()->id );
			
			if ( !$this->event->canView( \IPS\Member::loggedIn() ) )
			{
				\IPS\Output::i()->error( 'node_error', '2L179/1', 404, '' );
			}
			
			if ( $this->event->cover_photo )
			{
				\IPS\Output::i()->metaTags['og:image'] = $this->event->cover_photo;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2L179/2', 404, '' );
		}

		/* We want to present the same breadcrumb structure as the rest of the calendar */
		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=view", 'front', 'calendar' ), \IPS\Member::loggedIn()->language()->addToStack('module__calendar_calendar') );

		parent::execute();
	}
	
	/**
	 * View Event
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		parent::manage();

		/* Fetch RSVP data and pass to template */
		try
		{
			$attendees	= $this->event->attendees();
		}
		catch( \BadMethodCallException $e )
		{
			$attendees	= array( 0 => array(), 1 => array(), 2 => array() );
		}

		/* Sort out comments and reviews */
		$tabs = $this->event->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $this->event->commentReviews( $tab );
		
		if ( count( $tabs ) > 1 )
		{
			$commentsAndReviews = count( $tabs ) ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, $this->event->url(), 'tab', FALSE, TRUE ) : NULL;
		}
		else
		{
			$commentsAndReviews = $activeTabContents;
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}

		/* Online User Location */
		\IPS\Session::i()->setLocation( $this->event->url(), $this->event->onlineListPermissions(), 'loc_calendar_viewing_event', array( $this->event->title => FALSE ) );

		/* Display */
		//\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'view' )->eventSidebar( $this->event, $attendees );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->view( $this->event, $commentsAndReviews, $attendees );
	}

	/**
	 * Show a small version of the calendar as a "hovercard"
	 *
	 * @return	void
	 */
	protected function hovercard()
	{
		/* Figure out our date object */
		$date = NULL;

		if( \IPS\Request::i()->sd )
		{
			$dateBits	= explode( '-', \IPS\Request::i()->sd );

			if( count( $dateBits ) === 3 )
			{
				$date	= \IPS\calendar\Date::getDate( $dateBits[0], $dateBits[1], $dateBits[2] );
			}
		}

		if( $date === NULL )
		{
			$date	= \IPS\calendar\Date::getDate();
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->eventBlock( $this->event, $date );
	}

	/**
	 * Download file - Show terms and file selection
	 *
	 * @return	void
	 */
	protected function download()
	{
		$feed	= new \IPS\calendar\Icalendar\ICSParser;
		$feed->addEvent( $this->event );

		$ics	= $feed->buildICalendarFeed( $this->event->container() );

		\IPS\Output::i()->sendHeader( "Content-type: text/calendar; charset=UTF-8" );
		\IPS\Output::i()->sendHeader( 'Content-Disposition: inline; filename=calendarEvents.ics' );

		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $ics );
		print $ics;
		exit;
	}

	/**
	 * Download RSVP attendee list
	 *
	 * @return	void
	 */
	protected function downloadRsvp()
	{
		$output	= \IPS\Theme::i()->getTemplate( 'view' )->attendees( $this->event );
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );

		require_once \IPS\ROOT_PATH . '/system/3rd_party/tcpdf/tcpdf.php';
		$pdf = new \TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, TRUE, 'UTF-8', FALSE );
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);
		$pdf->AddFont('freeserif');
		$pdf->SetFont('freeserif');
		$pdf->AddPage();
		$pdf->writeHTML( $output );
		$pdf->Output( 'attendees.pdf', 'I' );
		exit;
	}

	/**
	 * RSVP for event
	 *
	 * @return	void
	 */
	protected function rsvp()
	{
		if( !$this->event->can('rsvp') )
		{
			\IPS\Output::i()->error( 'rsvp_error', '2L179/3', 403, '' );
		}

		/* We delete either way at this point, because even if we select a different action we have to remove any existing RSVP preference */
		\IPS\Db::i()->delete( 'calendar_event_rsvp', array( 'rsvp_event_id=? AND rsvp_member_id=?', $this->event->id, \IPS\Member::loggedIn()->member_id ) );

		if( \IPS\Request::i()->action == 'leave' )
		{
			$message	= 'rsvp_not_going';
		}
		else
		{
			/* Figure out the action */
			switch( \IPS\Request::i()->action )
			{
				case 'yes':
					$_go	= \IPS\calendar\Event::RSVP_YES;
				break;

				case 'maybe':
					$_go	= \IPS\calendar\Event::RSVP_MAYBE;
				break;

				case 'no':
				default:
					\IPS\Request::i()->action	= 'no';
					$_go	= \IPS\calendar\Event::RSVP_NO;
				break;
			}

			/* If there is a limit applied there are more rules */
			if( $this->event->rsvp_limit > 0 )
			{
				/* We do not accept "maybe" in this case */
				if( $_go === \IPS\calendar\Event::RSVP_MAYBE )
				{
					\IPS\Output::i()->error( 'rsvp_limit_nomaybe', '3L179/4', 403, '' );
				}

				/* And we have to actually check the limit */
				if( count( $this->event->attendees[1] ) >= $this->event->rsvp_limit )
				{
					\IPS\Output::i()->error( 'rsvp_limit_reached', '3L179/5', 403, '' );
				}
			}

			\IPS\Db::i()->insert( 'calendar_event_rsvp', array(
				'rsvp_event_id'		=> $this->event->id,
				'rsvp_member_id'	=> \IPS\Member::loggedIn()->member_id,
				'rsvp_date'			=> time(),
				'rsvp_response'		=> (int) $_go
			) );

			$message	= 'rsvp_selection_' . \IPS\Request::i()->action;
		}

		\IPS\Output::i()->redirect( $this->event->url(), $message );
	}
}