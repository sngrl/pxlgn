<?php
/**
 * @brief		Legacy Text Parser
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		12 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Text;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Legacy Text Parser
 */
class _LegacyParser
{	

	/**
	 * Parse statically
	 *
	 * @param	string				$value				The value to parse
	 * @param	\IPS\Member|null	$member				The member posting. NULL will use currently logged in member.
	 * @param	bool				$allowHtml			Allow HTML
	 * @param	string				$attachClass		Key to use for attachments
	 * @param	int					$id1				ID1 to use for attachments
	 * @param	int					$id2				ID2 to use for attachments
	 * @return	string
	 * @see		__construct
	 */
	public static function parseStatic( $value, $member=NULL, $allowHtml=FALSE, $attachClass=null, $id1=0, $id2=0 )
	{
		$obj = new self( $member, $allowHtml, $attachClass, $id1, $id2 );
		return $obj->parse( $value );
	}
	
	/**
	 * @brief	BBcodes
	 */
	public $bbcodes	= NULL;

	/**
	 * @brief	Emoticons
	 */
	public $emoticons	= NULL;

	/**
	 * @brief	Media tags
	 */
	public $media	= NULL;

	/**
	 * Can use plugin?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string		$bbcode	BBCode
	 * @return	bool
	 */
	public static function canUse( \IPS\Member $member, $bbcode )
	{
		foreach( static::$bbcodes as $code )
		{
			if( $code['bbcode_tag'] == $bbcode OR ( $code['bbcode_aliases'] AND in_array( $bbcode, explode( ',', $code['bbcode_aliases'] ) ) ) )
			{
				if( $code['bbcode_groups'] == 'all' )
				{
					return true;
				}

				if( $member->inGroup( explode( ',', $bbcode['bbcode_groups'] ) ) )
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @brief	Member
	 */
	protected $member		= NULL;

	/**
	 * @brief	Allow HTML
	 */
	protected $allowHtml	= FALSE;

	/**
	 * @brief	Parser object
	 */
	protected $parser		= NULL;

	/**
	 * @brief	Key for attachments
	 */
	protected $attachClass		= NULL;

	/**
	 * @brief	ID1 to use for attachments
	 */
	protected $id1				= 0;

	/**
	 * @brief	ID2 to use for attachments
	 */
	protected $id2				= 0;

	/**
	 * Constructor
	 *
	 * @param	\IPS\Member|null	$member				The member posting. NULL will use guest.
	 * @param	bool				$allowHtml			Allow HTML
	 * @param	string				$attachClass		Key to use for attachments
	 * @param	int					$id1				ID1 to use for attachments
	 * @param	int					$id2				ID2 to use for attachments
	 * @return	void
	 */
	public function __construct( $member=NULL, $allowHtml=FALSE, $attachClass=null, $id1=0, $id2=0 )
	{
		/* Get member */
		$this->member = $member === NULL ? \IPS\Member::load(0) : $member;

		/* Remember if we allow HTML */
		$this->allowHtml	= $allowHtml;

		/* Set attachment data */
		$this->attachClass	= $attachClass;
		$this->id1			= $id1;
		$this->id2			= $id2;

		/* Grab legacy custom bbcodes */
		if( \IPS\Db::i()->checkForTable('custom_bbcode') )
		{
			$this->bbcodes	= iterator_to_array( \IPS\Db::i()->select( '*', 'custom_bbcode' )->setKeyField( 'bbcode_tag' ) );
		}
		else
		{
			$this->bbcodes	= array();
		}

		/* Grab emoticons */
		if( \IPS\Db::i()->checkForTable('core_emoticons') )
		{
			$this->emoticons	= iterator_to_array( \IPS\Db::i()->select( '*', 'core_emoticons' )->setKeyField( 'typed' ) );
		}
		else
		{
			$this->emoticons	= array();
		}

		/* Grab a new parser object */
		$this->parser	= new \IPS\Text\Parser( TRUE, $this->id1 ? array( $this->id1, $this->id2 ) : NULL, $this->member, $this->attachClass, TRUE, ( $this->allowHtml ? FALSE : TRUE ), NULL );

		$updatedBbcodeTags	= array_keys( $this->parser->bbcodeTags( $this->member, TRUE ) );

		foreach( $updatedBbcodeTags as $tag )
		{
			if( isset( $this->bbcodes[ $tag ] ) AND $tag != 'media' )
			{
				unset( $this->bbcodes[ $tag ] );
			}
		}

		if( isset( $this->bbcodes['media'] ) )
		{
			$this->bbcodes['media']['bbcode_replace']	= '<p>{content}</p>';
		}

		if( $this->media === null )
		{
			if( \IPS\Db::i()->checkForTable('bbcode_mediatag') )
			{
				$this->media	= iterator_to_array( \IPS\Db::i()->select( '*', 'bbcode_mediatag' ) );
			}
			else
			{
				$this->media	= array();
			}
		}
	}
	
	/**
	 * Parse
	 *
	 * @param	string	$value	Text to parse
	 * @return	string
	 */
	public function parse( $value )
	{
		/* Start with legacy emoticon parsing */
		$value = str_replace( "<#EMO_DIR#>", "&lt;#EMO_DIR&gt;", $value );
		$value = preg_replace( "#(\s)?<([^>]+?)emoid=\"(.+?)\"([^>]*?)".">(\s)?#is", "\\1\\3\\5", $value );

		preg_match_all( "#(<img(?:[^>]+?)class=['\"]bbc_emoticon[\"'](?:[^>]+?)alt=['\"](.+?)[\"'](?:[^>]+?)?>)#is", $value, $matches );

		if( is_array($matches[1]) AND count($matches[1]) )
		{
			foreach( $matches[1] as $index => $match )
			{
				$value	= str_replace( $match, $matches[2][ $index ], $value );
			}
		}

		/* Parse emoticons */
		usort( $this->emoticons, function( $a, $b ){
			if ( mb_strlen( $a['typed'] ) == mb_strlen( $b['typed'] ) )
			{
				return 0;
			}

			return ( mb_strlen( $a['typed'] ) > mb_strlen( $b['typed'] ) ) ? -1 : 1;
		});

		foreach( $this->emoticons as $emoticon )
		{
			$code	= str_replace( '<', '&lt;', str_replace( '>', '&gt;', $emoticon['typed'] ) );	

			if( !\stristr( $value, $code ) )
			{
				continue;
			}

			$quotedCode = preg_quote( $code, '/' );
			$value = preg_replace( "/([^a-zA-Z0-9'\"\/]){$quotedCode}([^a-zA-Z0-9'\"\/])/i", "$1<img src='" . $emoticon['image'] . "' alt='" . $code . "'>$2", $value );
		}

		/* Old char conversions */
		$value = str_replace( "&#160;", " ", $value );
		$value = str_replace( "&#39;", "'", $value );
		$value = str_replace( "&amp;;", "&", $value );
		$value = str_replace( "&lt;#EMO_DIR&gt;", "<#EMO_DIR#>", $value );

		/* Just remove this - our new parser doesn't need (or like) it */
		$value = str_replace( "[/*]", '', $value );

		/* Convert some old HTML back into bbcode to be properly parsed */
		$value = preg_replace( "#<a href=[\"']index\.php\?automodule=blog(&|&amp;)showentry=(.+?)['\"]>(.+?)</a>#is", "[entry=\"\\2\"]\\3[/entry]", $value );
		$value = preg_replace( "#<a href=[\"']index\.php\?automodule=blog(&|&amp;)blogid=(.+?)['\"]>(.+?)</a>#is", "[blog=\"\\2\"]\\3[/blog]", $value );
		$value = preg_replace( "#<a href=[\"']index\.php\?act=findpost(&|&amp;)pid=(.+?)['\"]>(.+?)</a>#is", "[post=\"\\2\"]\\3[/post]", $value );
		$value = preg_replace( "#<a href=[\"']index\.php\?showtopic=(.+?)['\"]>(.+?)</a>#is", "[topic=\"\\1\"]\\2[/topic]", $value );
		$value = preg_replace( "#<a href=[\"'](.*?)index\.php\?act=findpost(&|&amp;)pid=(.+?)['\"]><\{POST_SNAPBACK\}></a>#is", "[snapback]\\3[/snapback]", $value );
		$value = preg_replace( "#<div class=[\"']codetop['\"]>(.+?)</div><div class=[\"']codemain['\"] style=[\"']height:200px;white\-space:pre;overflow:auto['\"]>(.+?)</div>#is", "[code]\\2[/code]", $value );
		$value = preg_replace( "#<!--blog\.extract\.start-->(.+?)<!--blog\.extract\.end-->#is", "[extract]\\1[/extract]", $value );
		$value = preg_replace( "#<span style=[\"']color:\#000000;background:\#000000['\"]>(.+?)</span>#is", "[spoiler]\\1[/spoiler]", $value );

		/* Unconvert code */
		$value = preg_replace_callback( "#<!--sql-->(.+?)<!--sql1-->(.+?)<!--sql2-->(.+?)<!--sql3-->#is", array( $this, '_parseOldCode'), $value );
		$value = preg_replace_callback( "#<!--html-->(.+?)<!--html1-->(.+?)<!--html2-->(.+?)<!--html3-->#is", array( $this, '_parseOldCode'), $value );
		$value = preg_replace_callback( "#<!--Flash (.+?)-->.+?<!--End Flash-->#", array( $this, '_parseOldFlash'), $value );
		$value = preg_replace( "#<!--c1-->(.+?)<!--ec1-->#", '[code]', $value );
		$value = preg_replace( "#<!--c2-->(.+?)<!--ec2-->#", '[/code]', $value );

		/* Unconvert quotes */
		$value = preg_replace( "#<!--QuoteBegin-->(.+?)<!--QuoteEBegin-->#"							, '[quote]'								, $value );
		$value = preg_replace( "#<!--QuoteBegin-{1,2}([^>]+?)\+([^>]+?)-->(.+?)<!--QuoteEBegin-->#"	, "[quote name='\\1' date='\\2']"		, $value );
		$value = preg_replace( "#<!--QuoteBegin-{1,2}([^>]+?)\+-->(.+?)<!--QuoteEBegin-->#"			, "[quote name='\\1']"					, $value );
		$value = preg_replace( "#<!--QuoteEnd-->(.+?)<!--QuoteEEnd-->#"								, '[/quote]'							, $value );
		$value = preg_replace( "#\[quote=(.+?),(.+?)\]#i"											, "[quote name='\\1' date='\\2']"		, $value );
		$value = preg_replace( "#\[quote=(.*?)\[url(.*?)\](.+?)\[\/url\]\]#i"						, "[quote=\\1\\3]"						, str_replace( "\\", "", $value ) );
		$value = preg_replace_callback( "#<!--quoteo([^>]+?)?-->(.+?)<!--quotec-->#si"				, array( $this, '_parseOldQuote' )		, $value );

		/* Unconvert indent tag */
		while( preg_match( "#<blockquote>(.+?)</blockquote>#is", $value ) )
		{
			$value = preg_replace( "#<blockquote>(.+?)</blockquote>#is"  , "[indent]\\1[/indent]", $value );
		}

		/* Capital inconsistency */
		foreach( array_keys( $this->parser->bbcodeTags( $this->member, TRUE ) ) as $bbcode )
		{
			$value = str_replace( '[' . mb_strtoupper( $bbcode ), '[' . $bbcode, $value );
			$value = str_replace( '[/' . mb_strtoupper( $bbcode ), '[/' . $bbcode, $value );
		}

		/* Convert quote tag */
		$value = preg_replace_callback( "#<blockquote\s+?class=['\"]ipsBlockquote[\"']([^>]*?)>(.+?)</blockquote>#si", array( $this, '_parseOldBlockquote' )		, $value );
		$value = preg_replace_callback( "#\[quote([^>]*?)\]#si"									, array( $this, '_parseOldQuoteBbcode' )	, $value );
		$value = str_replace( "[/quote]", "</div></blockquote>", $value );

		/* Fix download manager embedded screenshot URLs */
		$value = preg_replace_callback( "#<img src=['\"]" . preg_quote( rtrim( \IPS\Settings::i()->base_url, '/' ), '#' ) . "/index\.php\?app=downloads(?:&amp;|&)module=display(?:&amp;|&)section=screenshot(?:&amp;|&)id=(\d+?)['\"]([^>]*?)>#si", array( $this, '_fixDownloadsScreenshots' ), $value );

		/* These were auto-replacements previously */
		$value = str_ireplace( "(c)"	, "&copy;"	, $value );
		$value = str_ireplace( "(tm)"	, "&#153;"	, $value );
		$value = str_ireplace( "(r)"	, "&reg;"	, $value );
		
		/* Convert PHP tags if necessary */
		$value = str_ireplace( "<?php"	, "&lt;?php", $value );
		$value = str_ireplace( "?>"		, "?&gt;"	, $value );

		/* Convert bbcode tags, but only those our new parser doesn't handle */
		foreach( $this->bbcodes as $code )
		{
			/* If we don't have a regex replacement, probably because we used to use a plugin, we can't do this automatically */
			if( !$code['bbcode_replace'] )
			{
				continue;
			}

			/* Build the regex */
			$regex = "/\[(?:{$code['bbcode_tag']}" . ( $code['bbcode_aliases'] ? '|' . str_replace( ',', '|', $code['bbcode_aliases'] ) : '' ) . ")" .
				( $code['bbcode_useoption'] ? "=(.+?)" : '' ) . "\]" . 
				( $code['bbcode_single_tag'] ? '' : ( "(.+?)\[\/(?:{$code['bbcode_tag']}" . ( $code['bbcode_aliases'] ? '|' . str_replace( ',', '|', $code['bbcode_aliases'] ) : '' ) . ")\]" ) ) .
				"/ims";

			/* Now actually perform the replacement */
			while( preg_match( $regex, $value ) )
			{
				$value = preg_replace_callback( $regex, function( $matches ) use ( $code ) {
					$replacement	= $code['bbcode_replace'];

					if( $code['bbcode_single_tag'] )
					{
						if( $code['bbcode_useoption'] )
						{
							$replacement	= str_replace( '{option}', $matches[1], $replacement );
						}
					}
					else
					{
						if( $code['bbcode_useoption'] )
						{
							$replacement	= str_replace( '{option}', $matches[1], $replacement );
							$replacement	= str_replace( '{content}', $matches[2], $replacement );
						}
						else
						{
							$replacement	= str_replace( '{content}', $matches[1], $replacement );
						}
					}

					return $replacement;
				}, $value );
			}
		}

		/* Figure out what attachments are missing so we can add them at the end */
		if( $this->attachClass !== NULL AND $this->id1 !== 0 )
		{
			$embeddedAttachments	= array();

			preg_match_all( "/\[attachment=(.+?):(.+?)\]/ims", $value, $matches );

			if( count( $matches[1] ) )
			{
				foreach( $matches[1] as $id )
				{
					$embeddedAttachments[ $id ]	= $id;
				}
			}

			$where = array( array( 'location_key=?', $this->attachClass ), array( 'id1=?', $this->id1 ) );

			if( $this->id2 !== 0 )
			{
				$where[]	= array( 'id2=?', $this->id2 );
			}

			$mappedAttachments		= iterator_to_array( \IPS\Db::i()->select( '*', 'core_attachments_map', $where )->setKeyField( 'attachment_id' ) );

			foreach( $mappedAttachments as $attachmentId => $map )
			{
				if( !in_array( $attachmentId, $embeddedAttachments ) )
				{
					$value	.= '<p>[attachment=' . $attachmentId . ':name]</p>';
				}
			}
		}

		/* Now fix shared media */
		$value = preg_replace_callback( "/\[sharedmedia=(.+?):(.+?):(.+?)\]/ims", function( $matches ) {
			switch( $matches[1] )
			{
				case 'calendar':
					try
					{
						$event = \IPS\Db::i()->select( '*', 'calendar_events', array( 'event_id=?', (int) $matches[3] ) )->first();
						$url = \IPS\Http\Url::internal( "app=calendar&module=events&controller=event&id={$event['event_id']}", 'front', 'calendar_event', $event['event_title_seo'] );
						return "<p><a href='{$url}'>{$url}</a></p>";
					}
					catch( \Exception $e )
					{
						return "";
					}
				break;

				case 'gallery':
					if( $matches[2] == 'images' )
					{
						try
						{
							$image = \IPS\Db::i()->select( '*', 'gallery_images', array( 'image_id=?', (int) $matches[3] ) )->first();
							$url = \IPS\Http\Url::internal( "app=gallery&module=gallery&controller=view&id={$image['image_id']}", 'front', 'gallery_image', $image['image_caption_seo'] );
							return "<p><a href='{$url}'>{$url}</a></p>";
						}
						catch( \Exception $e )
						{
							return "";
						}
					}
					else
					{
						try
						{
							$album = \IPS\Db::i()->select( '*', 'gallery_albums', array( 'album_id=?', (int) $matches[3] ) )->first();
							$url = \IPS\Http\Url::internal( "app=gallery&module=gallery&controller=browse&album={$album['album_id']}", 'front', 'gallery_album', $album['album_name_seo'] );
							return "<p><a href='{$url}'>{$url}</a></p>";
						}
						catch( \Exception $e )
						{
							return "";
						}
					}
				break;

				case 'downloads':
					try
					{
						$file = \IPS\Db::i()->select( '*', 'downloads_files', array( 'file_id=?', (int) $matches[3] ) )->first();
						$url = \IPS\Http\Url::internal( "app=downloads&module=downloads&controller=view&id={$file['file_id']}", 'front', 'downloads_file', $file['file_name_furl'] );
						return "<p><a href='{$url}'>{$url}</a></p>";
					}
					catch( \Exception $e )
					{
						return "";
					}
				break;

				case 'core':
					/* We'll just let the attachment parsing next take care of this */
					return "[attachment={$matches[3]}:string]";
				break;

				case 'blog':
					try
					{
						$entry = \IPS\Db::i()->select( '*', 'blog_entries', array( 'entry_id=?', (int) $matches[3] ) )->first();
						$url = \IPS\Http\Url::internal( "app=blog&module=blogs&controller=entry&id={$entry['entry_id']}", 'front', 'blog_entry', $entry['entry_name_seo'] );
						return "<p><a href='{$url}'>{$url}</a></p>";
					}
					catch( \Exception $e )
					{
						return "";
					}
				break;
			}

			/* Could be a third party app - just return original embed code if we are still here */
			return $matches[0];
		}, $value );

		/* Now fix attachments */
		$value = preg_replace_callback( "/\[attachment=(.+?):(.+?)\]/ims", function( $matches ) {
			try
			{
				$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', (int) $matches[1] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$attachment = array( 'attach_is_image' => 0, 'attach_location' => '' );
			}

			if( $attachment['attach_is_image'] )
			{
				$attachment['attach_thumb_location']	= $attachment['attach_thumb_location'] ?: $attachment['attach_location'];
				$return = "<a href='{$attachment['attach_location']}'><img src='{$attachment['attach_thumb_location']}' data-fileid='" . \IPS\Settings::i()->base_url . "applications/core/interface/file/attachment.php?id={$attachment['attach_id']}'></a>";
			}
			else
			{
				$return = $attachment['attach_location'] ? "<a href='" . \IPS\Settings::i()->base_url . "applications/core/interface/file/attachment.php?id={$attachment['attach_id']}'>{$attachment['attach_file']}</a>" : '';
			}

			/* @note: Do we need to store an attachment map here? */

			return $return;
		}, $value );

		/* Some people may have tried to embed the direct youtube video...tsk tsk */
		preg_match_all( '#youtube.com/v/([^ \[]+?)#is', $value, $matches );

		foreach( $matches[0] as $idx => $m )
		{
			$value = str_replace( $m, "youtube.com/watch?v=" . $matches[1][ $idx ], $value );
		}

		/* Media */
		$urls = array();

		preg_match_all( '#\[url=[\'"]?(.+?)["\']?\](.+?)\[\/url\]#is', $value, $matches );

		foreach( $matches[0] as $idx => $m )
		{
			$value = str_replace( $m, "<a href='" . $matches[1][ $idx ] . "'>" . $matches[2][ $idx ] . "</a>", $value );
		}

		preg_match_all( '#\[img\](.+?)\[\/img\]#is', $value, $matches );

		foreach( $matches[0] as $idx => $m )
		{
			$value = str_replace( $m, "<img src='" . $matches[1][ $idx ] . "'>", $value );
		}

		preg_match_all( '#<a href=[\'"](.+?)["\'].*?>.+?\<\/a\>#is', $value, $matches );

		foreach( $matches[0] as $k => $m )
		{
			$c = count( $urls );
			$urls[ $c ] = $m;

			$replacement = '<!--url{' . $c . '}-->';

			if( mb_strpos( $matches[1][ $k ], \IPS\Settings::i()->base_url ) === 0 AND \IPS\Text\Parser::embeddableMedia( $matches[1][ $k ], TRUE ) )
			{
				$replacement = $matches[1][ $k ];
			}
			
			$value = str_replace( $m, $replacement, $value );
		}

		preg_match_all( '#<img.+?src=[\'"](.+?)["\'].*?>#is', $value, $matches );

		foreach( $matches[0] as $m )
		{
			$c = count( $urls );
			$urls[ $c ] = $m;
			
			$value = str_replace( $m, '<!--url{' . $c . '}-->', $value );
		}

		preg_match_all( '#((http|https|news|ftp)://(?:[^<>\)\[\"\s]+|[a-zA-Z0-9/\._\-!&\#;,%\+\?:=]+))#is', $value, $matches );

		foreach( $matches[0] as $m )
		{
			$me = \IPS\Text\Parser::embeddableMedia( $m, TRUE );
			
			if( $me )
			{
				$value = str_replace( $m, $me, $value );
			}
		}

		foreach( $urls as $k => $v )
		{
			$value = str_replace( '<!--url{' . $k . '}-->', $v, $value );
		}

		$value = preg_replace( "/\<a href=([^>]+?)\>\<a href=([^>]+?)\>(.+?)\<\/a\>\<\/a\>/ims", "<a href=$1>$3</a>", $value );

		/* Some old posts might have used <br> instead of <p>, which can cause severe parsing issues, so try to catch this and adapt */
		if( mb_substr( $value, 0, 3 ) !== '<p>' AND mb_substr( $value, 0, 5 ) !== '<div>' AND ( mb_strpos( $value, '<br>' ) !== FALSE OR mb_strpos( $value, '<br />' ) !== FALSE ) )
		{
			$value	= '<p>' . $value . '</p>';
			$value	= str_replace( array( '<br>', '<br />' ), '</p><p>', $value );
		}

		/* Fix lists */
		$value = preg_replace( "/\[list\](.+?)\[\/list\]/ims", "</p><ul data-ipsBBCode-list=\"true\">$1</ul><p>", $value );

		$value = preg_replace_callback( "/\[list=['\"]?(.+?)['\"]?\](.+?)\[\/list\]/ims", function( $matches ){
			switch( $matches[1] )
			{
				case '1':
					return "<ol data-ipsBBCode-list=\"true\" style='list-style-type: decimal'>{$matches[2]}</ol>";
				break;

				case '0':
					return "<ol data-ipsBBCode-list=\"true\" style='list-style-type: decimal-leading-zero'>{$matches[2]}</ol>";
				break;

				case 'a':
					return "<ol data-ipsBBCode-list=\"true\" style='list-style-type: lower-alpha'>{$matches[2]}</ol>";
				break;

				case 'A':
					return "<ol data-ipsBBCode-list=\"true\" style='list-style-type: upper-alpha'>{$matches[2]}</ol>";
				break;

				case 'i':
					return "<ol data-ipsBBCode-list=\"true\" style='list-style-type: lower-roman'>{$matches[2]}</ol>";
				break;

				case 'I':
					return "<ol data-ipsBBCode-list=\"true\" style='list-style-type: upper-roman'>{$matches[2]}</ol>";
				break;
			}

			return "<ul data-ipsBBCode-list=\"true\">{$matches[2]}</ul>";
		}, $value );

		$value = preg_replace( "/(<[u|o]l data-ipsBBCode-list=\"true\"(?:.+?)?>)\s*?<br \/>\s*?\[\*\]/", "$1\n[*]", $value );
		$value = preg_replace( "/(<[u|o]l data-ipsBBCode-list=\"true\"(?:.+?)?>)\s*?<br>\s*?\[\*\]/", "$1\n[*]", $value );
		$value = preg_replace( "/(<br \/>|<br>)\s*?\[\*\]/", "\n[*]", $value );

		$value = preg_replace_callback( "/<[u|o]l data-ipsBBCode-list=\"true\"(?:.+?)?>.+?<\/[u|o]l>/ims", function( $matches )
		{
			$v = str_replace( '</p><p>', '<br>', $matches[0] );
			$v = str_replace( array( '<p>', '</p>' ), '', $v );
			$v = str_replace( '[*]', '</li><li>', $v );
			$v = str_replace( '<ul></li>', '<ul>', $v );
			$v = str_replace( '<ol></li>', '<ol>', $v );
			$v = str_replace( '</ul>', '</li></ul>', $v );
			$v = str_replace( '</ol>', '</li></ol>', $v );
			$v = str_replace( ' data-ipsBBCode-list="true"', '', $v );
			return $v;
		}, $value );

		$value = preg_replace( "/(\<[u|o]l(?:.*?)?\>)\s*?\<\/li\>/ims", "$1", $value );
		$value = preg_replace( "/(\<[u|o]l(?:.*?)?\>)\<br\>\<li\>/ims", "$1<li>", $value );

		/* Block level tags */
		$value = preg_replace( "/\[(code|codebox|sql|php|xml|html)(.*?)\](.+?)\[\/(code|codebox|sql|php|xml|html)\]/ims", "</p><p>[$1$2]$3[/$4]</p><p>", $value );
		$value = preg_replace( "/\[(indent|left|center|right|spoiler|page)\](.+?)\[\/(indent|left|center|right|spoiler|page)\]/ims", "</p><p>[$1]$2[/$3]</p><p>", $value );
		$value = preg_replace( "/\<p\>\s*?\[page\]\s*?\<\/p\>/ims", "[page]", $value );

		//print htmlspecialchars( $value );print "<hr style='color:red;'>";

		/* Return */
		$result = $this->parser->parse( $value );

		/* URLs that are turned into embeds end up as <a href=''><iframe ...></a> */
		$result = preg_replace( "/\<a href=[\"']{2}.*?\>(?:&gt;)?(\<iframe.+?\>)\<\/a\>/i", "$1", $result );

		return $result;
	}

	/**
	 * Parse new quotes
	 *
	 * @param	array	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldQuote( $matches=array() )
	{
		if ( !$matches[1] )
		{
			return '[quote]';
		}
		else
		{
			$return		= array();

			preg_match( "#\(post=(.+?)?:date=(.+?)?:name=(.+?)?\)#", $matches[1], $match );
			
			if ( $match[3] )
			{
				$return[]	= " name='{$match[3]}'";
			}
			
			if ( $match[1] )
			{
				$return[]	= " post='" . intval($match[1]) . "'";
			}
			
			if ( $match[2] )
			{
				$return[]	= " date='{$match[2]}'";
			}
			
			return str_replace( '  ', ' ', '[quote' . implode( ' ', $return ).']' );
		}
	}

	/**
	 * Parse new quotes #2
	 *
	 * @param	array	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldBlockquote( $matches=array() )
	{
		$parameters	= array( 'data-ipsQuote' => '', 'class' => 'ipsQuote' );

		if( count( $matches[1] ) )
		{
			preg_match( "/data-author=['\"](.+?)[\"']/i", $matches[1], $author );
			preg_match( "/data-cid=['\"](.+?)[\"']/i", $matches[1], $cid );
			preg_match( "/data-time=['\"](.+?)[\"']/i", $matches[1], $time );

			if( isset( $cid[1] ) )
			{
				$parameters['data-ipsquote-contentcommentid']	= $cid[1];

				if( $this->attachClass )
				{
					$pieces = explode( '_', $this->attachClass );

					$parameters['data-ipsquote-contenttype']	= $pieces[0];
					$parameters['data-ipsquote-contentclass']	= $this->attachClass;
					$parameters['data-ipsquote-contentid']		= $this->id1;
				}
			}

			if( isset( $author[1] ) )
			{
				$parameters['data-ipsquote-username']	= $author[1];
				$parameters['data-cite']				= $author[1];
			}

			if( isset( $time[1] ) )
			{
				$parameters['data-ipsquote-timestamp']	= $time[1];
			}
		}

		$_parameterString	= '';

		foreach( $parameters as $key => $value )
		{
			$_parameterString	.= ' ' . $key . '="' . str_replace( '"', '\\"', $value ) . '"';
		}
		
		return "<blockquote{$_parameterString}><div>{$matches[2]}</div></blockquote>";
	}

	/**
	 * Parse new quotes #3
	 *
	 * @param	array	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldQuoteBbcode( $matches=array() )
	{
		$parameters	= array( 'data-ipsQuote' => '', 'class' => 'ipsQuote' );

		if( count( $matches[1] ) )
		{
			preg_match( "/name=['\"](.+?)[\"']/i", $matches[1], $author );
			preg_match( "/post=['\"](.+?)[\"']/i", $matches[1], $cid );
			preg_match( "/timestamp=['\"](.+?)[\"']/i", $matches[1], $time );

			if( isset( $cid[1] ) )
			{
				$parameters['data-ipsquote-contentcommentid']	= $cid[1];
			}

			if( isset( $author[1] ) )
			{
				$parameters['data-ipsquote-username']	= $author[1];
				$parameters['data-cite']				= $author[1];
			}

			if( isset( $time[1] ) )
			{
				$parameters['data-ipsquote-timestamp']	= $time[1];
			}
		}

		$_parameterString	= '';

		foreach( $parameters as $key => $value )
		{
			$_parameterString	.= ' ' . $key . '="' . str_replace( '"', '\\"', $value ) . '"';
		}
		
		return "<blockquote{$_parameterString}><div>";
	}

	/**
	 * Convert flash HTML back into BBCode
	 *
	 * @param	array	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldFlash( $matches=array() )
	{
		$f_arr	= explode( "+", $matches[1] );
		
		return '[flash=' . $f_arr[0] . ',' . $f_arr[1] . ']' . $f_arr[2] . '[/flash]';
	}

	/**
	 * Convert old code tags back into bbcode
	 *
	 * @param	array	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldCode( $matches=array() )
	{
		return '[code]' . rtrim( str_replace( "</span>", '', preg_replace( "#<span style='.+?'>#is", "", stripslashes( $matches[2] ) ) ) ) . '[/code]';
	}

	/**
	 * Convert download manager screenshot URLs
	 *
	 * @param	array	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _fixDownloadsScreenshots( $matches )
	{
		if( isset( $matches[1] ) )
		{
			try
			{
				$screenshot = \IPS\Db::i()->select( 'record_location, record_realname', 'downloads_files_records', array( "record_file_id=? and record_type IN('sslink','ssupload')", (int) $matches[1] ), 'record_id ASC', array( 0, 1 ) )->first();

				return "<img src='{$screenshot['record_location']}' alt='{$screenshot['record_realname']}'>";
			}
			catch( \Exception $e )
			{
				return $matches[0];
			}
		}
		else
		{
			return $matches[0];
		}
	}
}