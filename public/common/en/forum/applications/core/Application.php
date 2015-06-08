<?php
/**
 * @brief		Core Application Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */
 
namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * ACP Menu Numbers
	 *
	 * @param	array	$queryString	Query String
	 * @return	int
	 */
	public function acpMenuNumber( $queryString )
	{
		parse_str( $queryString, $queryString );
		switch ( $queryString['controller'] )
		{
			case 'advertisements':
				return \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( 'ad_active=-1' ) )->first();
				break;
		}
	}
	
	/**
	 * Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	public function get__badge()
	{
		/* Is there an update to show? */
		$badge	= NULL;

		if( $this->update_version )
		{
			$data	= json_decode( $this->update_version, TRUE );
			
			if( !empty($data['longversion']) AND $data['longversion'] > $this->long_version )
			{
				$released	= NULL;

				if( $data['released'] AND intval($data['released']) == $data['released'] AND \strlen($data['released']) == 10 )
				{
					$released	= (string) \IPS\DateTime::ts( $data['released'] )->localeDate();
				}
				else if( $data['released'] )
				{
					$released	= $data['released'];
				}

				$badge	= array(
					0	=> 'new',
					1	=> '',
					2	=> \IPS\Theme::i()->getTemplate( 'global', 'core' )->updatebadge( $data['version'], \IPS\Http\Url::internal( 'app=core&module=overview&controller=dashboard&do=upgrade', 'admin' ), $released, FALSE )
				);
			}
		}

		return $badge;
	}
}
