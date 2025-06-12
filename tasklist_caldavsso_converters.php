<?php

use Sabre\VObject;

class tasklist_caldavsso_converters{
	public static function driver2vtodo($driver_task, $tzid){
		$vcal = new VObject\Component\VCalendar;
		$vtodo = $vcal->createComponent('VTODO');

		if(strlen($driver_task['title'] ?? '')>0){$vtodo->SUMMARY = $driver_task['title'];}
		if(strlen($driver_task['id'] ?? '')>0){
			$vtodo->UID = $driver_task['id'];
		}else if(strlen($driver_task['uid'] ?? '')>0){
			$vtodo->UID = $driver_task['uid'];
		}
		if(strlen($driver_task['description'] ?? '')>0){$vtodo->DESCRIPTION = $driver_task['description'];}
		
		if(isset($driver_task['startdate']) && $driver_task['startdate']){
			if(isset($driver_task['starttime']) && $driver_task['starttime']){
				$vtodo->DTSTART = str_replace('-', '', $driver_task['startdate'])."T".str_replace(':', '', $driver_task['starttime'])."00";
				$vtodo->DTSTART['TZID'] = $tzid;
			}else{
				$vtodo->DTSTART = str_replace('-', '', $driver_task['startdate']);
				$vtodo->DTSTART['VALUE'] = 'DATE';
			}
		}
		if(isset($driver_task['date']) && $driver_task['date']){
			if(isset($driver_task['time']) && $driver_task['time']){
				$vtodo->DUE = str_replace('-', '', $driver_task['date'])."T".str_replace(':', '', $driver_task['time'])."00";
				$vtodo->DUE['TZID'] = $tzid;
			}else{
				$vtodo->DUE = str_replace('-', '', $driver_task['date']);
				$vtodo->DUE['VALUE'] = 'DATE';
			}
		}
		if(strlen($driver_task['status'] ?? '')>0){$vtodo->STATUS = $driver_task['status'];}
		if(strlen($driver_task['priority'] ?? '')>0){$vtodo->PRIORITY = $driver_task['priority'];}
		if(strlen($driver_task['parent_id'] ?? '')>0){
			$vtodo->{'RELATED-TO'} = $driver_task['parent_id'];
			$vtodo->{'RELATED-TO'}['RELTYPE'] = 'PARENT';
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

	public static function addTimezone(&$vcal, $tzid){
		$vtodo = $vcal->VTODO;
		if(!isset($vtodo->DTSTART['TZID']) && !isset($vtodo->DUE['TZID'])){return;}

		if(!in_array($tzid, timezone_identifiers_list())){return;}
		if(isset($vcal->VTIMEZONE->TZID)){
			if($vcal->VTIMEZONE->TZID == $tzid){
				return;
			}else{
				unset($vcal->VTIMEZONE);
			}
		}

		if(isset($vtodo->DTSTART['TZID'])){
			$year = substr((string)$vtodo->DTSTART, 0, 4);
		}else{
			$year = substr((string)$vtodo->DUE, 0, 4);
		}

		$vtimezone = $vcal->createComponent('VTIMEZONE');
		$vtimezone->TZID = $tzid;
		$timezone = new DateTimeZone($tzid);
		$transitions = $timezone->getTransitions(date("U", strtotime($year."0101T000000Z")), date("U", strtotime($year."1231T235959Z")));

		$offset_from = self::phpOffsetToIcalOffset($transitions[0]['offset']);
		for ($i=0; $i<count($transitions); $i++) {
			$offset_to = self::phpOffsetToIcalOffset($transitions[$i]['offset']);
			if ($i == 0) {
					$offset_from = $offset_to;
				if (count($transitions) > 1) {
					continue;
				}
			}
			$vtransition = $vcal->createComponent($transitions[$i]['isdst'] == 1 ? "DAYLIGHT" : "STANDARD");

			$vtransition->TZOFFSETFROM = $offset_from;
			$vtransition->TZOFFSETTO = $offset_to;
			$offset_from = $offset_to;

			$vtransition->TZNAME = $transitions[$i]['abbr'];
			$vtransition->DTSTART = date("Ymd\THis", $transitions[$i]['ts']);
			$vtimezone->add($vtransition);
		}
		$vcal->add($vtimezone);
	}
	private static function phpOffsetToIcalOffset($phpoffset) {
		$prefix = $phpoffset < 0 ? "-" : "+";
		$offset = abs($phpoffset);
		$hours = floor($offset / 3600);
		return sprintf("$prefix%'.02d%'.02d", $hours, ($offset - ($hours * 3600)) / 60);
	}

	public static function vtask2driver($vtodo, $list_id, $href){
		$driver_task = array();
		
		$driver_task["list"] = $list_id;
		$driver_task["uid"] = strrpos($href, '/') ? substr($href, strrpos($href, '/')+1) : $href; // Strip everything before the last /
		$driver_task['description'] = "";
		
		foreach($vtodo->children() as $value){
			switch($value->name){
				case "SUMMARY":
					$driver_task['title'] = (string)$value;
					break;
				case "UID":
					$driver_task["id"] = (string)$value;
					break;
				case "DESCRIPTION":
					$driver_task['description'] = (string)$value;
					break;
				case "DTSTART":
					$driver_task['startdate'] = substr((string)$value, 0, 4)
						."-".substr((string)$value, 4, 2)
						."-".substr((string)$value, 6, 2);
					if(strlen((string)$value)>10){
						$driver_task['starttime'] = $vtodo->DTSTART->getDateTime()->format('h:i');
					}
					break;
				case "DUE":
					$driver_task['date'] = substr((string)$value, 0, 4)
						."-".substr((string)$value, 4, 2)
						."-".substr((string)$value, 6, 2);
					if(strlen((string)$value)>10){
						$driver_task['time'] = $vtodo->DUE->getDateTime()->format('h:i');
					}
					break;
				case "STATUS":
					$driver_task['status'] = (string)$value;
					break;
				case "PRIORITY":
					$driver_task['priority'] = (string)$value;
					break;
				case "RELATED-TO":
					if(!isset($value['RELTYPE']) || $value['RELTYPE'] == 'PARENT'){
						$driver_task['parent_id'] = (string)$value;
					}
					break;
				case "VALARM":
					foreach($value->children() as $valarm_child){
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
				case "DTSTAMP":
				case "LAST-MODIFIED":
				case "CREATED":
				case "SEQUENCE":
				case "X-OC-HIDESUBTASKS":
				case "X-APPLE-SORT-ORDER":
					break;
				default:
					rcube::raise_error(array('code' => 500, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "unknown property: ".(string)$value->name.":".(string)$value), true, false);
			}
		}
		return $driver_task;
	}
}
