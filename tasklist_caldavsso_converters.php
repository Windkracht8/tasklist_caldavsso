<?php

use Sabre\VObject;

class tasklist_caldavsso_converters{
	public static function driver2vtodo($driver_task){
		$vcal = new VObject\Component\VCalendar;
		$vtodo = $vcal->createComponent('VTODO');

		if(isset($driver_task['title']) && $driver_task['title'] != ""){
			$vtodo->SUMMARY = $driver_task['title'];
		}
		if(isset($driver_task['uid']) && $driver_task['uid'] != ""){
			$vtodo->UID = $driver_task['uid'];
		}
		if(isset($driver_task['description']) && $driver_task['description'] != ""){
			$vtodo->DESCRIPTION = $driver_task['description'];
		}
		if(isset($driver_task['date']) && $driver_task['date']){
			$vtodo->DUE = str_replace('-', '', $driver_task['date']);
			$vtodo->DUE['VALUE'] = 'DATE';
		}
		if(isset($driver_task['status']) && $driver_task['status'] != ""){
			$vtodo->STATUS = $driver_task['status'];
		}
		if(isset($driver_task['priority']) && $driver_task['priority'] != ""){
			$vtodo->PRIORITY = $driver_task['priority'];
		}
		if(isset($driver_task['valarms']) && is_array($driver_task['valarms'])){
			foreach($driver_task['valarms'] as $alarm){
				$valarm = $vcal->createComponent('VALARM');
				$valarm->add('ACTION', "DISPLAY");
				$valarm->add('TRIGGER', $alarm['trigger'], ['RELATED' => strtoupper($alarm['related'])]);
				$vtodo->add($valarm);
			}
		}
		return $vtodo;
	}

	public static function vtask2driver($vtodo, $list_id, $href){
		$driver_task = array();
		
		$driver_task["list"] = $list_id;
		$driver_task["id"] = strrpos($href, '/') ? substr($href, strrpos($href, '/')+1) : $href; // Strip everything before the last /
		
		foreach($vtodo->children as $value){
			switch($value->name){
				case "SUMMARY":
					$driver_task['title'] = (string)$value;
					break;
				case "UID":
					$driver_task['uid'] = (string)$value;
					break;
				case "DESCRIPTION":
					$driver_task['description'] = (string)$value;
					break;
				case "DUE":
					$driver_task['date'] = (string)$value;
					break;
				case "STATUS":
					$driver_task['status'] = (string)$value;
					break;
				case "PRIORITY":
					$driver_task['priority'] = (string)$value;
					break;
				case "VALARM":
					foreach($value->children as $valarm_child){
						if($valarm_child->name == "TRIGGER"){
							$valarm = array();
							$valarm['action'] = "DISPLAY";
							if(isset($valarm_child->parameters['RELATED'])){
								$valarm['related'] = strtolower((string)$valarm_child->parameters['RELATED']);
							}else{
								$valarm['related'] = "end";
							}
							$valarm['trigger'] = (string)$valarm_child;
							$driver_task["valarms"][] = $valarm;
						}
					}
					break;
				
				case "PERCENT-COMPLETE":
				case "DTSTART":
				case "DTSTAMP":
				case "LAST-MODIFIED":
				case "CREATED":
					break;
				default:
					rcube::raise_error(array('code' => 500, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "unknown property: ".(string)$value->name.":".(string)$value), true, false);
			}
		}
		
		return $driver_task;
	}
}
