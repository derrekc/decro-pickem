<?php

class PickemController extends DashboardController {
	
	function home($f3) {		
		# does the user have picks for this week?
		$pick = new \DB\SQL\Mapper($this->db, 'pick');
		$pick->load(array('uid = ?', $f3->get('user')->uid));
		if ($pick->dry()) {
			$f3->set('has_picks', FALSE);
		}
		
		$q = "SELECT * FROM game WHERE week = :week ORDER BY event_date";
		$pickem_slate = $this->db->exec($q, array(':week' => $f3->get('pickem.current_week')));

		for ($i = 0; $i < count($pickem_slate); $i++) {
			$a = array();
			$a[] = array(
				'label' => $pickem_slate[$i]['host_team'],
				'value' => $pickem_slate[$i]['host_team_name'],
				'active' => TRUE,
			);
			$a[] = array(
				'label' => $pickem_slate[$i]['visiting_team'],
				'value' => $pickem_slate[$i]['visiting_team_name'],
				'active' => FALSE,
			);
			$pickem_slate[$i]['pick_options'] = $a;
		}

		$f3->set('pickem_slate', $pickem_slate);
		$f3->set('inc', 'pickem_slate.htm');
		
		$f3->set('SESSION.csrf', $this->s->csrf());
	}
	
	function post($f3, $args) {
		if (!$f3->exists('SESSION.csrf', $csrf)) {
			$f3->reroute('@welcome');
			return;
		}
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		echo json_encode($f3->get('POST'));
	}
}
