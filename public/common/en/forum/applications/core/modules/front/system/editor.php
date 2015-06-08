<?php
/**
 * @brief		Editor AJAX functions Controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		29 Apr 2013
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
 * Editor AJAX functions Controller
 */
class _editor extends \IPS\Dispatcher\Controller
{
	/**
	 * Link Dialog
	 *
	 * @return	void
	 */
	protected function link()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->link( \IPS\Request::i()->current, \IPS\Request::i()->editorId );
	}
	
	/**
	 * Image Dialog
	 *
	 * @return	void
	 */
	protected function image()
	{
		$maxImageDims = \IPS\Settings::i()->attachment_image_size ? explode( 'x', \IPS\Settings::i()->attachment_image_size ) : array( 1000, 750 );
		$maxWidth = ( \IPS\Request::i()->actualWidth < $maxImageDims[0] ) ?  \IPS\Request::i()->actualWidth : $maxImageDims[0];
		$maxHeight = ( \IPS\Request::i()->actualHeight < $maxImageDims[1] ) ? \IPS\Request::i()->actualHeight : $maxImageDims[1];
		
		if ( \IPS\Request::i()->width > $maxWidth )
		{
			$ratio = \IPS\Request::i()->height / \IPS\Request::i()->width;
			\IPS\Request::i()->width = $maxWidth;
			\IPS\Request::i()->height = floor( \IPS\Request::i()->width * $ratio );
		}
		if ( \IPS\Request::i()->height > $maxHeight )
		{
			$ratio = \IPS\Request::i()->height / \IPS\Request::i()->width;
			\IPS\Request::i()->height = $maxHeight;
			\IPS\Request::i()->width = floor( \IPS\Request::i()->height * $ratio );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->image( \IPS\Request::i()->editorId, \IPS\Request::i()->width, \IPS\Request::i()->height, $maxWidth, $maxHeight, \IPS\Request::i()->border, \IPS\Request::i()->margin, \IPS\Request::i()->float, \IPS\Request::i()->link );
	}
	
	/**
	 * AJAX validate link
	 *
	 * @return	void
	 */
	protected function validateLink()
	{
		try
		{
			$title = NULL;
			$isEmbed = FALSE;
			$url = \IPS\Request::i()->url;
			if ( !\IPS\Request::i()->noEmbed and !\IPS\Request::i()->image and $embed = \IPS\Text\Parser::embeddableMedia( $url ) )
			{
				$insert = $embed;
				$isEmbed = TRUE;
			}
			else
			{
				$response = \IPS\Http\Url::external( $url )->request()->get();
				if ( \IPS\Request::i()->image and isset( $response->httpHeaders['Content-Type'] ) and mb_substr( $response->httpHeaders['Content-Type'], 0, 6 ) === 'image/' )
				{
					$insert = "<img src='{$url}' class='ipsImage'>";
				}
				else
				{
					if ( \IPS\Request::i()->image )
					{
						throw new \DomainException;
					}
					
					if ( \IPS\Request::i()->title )
					{
						$title = \IPS\Request::i()->title;
					}
					elseif ( isset( $response->httpHeaders['Content-Type'] ) and mb_substr( $response->httpHeaders['Content-Type'], 0, 9 ) === 'text/html' and preg_match( '/<title>(.+)<\/title>/', $response, $matches ) and isset( $matches[1] ) )
					{
						$title = $matches[1];
					}
					else
					{
						$title = $url;
					}
					
					$insert = "<a href='{$url}' ipsNoEmbed='true'>{$title}</a>";
				}
			}
			
			\IPS\Output::i()->json( array( 'preview' => $insert, 'title' => $title, 'embed' => $isEmbed ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->json( $e->getMessage(), 500 );
		}
	}
			
	/**
	 * Get Emoticons Configuration
	 *
	 * @return	void
	 */
	protected function emoticons()
	{
		$emoticons = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_emoticons', NULL, 'emo_set,emo_position' ) as $row )
		{
			if ( !isset( $emoticons[ $row['emo_set'] ] ) )
			{
				$setLang = 'core_emoticon_group_' . $row['emo_set'];
				
				$emoticons[ $row['emo_set'] ] = array(
					'title'		=> \IPS\Member::loggedIn()->language()->addToStack( $setLang ),
					'total'		=> 0,
					'emoticons'	=> array(),
				);
			}
			
			$emoticons[ $row['emo_set'] ]['emoticons'][] = array(
				'src'	=> $row['image'],
				'text'	=> $row['typed']
			);
		}
		
		foreach ( $emoticons as $set => $data )
		{
			$emoticons[ $set ]['total'] = count( $data['emoticons'] );
		}
		
		\IPS\Output::i()->json( $emoticons );
	}
	
	/**
	 * My Media
	 *
	 * @return	void
	 */
	protected function myMedia()
	{
		/* Init */
		$perPage = 7;
		$search = isset( \IPS\Request::i()->search ) ? \IPS\Request::i()->search : null;
		
		/* Get all our available sources */
		$mediaSources = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'EditorMedia' ) as $k => $class )
		{
			if ( $class->count( \IPS\Member::loggedIn(), isset( \IPS\Request::i()->postKey ) ? \IPS\Request::i()->postKey : '' ) )
			{
				$mediaSources[] = $k;
			}
		}

		/* Work out what tab we're on */
		if ( !\IPS\Request::i()->tab )
		{
			\IPS\Request::i()->tab = array_shift( $mediaSources );
		}
		$exploded = explode( '_', \IPS\Request::i()->tab );
		$classname = "IPS\\{$exploded[0]}\\extensions\\core\\EditorMedia\\{$exploded[1]}";
		$extension = new $classname;
		$url = \IPS\Http\Url::internal( "app=core&module=system&controller=editor&do=myMedia&tab=" . \IPS\Request::i()->tab . "&key=" . \IPS\Request::i()->key . "&postKey=" . \IPS\Request::i()->postKey . "&existing=1" );
		
		/* Count how many we have */
		$count = $extension->count( \IPS\Member::loggedIn(), isset( \IPS\Request::i()->postKey ) ? \IPS\Request::i()->postKey : '', $search );

		$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		/* Display */
		if ( isset( \IPS\Request::i()->existing ) )
		{
			if ( isset( \IPS\Request::i()->search ) || ( isset( \IPS\Request::i()->page ) && \IPS\Request::i()->page !== 1 ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->myMediaResults(
					$extension->get( \IPS\Member::loggedIn(), $search, isset( \IPS\Request::i()->postKey ) ? \IPS\Request::i()->postKey : '', $page, $perPage ),
					\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination(
						$url,
						ceil( $count / $perPage ),
						$page,
						$perPage
					),
					$url
				);
			}
			else
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->myMediaContent(
					$extension->get( \IPS\Member::loggedIn(), $search, isset( \IPS\Request::i()->postKey ) ? \IPS\Request::i()->postKey : '', $page, $perPage ),
					\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination(
						$url,
						ceil( $count / $perPage ),
						$page,
						$perPage
					),
					$url
				);
			}
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->myMedia( \IPS\Request::i()->editorId, $mediaSources, \IPS\Request::i()->tab, $url, \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->myMediaContent(
				$extension->get( \IPS\Member::loggedIn(), $search, isset( \IPS\Request::i()->postKey ) ? \IPS\Request::i()->postKey : '', $page, $perPage ),
				\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination(
					$url,
					ceil( $count / $perPage ),
					$page,
					$perPage
				),
				$url
			) );
		}
	}
	
	/**
	 * Mentions
	 *
	 * @return	void
	 */
	protected function mention()
	{
		$results = '';
		foreach ( \IPS\Db::i()->select( '*', 'core_members', array( "name LIKE CONCAT( ?, '%' )", mb_strtolower( \IPS\Request::i()->input ) ), 'name', 10 ) as $row )
		{
			$results .= \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->mentionRow( \IPS\Member::constructFromData( $row ) );
		}
		
		echo $results;
		exit;
	}
}