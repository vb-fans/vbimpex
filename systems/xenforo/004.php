<?php
if (!defined('IDIR')) { die; }

class xenforo_004 extends xenforo_000
{
	var $_dependent = '003';

	function xenforo_004(&$displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_user'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		$class_num = substr(get_class($this) , -3);

		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source, 'clear_imported_users'))
				{;
					$displayobject->display_now("<h4>{$displayobject->phrases['users_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error($Db_target, 'fatal', $class_num, 0, $displayobject->phrases['user_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_user']);
			$displayobject->update_html($displayobject->do_form_header('index', $class_num));
			$displayobject->update_html($displayobject->make_hidden_code($class_num, 'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['units_per_page'], 'perpage', 1000));
			$displayobject->update_html($displayobject->make_yesno_code($displayobject->phrases['email_match'], "email_match",0));
			$displayobject->update_html($displayobject->make_yesno_code($displayobject->phrases['get_avatars'], 'get_avatars',0));
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['avatar_path'], 'get_avatars_path',$sessionobject->get_session_var('get_avatars_path'),1,60));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var("004_objects_done", '0');
			$sessionobject->add_session_var("004_objects_failed", '0');
			$sessionobject->add_session_var('startat','0');
		}
		else
		{
			// Dependant has not been run
			$displayobject->update_html($displayobject->do_form_header('index', ''));
			$displayobject->update_html($displayobject->make_description("<p>{$displayobject->phrases['dependant_on']}<i><b> " . $sessionobject->get_module_title($this->_dependent) . "</b> {$displayobject->phrases['cant_run']}</i> ."));
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],''));
			$sessionobject->set_session_var($class_num, 'FALSE');
			$sessionobject->set_session_var('module','000');
		}
	}

	function resume(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		// Set up working variables.
		$displayobject->update_basic('displaymodules','FALSE');
		$t_db_type		= $sessionobject->get_session_var('targetdatabasetype');
		$t_tb_prefix	= $sessionobject->get_session_var('targettableprefix');
		$s_db_type		= $sessionobject->get_session_var('sourcedatabasetype');
		$s_tb_prefix	= $sessionobject->get_session_var('sourcetableprefix');

		// Per page vars
		$start_at		= $sessionobject->get_session_var('startat');
		$per_page		= $sessionobject->get_session_var('perpage');
		$class_num		= substr(get_class($this) , -3);

		// Start the timing
		if(!$sessionobject->get_session_var("{$class_num}_start"))
		{
			$sessionobject->timing($class_num , 'start' ,$sessionobject->get_session_var('autosubmit'));
		}

		// Get an array data
		$users = $this->get_xenforo_users($Db_source, $s_db_type, $s_tb_prefix, $start_at, $per_page);
		$user_group_ids_array = $this->get_imported_group_ids($Db_target, $t_db_type, $t_tb_prefix);

		// Display count and pass time
		$displayobject->print_per_page_pass(count($users), $displayobject->phrases['users'], $start_at);

		$ImpExData_object = new ImpExData($Db_target, $sessionobject, 'user');

		foreach ($users as $import_id => $user)
		{
			$try = (phpversion() < '5' ? $ImpExData_object : clone($ImpExData_object));
			
			// Auto associate
			if ($sessionobject->get_session_var('email_match'))
			{
				$try->_auto_email_associate = true;
			}

			if (!empty($user['secondary_group_ids']))
			{
				$new_ids = array();
				$old_group_ids = preg_split('#,#si', $user['secondary_group_ids'], -1, PREG_SPLIT_NO_EMPTY);

				if (is_array($old_group_ids))
				{
					foreach ($old_group_ids as $old_id)
					{
						if ($user_group_ids_array[$old_id])
						{
							$new_ids[] = $user_group_ids_array[$old_id];
						}
					}
				}

				$try->set_value('nonmandatory', 'membergroupids', implode(',', $new_ids));
			}			
			
			// Mandatory
			$try->set_value('mandatory', 'usergroupid',			$user_group_ids_array[$user['user_group_id']]);
			$try->set_value('mandatory', 'importuserid',		$import_id);
			$try->set_value('mandatory', 'username',			$user["username"]);
			$try->set_value('mandatory', 'email',				trim($user['email']));

			// Non mandatory
			$try->set_value('nonmandatory', 'joindate',			$user['register_date']);
			$try->set_value('nonmandatory', 'options',			$this->_default_user_permissions);
			$try->set_value('nonmandatory', 'lastactivity',		$user['last_activity']);
			$try->set_value('nonmandatory', 'lastvisit',		$user['last_activity']);

			// Birthday
			if ($user['dob_day'] OR $user['dob_month'] OR $user['dob_year'])
			{
				$year 	= $user['dob_year'];
				$month 	= $user['dob_month'];
				$day 	= $user['dob_day'];

				$try->set_value('nonmandatory', 'birthday',			"{$month}-{$day}-{$year}");
				$try->set_value('nonmandatory', 'birthday_search',	"{$year}-{$month}-{$day}");
			}

			$try->set_value('nonmandatory', 'homepage',		$user["homepage"]);

			$try->add_default_value('Occupation',			$user['occupation']);
			$try->add_default_value('Location', 			$user['location']);
			$try->add_default_value('signature', 			$user['signature']);
			
			if ($sessionobject->get_session_var('get_avatars') AND $user['avatar_date'])
			{
				// Its going to be in the images/avatars/ dir somewhere hopefully.
				$ava_path = $sessionobject->get_session_var('get_avatars_path');

				$try->set_value('nonmandatory', 'avatar', $ava_path . '/m/' . floor($import_id / 1000) . '/' . $import_id . '.jpg');
			}

			// Check if object is valid
			if($try->is_valid())
			{
				if($try->import_user($Db_target, $t_db_type, $t_tb_prefix))
				{
					if(shortoutput)
					{
						$displayobject->display_now('.');
					}
					else
					{
						$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $import_id . ' ' . $displayobject->phrases['user'] . ' -> ' . $user['username']);
					}
					$sessionobject->add_session_var("{$class_num}_objects_done",intval($sessionobject->get_session_var("{$class_num}_objects_done")) + 1 );
				}
				else
				{
					$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
					$sessionobject->add_error($Db_target, 'warning', $class_num, $import_id, $displayobject->phrases['user_import_error'], $displayobject->phrases['user_error_rem']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['user_not_imported']}");
				}// $try->import_user
			}
			else
			{
				$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
				$sessionobject->add_error($Db_target, 'invalid', $class_num, $import_id, $displayobject->phrases['invalid_object'] . ' ' . $try->_failedon, $displayobject->phrases['invalid_object_rem']);
				$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $try->_failedon);
			}// is_valid
			unset($try);
		}// End foreach

		// Check for page end
		if (count($users) < $per_page)
		{
			$sessionobject->timing($class_num, 'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var("{$class_num}_start");

			$this->build_user_statistics($Db_target, $t_db_type, $t_tb_prefix);

			$displayobject->update_html($displayobject->module_finished($this->_modulestring,
				$sessionobject->return_stats($class_num, '_time_taken'),
				$sessionobject->return_stats($class_num, '_objects_done'),
				$sessionobject->return_stats($class_num, '_objects_failed')
			));

			$sessionobject->set_session_var($class_num , 'FINISHED');
			$sessionobject->set_session_var('module', '000');
			$sessionobject->set_session_var('autosubmit', '0');
		}
		$sessionobject->set_session_var('startat', $start_at + $per_page);
		$displayobject->update_html($displayobject->print_redirect('index.php',$sessionobject->get_session_var('pagespeed')));
	}// End resume
}//End Class
?>