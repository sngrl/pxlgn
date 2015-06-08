<?php
/**
 * @brief		Abstract class that Controllers should extend
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPSUtf8\Dispatcher;

/**
 * Abstract class that Controllers should extend
 */
abstract class Controller
{

	/**
	 * Execute
	 *
	 * @param	string				$command	The part of the query string which will be used to get the method
	 * @param	\IPS\Http\Url|NULL	$url		The base URL for this controller or NULL to calculate automatically
	 * @return	void
	 */
	public function execute()
	{
		if( \IPSUtf8\Request::i()->do and \substr( \IPSUtf8\Request::i()->do, 0, 1 ) !== '_' )
		{
			if ( method_exists( $this, \IPSUtf8\Request::i()->do ) )
			{
				call_user_func( array( $this, \IPSUtf8\Request::i()->do ) );
			}
			else
			{
				\IPSUtf8\Output\Browser::i()->error( "Page not found" );
			}
		}
		else
		{
			$this->manage();
		}
	}
}