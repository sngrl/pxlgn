<?php
/**
 * @brief		Binding Class for Prepared Statements
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPSUtf8\Db;

/**
 * Binding Class for Prepared Statements
 */
class Bind
{
	/**
	 * @brief	Values
	 */
	public $values = array();
	
	/**
	 * @brief	Types
	 */
	protected $types = ''; 
    
    /** 
     * Add value
     *
     * @param	string	$type	Type
     * @param	mixed	$value	Value
     * @return	void
     */
    public function add( $type, $value )
    { 
        $this->values[] = $value; 
        $this->types .= $type; 
    }
    
    /**
     * Do we have any bound values?
     *
     * @return bool
     */
    public function haveBinds()
    {
	    return !( empty( $this->values ) );
    }
    
    /**
     * Get array to pass to mysqli_stmt::bind_param
     *
     * @see		<a href='http://php.net/manual/en/mysqli-stmt.bind-param.php'>mysqli_stmt::bind_param</a>
     * @return	array
     */
    public function get()
    {
    	$values = array();
    	foreach ( $this->values as $k => $v )
    	{
	    	$values[ $k ] = &$this->values[ $k ];
    	}
    
    	return array_merge( array( $this->types ), $values );
    } 
}