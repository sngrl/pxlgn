<?php
/**
 * @brief		File IteratorIterator
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		10 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\File;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File IteratorIterator
 */
class _Iterator extends \IteratorIterator implements \Countable
{
	/**
	 * @brief	Stroage Extension
	 */
	protected $storageExtension;
	
	/**
	 * @brief	URL Field
	 */
	protected $urlField;
	
	/**
	 * Constructor
	 *
	 * @param	Traversable $iterator			The iterator
	 * @param	string		$storageExtension	The storage extension
	 * @param	string|NULL	$urlField			If passed a string, will look for an element with that key in the data returned from the iterator
	 * @return	void
	 */
	public function __construct( \Traversable $iterator, $storageExtension, $urlField=NULL )
	{
		$this->storageExtension = $storageExtension;
		$this->urlField = $urlField;
		return parent::__construct( $iterator );
	}
	
	/**
	 * Get current
	 *
	 * @return	\IPS\File
	 */
	public function current()
	{
		try
		{
			$data = $this->data();
			return \IPS\File::get( $this->storageExtension, $this->urlField ? $data[ $this->urlField ] : $data );
		}
		catch ( \Exception $e )
		{
			$this->next();
			return $this->current();
		}
	}
	
	/**
	 * Get data
	 *
	 * @return	mixed
	 */
	public function data()
	{
		return parent::current();
	}
	
	/**
	 * Get count
	 *
	 * @return	int
	 */
	public function count()
	{
		return $this->getInnerIterator()->count();
	}
}