<?php

class PickemController extends DashboardController {
	
	function home($f3) {		
		$pickem_id = $f3->get('pickem.default_pickem_id');
		$current_week = $f3->get('pickem.current_week');
		$user = $f3->get('user');

		# does the user have picks for this week?
		$pick = new \DB\SQL\Mapper($this->db, 'pick');
		$pset = $pick->find(array('week=? and uid=?', $current_week, $user->uid), array('order'=>'eid'));
		$picks = array();
		foreach ($pset as $eid => $p) {
			$picks[$p->eid] = $p;
		}
		
		$pickem_player_data = new \DB\SQL\Mapper($this->db, 'pickem_player_data');
		$pickem_player_data->load(array('uid = ? and week = ? and pickem_id = ?', $user->uid, $current_week, $pickem_id));
		$f3->set('has_picks', !$pickem_player_data->dry());
		$f3->set('pickem_player_data', $pickem_player_data);

		$q = "SELECT * FROM game WHERE week = :week ORDER BY event_date";
		$pickem_slate = $this->db->exec($q, array(':week' => $f3->get('pickem.current_week')));

		for ($i = 0; $i < count($pickem_slate); $i++) {
			$a = array();
			$a[] = array(
				'label' => $pickem_slate[$i]['host_team'],
				'value' => $pickem_slate[$i]['host_team_name'],
				'active' => TRUE || ($picks[$pickem_slate[$i]['eid']]->pick_team_name == $pickem_slate[$i]['host_team_name']),
			);
			$a[] = array(
				'label' => $pickem_slate[$i]['visiting_team'],
				'value' => $pickem_slate[$i]['visiting_team_name'],
				'active' => FALSE || ($picks[$pickem_slate[$i]['eid']]->pick_team_name == $pickem_slate[$i]['visiting_team_name']),
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
		if ($csrf != $f3->get('POST.token')) {
			$f3->reroute('@welcome');
		}

		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;

		$POST = $f3->get('POST');
		$pick = new \DB\SQL\Mapper($this->db, 'pick');
		$pickem_id = $f3->get('pickem.default_pickem_id');
		$user = $f3->get('user');
		$no_error = TRUE;
		
		foreach ($POST['pick_team_name'] as $eid => $pick_team_name) {
			$pick->load(array('uid=? and eid=? and pickem_id=?', $user->uid, $eid, $pickem_id));
			if (!$pick->dry()) {
				$pick->pick_team_name = $pick_team_name;
			} else {
				$pick->reset();
				$pick->eid = $eid;
				$pick->pick_team_name = $pick_team_name;
				$pick->pickem_id = $pickem_id;
				$pick->uid = $user->uid;
				$pick->playername = $user->name;
			}
			$res = $pick->save();
		}
		
		// update timestamps for completing picks
		$now = time();
		$pickem_player_data = new \DB\SQL\Mapper($this->db, 'pickem_player_data');
		$pickem_player_data->load(array('uid=? and pickem_id=?', $user->uid, $pickem_id));
		if ($pickem_player_data->dry()) {
			$pickem_player_data->week = $f3->get('pickem.current_week');
			$pickem_player_data->uid = $user->uid;
			$pickem_player_data->playername = $user->name;
			$pickem_player_data->completed_stamp = $now;
			$pickem_player_data->updated_stamp = 0;
			$pickem_player_data->pickem_id = $pickem_id;
		} else {
			$pickem_player_data->updated_stamp = $now;
		}
		$res = $pickem_player_data->save();
		
		$f3->set("SESSION.success_message", 'Your changes have been saved.  Good job!');

		$f3->clear('CACHE');
		$f3->reroute('@current_slate');
	}
}
