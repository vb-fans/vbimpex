<?php
if (!defined('IDIR')) { die; }

class xenforo_010 extends xenforo_000
{
	var $_dependent = '007';

	function xenforo_010(&$displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_attachment'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		$class_num = substr(get_class($this) , -3);

		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source, 'clear_imported_attachments', array('productid' => 'vbulletin', 'class' => 'Post')))
				{;
					$displayobject->display_now("<h4>{$displayobject->phrases['attachments_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error($Db_target, 'fatal', $class_num, 0, $displayobject->phrases['attachment_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_attachment']);
			$displayobject->update_html($displayobject->do_form_header('index', $class_num));
			$displayobject->update_html($displayobject->make_hidden_code($class_num, 'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['units_per_page'], 'perpage', 200));
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['path_to_upload'], 'attachmentsfolder',$sessionobject->get_session_var('attachmentsfolder'),1,60));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var("010_objects_done", '0');
			$sessionobject->add_session_var("010_objects_failed", '0');
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
		$idcache 		= new ImpExCache($Db_target, $t_db_type, $t_tb_prefix);
		$ImpExData		= new ImpExData($Db_target, $sessionobject, 'attachment');
		$dir 			= $sessionobject->get_session_var('attachmentsfolder');
		
		// Start the timing
		if(!$sessionobject->get_session_var("{$class_num}_start"))
		{
			$sessionobject->timing($class_num , 'start' ,$sessionobject->get_session_var('autosubmit'));
		}

		// Get an array data
		$attachments = $this->get_xenforo_attachments($Db_source, $s_db_type, $s_tb_prefix, $start_at, $per_page);

		$displayobject->print_per_page_pass(count($attachments), $displayobject->phrases['attachments'], $start_at);

		foreach ($attachments as $import_id => $attachment)
		{
				$try = (phpversion() < '5' ? $ImpExData : clone($ImpExData));

				$path = $dir . '/' .floor($attachment['data_id'] / 1000) . '/' . $attachment['data_id'] . '-' . $attachment['file_hash'] . '.data';

				if (!is_file($path))
				{
					$displayobject->display_now("<br /><b>{$displayobject->phrases['source_file_not']} </b> :: {$attachment['filename']}");
					$sessionobject->add_error($import_id, $displayobject->phrases['attachment_not_imported'], $attachment['filename'] . ' - ' . $displayobject->phrases['attachment_not_imported_rem_1']);
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
					continue;
				}

				$file = $this->vb_file_get_contents($path);

				// Mandatory
				$try->set_value('mandatory', 'filename',				addslashes($attachment['filename']));
				$try->set_value('mandatory', 'filedata',				$file);
				$try->set_value('mandatory', 'importattachmentid',		$import_id);

				// Non Mandatory
				$try->set_value('nonmandatory', 'userid',				$idcache->get_id('user', $attachment['user_id']));
				$try->set_value('nonmandatory', 'dateline',				$attachment['attach_date']);
				$try->set_value('nonmandatory', 'visible',				'1');
				$try->set_value('nonmandatory', 'counter',				$attachment['view_count']);
				$try->set_value('nonmandatory', 'filesize',				$attachment['file_size']);
				$try->set_value('nonmandatory', 'postid',				$attachment['content_id']);
				$try->set_value('nonmandatory', 'filehash',				md5($file));

				if (!$file)
				{
					continue;
				}

			// Check if object is valid
			if($try->is_valid())
			{
				if($try->import_attachment($Db_target, $t_db_type, $t_tb_prefix))
				{
					$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $displayobject->phrases['attachment'] . ' -> ' . $attachment['filename']);
					$sessionobject->add_session_var("{$class_num}_objects_done",intval($sessionobject->get_session_var("{$class_num}_objects_done")) + 1 );
				}
				else
				{
					$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
					$sessionobject->add_error($Db_target, 'warning', $class_num, $import_id, $displayobject->phrases['attachment_import_error'], $displayobject->phrases['attachment_error_rem']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['attachment_not_imported']}");
				}// $try->import_attachment
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
		if (count($attachments) < $per_page)
		{
			$sessionobject->timing($class_num, 'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var("{$class_num}_start");

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
		
		// Update post.attach (build_thread_counters will take care of thread.attach)
		$Db_target->query("
			UPDATE {$t_tb_prefix}post AS post,
			(
				SELECT COUNT(*) AS attach, contentid AS postid
				FROM {$t_tb_prefix}attachment
				WHERE contenttypeid = 1
					AND importattachmentid != 0
					AND state = 'visible'
				GROUP BY contentid
			) AS postattach
			SET post.attach = postattach.attach
			WHERE post.postid = postattach.postid;
		");
	}// End resume
}//End Class
?>