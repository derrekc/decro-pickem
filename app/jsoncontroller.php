<?php

class JsonController extends Controller {

	//! HTTP route pre-processor
	function beforeroute($f3) {
		if (!$f3->exists('SESSION.user_id')) {
			$f3->reroute('/login');
			return;
		}

		$db = $this->db;
		$user = new \DB\SQL\Mapper($db, 'users');
		$user->load(array('name=?', $f3->get('SESSION.user_id')));
		$f3->set('user', $user);
		
		if ($user->isadmin == 'N') {
			$f3->set('SESSION.error_message', 'You do not have permission to visit this section');
			$f3->reroute('/');
		}
		
		header('Content-type: application/json', TRUE);
	}

	//! HTTP route post-processor
	function afterroute($f3) {
	}

	//! Instantiate class
	function __construct() {
		parent::__construct();
	}
	
	function score_edit($f3, $args) {
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $args['eid']));

		echo json_encode(array(
			'host_team' => $game->host_team,
			'host_score' => $game->host_score,
			'visiting_team' => $game->visiting_team,
			'visiting_score' => $game->visiting_score,
			'eid' => $game->eid,
		));
	}
	
	function gamedate_edit($f3, $args) {
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $args['eid']));

		echo json_encode(array(
			'event_date' => $game->event_date,
			'event_date_string' => $game->event_date_string,
			'game_date_moment' => $game->game_date_moment,
			'eid' => $game->eid,
		));
	}
	
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
				'name' => $team['name'],
				'displaytitle' => $team['displaytitle'],
				'nickname' => $team['nickname'],
				'tid' => $team['tid'],
			);
		}
		
		echo json_encode($return);
	}
	
	function json_savegame($f3, $args) {
		
	}
}
