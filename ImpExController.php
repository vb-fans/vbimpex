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
* The controller mediates between the display and the session and deals with POST variables
*
*
* @package 		ImpEx
*
*/
if (!defined('IDIR')) { die; }

class ImpExController
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
	* Constructor
	*
	* Empty
	*
	*/
	public function __construct()
	{
	}

	/**
	* Places the $_POST values in the session array
	*
	* @param	object	$sessionobject	The current session object
	* @param	array	$postarray		The $_POST array
	*
	* @return	void
	*/
	public function get_post_values(&$sessionobject, array $postarray): void
	{
		// TODO: Need some checking and error handling in here.
		foreach ($postarray as $key => $value)
		{
			$sessionobject->add_session_var($key, $value);
		}
	}

	/**
	* Modifies the display depending on the state of the session object.
	*
	* @param	object	$sessionobject	The current session object
	* @param	object	$displayobject	The display object to be updated
	*
	* @return	void
	*/
	public function updateDisplay(&$sessionobject, &$displayobject): void
	{
		if ($sessionobject->_session_vars['system'] == 'NONE')
		{
			$displayobject->update_basic('status', 'Please choose a system to import from');
			$displayobject->update_basic('displaymodules', 'FALSE');
			$displayobject->update_basic('choosesystem', 'TRUE');
		}
		else
		{
			$displayobject->update_basic('system', $sessionobject->_session_vars['system']);
		}
		for ($i = 0; $i <= $sessionobject->get_number_of_modules(); $i++)
		{
			$position = str_pad($i, 3, '0', STR_PAD_LEFT);
			if ($sessionobject->_session_vars[$position] == 'WORKING')
			{
				$displayobject->update_basic('displaylinks', 'FALSE');
			}
		}
	}

	/**
	* Returns the current session or false if there isn't a current one
	*
	* @param	object	$Db_object		The database object connected to the dB where the session is stored
	* @param	string	$targettableprefix	Table prefix
	*
	* @return	object|false
	*/
	public function return_session(&$Db_object, string $targettableprefix)
	{
		$session_data = false;
		if (FORCESQLMODE)
		{
			$Db_object->query("SET sql_mode = ''");
		}
		$session_db = $Db_object->query("SELECT data FROM {$targettableprefix}datastore WHERE title = 'ImpExSession'");

		if ($session_db && $session_db->num_rows > 0)
		{
			$row = $session_db->fetch_assoc();
			$session_data = $row['data'];
		}

		return $session_data ? unserialize($session_data) : false;
	}

	/**
	* Stores the current session
	*
	* @param	object	$Db_object		The database object connected to the dB where the session is stored
	* @param	string	$targettableprefix	Table prefix
	* @param	object	$ImpExSession	The session to store
	*
	* @return	void
	*/
	public function store_session(&$Db_object, string $targettableprefix, &$ImpExSession): void
	{
		if (FORCESQLMODE)
		{
			$Db_object->query("SET sql_mode = ''");
		}
		$Db_object->query("
			REPLACE INTO {$targettableprefix}datastore (title, data)
			VALUES ('ImpExSession', '" . $Db_object->real_escape_string(serialize($ImpExSession)) . "')
		");
	}
}
/*======================================================================*/