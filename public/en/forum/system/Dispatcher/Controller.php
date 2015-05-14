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

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract class that Controllers should extend
 */
abstract class _Controller
{
	/**
	 * @brief	Base URL
	 */
	public $url;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url|NULL	$url		The base URL for this controller or NULL to calculate automatically
	 * @return	void
	 */
	public function __construct( $url=NULL )
	{
		if ( $url === NULL )
		{
			$class		= get_called_class();
			$exploded	= explode( '\\', $class );
			$this->url = \IPS\Http\Url::internal( "app={$exploded[1]}&module={$exploded[4]}&controller={$exploded[5]}", \IPS\Dispatcher::i()->controllerLocation );
		}
		else
		{
			$this->url = $url;
		}
	}

	/**
	 * Force a specific method within a controller to execute.  Useful for unit testing.
	 *
	 * @param	null|string		$method		The specific method to call
	 * @return	mixed
	 */
	public function forceExecute( $method=NULL )
	{
		if( \IPS\ENFORCE_ACCESS === true and $method !== null )
		{
			if ( method_exists( $this, $method ) )
			{
				return call_user_func( array( $this, $method ) );
			}
			else
			{
				return $this->execute();
			}
		}

		return $this->execute();
	}

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if( \IPS\Request::i()->do and \substr( \IPS\Request::i()->do, 0, 1 ) !== '_' )
		{
			if ( method_exists( $this, \IPS\Request::i()->do ) or method_exists( $this, '__call' ) )
			{
				call_user_func( array( $this, \IPS\Request::i()->do ) );
			}
			else
			{
				\IPS\Output::i()->error( 'page_not_found', '2S106/1', 404, '' );
			}
		}
		else
		{
			$this->manage();
		}
		
	}
	
	/**
	 * Embed
	 *
	 * @return	void
	 */
	protected function embed()
	{
		if( !isset( static::$contentModel ) )
		{
			\IPS\Output::i()->sendOutput(  \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedUnavailable() ) );
		}

        try
        {
            $class = static::$contentModel;
            $item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

            if( $item instanceof \IPS\Content\Embeddable )
            {
                \IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css' ) );

                $comment = NULL;
                if( \IPS\Request::i()->embedComment )
                {
                    $commentClass = $class::$commentClass;

                    $comment = $commentClass::load( \IPS\Request::i()->embedComment );
                }

                $templateKey = "embed" . ( ( $item instanceof \IPS\Content ) ? ucfirst( $item::$title ) : ucfirst( $item::$nodeTitle ) );

                \IPS\Output::i()->base = '_parent';
                \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->$templateKey( $item, $comment );
                \IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output, ( ( $item instanceof \IPS\Content ) ? ucfirst( $item->mapped('title') ) : ucfirst( $item->_title ) ) ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
            }
        }
        catch( \Exception $e )
        {
            \IPS\Output::i()->sendOutput(  \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedNoPermission() ) );
        }
    }
}