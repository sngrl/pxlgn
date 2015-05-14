<?php
/**
 * @brief		Date/Time Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		8 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Date/Time Class
 */
class _DateTime extends \DateTime
{
	/**
	 * Create from timestamp
	 *
	 * @param	int		$timestamp		UNIX Timestamp
	 * @param	bool	$bypassTimezone	Ignore timezone (useful for things like rfc1123() which forces to GMT anyways)
	 * @return	\IPS\DateTime
	 */
	public static function ts( $timestamp, $bypassTimezone=FALSE )
	{
		$obj = new static;
		$obj->setTimestamp( $timestamp );
		if ( !$bypassTimezone AND \IPS\Member::loggedIn()->timezone )
		{
			try
			{
				$obj->setTimezone( new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
			}
			catch ( \Exception $e )
			{
				\IPS\Member::loggedIn()->timezone	= null;
				\IPS\Member::loggedIn()->save();
			}
		}
		return $obj;
	}
	
	/**
	 * Create New
	 *
	 * @return	\IPS|DateTime
	 */
	public static function create()
	{
		return new static;
	}
	
	/**
	 * Format a DateInterval showing only the relevant pieces.
	 *
	 * @param	\DateInterval	$diff			The interval
	 * @param	int				$restrictParts	The maximum number of "pieces" to return.  Restricts "1 year, 1 month, 1 day, 1 hour, 1 minute, 1 second" to just "1 year, 1 month".  Pass 0 to not reduce.
	 * @return	string
	 */
	public static function formatInterval( \DateInterval $diff, $restrictParts=2 )
	{
		/* Figure out what pieces we have.  Note that we are letting the language manager perform the formatting to implement better pluralization. */
		$format		= array();

		if( $diff->y !== 0 )
		{
			$format[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_years', FALSE, array( 'pluralize' => array( $diff->y ) ) );
		}

		if( $diff->m !== 0 )
		{
			$format[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_months', FALSE, array( 'pluralize' => array( $diff->m ) ) );
		}

		if( $diff->d !== 0 )
		{
			$format[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_days', FALSE, array( 'pluralize' => array( $diff->d ) ) );
		}

		if( $diff->h !== 0 )
		{
			$format[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_hours', FALSE, array( 'pluralize' => array( $diff->h ) ) );
		}

		if( $diff->i !== 0 )
		{
			$format[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_minutes', FALSE, array( 'pluralize' => array( $diff->i ) ) );
		}

		/* If we don't have anything but seconds, return "less than a minute ago" */
		if( !count($format) )
		{
			if( $diff->s !== 0 )
			{
				return \IPS\Member::loggedIn()->language()->addToStack('less_than_a_minute');
			}
		}
		else if( $diff->s !== 0 )
		{
			$format[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_seconds', FALSE, array( 'pluralize' => array( $diff->s ) ) );
		}

		/* If we are still here, reduce the number of items in the $format array as appropriate */
		if( $restrictParts > 0 )
		{
			$useOnly	= array();
			$haveUsed	= 0;

			foreach( $format as $period )
			{
				$useOnly[]	= $period;
				$haveUsed++;

				if( $haveUsed >= $restrictParts )
				{
					break;
				}
			}

			$format	= $useOnly;
		}
		
		return \IPS\Member::loggedIn()->language()->formatList( $format );
	}
	
	/**
	 * Format the date and time according to the user's locale
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return \IPS\Member::loggedIn()->language()->convertString( strftime( '%x ' . $this->localeTimeFormat(), $this->getTimestamp() + $this->getTimezone()->getOffset( $this ) ) );
	}
	
	/**
	 * Get HTML output
	 *
	 * @param	bool	$capialize	TRUE if by itself, FALSE if in the middle of a sentence 
	 * @return	string
	 */
	public function html( $capialize=TRUE )
	{
		$format = $capialize ? static::RELATIVE_FORMAT_NORMAL : static::RELATIVE_FORMAT_LOWER;
		return "<time datetime='{$this->rfc3339()}' title='{$this}' data-short='{$this->relative(1)}'>" . $this->relative( $format ) . "</time>";
	}
	
	/**
	 * Format the date according to the user's locale (without the time)
	 *
	 * @return	string
	 */
	public function localeDate()
	{
		return \IPS\Member::loggedIn()->language()->convertString( strftime( '%x', $this->getTimestamp() + $this->getTimezone()->getOffset( $this ) ) );
	}

	/**
	 * Get locale date, forced to 4-digit year format
	 *
	 * @return	string
	 */
	public function fullYearLocaleDate()
	{
		$timeStamp		= $this->getTimestamp() + $this->getTimezone()->getOffset( $this );
		$dateString		= strftime( '%x', $timeStamp );
		$twoDigitYear	= strftime( '%y', $timeStamp );
		$fourDigitYear	= strftime( '%Y', $timeStamp );
		$dateString		= preg_replace_callback( "/(\s|\/|,|-){$twoDigitYear}$/", function( $matches ) use ( $fourDigitYear ) {
			return $matches[1] . $fourDigitYear;
		}, $dateString );
		return \IPS\Member::loggedIn()->language()->convertString( $dateString );
	}
		
	/**
	 * Locale time format
	 *
	 * PHP always wants to use 24-hour format but some
	 * countries prefer 12-hour format, so we override
	 * specifically for them
	 *
	 * @param	bool	$seconds	If TRUE, will include seconds
	  * @param	bool	$minutes	If TRUE, will include minutes
	 * @return	string
	 */
	public function localeTimeFormat( $seconds=FALSE, $minutes=TRUE )
	{
		if ( in_array( preg_replace( '/\.UTF-?8$/', '', \IPS\Member::loggedIn()->language()->short ), array(
			'sq_AL', // Albanian - Albania
			'zh_SG', 'sgp', 'singapore', // Chinese - Singapore
			'zh_TW', 'twn', 'taiwan', // Chinese - Taiwan
			'en_AU', 'aus', 'australia', 'australian', 'ena', 'english-aus', // English - Australia
			'en_CA', 'can', 'canda', 'canadian', 'enc', 'english-can', // English - Canada
			'en_NZ', 'nzl', 'new zealand', 'new-zealand', 'nz', 'english-nz', 'enz', // English - New Zealand
			'en_PH', // English - Phillipines
			'en_ZA', // English - South Africa
			'en_US', 'american', 'american english', 'american-english', 'english-american', 'english-us', 'english-usa', 'enu', 'us', 'usa', 'america', 'united states', 'united-states', // English - United States
			'el_CY', // Greek - Cyprus
			'el_GR', 'grc', 'greece', 'ell', 'greek', // Greek - Greece
			'ms_MY', // Malay - Malaysia
			'ko_KR', 'kor', 'korean', // Korean - South Korea
			'es_MX', 'mex', 'mexico', 'esm', 'spanish-mexican', // Spanish - Mexico
		) ) )
		{
			if( mb_strtoupper( mb_substr( PHP_OS, 0, 3 ) ) === 'WIN' )
			{
				return '%I' . ( $minutes ? ':%M' : '' ) . ( $seconds ? ':%S ' : ' ' ) . ' %p';
			}
			else
			{
				return '%l' . ( $minutes ? ':%M' : '' ) . ( $seconds ? ':%S ' : ' ' ) . ' %p';
			}
		}
		
		return '%H' . ( $minutes ? ':%M' : '' ) . ( $seconds ? ':%S ' : ' ' );
	}
	
	/**
	 * Format the time according to the user's locale (without the date)
	 *
	 * @param	bool	$seconds	If TRUE, will include seconds
	 * @param	bool	$minutes	If TRUE, will include minutes
	 * @return	string
	 */
	public function localeTime( $seconds=TRUE, $minutes=TRUE )
	{		
		return \IPS\Member::loggedIn()->language()->convertString( strftime( $this->localeTimeFormat( $seconds, $minutes ), $this->getTimestamp() + $this->getTimezone()->getOffset( $this ) ) );
	}
	
	const RELATIVE_FORMAT_NORMAL = 0;	// Yesterday at 2pm
	const RELATIVE_FORMAT_LOWER  = 2;	// yesterday at 2pm (e.g. "Edited yesterday at 2pm")
	const RELATIVE_FORMAT_SHORT  = 1;	// 1dy (for mobile view)

	/**
	 * Format the date relative to the current date/time
	 * e.g. "30 minutes ago"
	 *
	 * @param	int	$format	The format (see RELATIVE_FORMAT_* constants)
	 * @return	string
	 */
	public function relative( $format=0 )
	{
		$now		= static::create();
		$difference	= $this->diff( $now );
		$capitalKey = ( $format == static::RELATIVE_FORMAT_LOWER ) ? '' : '_c';
				
		if ( !$difference->invert and $now->format('Y') == $this->format('Y') )
		{
            if ( $difference->m or $difference->d >= 6 )
			{
				return \IPS\Member::loggedIn()->language()->convertString( strftime( ( ( mb_strtoupper( mb_substr( PHP_OS, 0, 3 ) ) === 'WIN' ) ? '%d' : '%e' ) . ( $format == static::RELATIVE_FORMAT_SHORT ? ' %b' : ' %B' ), $this->getTimestamp() + $this->getTimezone()->getOffset( $this ) ) );
			}
			elseif ( $difference->d )
			{
				$compare = clone $this;

				if ( $format === static::RELATIVE_FORMAT_SHORT )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_days_short', FALSE, array( 'sprintf' => array( $difference->d ) ) );
				}
				elseif ( $difference->d == 1 && ( $compare->add( new \DateInterval( 'P1D' ) )->format('Y-m-d') == $now->format('Y-m-d') ) )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "_date_yesterday{$capitalKey}", FALSE, array( 'sprintf' => array( $this->localeTime( FALSE ) ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "_date_this_week{$capitalKey}", FALSE, array( 'sprintf' => array( $this->strFormat('%A'), $this->localeTime( FALSE ) ) ) );
				}
			}
			elseif ( $difference->h )
			{
				if ( $format == static::RELATIVE_FORMAT_SHORT )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_hours_short', FALSE, array( 'sprintf' => array( $difference->h ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "_date_ago{$capitalKey}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'f_hours', FALSE, array( 'pluralize' => array( $difference->h ) ) ) ) ) );
				}
			}
			elseif ( $difference->i )
			{
				if ( $format == static::RELATIVE_FORMAT_SHORT )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_minutes_short', FALSE, array( 'sprintf' => array( $difference->i ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "_date_ago{$capitalKey}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'f_minutes', FALSE, array( 'pluralize' => array( $difference->i ) ) ) ) ) );
				}
			}
			else
			{
				if ( $format == static::RELATIVE_FORMAT_SHORT )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_minutes_short', FALSE, array( 'sprintf' => array( 1 ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "_date_just_now{$capitalKey}" );
				}
			}
		}

		return \IPS\Member::loggedIn()->language()->convertString( strftime( $format == static::RELATIVE_FORMAT_SHORT ? '%b %y': ( ( mb_strtoupper( mb_substr( PHP_OS, 0, 3 ) ) === 'WIN' ) ? '%d %b %Y' : '%e %b %Y' ), $this->getTimestamp() + $this->getTimezone()->getOffset( $this ) ) );
	}

	/**
	 * Format times based on strftime() calls instead of date() calls, and convert to UTF-8 if necessary
	 *
	 * @param	string	$format	Format accepted by strftime()
	 * @return	string
	 */
	public function strFormat( $format )
	{
		return \IPS\Member::loggedIn()->language()->convertString( strftime( $format, $this->getTimestamp() + $this->getTimezone()->getOffset( $this ) ) );
	}

	/**
	 * Wrapper for format() so we can convert to UTF-8 if needed
	 *
	 * @param	string	$format	Format accepted by date()
	 * @return	string
	 */
	public function format( $format )
	{
		return \IPS\Member::loggedIn()->language()->convertString( parent::format( $format ) );
	}
	
	/**
	 * Format the date for the datetime attribute HTML <time> tags
	 * This will always be in UTC (so offset is not included) and so should never be displayed normally to users
	 *
	 * @return	string
	 */
	public function rfc3339()
	{
		return date( 'Y-m-d', $this->getTimestamp() ) . 'T' . date( 'H:i:s', $this->getTimestamp() ) . 'Z';
	}

	/**
	 * Format the date for the expires header
	 * This must be in english only and follow a very specific format in GMT (so offset is not included)
	 *
	 * @return	string
	 */
	public function rfc1123()
	{
		return gmdate( "D, d M Y H:i:s", $this->getTimestamp() ) . ' GMT';
	}
}