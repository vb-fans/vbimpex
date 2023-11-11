<?php
if (!defined('IDIR')) { die; }

class xenforo_000 extends ImpExModule
{
	/**
	* Supported version
	*
	* @var    string
	*/
	var $_version = '1.0.4';
	var $_tested_versions = array();

	/**
	* Module string
	*
	* Class string
	*
	* @var    array
	*/
	var $_modulestring = 'XenForo';
	var $_homepage 	= 'http://www.xenforo.com/';
	var $_tier = '2';

	/**
	* Valid Database Tables
	*
	* @var    array
	*/
	var $_valid_tables = array(
		'addon',
		'admin',
		'admin_navigation',
		'admin_permission',
		'admin_permission_entry',
		'admin_template',
		'admin_template_compiled',
		'admin_template_include',
		'admin_template_phrase',
		'attachment',
		'attachment_data',
		'attachment_view',
		'ban_email',
		'bb_code_media_site',
		'captcha_log',
		'captcha_question',
		'code_event',
		'code_event_listener',
		'content_type',
		'content_type_field',
		'conversation_master',
		'conversation_message',
		'conversation_recipient',
		'conversation_user',
		'cron_entry',
		'data_registry',
		'deletion_log',
		'email_template',
		'email_template_compiled',
		'email_template_phrase',
		'error_log',
		'feed',
		'feed_log',
		'flood_check',
		'forum',
		'forum_read',
		'identity_service',
		'import_log',
		'ip',
		'ip_match',
		'language',
		'liked_content',
		'link_forum',
		'login_attempt',
		'moderation_queue',
		'moderator',
		'moderator_content',
		'news_feed',
		'node',
		'node_type',
		'option',
		'option_group',
		'option_group_relation',
		'page',
		'permission',
		'permission_cache_content',
		'permission_cache_content_type',
		'permission_cache_global_group',
		'permission_combination',
		'permission_combination_user_group',
		'permission_entry',
		'permission_entry_content',
		'permission_group',
		'permission_interface_group',
		'phrase',
		'phrase_compiled',
		'phrase_map',
		'poll',
		'poll_response',
		'poll_vote',
		'post',
		'profile_post',
		'profile_post_comment',
		'report',
		'report_comment',
		'route_prefix',
		'search',
		'search_index',
		'session',
		'session_activity',
		'session_admin',
		'smilie',
		'spam_cleaner_log',
		'style',
		'style_property',
		'style_property_definition',
		'style_property_group',
		'template',
		'template_compiled',
		'template_include',
		'template_map',
		'template_phrase',
		'thread',
		'thread_read',
		'thread_redirect',
		'thread_user_post',
		'thread_view',
		'thread_watch',
		'trophy',
		'trophy_user_title',
		'upgrade_log',
		'user',
		'user_alert',
		'user_alert_optout',
		'user_authenticate',
		'user_ban',
		'user_confirmation',
		'user_external_auth',
		'user_follow',
		'user_group',
		'user_group_change',
		'user_group_relation',
		'user_identity',
		'user_news_feed_cache',
		'user_option',
		'user_privacy',
		'user_profile',
		'user_status',
		'user_trophy',
		'user_upgrade',
		'user_upgrade_active',
		'user_upgrade_expired',
		'user_upgrade_log',
	);

	function xenforo_000()
	{
	}

	function get_xenforo_members_list($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();
		
		$users = $Db_object->query("
			SELECT user_id, username
			FROM {$tableprefix}user
			LIMIT $start_at, $per_page
		");
		while ($user = $Db_object->fetch_array($users))
		{
			$return_array[$user['user_id']] = $user['username'];
		}

		return $return_array;
	}

	function get_xenforo_smilies($Db_object, $databasetype, $tableprefix)
	{
		$return_array = array();

		$req_fields = array('smilie_text' => 'mandatory');

		if(!$this->check_table($Db_object, $databasetype, $tableprefix, "smilie", $req_fields))
		{
			return $return_array;
		}

		$id = 1;

		$smilies = $Db_object->query("
			SELECT smilie_id, title, smilie_text, image_url
			FROM {$tableprefix}smilie
		");
		while ($smilie = $Db_object->fetch_array($smilies))
		{
			$codes = preg_split('#\r\n|\r|\n#si', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($codes AS $code)
			{
				$smilie['smilie_text'] = $code;
				$smilie['smilie_id'] = $id;
				$return_array[$id] = $smilie;
				$id++;
			}
		}

		return $return_array;
	}

	function get_xenforo_forums($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();

		$forums = $Db_object->query("
			SELECT
			*
			FROM {$tableprefix}node
			ORDER BY lft ASC, node_id ASC
			LIMIT $start_at, $per_page
		");
		while ($forum = $Db_object->fetch_array($forums))
		{
			$return_array[$forum['node_id']] = $forum;
		}

		return $return_array;
	}


	function get_xenforo_pms($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();

		$pms = $Db_object->query("
			SELECT conversation_master.title, conversation_message.message_id, conversation_message.message, conversation_message.user_id, conversation_message.message_date, conversation_recipients.recipient_userids
			FROM {$tableprefix}conversation_master AS conversation_master
			INNER JOIN {$tableprefix}conversation_message AS conversation_message ON (conversation_message.conversation_id = conversation_master.conversation_id)
			INNER JOIN (
				SELECT conversation_id, GROUP_CONCAT(user_id) AS recipient_userids
				FROM {$tableprefix}conversation_recipient
				GROUP BY conversation_id
			) AS conversation_recipients ON (conversation_recipients.conversation_id = conversation_master.conversation_id)
			ORDER BY conversation_message.message_id ASC
			LIMIT $start_at, $per_page
		");
		while ($pm = $Db_object->fetch_array($pms))
		{
			$pm['recipients'] = preg_split('#,#si', $pm['recipient_userids'], -1, PREG_SPLIT_NO_EMPTY);
			unset($pm['recipient_userids']);
			$return_array[$pm['message_id']] = $pm;
		}

		return $return_array;
	}

	function get_pm_text_id($Db_object, $databasetype, $tableprefix, $id)
	{
		$id = $Db_object->query_first("SELECT pmtextid FROM {$tableprefix}pmtext WHERE importpmid={$id}");
		return $id['pmtextid'];
	}

	function get_xenforo_poll_details($Db_object, $databasetype, $tableprefix, $source_thread_id)
	{
		$poll = array();

		// Check that there isn't a empty value
		if(empty($source_thread_id)) { return $return_array; }

		$req_fields = array(
			'poll_id'		=> 'mandatory',
			'content_type'	=> 'mandatory',
			'content_id'	=> 'mandatory',
			'question'		=> 'mandatory',
			'voter_count'	=> 'mandatory',
			'public_votes'	=> 'mandatory',
			'multiple'		=> 'mandatory',
			'close_date'	=> 'mandatory',
		);

		if (!$this->check_table($Db_object, $databasetype, $tableprefix, "poll", $req_fields))
		{
			return $poll;
		}

		$poll = $Db_object->query_first("SELECT * FROM {$tableprefix}poll WHERE content_type = 'thread' AND content_id = $source_thread_id");
		
		if ($poll)
		{
			$poll['numberoptions'] = 0;
			
			$responses = $Db_object->query("
				SELECT
				*
				FROM {$tableprefix}poll_response
				WHERE poll_id = $poll[poll_id]
			");
			while ($response = $Db_object->fetch_array($responses))
			{
				$poll['options'] .= "|||$response[response]";
				$poll['votes'] .= "|||$response[response_vote_count]";
				$poll['numberoptions']++;
			}
			
			$poll['options'] = substr($poll['options'], 3);
			$poll['votes'] = substr($poll['votes'], 3);
			$poll['numberoptions'] = $i;
		}

		return $poll;
	}

	function get_xenforo_poll_voters($Db_object, $databasetype, $tableprefix, $poll_id)
	{
		$return_array = array();

		// Check that there isn't a empty value
		if(empty($poll_id)) { return $return_array; }

		$req_fields = array(
			'poll_id'	=> 'mandatory',
			'poll_response_id'	=> 'mandatory',
			'user_id'	=> 'mandatory',
		);

		if (!$this->check_table($Db_object, $databasetype, $tableprefix, "poll_vote", $req_fields))
		{
			return $return_array;
		}

		$i = 0;
		
		$responses = $Db_object->query("
			SELECT poll_response_id
			FROM {$tableprefix}poll_response
			WHERE poll_id = $poll_id
		");
		while ($response = $Db_object->fetch_array($responses))
		{
			$responsecache[$response['poll_response_id']] = $i;
			$i++;
		}
	
		$votes = $Db_object->query("
			SELECT
			*
			FROM {$tableprefix}poll_vote
			WHERE poll_id = $poll_id
		");
		while ($vote = $Db_object->fetch_array($votes))
		{
			$return_array[] = array(
				'user_id' => $vote['user_id'],
				'response_id' => $responsecache[$vote['poll_response_id']]
			);
		}

		return $return_array;
	}

	function get_xenforo_usergroups($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();

		$usergroups = $Db_object->query("
			SELECT user_group_id, title, user_title
			FROM {$tableprefix}user_group
			ORDER BY user_group_id ASC
			LIMIT $start_at, $per_page
		");

		while ($usergroup = $Db_object->fetch_array($usergroups))
		{
			$return_array[$usergroup['user_group_id']] = $usergroup;
		}

		return $return_array;
	}

	function get_xenforo_users($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();

		$users = $Db_object->query("
			SELECT
			*
			FROM {$tableprefix}user AS user
			LEFT JOIN {$tableprefix}user_profile AS user_profile ON (user_profile.user_id = user.user_id)
			LEFT JOIN {$tableprefix}user_option AS user_option ON (user_option.user_id = user.user_id)
			ORDER BY user.user_id ASC
			LIMIT $start_at, $per_page
		");

		while ($user = $Db_object->fetch_array($users))
		{
			$return_array[$user['user_id']] = $user;
		}

		return $return_array;
	}

	function get_xenforo_threads($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();
		
		$threads = $Db_object->query("
			SELECT thread.*, poll.poll_id
			FROM {$tableprefix}thread AS thread
			LEFT JOIN {$tableprefix}poll AS poll ON (poll.content_id = thread.thread_id AND poll.content_type = 'thread')
			ORDER BY thread_id ASC
			LIMIT $start_at, $per_page
		");
		while ($thread = $Db_object->fetch_array($threads))
		{
			$return_array[$thread['thread_id']] = $thread;
		}

		return $return_array;
	}

	function get_xenforo_posts($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();

		$posts = $Db_object->query("
			SELECT
			*
			FROM {$tableprefix}post
			ORDER BY post_id ASC
			LIMIT $start_at, $per_page
		");
		while ($post = $Db_object->fetch_array($posts))
		{
			$return_array[$post['post_id']] = $post;
		}

		return $return_array;
	}

	function get_xenforo_attachments($Db_object, $databasetype, $tableprefix, $start_at, $per_page)
	{
		$return_array = array();

		$attachments = $Db_object->query("
			SELECT attachment.attachment_id, attachment.content_id, attachment.view_count, attachment.attach_date, post.user_id, attachment_data.data_id, attachment_data.file_hash, attachment_data.filename, attachment_data.file_size
			FROM {$tableprefix}attachment AS attachment
			INNER JOIN {$tableprefix}post AS post ON (post.post_id = attachment.content_id)
			INNER JOIN {$tableprefix}attachment_data AS attachment_data ON (attachment_data.data_id = attachment.data_id)
			WHERE content_type = 'post'
			ORDER BY attachment.attachment_id ASC
			LIMIT $start_at, $per_page
		");
		while ($attachment = $Db_object->fetch_array($attachments))
		{
			$return_array[$attachment['attachment_id']] = $attachment;
		}
		
		return $return_array;
	}

	function parent_id_update($Source_Db_object, $Target_Db_object, $Source_tableprefix, $Target_tableprefix)
	{
		// mapping table: importforumid => forumid
		$importforums = array();
		
		$forums = $Target_Db_object->query("
			SELECT forumid, importforumid, importcategoryid
			FROM {$Target_tableprefix}forum
			WHERE importforumid != 0 OR importcategoryid != 0
		");
		while ($forum = $Target_Db_object->fetch_array($forums))
		{
			if ($forum['importcategoryid'] > 0)
			{
				$importforums[$forum['importcategoryid']] = $forum['forumid'];
			}
			else
			{
				$importforums[$forum['importforumid']] = $forum['forumid'];
			}
		
			$forumcache[$forum['forumid']] = $forum;
		}
		
		// mapping table: nodeid -> parentnodeid
		$parentnodes = array();
		
		$nodes = $Source_Db_object->query("
			SELECT node_id, parent_node_id
			FROM {$Source_tableprefix}node
		");
		while ($node = $Source_Db_object->fetch_array($nodes))
		{
			$parentnodes[$node['node_id']] = $node['parent_node_id'];
		}
		
		foreach ($forumcache AS $forumid => $forum)
		{
			if ($forum['importcategoryid'] > 0)
			{
				$importid = $forum['importcategoryid'];
			}
			else
			{
				$importid = $forum['importforumid'];
			}
			
			if (isset($parentnodes[$importid]) AND isset($importforums[$parentnodes[$importid]]))
			{
				$Target_Db_object->query("
					UPDATE {$tableprefix}forum
					SET parentid = " . $importforums[$parentnodes[$importid]] . "
					WHERE forumid = $forumid
				");
			}
		}
	}
}
?>