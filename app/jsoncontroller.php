<?php

class JsonController extends Controller {

	//! HTTP route pre-processor
	function beforeroute($f3) {
		header('Content-type: application/json', TRUE);
	}

	//! HTTP route post-processor
	function afterroute($f3) {
	}

	//! Instantiate class
	function __construct() {
		parent::__construct();
	}
	
	public function prepareUserMenu($f3) {}
	public function do_render($f3) {}
	
	function gamelocation_typeahead($f3, $args) {
		$location = strtolower($args['q']);
		$query = "
			SELECT DISTINCT(location)  
			FROM sportsevent 
			WHERE 
				(
				LOWER(location) like '" . $location . "%' 
				)
			ORDER BY location
		";
		$locations = $this->db->exec($query);
		
		$return = array();
		foreach ($locations as $l) {
			$return[] = array(
				'name' => $l['location'],
			);
		}
		
		echo json_encode($return);
	}
	
	function team_typeahead($f3, $args) {
		$team = strtolower($args['q']);
		$query = "
			SELECT name, displaytitle, nickname, tid 
			FROM team 
			WHERE 
				(
				LOWER(name) like '" . $team . "%' OR 
				LOWER(displaytitle) LIKE '" . $team . "%' OR
				LOWER(title) LIKE '" . $team . "%'
				)
				AND sport = 'ncaaf'
			ORDER BY displaytitle
		";
		$teams = $this->db->exec($query);
		
		$return = array();
		foreach ($teams as $team) {
			$return[] = array(
				'name' => $team['displaytitle'],
				'machinename' => $team['name'],
				'nickname' => $team['nickname'],
				'tid' => $team['tid'],
			);
		}
		
		echo json_encode($return);
	}
	
	function json_savegame($f3, $args) {
		
	}
}
