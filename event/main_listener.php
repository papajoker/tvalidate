<?php
/**
 *
 * tvalidate. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, papajoke
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace papajoke\tvalidate\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * tvalidate Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public function __construct(
        \phpbb\user $user,
		\phpbb\template\template $template,
		\phpbb\db\driver\driver_interface $db)
	{
		$this->user = $user;
		$this->template = $template;
		$this->db = $db;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_highlight_modify'			=> 'view_topic',
			'core.modify_posting_parameters'			=> 'modify_posting_parameters',
		);
	}


	/** 
	 * hook debut du topic 
	 */
	function view_topic($event) {
		$sql='SELECT icons_alt FROM '.ICONS_TABLE.' WHERE icons_id='.(int)$event['topic_data']['icon_id'].' LIMIT 1';
		$result = $this->db->sql_query($sql);
		$is_resolved = $this->db->sql_fetchfield('icons_alt') == 'résolu';
		$this->db->sql_freeresult($result);
		$this->template->assign_vars([
			'TOPIC_RESOLVED' => $is_resolved,
			'TOPIC_FIRST_POST' =>$event['topic_data']['topic_first_post_id'],
			'USER_ID_VISIT' => isset($this->user->data['user_id'])? $this->user->data['user_id'] : 0 ,
		]);
	}

	/** 
	 * modifier un post existant
	 */
	function modify_posting_parameters($event){
		if ($event['mode']<>'validate') return;

		if ($this->user->data['user_id']<2 /*|| ! $this->auth->acl_gets('m_edit', $event['forum_id'])*/) {
			trigger_error('NOT_AUTHORISED');
		};

		$sql= 'SELECT poster_id FROM '. POSTS_TABLE .' WHERE post_id='.(int)$event['post_id'].' LIMIT 1';
		//echo $sql;
		$result = $this->db->sql_query($sql);
		$poster = (int) $this->db->sql_fetchfield('poster_id');
		$this->db->sql_freeresult($result);
		if ( $poster != (int)$this->user->data['user_id'] && ! $this->auth->acl_gets('a_', 'm_') ) {
			trigger_error('NOT_AUTHORISED');
		}

		// cherche quelle icone est "résolu"
		$icon=0;
		$sql= 'SELECT icons_id FROM '. ICONS_TABLE .' WHERE icons_alt="résolu" LIMIT 1';
		$result = $this->db->sql_query($sql);
		$icon = (int) $this->db->sql_fetchfield('icons_id');
		if ($icon>0) {
			$sql= 'UPDATE '. POSTS_TABLE .' SET icon_id=IF(icon_id!='.$icon.','.$icon. ',0) WHERE post_id='.$event['post_id'];
			$result = $this->db->sql_query($sql);
			$sql= 'UPDATE '. TOPICS_TABLE .' SET icon_id=IF(icon_id!='.$icon.','.$icon. ',0) WHERE topic_id='.$event['topic_id'];
			$result = $this->db->sql_query($sql);
		}
		$this->db->sql_freeresult($result);
		
		header('Location: '.str_replace('&amp;','&',$this->user->referer));
		exit;
	}

}
