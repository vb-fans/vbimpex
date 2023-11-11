<?php
if (!defined('IDIR')) { die; }

class xenforo_005 extends xenforo_000
{
	var $_dependent = '004';

	function xenforo_005(&$displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_forum'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		$class_num = substr(get_class($this) , -3);

		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source, 'clear_imported_forums'))
				{;
					$displayobject->display_now("<h4>{$displayobject->phrases['forums_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error($Db_target, 'fatal', $class_num, 0, $displayobject->phrases['forum_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_forum']);
			$displayobject->update_html($displayobject->do_form_header('index', $class_num));
			$displayobject->update_html($displayobject->make_hidden_code($class_num, 'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['units_per_page'], 'perpage', 1000));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var("005_objects_done", '0');
			$sessionobject->add_session_var("005_objects_failed", '0');
			$sessionobject->add_session_var('startat','0');
			$sessionobject->add_session_var('categoriesfinished','FALSE');

			// Private Category
			$t_db_type		= $sessionobject->get_session_var('targetdatabasetype');
			$t_tb_prefix	= $sessionobject->get_session_var('targettableprefix');
			
   		    $try = new ImpExData($Db_target, $sessionobject, 'forum');
			$try->set_value('mandatory', 'title', 				'Lost & Found');
			$try->set_value('mandatory', 'displayorder',		'1');
			$try->set_value('mandatory', 'parentid',			'-1');
			$try->set_value('mandatory', 'importforumid',		'99999');
			$try->set_value('mandatory', 'importcategoryid',	'0');
			$try->set_value('mandatory', 'options',				$this->_default_forum_permissions);
			$try->set_value('nonmandatory', 'description', 		'This is a default forum for orphaned threads');
			$try->import_forum($Db_target, $t_db_type, $t_tb_prefix);
			unset($try);
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
		$forums = $this->get_xenforo_forums($Db_source, $s_db_type, $s_tb_prefix, $start_at, $per_page);

		// Display count and pass time
		$displayobject->print_per_page_pass(count($forums), $displayobject->phrases['forums'], $start_at);

		$ImpExData_object = new ImpExData($Db_target, $sessionobject, 'forum');

		foreach ($forums as $import_id => $forum)
		{
			$try = (phpversion() < '5' ? $ImpExData_object : clone($ImpExData_object));

			$try->set_value('mandatory', 'displayorder',		$forum['display_order']);
			// set all parentids to -1, we'll fix this later
			$try->set_value('mandatory', 'parentid', -1);
			if ($forum['node_type_id'] == 'Category')
			{
				$try->set_value('mandatory', 'options',				$this->_default_cat_permissions);
				$try->set_value('mandatory', 'importforumid',		"0");
				$try->set_value('mandatory', 'importcategoryid',	"$import_id");
			}
			else
			{
				$try->set_value('mandatory', 'options',				$this->_default_forum_permissions);
				$try->set_value('mandatory', 'importforumid',		"$import_id");
				$try->set_value('mandatory', 'importcategoryid',	"-1");
			}
			$try->set_value('mandatory', 'title',				$forum['title']);
			$try->set_value('nonmandatory', 'description',		$forum['description']);

			// Check if object is valid
			if($try->is_valid())
			{
				if($try->import_forum($Db_target, $t_db_type, $t_tb_prefix))
				{
					$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $displayobject->phrases['forum'] . ' -> ' . $forum['title']);
					$sessionobject->add_session_var("{$class_num}_objects_done",intval($sessionobject->get_session_var("{$class_num}_objects_done")) + 1 );
				}
				else
				{
					$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
					$sessionobject->add_error($Db_target, 'warning', $class_num, $import_id, $displayobject->phrases['forum_import_error'], $displayobject->phrases['forum_error_rem']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['forum_not_imported']}");
				}// $try->import_forum
			}
			else
			{
				$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
				$sessionobject->add_error($Db_target, 'invalid', $class_num, $import_id, $displayobject->phrases['invalid_object'] . ' ' . $try->_failedon, $displayobject->phrases['invalid_object_rem']);
				$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $try->_failedon);
			}// is_valid
			//die;
			unset($try);
		}// End foreach

		// Check for page end
		if (count($forums) < $per_page)
		{
			$sessionobject->timing($class_num, 'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var("{$class_num}_start");

			$this->parent_id_update($Db_source, $Db_target, $s_tb_prefix, $t_tb_prefix);
			$this->build_forum_child_lists($Db_target, $t_db_type, $t_tb_prefix);

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
		//die;
	}// End resume
}//End Class
?>
