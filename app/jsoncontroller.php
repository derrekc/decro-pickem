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
	
	public function do_render($f3) {}
	
	function team_typeahead($f3, $args) {
		$query = "
			SELECT name, displaytitle, nickname, tid 
			FROM team 
			WHERE 
				LOWER(name) like '" . $args['q'] . "%' OR LOWER(displaytitle) LIKE '" . $args['q'] . "%'
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
