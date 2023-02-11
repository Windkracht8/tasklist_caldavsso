<?php

require_once (dirname(__FILE__).'/tasklist_caldavsso_converters.php');

use Sabre\VObject;

class tasklist_caldavsso_dav{
	public static function create_task($driver_task){
		$list = tasklist_caldavsso_db::get_instance()->get_list($driver_task['list']);
		if(!isset($list['dav_url'])){rcube::raise_error(array('code' => 404, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "no dav url"), true, true);}
		if(isset($list['dav_readonly']) && $list['dav_readonly'] == 1){return false;}
		
		$driver_task['uid'] = self::generateUID($list, $driver_task['uid']);

		$vcal = new VObject\Component\VCalendar;
		$vtodo = tasklist_caldavsso_converters::driver2vtodo($driver_task);
		$vcal->add($vtodo);
		$vcal->PRODID = tasklist_caldavsso_driver::PRODID;

		$headers = array('Content-type: text/calendar; charset="utf-8"');
		
		$reponse = self::makeRequest($list['dav_url']."/".$driver_task['uid'].".ics", 'PUT', $headers, $vcal->serialize(), $list['dav_user'], $list['dav_pass']);
		if($reponse["code"] != "201"){
			rcube::raise_error(array('code' => $reponse["code"], 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to create task on server: ".$reponse["body"]), true, true);
		}
		return true;
	}
	
	public static function edit_task($driver_task){
		$list = tasklist_caldavsso_db::get_instance()->get_list($driver_task['list']);
		if(!isset($list['dav_url'])){rcube::raise_error(array('code' => 404, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "no dav url"), true, true);}
		if(isset($list['dav_readonly']) && $list['dav_readonly'] == 1){return false;}
		
		$vcal = new VObject\Component\VCalendar;
		$vtodo = tasklist_caldavsso_converters::driver2vtodo($driver_task);
		$vcal->add($vtodo);
		$vcal->PRODID = tasklist_caldavsso_driver::PRODID;

		$headers = array('Content-type: text/calendar; charset="utf-8"');
		
		$reponse = self::makeRequest($list['dav_url']."/".$driver_task['id'], 'PUT', $headers, $vcal->serialize(), $list['dav_user'], $list['dav_pass']);
		if($reponse["code"] != "204"){
			rcube::raise_error(array('code' => $reponse["code"], 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to create task on server: ".$reponse["body"]), true, true);
		}
		return true;
	}	
	public static function delete_task($driver_task){
		$list = tasklist_caldavsso_db::get_instance()->get_list($driver_task['list']);
		if(!isset($list['dav_url'])){rcube::raise_error(array('code' => 404, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "no dav url"), true, true);}
		if(isset($list['dav_readonly']) && $list['dav_readonly'] == 1){return false;}
		
		$reponse = self::makeRequest($list['dav_url']."/".$driver_task['id'], 'DELETE', "", "", $list['dav_user'], $list['dav_pass']);
		if($reponse["code"] != "204"){
			rcube::raise_error(array('code' => $reponse["code"], 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to delete on server: ".$list['dav_url'].$id), true, true);
		}
		return true;
	}

	public static function get_tasks($list_id, $search, $mask){
		//TODO: mask
		$headers = array('Content-type: text/xml; charset="utf-8"', 'Depth: 1');
		$body = '<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
					.'<d:prop><c:calendar-data /></d:prop>'
					.'<c:filter>'
						.'<c:comp-filter name="VCALENDAR">'
							.'<c:comp-filter name="VTODO">';
		if(!is_null($search) && strlen($search) > 0){// TODO: query in DESCRIPTION, needs multiple queries
			$body .=			'<c:prop-filter name="SUMMARY">'
									.'<c:text-match match-type="contains">'.$search.'</c:text-match>'
								.'</c:prop-filter>';
		}
		$body .=			'</c:comp-filter>'
						.'</c:comp-filter>'
					.'</c:filter>'
				.'</c:calendar-query>';

		$list = tasklist_caldavsso_db::get_instance()->get_list($list_id);
		if(!isset($list['dav_url'])){rcube::raise_error(array('code' => 404, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "no dav url for list $list_id"), true, true);}

		$reponse = self::makeRequest($list['dav_url'], 'REPORT', $headers, $body, $list['dav_user'], $list['dav_pass']);
		if($reponse["code"] != "207"){
			rcube::raise_error(array('code' => $reponse["code"], 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to get events from server: ".$reponse["body"]), true, true);
		}

		$xmlDoc = new DOMDocument();
		if(!$xmlDoc->loadXML($reponse["body"])){
			rcube::raise_error(array('code' => 500, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to interpret response from server", true, true));
		}
		$driver_tasks = array();
		$xmlresponses = $xmlDoc->getElementsByTagName('response');
		foreach($xmlresponses as $xmlresponse){
			$hrefs = $xmlresponse->getElementsByTagName('href');
			$href = $hrefs[0]->nodeValue;
			
			$calendar_datas = $xmlresponse->getElementsByTagName('calendar-data');
			$calendar_data = $calendar_datas[0]->nodeValue;

			try{
				$vcal = VObject\Reader::read($calendar_data, VObject\Reader::OPTION_FORGIVING);
			}catch(Exception $e){
				rcube::raise_error(array('code' => 500, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to parse vobject: ".$e->getMessage(), true, true));
			}

			foreach($vcal->children() as $vtodo){
				if($vtodo->name != "VTODO"){continue;}
				$driver_tasks[] = tasklist_caldavsso_converters::vtask2driver($vtodo, $list_id, $href);
			}
		}
		return $driver_tasks;
	}

	public static function get_task_childs($list_id, $parent_id){
		$headers = array('Content-type: text/xml; charset="utf-8"', 'Depth: 1');
		$body = '<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-data /></d:prop><c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VTODO"><c:prop-filter name="RELATED-TO"><c:text-match>%UID%</c:text-match></c:prop-filter></c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';
		$body = str_replace("%UID%", $parent_id, $body);

		$list = tasklist_caldavsso_db::get_instance()->get_list($list_id);
		if(!isset($list['dav_url'])){rcube::raise_error(array('code' => 404, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "no dav url for list $list_id"), true, true);}

		$reponse = self::makeRequest($list['dav_url'], 'REPORT', $headers, $body, $list['dav_user'], $list['dav_pass']);
		if($reponse["code"] != "207"){
			rcube::raise_error(array('code' => $reponse["code"], 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to get events from server: ".$reponse["body"]), true, true);
		}

		$xmlDoc = new DOMDocument();
		if(!$xmlDoc->loadXML($reponse["body"])){
			rcube::raise_error(array('code' => 500, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "failed to interpret response from server", true, true));
		}
		$task_childs = array();
		$xmlresponses = $xmlDoc->getElementsByTagName('response');
		foreach($xmlresponses as $xmlresponse){
			$hrefs = $xmlresponse->getElementsByTagName('href');
			$href = $hrefs[0]->nodeValue;
			$href = substr($href, strrpos($href, "/")+1);
			$task_childs[] = $href;
		}
		return $task_childs;
	}

	public static function generateUID($list, $uid = null){
		$headers = array('Content-type: text/calendar; charset="utf-8"');
		$uid = $uid == null ? uniqid() : $uid;
		$reponse = self::makeRequest($list['dav_url']."/".$uid.".ics", 'GET', $headers, "", $list['dav_user'], $list['dav_pass']);
		if($reponse["code"] == "404"){return $uid;}
		return self::generateUID($list);
	}

	public static function makeRequest($request_url, $request_method, $request_headers, $request_body, $user, $pass){
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $request_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request_method);
		curl_setopt($curl, CURLOPT_USERPWD, $user.':'.$pass);
		curl_setopt($curl, CURLOPT_USERAGENT, "roundcube_caldavsso");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
		if(is_array($request_headers)) curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

		$reponse = array();
		try{
			$reponse["body"] = curl_exec($curl);
			$reponse["code"] = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
			curl_close($curl);
		}catch(Exception $e){
			$reponse["code"] = 0;
			$reponse["body"] = $e->getMessage();
		}
		return $reponse;
	}
}
