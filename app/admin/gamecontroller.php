<?php
namespace Admin;

class GameController extends \DashboardController {
	
	function games_home($f3) {
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$limit = 25;
		$page = \Pagination::findCurrentPage();
		$filter = array('season = ?', 2015);
		$option = array('order' => 'event_date ASC');
		
		$subset = $game->paginate($page-1, $limit, $filter, $option);
		
		$f3->set('gameList', $subset);
		$f3->set('inc', 'admin/gamelist.htm');
		
		$modals = array(
			'modal-gameedit.htm',
		);
		$f3->set('modals', $modals);
	}
	
	function game_edit($f3, $args) {
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $args['eid']));

		$f3->set('game', $game);
		$f3->set('inc', 'admin/gameedit.htm');
	}
	
	function game_editscore($f3, $args) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
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
	
	function game_editdate($f3, $args) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $args['eid']));

		echo json_encode(array(
			'event_date' => $game->event_date,
			'event_date_string' => $game->event_date_string,
			'game_date_moment' => $game->game_date_moment,
			'eid' => $game->eid,
		));
	}
		
	function game_postdata($f3) {
		#header("Content-type: application/json", TRUE);
		#$this->use_json = TRUE;

		#echo json_encode($f3->get('POST'));
		$f3->reroute(html_entity_decode($f3->get('POST.redirect_to')));		
	}
	
	//! Instantiate class
	function __construct() {
		parent::__construct();
		$this->default_template = 'dashboard.htm';
	}
}
