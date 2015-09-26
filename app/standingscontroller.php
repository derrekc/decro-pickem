<?php

class StandingsController extends DashboardController {
	
	function overall($f3) {
		$standings = \Dashboard\Model\Pickem::standings($f3->get('pickem.default_pickem_id'), $f3->get('pickem.standings_week'));
		$f3->set('standings', $standings);
		$f3->set('inc', 'overall_standings.html');
	}
	
	function week($f3) {
		$pickem_id = FALSE;
		if (!$f3->exists('REQUEST.pickem_id', $pickem_id)) {
			$pickem_id = $f3->get('pickem.default_pickem_id');
		}
		
		$current_week = FALSE;
		if (!$f3->exists('PARAMS.week', $current_week)) {
			$current_week = $f3->get('pickem.current_week');
		}
		
		$pickem_week = new \DB\SQL\Mapper($this->db, 'week');
		$pickem_week->load(array('week=? AND pickem_id=?', $current_week, $pickem_id));

		$standings = $this->db->exec(
			'SELECT p.*, u.favorite_team_name, u.picture FROM ' . $f3->get('pickem.table.pickem_player_data') . ' p 
			 LEFT JOIN users u ON p.uid = u.uid 
			 WHERE week = ? AND pickem_id = ? ORDER BY correct DESC, playername', array('1' => $current_week, '2' => $pickem_id));

		$f3->set('page_title', sprintf("Week %s Results", $current_week));
		$f3->set('pickem_week', $pickem_week);
		$f3->set('standings', $standings);
		$f3->set('inc', 'week_standings.html');
		$f3->set('pickem_id', $pickem_id);
	}
}
