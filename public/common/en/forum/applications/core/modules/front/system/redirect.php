<?php
/**
 * @brief		External redirector with key checks
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redirect
 */
class _redirect extends \IPS\Dispatcher\Controller
{
	/**
	 * Redirect the request appropriately
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* First check the key to make sure this actually came from HTMLPurifier */
		if ( \IPS\Request::i()->key == hash_hmac( "sha256", \IPS\Request::i()->url, md5( \IPS\Settings::i()->sql_pass . \IPS\Settings::i()->board_url . \IPS\Settings::i()->sql_database ) ) )
		{
			/* Is it a resource? (image, etc.) */
			if ( \IPS\Request::i()->resource )
			{
				/* If it's a resource, redirect without a message.  Browsers should follow a location header for resources such as image fine. */
				/* Note that this scenario is hit if we have <img src='munged url' /> */
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( \IPS\Request::i()->url ) );
			}
			else
			{
				/* Regular URL?  Show a redirect page. */
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( \IPS\Request::i()->url ), \IPS\Member::loggedIn()->language()->addToStack('external_redirect'), 303, TRUE );
			}
		}
		else
		{
			/* Key did not validate, send the user to the index page */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('') );
		}
	}

	/**
	 * Redirect an ACP click
	 *
	 * @note	The purpose of this method is to avoid exposing \IPS\CP_DIRECTORY to non-admins
	 * @return	void
	 */
	protected function admin()
	{
		if( \IPS\Member::loggedIn()->isAdmin() )
		{
			$queryString	= base64_decode( \IPS\Request::i()->_data );
			\IPS\Output::i()->redirect( new \IPS\Http\Url( \IPS\Http\Url::baseUrl() . \IPS\CP_DIRECTORY . '/?' . $queryString, TRUE ) );
		}

		\IPS\Output::i()->error( 'no_access_cp', '2C159/3', 403 );
	}

	/**
	 * Redirect an advertisement click
	 *
	 * @return	void
	 */
	protected function advertisement()
	{
		/* CSRF check */
		\IPS\Session::i()->csrfCheck();

		/* Get the advertisement */
		$advertisement	= array();

		if( isset( \IPS\Request::i()->ad ) )
		{
			try
			{
				$advertisement	= \IPS\core\Advertisement::load( \IPS\Request::i()->ad );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'ad_not_found', '2C159/2', 404, 'ad_not_found_admin' );
			}
		}

		if( !$advertisement->id OR !$advertisement->link )
		{
			\IPS\Output::i()->error( 'ad_not_found', '2C159/1', 404, 'ad_not_found_admin' );
		}

		/* We need to update click count for this advertisement. Does it need to be shut off too due to hitting click maximum?
			Note that this needs to be done as a string to do "col=col+1", which is why we're not using the ActiveRecord save() method.
			Updating by doing col=col+1 is more reliable when there are several clicks at nearly the same time. */
		$update	= "ad_clicks=ad_clicks+1";

		if( $advertisement->maximum_unit == 'c' AND $advertisement->maximum_value > -1 AND $advertisement->clicks + 1 >= $advertisement->maximum_value )
		{
			$update	.= ", ad_active=0";

			unset( \IPS\Data\Cache::i()->advertisements );
		}

		/* Update the database */
		\IPS\Db::i()->update( 'core_advertisements', $update, array( 'ad_id=?', $advertisement->id ) );

		/* And do the redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::external( $advertisement->link ) );
	}
}