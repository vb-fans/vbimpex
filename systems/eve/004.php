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
* eve_004 Import Forum module
*
* @package			ImpEx.eve
*
*/
class eve_004 extends eve_000
{
	var $_dependent 	= '003';

	function eve_004(&$displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_forum'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source,'clear_imported_forums'))
				{
					$displayobject->display_now("<h4>{$displayobject->phrases['forums_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error(substr(get_class($this) , -3), $displayobject->phrases['forum_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_forum']);
			$displayobject->update_html($displayobject->do_form_header('index',substr(get_class($this) , -3)));
			$displayobject->update_html($displayobject->make_hidden_code(substr(get_class($this) , -3),'WORKING'));
			$displayobject->update_html($displayobject->make_hidden_code('import_forum','working'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['forums_per_page'],'forumperpage',500));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_done', '0');
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_failed', '0');
			$sessionobject->add_session_var('forumstartat','0');

			
			$target_database_type = $sessionobject->get_session_var('targetdatabasetype');
			$target_table_prefix = $sessionobject->get_session_var('targettableprefix');
			$sessionobject->add_session_var('categoriesfinished','FALSE');
			
			// Default
			$target_database_type	= $sessionobject->get_session_var('targetdatabasetype');
			$target_table_prefix	= $sessionobject->get_session_var('targettableprefix');
			$try = new ImpExData($Db_target, $sessionobject, 'forum');
			
			$try->set_value('mandatory', 'title', 				'Default cat');
			$try->set_value('mandatory', 'displayorder',		'1');
			$try->set_value('mandatory', 'parentid',			'-1');
			$try->set_value('mandatory', 'options',				$this->_default_cat_permissions);
			$try->set_value('mandatory', 'importforumid',		'0');
			$try->set_value('mandatory', 'importcategoryid',	'1');

			$try->import_category($Db_target, $target_database_type, $target_table_prefix);
			unset($try);
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
		$forum_start_at			= $sessionobject->get_session_var('forumstartat');
		$forum_per_page			= $sessionobject->get_session_var('forumperpage');
		$class_num				= substr(get_class($this) , -3);

		// Start the timing
		if(!$sessionobject->get_session_var($class_num . '_start'))
		{
			$sessionobject->timing($class_num ,'start' ,$sessionobject->get_session_var('autosubmit'));
		}


		if($sessionobject->get_session_var('categoriesfinished') == 'FALSE')
		{
			// Sort out the categories
			$categories_array = $this->get_eve_cat_details($Db_source, $source_database_type, $source_table_prefix);

			$displayobject->display_now("<h4>{$displayobject->phrases['importing']} " . count($categories_array) . " {$displayobject->phrases['categories']}</h4>");

			$category_object = new ImpExData($Db_target, $sessionobject, 'forum');

			foreach ($categories_array as $cat_id => $cat)
			{
				$try = (phpversion() < '5' ? $category_object : clone($category_object));

				$try->set_value('mandatory', 'title', 				$cat['CATEGORY_NAME']);
				$try->set_value('mandatory', 'displayorder',		$cat['THREADING_ORDER']);

				if(!$cat['PARENT_CATEGORY_OID'])
				{
					$try->set_value('mandatory', 'parentid',			'-1');
					$try->set_value('mandatory', 'options',				$this->_default_cat_permissions);
				}
				else
				{
					$cat_ids_array = $this->get_category_ids($Db_target, $target_database_type, $target_table_prefix);
					$try->set_value('mandatory', 'parentid',			$cat_ids_array["$cat[PARENT_CATEGORY_OID]"]);
					$try->set_value('mandatory', 'options',				$this->_default_forum_permissions);
				}


				$try->set_value('mandatory', 'importforumid',		'0');
				$try->set_value('mandatory', 'importcategoryid',	$cat['CATEGORY_OID']);

				$try->set_value('nonmandatory', 'description', 		$this->html_2_bb(trim($cat['CATEGORY_DESCRIPTION'])));

				if($try->is_valid())
				{
					if($try->import_category($Db_target, $target_database_type, $target_table_prefix))
					{
						$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $displayobject->phrases['forum'] . ' -> ' . $try->get_value('mandatory', 'title'));
						$sessionobject->add_session_var($class_num  . '_objects_done', intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1);
					}
					else
					{
						$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
						$sessionobject->add_error($try->get_value('mandatory', 'importcategoryid'), $displayobject->phrases['forum_not_imported'], $displayobject->phrases['forum_not_imported_rem']);
						$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['forum_not_imported']}");
					}
				}
				else
				{
					$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $try->_failedon);
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
				}
				unset($try);
			}
			$sessionobject->add_session_var('categoriesfinished','TRUE');

		}
		else
		{
			// Sort out the forums
			$forum_array 		= $this->get_eve_forum_details($Db_source, $source_database_type, $source_table_prefix, $forum_start_at, $forum_per_page);
			$cat_ids_array 		= $this->get_category_ids($Db_target, $target_database_type, $target_table_prefix);
			$forum_id_array 	= $this->get_forum_ids($Db_target, $target_database_type, $target_table_prefix);

			// Display count and pass time
			$displayobject->display_now("<h4>{$displayobject->phrases['importing']} " . count($forum_array) . " {$displayobject->phrases['forums']}</h4><p><b>{$displayobject->phrases['from']}</b> : " . $forum_start_at . " ::  <b>{$displayobject->phrases['to']}</b> : " . ($forum_start_at + count($forum_array)) . "</p>");

			$forum_object = new ImpExData($Db_target, $sessionobject, 'forum');

			
			foreach ($forum_array as $forum_id => $forum)
			{
				$try = (phpversion() < '5' ? $forum_object : clone($forum_object));

			// Mandatory
				
				if ($cat_ids_array["$forum[PARENT_CATEGORY_OID]"]) {
					$try->set_value('mandatory', 'parentid', 			$cat_ids_array["$forum[PARENT_CATEGORY_OID]"]);
					$try->set_value('mandatory', 'title', 				'title - ' . $forum['CATEGORY_NAME']);
					
					if (!$forum['IS_CATEGORY_HIDDEN']) {
						$try->set_value('mandatory', 'displayorder',		$forum['THREADING_ORDER']);	
					} else {
						$try->set_value('mandatory', 'displayorder',		"0");
					}
					
				} elseif ($cat_ids_array["$forum[CATEGORY_OID]"]) {
					$try->set_value('mandatory', 'parentid', 			$cat_ids_array["$forum[CATEGORY_OID]"]);
					$try->set_value('mandatory', 'title', 				'title - ' . $forum['FORUM_INTRO']);
					$try->set_value('mandatory', 'displayorder',		"1");
				} else {
					var_dump($forum);
					die;
				}
				 
				#$try->set_value('mandatory', 'parentid', 			$cat_ids_array["$forum[CATEGORY_OID]"]);
				$try->set_value('mandatory', 'importforumid',		$forum['FORUM_OID']);
				$try->set_value('mandatory', 'importcategoryid',	'0');
				
				$try->set_value('mandatory', 'options',				$this->_default_forum_permissions);
				

				
				
				
				
			// Non mandatory
				
				if (trim($forum['CATEGORY_DESCRIPTION']))
					$description = trim($forum['CATEGORY_DESCRIPTION']);
					
				$try->set_value('nonmandatory', 'description', 	'description');#$description);


				if($try->is_valid())
				{
					if($try->import_forum($Db_target, $target_database_type, $target_table_prefix))
					{
						$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> ' . $displayobject->phrases['forum'] . ' -> ' . $try->get_value('mandatory', 'title'));
						$sessionobject->add_session_var($class_num  . '_objects_done', intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1);
					}
					else
					{
						$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
						$sessionobject->add_error($try->get_value('mandatory', 'importforumid'), $displayobject->phrases['forum_not_imported'], $displayobject->phrases['forum_not_imported_rem']);
						$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['forum_not_imported']}");
					}
				}
				else
				{
					$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $try->_failedon);
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
				}

				unset($try);
			}

			if (count($forum_array) == 0 OR count($forum_array) < $forum_per_page)
			{
				$this->build_forum_child_lists($Db_target, $target_database_type, $target_table_prefix);

				#$this->get_out_of_sync_parentids($Db_target, $target_database_type, $target_table_prefix);

				$sessionobject->timing($class_num, 'stop', $sessionobject->get_session_var('autosubmit'));
				$sessionobject->remove_session_var($class_num . '_start');

				$displayobject->update_html($displayobject->module_finished($this->_modulestring,
					$sessionobject->return_stats($class_num, '_time_taken'),
					$sessionobject->return_stats($class_num, '_objects_done'),
					$sessionobject->return_stats($class_num, '_objects_failed')
				));

				$sessionobject->set_session_var($class_num, 'FINISHED');
				$sessionobject->set_session_var('module', '000');
				$sessionobject->set_session_var('autosubmit', '0');
			}
			$sessionobject->set_session_var('forumstartat',$forum_start_at+$forum_per_page);
		}
		$displayobject->update_html($displayobject->print_redirect('index.php',$sessionobject->get_session_var('pagespeed')));
	}// End resume
}//End Class
# Autogenerated on : March 8, 2005, 12:17 am
# By ImpEx-generator 1.4.
/*======================================================================*/
?>

