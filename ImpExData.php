<?php
/*======================================================================*\
|| ####################################################################
|| # vBulletin Impex
|| # ----------------------------------------------------------------
|| # All PHP code in this file is Copyright 2000-2014 vBulletin Solutions Inc.
|| # This code is made available under the Modified BSD License -- see license.txt
|| # http://www.vbulletin.com 
|| ####################################################################
\*======================================================================*/
/**
* Is the abstract factory that handles data object instantiation.
*
* The object will create itself depending on the type that is passed to
* the constructor. The object will consist of a number of elements
* some being vbmandatory and the other nonvbmandatory.
*
* A valid object is one that has values for all the vbmandatory elements.
*
* @package 		ImpEx
*
*/
if (!class_exists('ImpExDatabase')) { die('Direct class access violation'); }

class ImpExData extends ImpExDatabase
{
	/**
	* Class version
	*
	* This will allow the checking for interoperability of class version in different
	* versions of ImpEx
	*
	* @var    string
	*/
	private string $_version = '0.0.1';

	/**
	* Data elements store
	*
	* 3D array to contain  'mandatory', 'nonmandatory', 'dictionary'
	*
	* @var    array
	*/
	private array $_values = [];

	/**
	* Element types
	*
	* Element types in the _values array
	*
	* @var    array
	*/
	private array $_elementtypes = ['mandatory', 'nonmandatory'];

	/**
	* Object data type
	*
	* Stores the type of data object, i.e. user, post, thread.
	*
	* @var    string
	*/
	private string $_datatype = '';

	/**
	* Object data type
	*
	* Stores the type of product, i.e. blog, PT, vBulletin
	*
	* @var    string
	*/
	private string $_producttype = '';

	/**
	* is_valid error store
	*
	* Stores the type elements that is_valid failed on
	*
	* @var    string
	*/
	private string $_failedon = '';

	/**
	* flag for default values
	*
	* Stores if the object has any default fields, i.e. Location, Occupation
	*
	* @var    boolean
	*/
	private bool $_has_default_values = false;

	/**
	* store for default values
	*
	* Stores data for default values
	*
	* @var    array
	*/
	private array $_default_values = [];

	/**
	* flag for customFields
	*
	* Stores if the object has any custom fields, i.e. new profile field entries
	*
	* @var    boolean
	*/
	private bool $_has_custom_types = false;

	/**
	* store for custom fields
	*
	* Stores data for custom fields
	*
	* @var    array
	*/
	private array $_custom_types = [];

	/**
	* Password flag
	*
	* Defines where the password needs to be md5() before md5($password . $salt) or not.
	*
	* @var    boolean
	*/
	private bool $_password_md5_already = false;

	/**
	* Email Associate
	*
	* Matches imported users to existing associations opposed to creating new users.
	*
	* @var    boolean
	*/
	private bool $_auto_email_associate = false;

	/**
	* Userid Associate
	*
	* Matches imported users to existing associations opposed to creating new users, to be used when migrating
	* from a vBulletin installed product to another.
	*
	* @var    boolean
	*/
	private bool $_auto_userid_associate = false;

	/**
	* Password flag
	*
	* Here in case the imported password can't be retrieved, i.e. it is in crypt so it
	* forces the board to assign a new one.
	*
	* @var    boolean
	*/
	private bool $_setforgottenpassword = false;

	/**
	* Instantiates a class of the child module being called by index.php
	*
	* @param	object	$Db_object		The database that has the vbfield definitions
	* @param	object	$sessionobject	The current session object.
	* @param	string	$type			The name of the object to create user, post, thread, etc.
	* @param	string	$product		The product type (default is 'vbulletin').
	*
	* @return	void
	*/
	public function __construct(&$Db_object, &$sessionobject, string $type, string $product = 'vbulletin')
	{
		$targetdatabasetype = $sessionobject->get_session_var('targetdatabasetype');
		$targettableprefix = $sessionobject->get_session_var('targettableprefix');
		$this->_datatype = $type;
		$this->_producttype = $product;
		$this->_values = $this->create_data_type(
			$Db_object,
			$targetdatabasetype,
			$targettableprefix,
			$type,
			$product
		);
		if (!$this->_values) {
			$sessionobject->add_error(
				'fatal',
				'ImpExData',
				"ImpExData constructor failed trying to construct a $type object",
				'Does the database user have modify permissions? Is it a valid connection? Are all the tables ok?'
			);
		}
	}

	/**
	* Returns the valid state of the data object
	*
	* Searches the mandatory elements for a NULL value, if it finds one it stores it in _failedon and
	* returns FALSE, otherwise returns TRUE
	*
	* @return	bool
	*/
	public function is_valid(): bool
	{
		$return_state = true;

		if (!isset($this->_values[$this->_datatype]['mandatory'])) {
			echo "No valid entries in vbfields.php for this type<br />Datatype: {$this->_datatype}";
			exit;
		}

		foreach ($this->_values[$this->_datatype]['mandatory'] as $key => $value) {
			// Guest user hack
			if (($key === 'userid' || $key === 'bloguserid') && $value == 0) {
				continue;
			}
			if (empty($value) && $value != 0 || $value === '!##NULL##!' || strlen($value) === 0) {
				$this->_failedon = $key;
				return false;
			}
			if ($this->_values[$this->_datatype]['dictionary'][$key] === 'return true;') {
				$return_state = true;
			} else {
				$check_data = eval('return ' . $this->_values[$this->_datatype]['dictionary'][$key] . ';');
				if (!$check_data($value)) {
					$this->_failedon = $key;
					return false;
				}
			}
		}

		// Check all the nonmandatory ones as well, if there are any - subscriptionlog
		if (is_array($this->_values[$this->_datatype]['nonmandatory'])) {
			foreach ($this->_values[$this->_datatype]['nonmandatory'] as $key => $value) {
				if ($value === '!##NULL##!') {
					$this->_values[$this->_datatype]['nonmandatory'][$key] = ''; // Empty it for the SQL so the database will default to the field default
				}
				if ($this->_values[$this->_datatype]['dictionary'][$key] === 'return true;') {
					$return_state = true;
				} else {
					$check_data = eval('return ' . $this->_values[$this->_datatype]['dictionary'][$key] . ';');
					if (!$check_data($value)) {
						$this->_failedon = $key;
						return false;
					}
				}
			}
		}
		return $return_state;
	}

	/**
	* Returns the percentage completeness of the object
	*
	* Calculates the NULL's from the total amount of elements to discover the percentage
	* complete that the object is
	*
	* @return	double
	*/
	public function how_complete(): float
	{
		$totalelements = 0;
		$nullelements = 0;
		foreach ($this->_elementtypes as $type) {
			if (is_array($this->_values[$this->_datatype][$type])) {
				foreach ($this->_values[$this->_datatype][$type] as $value) {
					if ($value === '!##NULL##!' || $value === '' || $value === null) {
						$nullelements++;
					}
					$totalelements++;
				}
			}
		}
		return number_format((($totalelements - $nullelements) * 100) / $totalelements, 2, '.', '');
	}

	/**
	* Accessor
	*
	* @param	string	$section		The type of value being retrieved
	* @param	string	$name			The name of value being retrieved
	*
	* @return	mixed|string|null
	*/
	public function get_value(string $section, string $name)
	{
		return $this->_values[$this->_datatype][$section][$name] ?? null;
	}

	/**
	* Accessor
	*
	* @param	string	$section		The type of value being set
	* @param	string	$name			The name of value being set
	* @param	mixed	$value			The passed value
	*
	* @return	bool
	*/
	public function set_value(string $section, string $name, $value): bool
	{
		if (array_key_exists($name, $this->_values[$this->_datatype][$section])) {
			$this->_values[$this->_datatype][$section][$name] = $value;
			return true;
		}
		return false;
	}

	/**
	* Accessor
	*
	* @param	string	$key			The name of value being set
	* @param	mixed	$value			The passed value
	*
	* @return	bool
	*/
	public function add_default_value(string $key, $value): bool
	{
		if (empty($this->_default_values[$key])) {
			$this->_default_values[$key] = $value;
			$this->_has_default_values = true;
		} else {
			$this->_default_values[$key] = $value;
		}
		return $this->_has_default_values;
	}

	/**
	* Accessor : Returns the array of default values
	*
	* @return	array
	*/
	public function get_default_values(): array
	{
		return $this->_default_values;
	}

	/**
	* Accessor
	*
	* @param	string	$key			The name of value being set
	* @param	mixed	$value			The passed value
	*
	* @return	bool
	*/
	public function add_custom_value(string $key, $value): bool
	{
		if (empty($this->_custom_types[$key])) {
			$this->_custom_types[$key] = $value;
			$this->_has_custom_types = true;
		} else {
			$this->_custom_types[$key] = $value;
		}
		return $this->_has_custom_types;
	}

	/**
	* Accessor : Returns the array of custom values
	*
	* @return	array
	*/
	public function get_custom_values(): array
	{
		return $this->_custom_types;
	}
}
/*======================================================================*/
?>