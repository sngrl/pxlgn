<?php
/**
 * @brief		Text input class for Form Builder
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Text input class for Form Builder
 */
class _Text extends TextArea
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'minLength'			=> 1,			// Minimum number of characters. NULL is no minimum. Default is NULL.
	 		'maxLength'			=> 255,			// Maximum number of characters. NULL is no maximum. Default is NULL.
	 		'size'				=> 20,			// Text input size. NULL will use default size. Default is NULL.
	 		'disabled'			=> FALSE,		// Disables input. Default is FALSE.
	 		'autocomplete'		=> array(		// An array of options for autocomplete.
	 			'source'			=> array(),			// An array of values, or a URI which will be passed an 'input' parameter and return a JSON array of autocomplete values,
	 			'freeChoice'		=> TRUE,			// If FALSE, users will only be able to choose from autocomplete values
	 			'maxItems'			=> 5,				// Maximum number of items (if it should be unlimited, do not specify this element)
	 			'minItems'			=> 2,				// Minimum number of items (if it should be unlimited, do not specify this element) - if field is not required, 0 items will be allowed
	 			'unique'			=> TRUE,			// Specifies if the values must be unique
	 			'forceLower'		=> TRUE,			// If TRUE, all values will be converted to lowercase
	 			'minLength'			=> 5,				// The minimum length of each tag (characters) - if not specified, will be unlimited
	 			'maxLength'			=> 10,				// The minimum length of each tag (characters) - if not specified, will be unlimited
	 			'prefix'			=> TRUE,			// If TRUE, user will have option to specify one tag as a prefix
	 			'resultItemTemplate'=> 'core.foo.bar',	// Can be used to specify a custom JavaScript template to use for the result
	 		),
	 		'placeholder'		=> 'e.g. ...',	// A placeholder (NB: Will only work on compatible browsers)
	 		'regex'				=> '/[A-Z]+/i',	// RegEx of acceptable value
	 		'nullLang'			=> 'no_value',	// If provided, an "or X" checkbox will appear with X being the value of this language key. When checked, NULL will be returned as the value.
	 		'accountUsername'	=> TRUE,		// If TRUE or an \IPS\Member, additional checks will be performed to ensure provided value is acceptable for use as a username. Pass an \IPS\Member object to exclude that member from the duplicate checks
	 		'trim'				=> TRUE,		// If TRUE (which is the default), whitespace will be stripped from the start and end of the value
	 	);
	 * @encode
	 */
	protected $defaultOptions = array(
		'minLength'			=> NULL,
		'maxLength'			=> NULL,
		'size'				=> NULL,
		'disabled'			=> FALSE,
		'autocomplete'		=> NULL,
		'placeholder'		=> NULL,
		'regex'				=> NULL,
		'nullLang'			=> NULL,
		'accountUsername'	=> FALSE,
		'trim'				=> TRUE,
	);
	
	/**
	 * @brief	Child default Options
	 */
	protected $childDefaultOptions = array();
	
	/**
	 * @brief	Form type
	 */
	public $formType = 'text';

	/**
	 * Constructor
	 * Sets that the field is required if there is a minimum length and vice-versa
	 *
	 * @see		\IPS\Helpers\Form\Abstract::__construct
	 * @return	void
	 */
	public function __construct()
	{
		/* Pull in default options from child class */
		$this->defaultOptions = array_merge( $this->defaultOptions, $this->childDefaultOptions );

		/* Set username regex */
		$args = func_get_args();
		if ( isset( $args[3]['accountUsername'] ) and $args[3]['accountUsername'] === TRUE )
		{
			$args[3]['maxLength'] = \IPS\Settings::i()->max_user_name_length;
			
			if ( \IPS\Settings::i()->username_characters )
			{
				$args[3]['regex'] =  '/^[' . str_replace( '\-', '-', preg_quote( \IPS\Settings::i()->username_characters, '/' ) ) . ']*$/i';
			}
		}
		
		/* Call parent constructor */
		call_user_func_array( 'parent::__construct', $args );

		/* Add JS */
		if ( isset( $this->options['autocomplete']['prefix'] ) and $this->options['autocomplete']['prefix'] )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_core.js', 'core', 'global' ) );
		}
		
		/* Set the form type */
		$this->formType = mb_strtolower( mb_substr( get_called_class(), mb_strrpos( get_called_class(), '\\' ) + 1 ) );
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 * @note	We cannot pass the regex to the HTML5 'pattern' attribute for two reasons:
	 *	@li	PCRE and ECMAScript regex are not 100% compatible (though the instances this present a problem are admittedly rare)
	 *	@li	You cannot specify modifiers with the pattern attribute, which we need to support on the PHP side
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->text( $this->name, $this->formType, $this->value, $this->required, $this->options['maxLength'], $this->options['size'], $this->options['disabled'], $this->options['autocomplete'], $this->options['placeholder'], NULL, $this->options['nullLang'], $this->htmlId );
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$name = $this->name . '_noscript';
		if ( isset( \IPS\Request::i()->$name ) )
		{
			$return = \IPS\Request::i()->$name;
		}
		else
		{
			$return = parent::getValue();
		}

		if ( $this->options['trim'] )
		{
			if ( is_array( $return ) )
			{
				$return = array_map( 'trim', $return );
			}
			else
			{
				$return = trim( $return );
			}
		}
		
		/* Remove all invisible characters */
		$return = preg_replace( '/\p{C}+/u', '', $return );

		if ( isset( $this->options['autocomplete']['prefix'] ) and $this->options['autocomplete']['prefix'] )
		{
			if ( !is_array( $return ) )
			{
				$return = array_filter( explode( ',', $return ) );
			}

			$firstAsPrefix = $this->name . '_first_as_prefix';
			$freechoicePrefixCheckbox = $this->name . '_freechoice_prefix';
			$freechoicePrefix = $this->name . '_prefix';
			$noscriptPrefix = $this->name . '_noscript_prefix';
			if ( isset( \IPS\Request::i()->$noscriptPrefix ) and \IPS\Request::i()->$noscriptPrefix )
			{
				$currentIndex = array_search( \IPS\Request::i()->$noscriptPrefix, $return );
				if ( $currentIndex !== FALSE )
				{
					unset( $return[ $currentIndex ] );
				}
				$return['prefix'] = \IPS\Request::i()->$noscriptPrefix;
			}
			elseif ( isset( \IPS\Request::i()->$freechoicePrefixCheckbox ) and \IPS\Request::i()->$freechoicePrefixCheckbox and isset( \IPS\Request::i()->$freechoicePrefix ) and \IPS\Request::i()->$freechoicePrefix )
			{
				$currentIndex = array_search( \IPS\Request::i()->$freechoicePrefix, $return );
				if ( $currentIndex !== FALSE )
				{
					unset( $return[ $currentIndex ] );
				}
				$return['prefix'] = \IPS\Request::i()->$freechoicePrefix;
			}
			elseif ( isset( \IPS\Request::i()->$firstAsPrefix ) and \IPS\Request::i()->$firstAsPrefix )
			{
				$return = array_merge( array( 'prefix' => array_shift( $return ) ), $return );
			}
		}

		return $return;
	}
	
	/**
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{
		if ( $this->options['autocomplete'] !== NULL and ( !isset( $this->options['autocomplete']['maxItems'] ) or $this->options['autocomplete']['maxItems'] != 1 ) and !is_array( $this->value ) and $this->value !== NULL )
		{
			return array_filter( array_map( 'trim', explode( ',', $this->value ) ) );
		}
		
		return $this->value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @throws	\DomainException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();

		/* Regex */
		if ( $this->options['regex'] !== NULL and $this->value and !preg_match( $this->options['regex'], $this->value ) )
		{
			throw new \InvalidArgumentException( 'form_bad_value' );
		}
		
		/* Username */
		if ( $this->options['accountUsername'] )
		{
			/* Check if it exists */
			if ( !( $this->options['accountUsername'] instanceof \IPS\Member ) or mb_strtolower( $this->options['accountUsername']->name ) !== mb_strtolower( $this->value ) )
			{
				foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
				{
					if ( $handler->usernameIsInUse( $this->value, $this->options['accountUsername'] ) === TRUE )
					{
						if ( \IPS\Member::loggedIn()->isAdmin() )
						{
							throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('member_name_exists_admin', FALSE, array( 'sprintf' => array( $k ) ) ) );
						}
						else
						{
							throw new \DomainException( 'member_name_exists' );
						}
					}
				}
			}
			
			/* Check it's not banned */
 			foreach ( \IPS\Db::i()->select( 'ban_content', 'core_banfilters', array( "ban_type=?", 'name' ) ) as $bannedName )
 			{	 			
	 			if ( preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $bannedName, '/' ) ) . '$/i', $this->value ) )
	 			{
	 				throw new \DomainException( 'form_name_banned' );
	 			}
 			}
		}
		
		/* Tags */
		if ( $this->options['autocomplete'] !== NULL )
		{
			if ( isset( $this->options['autocomplete']['maxItems'] ) and count( $this->value ) > $this->options['autocomplete']['maxItems'] )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_max', FALSE, array( 'pluralize' => $this->options['autocomplete']['maxItems'] ) ) );
			}
			if ( isset( $this->options['autocomplete']['minItems'] ) and ( $this->required or count( $this->value ) > 0 ) and count( $this->value ) < $this->options['autocomplete']['minItems'] )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_min', FALSE, array( 'pluralize' => $this->options['autocomplete']['minItems'] ) ) );
			}
			
			if ( isset( $this->options['autocomplete']['minLength'] ) )
			{
				foreach ( $this->value as $v )
				{
					if ( mb_strlen( $v ) < $this->options['autocomplete']['minLength'] )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_length_min', FALSE, array( 'pluralize' => $this->options['autocomplete']['minItems'] ) ) );
					}
				}
			}
			if ( isset( $this->options['autocomplete']['maxLength'] ) )
			{
				foreach ( $this->value as $v )
				{
					if ( mb_strlen( $v ) > $this->options['autocomplete']['maxLength'] )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_length_max', FALSE, array( 'pluralize' => $this->options['autocomplete']['maxLength'] ) ) );
					}
				}
			}
			if ( isset( $this->options['autocomplete']['filterProfanity'] ) )
			{
				$exactProfanity = iterator_to_array( \IPS\Db::i()->select( '*', 'core_profanity_filters', array( 'm_exact=?', TRUE ) )->setKeyField('type')->setValueField('swop') );
				$looseProfanity = iterator_to_array( \IPS\Db::i()->select( '*', 'core_profanity_filters', array( 'm_exact=?', FALSE ) )->setKeyField('type')->setValueField('swop') );
					
				foreach ( $this->value as $k => $v )
				{
					if ( array_key_exists( $v, $exactProfanity ) )
					{
						$this->value[ $k ] = $exactProfanity[ $v ];
					}
					else
					{
						$this->value[ $k ] = str_replace( array_keys( $looseProfanity ), array_values( $looseProfanity ), $v );
					}
				}
			}

			if ( isset( $this->options['autocomplete']['unique'] ) AND $this->options['autocomplete']['unique'] AND is_array( $this->value ) AND count( $this->value ) )
			{
				foreach ( $this->value as $v )
				{
					if ( is_scalar( $v ) )
					{
						$this->value = array_unique( $this->value );
					}
					break;
				}
			}

			if ( is_array ( $this->value ) AND isset( $this->options['autocomplete']['source'] ) AND is_array( $this->options['autocomplete']['source'] ) AND !$this->options['autocomplete']['freeChoice'] )
			{
				$this->value = array_uintersect( $this->value, $this->options['autocomplete']['source'], function( $a, $b ){
					return ( mb_strtolower( $a ) === mb_strtolower( $b ) );
				} );
			}
		}

		return TRUE;
	}
}