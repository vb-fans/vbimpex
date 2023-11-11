<?php if (!defined('IDIR')) { die; }
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
* ipb2_010 Import Moderator module
*
* @package			ImpEx.ipb2
*
*/
class ipb2_010 extends ipb2_000
{
	var $_dependent 	= '007';

	function ipb2_010(&$displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_moderator'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source,'clear_imported_moderators'))
				{
					$displayobject->display_now("<h4>{$displayobject->phrases['moderators_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error(substr(get_class($this) , -3), $displayobject->phrases['moderator_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_moderator']);
			$displayobject->update_html($displayobject->do_form_header('index',substr(get_class($this) , -3)));
			$displayobject->update_html($displayobject->make_hidden_code(substr(get_class($this) , -3),'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['moderators_per_page'],'moderatorperpage',50));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_done', '0');
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_failed', '0');
			$sessionobject->add_session_var('moderatorstartat','0');
		}
		else
		{
			// Dependant has not been run
			$displayobject->update_html($displayobject->do_form_header('index',''));
			$displayobject->update_html($displayobject->make_description("<p>{$displayobject->phrases['dependant_on']}<i><b> " . $sessionobject->get_module_title($this->_dependent) . "</b> {$displayobject->phrases['cant_run']}</i> ."));
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],''));
			$sessionobject->set_session_var(substr(get_class($this) , -3),'FALSE');
			$sessionobject->set_session_var('module','000');
		}
	}

	function resume(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		// Set up working variables.
		$displayobject->update_basic('displaymodules','FALSE');
		$target_database_type	= $sessionobject->get_session_var('targetdatabasetype');
		$target_table_prefix	= $sessionobject->get_session_var('targettableprefix');
		$source_database_type	= $sessionobject->get_session_var('sourcedatabasetype');
		$source_table_prefix	= $sessionobject->get_session_var('sourcetableprefix');

		// Per page vars
		$moderator_start_at		= $sessionobject->get_session_var('moderatorstartat');
		$moderator_per_page		= $sessionobject->get_session_var('moderatorperpage');
		$class_num				= substr(get_class($this) , -3);

		// Start the timing
		if(!$sessionobject->get_session_var($class_num . '_start'))
		{
			$sessionobject->timing($class_num ,'start' ,$sessionobject->get_session_var('autosubmit'));
		}

		// Get an array of moderator details
		$moderator_array 		= $this->get_ipb2_moderator_details($Db_source, $source_database_type, $source_table_prefix, $moderator_start_at, $moderator_per_page);

		$user_ids_array 		= $this->get_user_ids($Db_target, $target_database_type, $target_table_prefix);
		$user_name_array 		= $this->get_username($Db_target, $target_database_type, $target_table_prefix);
		$forum_ids_array 		= $this->get_forum_ids($Db_target, $target_database_type, $target_table_prefix);

		// Display count and pass time
		$displayobject->display_now("<h4>{$displayobject->phrases['importing']} " . count($moderator_array) . " {$displayobject->phrases['users']}</h4><p><b>{$displayobject->phrases['from']}</b> : " . $moderator_start_at . " ::  <b>{$displayobject->phrases['to']}</b> : " . ($moderator_start_at + count($moderator_array)) . "</p>");

		$moderator_object = new ImpExData($Db_target, $sessionobject, 'moderator');

		foreach ($moderator_array as $moderator_id => $moderator_details)
		{
			$try = (phpversion() < '5' ? $moderator_object : clone($moderator_object));

				if($moderator_details['member_id'] == -1)
				{
					$try->set_value('mandatory', 'userid',			'1');
					$name = $displayobject->phrases['ipb_default_admin'];
				}
				else
				{
					$try->set_value('mandatory', 'userid',			$user_ids_array["$moderator_details[member_id]"]);
					$name = $user_name_array["$moderator_details[member_id]"];
				}

			// Mandatory
			$try->set_value('mandatory', 'forumid',					$forum_ids_array["$moderator_details[forum_id]"]);
			$try->set_value('mandatory', 'importmoderatorid',		$moderator_id);

			$permissions = 0;

			if($moderator_details['edit_post'])						{ $permissions += 1;}
			if($moderator_details['delete_post'])					{ $permissions += 2;}
			if($moderator_details['open_topic'] OR
				$moderator_details['close_topic'])					{ $permissions += 4;}
			if($moderator_details['edit_topic'])					{ $permissions += 8;}
			#if($moderator_details['canmanagethreads'])				{ $permissions += 16;}
			#if($moderator_details['canannounce'])					{ $permissions += 32;}
			#if($moderator_details['canmoderateposts'])				{ $permissions += 64;}
			#if($moderator_details['canmoderateattachments'])		{ $permissions += 128;}
			if($moderator_details['mass_move'])						{ $permissions += 256;}
			if($moderator_details['mass_prune'])					{ $permissions += 512;}
			if($moderator_details['view_ip'])						{ $permissions += 1024;}
			#if($moderator_details['canviewprofile'])				{ $permissions += 2048;}
			#if($moderator_details['canbanusers'])					{ $permissions += 4096;}
			#if($moderator_details['canunbanusers'])				{ $permissions += 8192;}
			#if($moderator_details['newthreademail'])				{ $permissions += 16384;}
			#if($moderator_details['newpostemail'])					{ $permissions += 32768;}
			#if($moderator_details['cansetpassword'])				{ $permissions += 65536;}
			#if($moderator_details['canremoveposts'])				{ $permissions += 131072;}
			#if($moderator_details['caneditsigs'])					{ $permissions += 262144;}
			#if($moderator_details['caneditavatar'])				{ $permissions += 524288;}
			#if($moderator_details['caneditpoll'])					{ $permissions += 1048576;}
			#if($moderator_details['caneditprofilepic'])			{ $permissions += 2097152;}
			#if($moderator_details['caneditreputation'])			{ $permissions += 4194304;}

			// Non Mandatory
			$try->set_value('nonmandatory', 'permissions',			$permissions);

			// Check if moderator object is valid
			if($try->is_valid())
			{
				if($try->import_moderator($Db_target, $target_database_type, $target_table_prefix))
				{
					$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $displayobject->phrases['moderator'] . ' -> ' . $name);
					$sessionobject->add_session_var($class_num . '_objects_done',intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1 );
				}
				else
				{
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
					$sessionobject->add_error($Db_target, 'warning', $class_num, $moderator_id,$displayobject->phrases['moderator_not_imported'], $displayobject->phrases['moderator_not_imported_rem']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['moderator_not_imported']}");
				}
			}
			else
			{
				$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $try->_failedon);
				$sessionobject->add_error($Db_target, 'invalid', $class_num, $moderator_id, $displayobject->phrases['invalid_object'], $try->_failedon);
				$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
			}
			unset($try);
		}// End foreach

		// Check for page end
		if (count($moderator_array) == 0 OR count($moderator_array) < $moderator_per_page)
		{
			$sessionobject->timing($class_num,'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var($class_num . '_start');

			$displayobject->update_html($displayobject->module_finished($this->_modulestring,
				$sessionobject->return_stats($class_num, '_time_taken'),
				$sessionobject->return_stats($class_num, '_objects_done'),
				$sessionobject->return_stats($class_num, '_objects_failed')
			));

			$sessionobject->set_session_var($class_num ,'FINISHED');
			$sessionobject->set_session_var('module','000');
			$sessionobject->set_session_var('autosubmit','0');
		}

		$sessionobject->set_session_var('moderatorstartat',$moderator_start_at+$moderator_per_page);
		$displayobject->update_html($displayobject->print_redirect('index.php',$sessionobject->get_session_var('pagespeed')));
	}// End resume
}//End Class
# Autogenerated on : August 20, 2004, 2:31 pm
# By ImpEx-generator 1.0.
/*======================================================================*/
?>
