<?php
if (!defined('IDIR')) { die; }

class xenforo_006 extends xenforo_000
{
	var $_dependent = '005';

	function xenforo_006($displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_thread'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		$class_num = substr(get_class($this) , -3);

		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source, 'clear_imported_threads') AND
					$this->restart($sessionobject, $displayobject, $Db_target, $Db_source,'clear_imported_polls'))
				{;
					$displayobject->display_now("<h4>{$displayobject->phrases['threads_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error($Db_target, 'fatal', $class_num, 0, $displayobject->phrases['thread_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_thread']);
			$displayobject->update_html($displayobject->do_form_header('index', $class_num));
			$displayobject->update_html($displayobject->make_hidden_code($class_num, 'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['units_per_page'], 'perpage', 1000));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var("006_objects_done", '0');
			$sessionobject->add_session_var("006_objects_failed", '0');
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
		$ImpExData		= new ImpExData($Db_target, $sessionobject, 'thread');
		$poll_object	= new ImpExData($Db_target, $sessionobject, 'poll');

		// Start the timing
		if(!$sessionobject->get_session_var("{$class_num}_start"))
		{
			$sessionobject->timing($class_num , 'start' ,$sessionobject->get_session_var('autosubmit'));
		}

		// Get an array data
		$threads = $this->get_xenforo_threads($Db_source, $s_db_type, $s_tb_prefix, $start_at, $per_page);
		
		$forum_ids_array 	= $this->get_forum_ids($Db_target, $t_db_type, $t_tb_prefix);
		$cat_ids_array = $this->get_category_ids($Db_target, $t_db_type, $t_tb_prefix);
		
		// Display count and pass time
		$displayobject->print_per_page_pass(count($threads), $displayobject->phrases['threads'], $start_at);

		foreach ($threads as $import_id => $thread)
		{
			$try = (phpversion() < '5' ? $ImpExData : clone($ImpExData));

			// Mandatory
			$try->set_value('mandatory', 'title',			$thread['title']);
			$try->set_value('mandatory', 'importforumid',	$thread['node_id']);
			$try->set_value('mandatory', 'importthreadid',	$import_id);
			if ($forum_ids_array[$thread['node_id']])
			{
				$try->set_value('mandatory', 'forumid',                 $forum_ids_array[$thread['node_id']]);
			}
			else
			{
				if ($catid = $cat_ids_array[$thread['node_id']])
				{
					$try->set_value('mandatory', 'forumid',                 $catid);
				}
				else
				{
					$try->set_value('mandatory', 'forumid', $forum_ids_array[99999]);
				}
			}

			// Non mandatory
			$try->set_value('nonmandatory', 'visible',			(($thread['discussion_state'] == 'deleted') ? '2' : (($thread['discussion_state']) == 'moderated' ? '0' : '1')));
			$try->set_value('nonmandatory', 'open', 		($thread['discussion_open'] == 1 ? '1' : '0'));
			$try->set_value('nonmandatory', 'sticky',       ($thread['sticky'] == 1 ? '1' : '0'));
			$try->set_value('nonmandatory', 'replycount',	"$thread[reply_count]");
			$try->set_value('nonmandatory', 'postusername',	$thread['username']);
			$try->set_value('nonmandatory', 'postuserid',	$idcache->get_id('user', $thread['user_id']));
			$try->set_value('nonmandatory', 'views', 		"$thread[view_count]");
			$try->set_value('nonmandatory', 'lastpost', 	"$thread[last_post_date]");
			$try->set_value('nonmandatory', 'lastposter', 	$thread['last_post_username']);
			//$try->set_value('nonmandatory', 'lastposterid',	$idcache->get_id('user', $thread['last_post_user_id']));
			
			// Check if object is valid
			if($try->is_valid())
			{
				if($try->import_thread($Db_target, $t_db_type, $t_tb_prefix))
				{
					if(shortoutput)
					{
						$displayobject->display_now('.');
					}
					else
					{
						$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $displayobject->phrases['thread'] . ' -> ' . $thread['title']);
					}
					$sessionobject->add_session_var("{$class_num}_objects_done",intval($sessionobject->get_session_var("{$class_num}_objects_done")) + 1 );


					// Do the polls here for ease
					if ($thread['poll_id'])
					{
						unset($poll, $poll_voters, $p_try, $poll_voters_array);

						$p_try = (phpversion() < '5' ? $poll_object : clone($poll_object));

						$poll = $this->get_xenforo_poll_details($Db_source, $s_db_type, $s_tb_prefix, $import_id);
						
						$poll_voters = $this->get_xenforo_poll_voters($Db_source, $s_db_type, $s_tb_prefix, $poll['poll_id']);

						foreach ($poll_voters AS &$vote)
						{
							$vote = array($idcache->get_id('user', $vote['user_id']) => $vote['response_id']+1);
						}

						// Mandatory
						$p_try->set_value('mandatory', 'importpollid',		$poll['poll_id']);
						$p_try->set_value('mandatory', 'question',			$poll['question']);
						$p_try->set_value('mandatory', 'dateline',			$thread['post_date']);
						$p_try->set_value('mandatory', 'options',			$poll['options']);
						$p_try->set_value('mandatory', 'votes',				$poll['votes']);

						// Non Mandatory
						$p_try->set_value('nonmandatory', 'active',			'1');
						$p_try->set_value('nonmandatory', 'numberoptions',	$poll['numberoptions']);
						$p_try->set_value('nonmandatory', 'timeout',			"$poll[close_date]");  // TODO: Is it ? $poll['vote_length']
						$p_try->set_value('nonmandatory', 'multiple',			"$poll[multiple]");
						$p_try->set_value('nonmandatory', 'voters',			$poll['voter_count']);
						$p_try->set_value('nonmandatory', 'public',			"$poll[public_votes]");

						if($p_try->is_valid())
						{
							$result = $p_try->import_poll($Db_target, $t_db_type, $t_tb_prefix);
							$vb_poll_id = $Db_target->insert_id();

							if($result)
							{
								if($p_try->import_poll_to_thread($Db_target, $t_db_type, $t_tb_prefix, $vb_poll_id, $import_id))
								{
									if($p_try->import_poll_voters($Db_target, $t_db_type, $t_tb_prefix, $poll_voters, $vb_poll_id))
									{
										$displayobject->display_now('<br /><span class="isucc"><b>' . $p_try->how_complete() . '%</b></span> ' . $displayobject->phrases['poll'] . ' -> ' . $p_try->get_value('mandatory', 'question'));
										$sessionobject->add_session_var($class_num . '_objects_done', intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1);
									}
									else
									{
										$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
										$sessionobject->add_error($import_id, $displayobject->phrases['poll_not_imported_3'], $displayobject->phrases['poll_not_imported_rem']);
										$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['poll_not_imported']}");
									}
								}
								else
								{
									$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
									$sessionobject->add_error($import_id, $displayobject->phrases['poll_not_imported_1'], $displayobject->phrases['poll_not_imported_rem']);
									$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['poll_not_imported']}");
								}
							}
							else
							{
								$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
								$sessionobject->add_error($import_id, $displayobject->phrases['poll_not_imported_2'], $displayobject->phrases['poll_not_imported_rem']);
								$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['poll_not_imported']}");
							}
						}
						else
						{
							$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $p_try->_failedon);
							$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
						}
						unset($p_try);
					}

				}
				else
				{
					$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
					$sessionobject->add_error($Db_target, 'warning', $class_num, $import_id, $displayobject->phrases['thread_not_imported'], $displayobject->phrases['thread_not_imported_rem']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['thread_not_imported']}");
				}// $try->import_thread
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
		if (count($threads) < $per_page)
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
	}// End resume
}//End Class
?>