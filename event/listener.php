<?php
/**
*
* Moderate Own Topics extension.
*
* @copyright (c) 2014 Daniel Chalsèche <Dakin Quelia> <https://www.danielchalseche.fr.cr/>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/


namespace dakinquelia\moderateowntopics\event;

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/*
use Symfony\Component\EventDispatcher\EventSubscriberInterface,
	phpbb\event\data as phpbbEvent,
	phpbb\user as phpbbUser,
	phpbb\auth\auth as phpbbAuth,
	phpbb\controller\helper as phpbbHelper,
	phpbb\request\request_interface as phpbbRequest;
*/


class listener implements EventSubscriberInterface
{
	protected $helper; 
	protected $auth; 
	protected $template; 
	protected $user; 
	protected $config; 
	protected $request; 
	protected $root_path; 
	protected $php_ext;
	protected $edit_allowed;
	protected $delete_allowed;

	/**
	* Instead of using "global $user;" in the function, we use dependencies again.
	*/
	public function __construct(\phpbb\controller\helper $helper, \phpbb\auth\auth $auth, \phpbb\template\template $template, \phpbb\user $user, \phpbb\config\config $config, \phpbb\request\request $request, $root_path, $php_ext)
	{
        $this->helper = $helper;
		$this->auth = $auth;
		$this->template = $template;
		$this->user = $user;
		$this->config = $config;
		$this->request = $request;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->edit_allowed = $edit_allowed = false;
		$this->delete_allowed = $delete_allowed = false;
	}
	
	/**
	*	Load Language
	**/
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'dakinquelia/moderateowntopics',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
	
	/**
	*	Get Events
	**/
	static public function getSubscribedEvents()
	{
		return array(
			// Config
			'core.permissions'								=> 'add_permission',
			'core.user_setup'  								=> 'load_language_on_setup',
			
			// Posting
			'core.posting_modify_cannot_edit_conditions'	=> 'posting_edit_check_perm',
	//		'core.submit_post_end' 							=> 'posting_moderate_topic',
			'core.posting_modify_submit_post_before'		=> 'posting_moderate_topic',
			'core.posting_modify_template_vars'				=> 'posting_moderate_topic_tpl',
			
			// Viewtopic
			'core.viewtopic_modify_post_action_conditions'	=> 'viewtopic_check_perm',	
			'core.viewtopic_modify_post_row'				=> 'viewtopic_moderate_topic_tpl',
		);
	}
	
	/**
	* Check Permission in viewtopic
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function viewtopic_check_perm($event)
	{
		//
		$mode = $this->request->variable('mode', '');
		$poster_id = $event['poster_id'];
		$forum_id = $event['row']['forum_id'];
		$topic_poster = $event['topic_data']['topic_poster'];
		
		$this->edit_allowed = ($this->user->data['is_registered'] && (($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $topic_poster == $this->user->data['user_id']) || (
		!$event['s_cannot_edit'] && 
		!$event['s_cannot_edit_time'] && 
		!$event['s_cannot_edit_locked']
		))) ? true : false;
		
		$this->delete_allowed = ($this->user->data['is_registered'] && (($this->auth->acl_get('m_delete', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $topic_poster == $this->user->data['user_id'])) || (
		!$event['s_cannot_delete'] && 
		!$event['s_cannot_delete_time'] && 
		!$event['s_cannot_delete_locked']
		)) ? true : false;
	}
	
	/**
	* Viewtopic Moderate Topic 
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function viewtopic_moderate_topic_tpl($event)
	{
		//
		$post_row = $event['post_row'];
		$post_id = $event['row']['post_id'];
		$forum_id = $event['row']['forum_id'];
		
		// Button on viewtopic
		$post_row['U_EDIT'] = ($this->edit_allowed) ? append_sid($this->root_path . 'posting.' . $this->php_ext, 'mode=edit&amp;f=' . $forum_id . '&amp;p=' . $post_id) : '';
		$post_row['U_DELETE'] = ($this->delete_allowed) ? append_sid($this->root_path . 'posting.' . $this->php_ext, 'mode=delete&amp;f=' . $forum_id . '&amp;p=' . $post_id) : ''; 
		$event['post_row'] = $post_row;
	}
	
	/**
	* Check Edit Permission in posting
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function posting_edit_check_perm($event)
	{
		// Variables
		$data = $event['post_data'];
		$forum_id = $data['forum_id'];
		$topic_poster = $data['topic_poster'];
		$mode = $this->request->variable('mode', '');
		
		// Checking permission for edit
		if ($mode == 'edit' && ($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $this->user->data['user_id'] == $topic_poster))
		{
			$event['force_edit_allowed'] = true;
		}
		else
		{
			$event['force_edit_allowed'] = false;
		}
	}
	
	/**
	* Posting Moderate Topic 
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function posting_moderate_topic($event)
	{
		// Request
		$post_lock = $this->request->is_set('lock_post');
		//$submit	= $this->request->is_set('submit');
		//$mode = $this->request->variable('mode', '');
		
		// Variables
		$error = $event['error'];
		$mode = $event['mode']; 
		$submit = $event['submit'];
		$post_data = $event['post_data'];
		$forum_id = $post_data['forum_id'];
		$topic_id = $post_data['topic_id'];
		$post_id = $post_data['post_id'];
		$topic_poster = !empty($post_data['topic_poster']) ? $post_data['topic_poster'] : '' ;
		
		// Edit Mode
		if ($mode == 'edit')
		{
			// Lock/Unlock Post Edit
			if ($post_data['post_edit_locked'] == ITEM_LOCKED && !$post_lock && ($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $this->user->data['user_id'] == $topic_poster))
			{
				$post_data['post_edit_locked'] = ITEM_UNLOCKED;
			}
			else if ($post_data['post_edit_locked'] == ITEM_UNLOCKED && $post_lock && ($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $this->user->data['user_id'] == $topic_poster))
			{
				$post_data['post_edit_locked'] = ITEM_LOCKED;
			}

			// Edit Reason
			$post_data['post_edit_reason'] = ($this->request->variable('edit_reason', false, false, \phpbb\request\request_interface::POST) && $mode == 'edit' && ($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id))) ? utf8_normalize_nfc(request_var('edit_reason', '', true)) : '';	
		}
		
		// Delete Mode
		if ($mode == 'delete') 
		{
			// Handle delete mode...
			if ($this->request->is_set_post('delete'))
			{
				$can_delete = ($this->auth->acl_get('m_delete', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $this->user->data['user_id'] == $post_data['topic_poster']) || ($post_data['poster_id'] == $this->user->data['user_id'] && $this->user->data['is_registered'] && $this->auth->acl_get('f_delete', $forum_id));
				$allow_reason = $this->auth->acl_get('m_softdelete', $forum_id) || ($thisauth->acl_gets('m_delete', 'f_delete', 'f_author', $forum_id) && $this->auth->acl_gets('m_softdelete', 'f_softdelete', 'f_author', $forum_id));
				$soft_delete_reason = (!$this->request->is_set_post('delete_permanent') && $allow_reason) ? $this->request->variable('delete_reason', '', true) : '';
								
				phpbb_handle_post_delete($forum_id, $topic_id, $post_id, $post_data, true, $soft_delete_reason);
				return;
			}
		}
		
		//
		$event['post_data'] = $post_data; 
	}
	
	/**
	* Posting Moderate Topic Template
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function posting_moderate_topic_tpl($event)
	{
		$mode = $event['mode'];
		$page_data = $event['page_data'];
		$post_data = $event['post_data'];
		$forum_id = $post_data['forum_id'];
		$topic_id = $event['topic_id'];
		$post_id = $event['post_id'];
		$post_lock = $this->request->is_set('lock_post');
		$topic_poster = !empty($post_data['topic_poster']) ? $post_data['topic_poster'] : '' ;
		$lock_post_checked	= (isset($post_lock)) ? $post_lock : $post_data['post_edit_locked'];	
		
		// Options
		$page_data['S_EDIT_REASON'] = ($mode == 'edit' && ($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id)) && $this->user->data['user_id'] == $post_data['topic_poster']) ? true : false;
		$page_data['S_LOCK_POST_ALLOWED'] = ($mode == 'edit' && ($this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('f_author', $forum_id)) && $this->user->data['user_id'] == $post_data['topic_poster']) ? true : false;
		$page_data['S_LOCK_POST_CHECKED'] = ($lock_post_checked) ? ' checked="checked"' : '';
		$page_data['S_DELETE_ALLOWED']	= ($mode == 'edit' && (($post_id == $post_data['topic_last_post_id'] && $post_data['poster_id'] == $this->user->data['user_id'] && $this->auth->acl_get('f_delete', $forum_id) && !$post_data['post_edit_locked'] && ($post_data['post_time'] > time() - ($this->config['delete_time'] * 60) || !$this->config['delete_time'])) || ($this->auth->acl_get('m_delete', $forum_id) || $this->auth->acl_get('f_author', $forum_id) && $topic_poster == $this->user->data['user_id']))) ? true : false;
		
		$event['page_data'] = $page_data;
		$event['post_data'] = $post_data; 
	}
	
	/**
	* Add administrative permission to moderate own topics
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['f_author'] = array('lang' => 'ACL_F_MODERATE_OWN_TOPICS', 'cat' => 'misc');
		//$permissions['f_moderate_own_topics'] = array('lang' => 'ACL_F_MODERATE_OWN_TOPICS', 'cat' => 'misc');
		$event['permissions'] = $permissions;
	}
}