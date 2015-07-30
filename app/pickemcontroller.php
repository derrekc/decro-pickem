<?php

class PickemController extends DashboardController {
	
	function pickem_home($f3) {
		$q = "SELECT * FROM game WHERE week = :week ORDER BY event_date";
		$f3->set('pickem_slate', $this->db->exec($q, array(':week' => $f3->get('pickem.current_week'))));
		
		$f3->set('inc', 'pickem_slate.htm');
	}
}
