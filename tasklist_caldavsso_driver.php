<?php

/**
 * CalDAV with sso driver for the Tasklist plugin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once (dirname(__FILE__).'/config.inc.php');
require_once (dirname(__FILE__).'/tasklist_caldavsso_db.php');
require_once (dirname(__FILE__).'/tasklist_caldavsso_dav.php');

//TODO: alarms
//TODO: add priority to ui

class tasklist_caldavsso_driver extends tasklist_driver{
	const PRODID = "windkracht8/tasklist_caldavsso";
	// features this backend supports
	public $alarms = true;
	public $attachments = false; // ActiveSync version >16
	public $attendees = false; // Not supported by ActiveSync
	public $undelete = false;
	public $sortable = false;
	public $alarm_types = array('DISPLAY');

	private $rc;
	private $lists = array();
	private $tags = array();
	
	/**
	 * Default constructor
	 */
	public function __construct($plugin){
		$this->rc = $plugin->rc;
        $plugin->include_stylesheet("drivers/caldavsso/tasklist_caldavsso.css");
	}

	/**
	 * Get a list of available tasks lists from this source
	 */
	public function get_lists($filter = 0){
		$lists = array();
		$lists_db = tasklist_caldavsso_db::get_instance()->get_lists();

		if(empty($lists_db)){
			$default_list = array(
				'list_id' => 0
				,'name' => "My Tasklist"
				,'color' => "0"
				,'showalarms' => true
				,'dav_readonly' => false
				,'dav_url' => str_replace("%USER%", $this->rc->get_user_name(), tasklist_caldavsso_config::$DEFAULT_DAVSERVER).tasklist_caldavsso_config::$DEFAULT_TASKLIST
				,'dav_sso' => true
				,'dav_user' => ''
				,'dav_pass' => ''
			);
			$this->create_list($default_list);
			$lists_db = tasklist_caldavsso_db::get_instance()->get_lists();
		}

		foreach($lists_db as $list_db){
			$lists[$list_db['list_id']] = array(
				'id' => $list_db['list_id']
				,'name' => $list_db['name']
				,'listname' => $list_db['name']
				,'color' => $list_db['color']
				,'showalarms' => $list_db['showalarms']
				,'active' => true
				,'editable' => $list_db['dav_readonly'] != 1
				,'rights' => 'lrswikxtea'
			);
		}
		
		return $lists;
	}

	/**
	 * Create a new list assigned to the current user
	 *
	 * @param array Hash array with list properties
	 * @return mixed ID of the new list on success, False on error
	 * @see tasklist_driver::create_list()
	 */
	public function create_list(&$prop){
		return tasklist_caldavsso_db::get_instance()->set_list_data(
				$prop['list_id']
				,$prop['name']
				,$prop['color']
				,$prop['showalarms']
				,$prop['dav_url']
				,$prop['dav_sso']
				,$prop['dav_user']
				,$prop['dav_pass']
				,$prop['dav_readonly']
		);
	}

	/**
	 * Update properties of an existing tasklist
	 *
	 * @param array Hash array with list properties
	 * @return boolean True on success, Fales on failure
	 * @see tasklist_driver::edit_list()
	 */
	public function edit_list(&$prop){
		return tasklist_caldavsso_db::get_instance()->set_list_data(
				$prop['id']
				,$prop['name']
				,$prop['color']
				,$prop['showalarms']
				,$prop['dav_url']
				,$prop['dav_sso']
				,$prop['dav_user']
				,$prop['dav_pass']
				,$prop['dav_readonly']
		);
	}

	/**
	 * Set active/subscribed state of a list
	 *
	 * @param array Hash array with list properties
	 * @return boolean True on success, Fales on failure
	 * @see tasklist_driver::subscribe_list()
	 */
	public function subscribe_list($prop){
		return true;
	}

	/**
	 * Delete the given list with all its contents
	 *
	 * @param array Hash array with list properties
	 * @return boolean True on success, Fales on failure
	 * @see tasklist_driver::delete_list()
	 */
	public function delete_list($prop){
		return tasklist_caldavsso_db::get_instance()->del_list($prop['id']);
	}

	/**
	 * Search for shared or otherwise not listed tasklists the user has access
	 *
	 * @param string Search string
	 * @param string Section/source to search
	 * @return array List of tasklists
	 */
	public function search_lists($query, $source){
		return array();
	}

	/**
	 * Get a list of tags to assign tasks to
	 *
	 * @return array List of tags
	 */
	public function get_tags(){
		return array_values(array_unique($this->tags, SORT_STRING));
	}

	/**
	 * Get number of tasks matching the given filter
	 *
	 * @param array List of lists to count tasks of
	 * @return array Hash array with counts grouped by status (all|flagged|today|tomorrow|overdue|nodate)
	 * @see tasklist_driver::count_tasks()
	 */
	function count_tasks($lists = null){
		//TODO: count_tasks
		$counts = array('all' => 0
						,'flagged' => 0
						,'today' => 0
						,'tomorrow' => 0
						,'overdue' => 0
						,'nodate'  => 0);
		return $counts;
	}

	/**
	 * Get all task records matching the given filter
	 *
	 * @param array Hash array wiht filter criterias
	 * @param array List of lists to get tasks from
	 * @return array List of tasks records matchin the criteria
	 * @see tasklist_driver::list_tasks()
	 */
	function list_tasks($filter, $lists = null){
		if(is_array($lists)){
			$tasks = array();
			foreach($lists as $list){
				$temp = tasklist_caldavsso_dav::get_tasks($list, $filter['search'], $filter['mask']);
				if(is_array($temp) && count($temp) > 0){
					$tasks = array_merge($tasks, $temp);
				}
			}
			return $tasks;
		}else{
			return tasklist_caldavsso_dav::get_tasks($lists, $filter['search'], $filter['mask']);
		}
		return false;
	}

	/**
	 * Return data of a specific task
	 *
	 * @param mixed Hash array with task properties or task UID
	 * @param integer Bitmask defining filter criterias.
	 *				See FILTER_* constants for possible values.
	 *
	 * @return array Hash array with task properties or false if not found
	 */
	public function get_task($prop, $filter = 0){
		if(!isset($prop['id'])){
			$prop['id'] = $prop['uid'].".ics";
		}
		return $prop;
	}

	/**
	 * Get all decendents of the given task record
	 *
	 * @param mixed	Hash array with task properties or task UID
	 * @param boolean True if all childrens children should be fetched
	 * @return array List of all child task IDs
	 */
	public function get_childs($prop, $recursive = false){
		return array();
	}

	/**
	 * Get a list of pending alarms to be displayed to the user
	 *
	 * @param	integer Current time (unix timestamp)
	 * @param	mixed List of list IDs to show alarms for (either as array or comma-separated string)
	 * @return array A list of alarms, each encoded as hash array with task properties
	 * @see tasklist_driver::pending_alarms()
	 */
	public function pending_alarms($time, $lists = null){
		return array();
	}

	/**
	 * Feedback after showing/sending an alarm notification
	 *
	 * @see tasklist_driver::dismiss_alarm()
	 */
	public function dismiss_alarm($task_id, $snooze = 0){
		return false;
	}

	/**
	 * Remove alarm dismissal or snooze state
	 *
	 * @param	string	Task identifier
	 */
	public function clear_alarms($id){
		// Nothing to do here. Alarms are reset in edit_task()
	}

	/**
	 * Add a single task to the database
	 *
	 * @param array Hash array with task properties (see header of this file)
	 * @return mixed New event ID on success, False on error
	 * @see tasklist_driver::create_task()
	 */
	public function create_task($task){
		return tasklist_caldavsso_dav::create_task($task);
	}

	/**
	 * Update an task entry with the given data
	 *
	 * @param array Hash array with task properties
	 * @return boolean True on success, False on error
	 * @see tasklist_driver::edit_task()
	 */
	public function edit_task($prop){
		return tasklist_caldavsso_dav::edit_task($prop);
	}

	/**
	 * Move a single task to another list
	 *
	 * @param array Hash array with task properties:
	 * @return boolean True on success, False on error
	 * @see tasklist_driver::move_task()
	 */
	public function move_task($prop){
		return $this->edit_task($prop);
	}

	/**
	 * Remove a single task from the database
	 *
	 * @param array Hash array with task properties
	 * @param boolean Remove record irreversible
	 * @return boolean True on success, False on error
	 * @see tasklist_driver::delete_task()
	 */
	public function delete_task($prop, $force = true){
		return tasklist_caldavsso_dav::delete_task($prop);
	}

	/**
	 * Restores a single deleted task (if supported)
	 *
	 * @param array Hash array with task properties
	 * @return boolean True on success, False on error
	 * @see tasklist_driver::undelete_task()
	 */
	public function undelete_task($prop){
		return false;
	}

	/**
	 * Helper method to serialize the list of alarms into a string
	 */
	private function serialize_alarms($valarms){
		foreach ((array)$valarms as $i => $alarm) {
			if ($alarm['trigger'] instanceof DateTime) {
				$valarms[$i]['trigger'] = '@' . $alarm['trigger']->format('c');
			}
		}

		return $valarms ? json_encode($valarms) : null;
	}

	/**
	 * Helper method to decode a serialized list of alarms
	 */
	private function unserialize_alarms($alarms){
		// decode json serialized alarms
		if ($alarms && $alarms[0] == '[') {
			$valarms = json_decode($alarms, true);
			foreach ($valarms as $i => $alarm) {
				if ($alarm['trigger'][0] == '@') {
					try {
						$valarms[$i]['trigger'] = new DateTime(substr($alarm['trigger'], 1));
					}
					catch (Exception $e) {
						unset($valarms[$i]);
					}
				}
			}
		}
		// convert legacy alarms data
		else if (strlen($alarms)) {
			list($trigger, $action) = explode(':', $alarms, 2);
			if ($trigger = libcalendaring::parse_alarm_value($trigger)) {
				$valarms = array(array('action' => $action, 'trigger' => $trigger[3] ?: $trigger[0]));
			}
		}

		return $valarms;
	}

	/**
	 * Helper method to serialize task recurrence properties
	 */
	private function serialize_recurrence($recurrence){
		foreach ((array)$recurrence as $k => $val) {
			if ($val instanceof DateTime) {
				$recurrence[$k] = '@' . $val->format('c');
			}
		}

		return $recurrence ? json_encode($recurrence) : null;
	}

	/**
	 * Helper method to decode a serialized task recurrence struct
	 */
	private function unserialize_recurrence($ser){
		if (strlen($ser)) {
			$recurrence = json_decode($ser, true);
			foreach ((array)$recurrence as $k => $val) {
				if ($val[0] == '@') {
					try {
						$recurrence[$k] = new DateTime(substr($val, 1));
					}
					catch (Exception $e) {
						unset($recurrence[$k]);
					}
				}
			}
		}
		else {
			$recurrence = '';
		}

		return $recurrence;
	}

	/**
	 * Handler for user_delete plugin hook
	 */
	public function user_delete($args){
		// TODO, cleanup db
	}

	/**
	 * Add the dav fields to the ui
	 */
	public function tasklist_edit_form($action, $list, $fieldprop){
		if($action == "form-edit"){
			$list = tasklist_caldavsso_db::get_instance()->get_list($list['id']);
			$dav_url = $list['dav_url'];
			$dav_sso = $list['dav_sso'];
			$dav_user = $dav_sso == 1 ? " " : $list['dav_user'];
			$dav_pass = $dav_sso == 1 ? "" : $list['dav_pass'];
		}

		$fieldprop["dav_url"] = array('id' => "taskedit-dav_url"
							,'label' => "URL"
							,'value' => '<input id="taskedit-dav_url" name="dav_url" type="text" class="text" size="40" value="'.$dav_url.'">'
							);
		$dav_sso_value = $dav_sso == 1 ? '<input id="taskedit-dav_sso" name="dav_sso" type="checkbox" checked>' : '<input id="taskedit-dav_sso" name="dav_sso" type="checkbox">';
		$fieldprop["dav_sso"] = array('id' => "taskedit-dav_sso"
							,'label' => "SSO"
							,'value' => $dav_sso_value
							);
		$fieldprop["dav_user"] = array('id' => "taskedit-dav_user"
							,'label' => "User"
							,'value' => '<input id="taskedit-dav_user" name="dav_user" type="text" class="text" size="40" value="'.$dav_user.'">'
							);
		$fieldprop["dav_pass"] = array('id' => "taskedit-dav_pass"
							,'label' => "Password"
							,'value' => '<input id="taskedit-dav_pass" name="dav_pass" type="password" class="text" size="40" value="'.$dav_pass.'">'
							);
		$dav_readonly_value = $dav_readonly == 1 ? '<input id="taskedit-dav_readonly" name="dav_readonly" type="checkbox" checked>' : '<input id="taskedit-dav_readonly" name="dav_readonly" type="checkbox">';
		$fieldprop["dav_readonly"] = array('id' => "taskedit-dav_readonly"
							,'label' => "Readonly"
							,'value' => $dav_readonly_value
							);
		return parent::tasklist_edit_form($action, $list, $fieldprop);
	}

}
