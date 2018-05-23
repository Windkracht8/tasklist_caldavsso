<?php

class tasklist_caldavsso_db{
	static private $instance;
	private $username;
	private $dbh;
	private $prefix;
	
	static function get_instance(){
		if(!self::$instance){self::$instance = new tasklist_caldavsso_db();}
		return self::$instance;
	}
	private function __construct(){
		$this->rc = rcube::get_instance();
		$this->username = $this->rc->get_user_name();
		$this->dbh = rcmail::get_instance()->db;
		$this->prefix = $this->rc->config->get("db_prefix", "");
	}

	public function get_lists(){
		$sql = "SELECT * FROM ".$this->prefix."tasklist_caldavsso_lists WHERE username = ?;";
		$sql_result = $this->dbh->query($sql, array($this->username));
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return false;}
		for($lists = array(); $temp = $this->dbh->fetch_assoc($sql_result);){$lists[] = $temp;}
		return $lists;
	}
	public function get_list($list_id){
		$sql = "SELECT * FROM ".$this->prefix."tasklist_caldavsso_lists WHERE username = ? AND list_id = ?;";
		$sql_result = $this->dbh->query($sql, array($this->username, $list_id));
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return false;}
		$list = $this->dbh->fetch_assoc($sql_result);
		if($list['dav_sso'] == 1){
			$list['dav_user'] = $this->username;
			$list['dav_pass'] = $this->rc->get_user_password();
		}else{
			$list['dav_pass'] = $this->rc->decrypt($list['dav_pass']);
		}
		return $list;
	}
	public function set_list_data($list_id, $name, $color, $showalarms, $dav_url, $dav_sso, $dav_user, $dav_pass, $dav_readonly){
		if(!$list_id){$list_id = $this->incr_list_id();if(!$list_id){return false;}}
		$dav_pass_enc = $this->rc->encrypt($dav_pass);
		$sql = "INSERT INTO ".$this->prefix."tasklist_caldavsso_lists "
					."(username, list_id, name, color, showalarms, dav_url, dav_sso, dav_user, dav_pass, dav_readonly) "
					."VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
					."ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), showalarms=VALUES(showalarms)"
						.", dav_url=VALUES(dav_url), dav_sso=VALUES(dav_sso), dav_user=VALUES(dav_user), dav_pass=VALUES(dav_pass), dav_readonly=VALUES(dav_readonly);";
		$sql_result = $this->dbh->query($sql, array($this->username, $list_id, $name, $color, $showalarms, $dav_url, $dav_sso, $dav_user, $dav_pass_enc, $dav_readonly));
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return false;}
		return true;
	}
	private function incr_list_id(){
		$sql = "SELECT MAX(list_id) AS max FROM ".$this->prefix."tasklist_caldavsso_lists WHERE username = ?;";
		$sql_result = $this->dbh->query($sql, array($this->username));
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return null;}
		$sql_max = $this->dbh->fetch_assoc($sql_result);
		return $sql_max['max'] + 1;
	}
	public function del_list($list_id){
		$sql = "DELETE FROM ".$this->prefix."tasklist_caldavsso_lists WHERE username = ? AND list_id = ?;";
		$sql_result = $this->dbh->query($sql, array($this->username, $list_id));
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return false;}
		return true;
	}
	public function del_user($username){
		$sql = "DELETE FROM ".$this->prefix."tasklist_caldavsso_lists WHERE username = ?;";
		$sql_result = $this->dbh->query($sql, array($username));
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return false;}
		return true;
	}

	private function handle_error($error){
		if(strpos($error, "Table") !== false
			&& strpos($error, "doesn't exist") !== false
		){
			if(strpos($error, "tasklist_caldavsso_lists") !== false){
				rcube::raise_error(array('code' => 600, 'type' => 'db', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Sync table does not exist, I will create it now"), true, false);
				$this->create_table_lists();
			}else{
				rcube::raise_error(array('code' => 600, 'type' => 'db', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Unkown table does not exists: $error"), true, false);
			}
		}else{
			rcube::raise_error(array('code' => 600, 'type' => 'db', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Error while executing db query: $error"), true, false);
		}
	}

	private function create_table_lists(){
		$create_db_lists = "CREATE TABLE IF NOT EXISTS ".
				$this->prefix."tasklist_caldavsso_lists(".
				"username VARCHAR(255),list_id INT".
				",name VARCHAR(255),showalarms INT,color VARCHAR(16)".
				",dav_url VARCHAR(255),dav_sso INT,dav_user VARCHAR(255),dav_pass VARCHAR(255),dav_readonly INT".
				",UNIQUE KEY unique_index(username,list_id));";
		$sql_result = $this->dbh->query($create_db_lists);
		if($db_error = $this->dbh->is_error($sql_result)){$this->handle_error($db_error);return false;}
	}

}
