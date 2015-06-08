<?php
/**
 * @brief		Image Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		19 Feb 2013
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
 * Image Class
 */
abstract class _Image
{
	/**
	 * @brief	Image Extensions
	 */
	public static $imageExtensions = array( 'gif', 'jpeg', 'jpe', 'jpg', 'png' );
	
	/**
	 * Determine if EXIF extraction is supported
	 *
	 * @return	bool
	 */
	public static function exifSupported()
	{
		return function_exists( 'exif_read_data' );
	}

	/**
	 * Create Object
	 *
	 * @param	string	$contents	Contents
	 * @return	\IPS\Image
	 * @throws	\InvalidArgumentException
	 */
	public static function create( $contents )
	{
		/* Create object */
		if( \IPS\Settings::i()->image_suite == 'imagemagick' )
		{
			$obj = new Image\Imagemagick( $contents );
		}
		else
		{
			$obj = new Image\Gd( $contents );
		}
		
		/* Work out the type */
		preg_match( '/\A(?:(\xff\xd8\xff)|(GIF8[79]a)|(\x89PNG\x0d\x0a)|(BM)|(\x49\x49(?:\x2a\x00|\x00\x4a))|(FORM.{4}ILBM))/', $contents, $matches );
		switch ( count( $matches ) )
		{
			case 2:
				$obj->type = 'jpeg';
			break;
			case 3:
				$obj->type = 'gif';
				if ( (boolean) preg_match( '#(\x00\x21\xF9\x04.{4}\x00\x2C.*){2,}#s', $contents ) )
				{
					$obj->isAnimatedGif = TRUE;
					$obj->contents      = $contents;
				}
			break;
			case 4:
				$obj->type = 'png';
			break;
			default:
				throw new \InvalidArgumentException;
		}

		/* Set EXIF data immediately */
		if( static::exifSupported() )
		{
			$obj->setExifData( $contents );
		
			/* If the image is misoriented, attempt to automatically reorient */
			if( \IPS\Image::exifSupported() )
			{
				/* Differences in orientation between GD and ImageMagick can cause auto-reorient to not work properly */
				/* @see http://community.invisionpower.com/4bugtrack/rc1-imagemagick-and-vertical-photos-r2952 */
				$orientation = $obj->getImageOrientation();
				
				if ( !( $obj instanceof Image\Imagemagick ) )
				{
					switch ( $orientation )
					{
						case 3:
							$obj->rotate( 180 );
						break;
	
						case 6:
							$obj->rotate( -90 );
						break;
			
						case 8:
							$obj->rotate( 90 );
						break;
					}
				}
				else
				{
					switch( $orientation )
					{
						case 3:
							$obj->rotate( 180 );
						break;
						
						case 6:
							$obj->rotate( 90 );
						break;
						
						case 8:
							$obj->rotate( -90 );
						break;
					}
				}
				
				/* ImageMagick requires orientation be reset when rotated */
				$obj->setImageOrientation( 1 );
			}
		}
		
		/* Return */
		return $obj;
	}
	
	/**
	 * @brief	Type ('png', 'jpeg' or 'gif')
	 */
	protected $type;
	
	/**
	 * @brief	Width
	 */
	public $width;
	
	/**
	 * @brief	Height
	 */
	public $height;
		
	/**
	 * @brief	Is this an animated gif?
	 */
	protected $isAnimatedGif	= FALSE;
	
	/**
	 * @brief	Contents of the image file when animated gif
	 */
	public $contents			= NULL;

	/**
	 * @brief	EXIF data - has to be pulled and stored before GD manipulates image
	 */
	protected $exif				= array();
	
	/**
	 * Resize to maximum
	 *
	 * @param	int|NULL		$maxWidth		Max Width (in pixels) or NULL
	 * @param	int|NULL		$maxHeight		Max Height (in pixels) or NULL
	 * @param	bool			$retainRatio	If TRUE, the image will keep it's current width/height ratio rather than being squashed
	 * @return	bool
	 */
	public function resizeToMax( $maxWidth=NULL, $maxHeight=NULL, $retainRatio=TRUE )
	{
		/* Work out the maximum width/height */
		$width = ( $maxWidth !== NULL and $this->width > $maxWidth ) ? $maxWidth : $this->width;
		$height = ( $maxHeight !== NULL and $this->height > $maxHeight ) ? $maxHeight : $this->height;
		
		if ( $width != $this->width or $height != $this->height )
		{
			/* Adjust the width/height as necessary if we want to keep the ratio */
			if ( $retainRatio === TRUE )
			{
				if ( $width <= $height )
				{
					$ratio = $this->height / $this->width;
					$height = $width * $ratio;
				}
				else
				{
					$ratio = $this->width / $this->height;
					$width = $height * $ratio;
				}
			}

			/* And resize */
			return $this->resize( $width, $height );
		}
	
		return TRUE;
	}
	
	/**
	 * Add Watermark
	 *
	 * @param	\IPS\Image	$watermark	The watermark
	 * @return	void
	 */
	public function watermark( \IPS\Image $watermark )
	{		
		/* If it's too big, resize the watermark */
		$watermark->resizeToMax( $this->width, $this->height );
		
		/* Impose */		
		$this->impose( $watermark, $this->width - $watermark->width, $this->height - $watermark->height );
	}

	/**
	 * Parse file object to extract EXIF data
	 *
	 * @return	array
	 * @throws	\LogicException
	 */
	public function parseExif()
	{
		if( !static::exifSupported() )
		{
			throw new \LogicException( 'NO_EXIF' );
		}

		if( !in_array( $this->type, array( 'jpeg', 'jpg', 'jpe' ) ) )
		{
			return array();
		}

		$result	= array();

		/* Read the data and store in an array */
		if( $values = $this->getExifData() )
		{
			foreach( $values as $section => $data )
			{
				foreach( $data as $name => $value )
				{
					$result[ $section . '.' . $name ]	= $value;
				}
			}
		}

		/* Return the EXIF data */
		return $result;
	}
	
	/**
	 * Get EXIF data, if possible
	 *
	 * @return	array
	 */
	public function getExifData()
	{
		return $this->exif;
	}

	/**
	 * Get EXIF data, if possible
	 *
	 * @param	string	$image	Image contents
	 * @return	void
	 */
	public function setExifData( $contents )
	{
		if( !in_array( $this->type, array( 'jpeg', 'jpg', 'jpe' ) ) )
		{
			return;
		}

		/* Exif requires a file on disk, so write it temporarily */
		$temporary	= tempnam( \IPS\TEMP_DIRECTORY, 'exif' );
		\file_put_contents( $temporary, $contents );

		$result	= @exif_read_data( $temporary, NULL, TRUE );

		/* Remove the temporary file */
		if( @is_file( $temporary ) )
		{
			@unlink( $temporary );
		}

		$this->exif	= $result;
	}
	
	/**
	 * Get Contents
	 *
	 * @return	string
	 */
	abstract public function __toString();
	
	/**
	 * Resize
	 *
	 * @param	int		$width			Width (in pixels)
	 * @param	int		$height			Height (in pixels)
	 * @return	void
	 */
	abstract public function resize( $width, $height );

	/**
	 * Crop to a given width and height (will attempt to downsize first)
	 *
	 * @param	int		$width			Width (in pixels)
	 * @param	int		$height			Height (in pixels)
	 * @return	void
	 */
	abstract public function crop( $width, $height );
	
	/**
	 * Crop at specific points
	 *
	 * @param	int		$point1X		x-point for top-left corner
	 * @param	int		$point1Y		y-point for top-left corner
	 * @param	int		$point2X		x-point for bottom-right corner
	 * @param	int		$point2Y		y-point for bottom-right corner
	 * @return	void
	 */
	abstract public function cropToPoints( $point1X, $point1Y, $point2X, $point2Y );
	
	/**
	 * Impose image
	 *
	 * @param	\IPS\Image	$image	Image to impose
	 * @param	int			$x		Location to impose to, x axis
	 * @param	int			$y		Location to impose to, y axis
	 * @return	void
	 */
	abstract public function impose( $image, $x=0, $y=0 );

	/**
	 * Rotate image
	 *
	 * @param	int		$angle	Angle of rotation
	 * @return	void
	 */
	abstract public function rotate( $angle );
	
	/**
	 * Get Image Orientation
	 *
	 * @return	int|NULL
	 */
	abstract public function getImageOrientation();
	
	/**
	 * Set Image Orientation
	 *
	 * @param	int		$orientation The orientation
	 * @return	void
	 */
	abstract public function setImageOrientation( $orientation );
}