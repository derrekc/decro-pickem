<?php

class StandingsController extends DashboardController {
	
	function overall($f3) {
		
		$res = $this->db->exec('SELECT * FROM standings WHERE week = ?', 1);		
	}
	
	function week($f3) {
		$standings = $this->db->exec(
			'SELECT p.*, u.favorite_team_name, u.picture FROM pickem_player_data p 
			 LEFT JOIN users u ON p.uid = u.uid 
			 WHERE week = ? ORDER BY correct DESC, playername', 1);

		$f3->set('standings', $standings);
		$f3->set('inc', 'week_standings.html');
	}
}
