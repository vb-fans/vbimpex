<?php
if (!defined('IDIR')) { die; }

class xenforo_009 extends xenforo_000
{
	var $_dependent = '004';

	function xenforo_009($displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_pm'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		$class_num = substr(get_class($this) , -3);

		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source, 'clear_imported_private_messages'))
				{;
					$displayobject->display_now("<h4>{$displayobject->phrases['pms_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error($Db_target, 'fatal', $class_num, 0, $displayobject->phrases['pm_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			if($sessionobject->get_session_var('added_default_pm_folder') != 'yup')
			{
				$target_database_type 	= $sessionobject->get_session_var('targetdatabasetype');
				$target_table_prefix  	= $sessionobject->get_session_var('targettableprefix');
				// Add the default PM folder
				$this->add_pm_folder_for_all_users($Db_target, $target_database_type, $target_table_prefix, 'Imported Received Messages');
				//$this->add_pm_folder_for_all_users($Db_target, $target_database_type, $target_table_prefix, 'Imported Sent Messages');
				$sessionobject->add_session_var('added_default_pm_folder', 'yup');
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_pm']);
			$displayobject->update_html($displayobject->do_form_header('index', $class_num));
			$displayobject->update_html($displayobject->make_hidden_code($class_num, 'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['units_per_page'], 'perpage', 1000));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var("008_objects_done", '0');
			$sessionobject->add_session_var("008_objects_failed", '0');
			$sessionobject->add_session_var('startat', "0");
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
		$pm_text_object = new ImpExData($Db_target, $sessionobject, 'pmtext');
		$pm_object 		= new ImpExData($Db_target, $sessionobject, 'pm');
		$idcache 		= new ImpExCache($Db_target, $t_db_type, $t_tb_prefix);

		// Start the timing
		if(!$sessionobject->get_session_var("{$class_num}_start"))
		{
			$sessionobject->timing($class_num , 'start' ,$sessionobject->get_session_var('autosubmit'));
		}

		// Get an array data
		$pms = $this->get_xenforo_pms($Db_source, $s_db_type, $s_tb_prefix, $start_at, $per_page);


		// Display count and pass time
		$displayobject->print_per_page_pass($data_array['count'], $displayobject->phrases['pms'], $start_at);

		foreach ($pms AS $import_id => $pm)
		{
			$vB_pm_text = (phpversion() < '5' ? $pm_text_object : clone($pm_text_object));

			$touserarray = array();
			foreach ($pm['recipients'] AS $userid)
			{
				if ($userid != $pm['user_id'])
				{
					$touserarray[$idcache->get_id('user', $userid)] = $idcache->get_id('username', $userid);
				}
			}

			$vB_pm_text->set_value('mandatory', 'fromuserid',		$idcache->get_id('user', $pm['user_id']));
			$vB_pm_text->set_value('mandatory', 'title',			$pm['title']);
			$vB_pm_text->set_value('mandatory', 'message',			$pm['message']);
			$vB_pm_text->set_value('mandatory', 'importpmid',		$pm['message_id']);

			$vB_pm_text->set_value('mandatory', 'touserarray',		addslashes(serialize($touserarray)));
			$vB_pm_text->set_value('nonmandatory', 'fromusername',	$idcache->get_id('username', $pm['user_id']));
			$vB_pm_text->set_value('nonmandatory', 'dateline',		$pm['message_date']);
			$vB_pm_text->set_value('nonmandatory', 'showsignature',	"1");
			$vB_pm_text->set_value('nonmandatory', 'allowsmilie',	"1");

			// Check if object is valid
			if($vB_pm_text->is_valid())
			{
				$pm_text_id = $vB_pm_text->import_pm_text($Db_target, $t_db_type, $t_tb_prefix);

				// Might be an already imported
				if ($pm_text_id == false)
				{
					$pm_text_id = $this->get_pm_text_id($Db_target, $t_db_type, $t_tb_prefix, $pm['message_id']);
				}

				if ($pm_text_id)
				{
					// Sender pm
					$vB_pm_from = (phpversion() < '5' ? $pm_object : clone($pm_object));
					$vB_pm_from->set_value('mandatory', 'pmtextid',			$pm_text_id);
					$vB_pm_from->set_value('mandatory', 'importpmid',  		$pm['message_id']);
					$vB_pm_from->set_value('mandatory', 'userid',			$idcache->get_id('user', $pm['user_id']));
					//$vB_pm_from->set_value('nonmandatory', 'folderid',		$this->get_custom_pm_folder_id($Db_target, $t_db_type, $t_tb_prefix, $idcache->get_id('user', $pm['user_id']), 'Imported Sent Messages'));
					$vB_pm_from->set_value('nonmandatory', 'folderid',		-1);
					$vB_pm_from->set_value('nonmandatory', 'messageread',	'0');

					// Recipients PMs
					foreach ($touserarray AS $userid => $username)
					{
						// The touser pm
						$vB_pm_to = (phpversion() < '5' ? $pm_object : clone($pm_object));
						$vB_pm_to->set_value('mandatory', 'pmtextid',			$pm_text_id);
						$vB_pm_to->set_value('mandatory', 'userid',    			$userid);
						$vB_pm_to->set_value('mandatory', 'importpmid',  		$pm['message_id']);
						$vB_pm_to->set_value('nonmandatory', 'folderid', $this->get_custom_pm_folder_id($Db_target, $t_db_type, $t_tb_prefix, $userid, 'Imported Received Messages'));
						$vB_pm_to->set_value('nonmandatory', 'messageread',		'0');
						$vB_pm_to->import_pm($Db_target, $t_db_type, $t_tb_prefix);
					}

					if ($vB_pm_text->is_valid())
					{
						if ($vB_pm_from->import_pm($Db_target, $t_db_type, $t_tb_prefix))
						{
							if(shortoutput)
							{
								$displayobject->display_now('.');
							}
							else
							{
								//$displayobject->display_now('<br /><span class="isucc"><b>' . $vB_pm_to->how_complete() . '%</b></span> ' . $displayobject->phrases['pm'] . ' -> ' . $pm['title']);
							}

							$sessionobject->add_session_var($class_num . '_objects_done', intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1);
						}
						else
						{
							echo "<br> a failed here";
							// TODO: errors
							$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
							$sessionobject->add_error($data['msg_id'], $displayobject->phrases['pm_not_imported'], $displayobject->phrases['pm_not_imported_rem_1']);
							$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['pm_not_imported']}");
						}
					}
					else
					{
						// TODO: errors
						$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $vB_pm->_failedon);
						$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
					}
				}
				else
				{
					// TODO: errors
					echo "<br> a failed there";
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
					$sessionobject->add_error($Db_target, 'invalid', $class_num, $data['msg_id'], $displayobject->phrases['pm_not_imported'] . ' ' . $try->_failedon, $displayobject->phrases['pm_not_imported_rem_2']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['pm_not_imported']}");
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num . '_objects_failed') + 1 );
				}
			}// is_valid
			else
			{

				$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $vB_pm_text->_failedon);
				$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
			}
			unset($vB_pm_text);
		}// End foreach

		// Check for page end
		if (count($pms) < $per_page)
		{
			$sessionobject->timing($class_num, 'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var("{$class_num}_start");

			if ($this->update_user_pm_count($Db_target, $t_db_type, $t_tb_prefix))
			{
				$displayobject->display_now($displayobject->phrases['completed']);
			}
			else
			{
				$displayobject->display_now($displayobject->phrases['failed']);
			}

			$displayobject->update_html($displayobject->module_finished($this->_modulestring,
				$sessionobject->return_stats($class_num, '_time_taken'),
				$sessionobject->return_stats($class_num, '_objects_done'),
				$sessionobject->return_stats($class_num, '_objects_failed')
			));

			$sessionobject->set_session_var($class_num , 'FINISHED');
			$sessionobject->set_session_var('module', '000');
			$sessionobject->set_session_var('autosubmit', '0');
		}


		$sessionobject->set_session_var('startat', $per_page + $start_at);
		$displayobject->update_html($displayobject->print_redirect('index.php',$sessionobject->get_session_var('pagespeed')));
	}// End resume
}//End Class
?>
