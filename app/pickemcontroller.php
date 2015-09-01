<?php

class PickemController extends DashboardController {
	
	protected $pickem_id;
	
	function __construct() {
		parent::__construct();
		$f3 = \Base::instance();
		$this->pickem_id = $f3->get('pickem.default_pickem_id');	
	}
	
	function beforeroute($f3) {
		parent::beforeroute($f3);
		
		$f3->team_chooser = new \TeamChooser();
	}
	
	function home($f3, $args) {		
		$pickem_id = $this->pickem_id;
		
		$all_picks = new \DB\SQL\Mapper($this->db, 'pick');
		
		$current_week = $f3->get('pickem.current_week');
		if (isset($args['week'])) {
			$current_week = $args['week'];
		}
		$pickem_week = new \DB\SQL\Mapper($this->db, 'week');
		$pickem_week->load(array('week=?', $current_week));
		
		$user = $f3->get('user');
		
		$active_players = $this->db->exec(
			"SELECT * FROM pickem_player_data WHERE week = :week AND uid <> :current_user ORDER BY playername", 
			array(':week' => $current_week, ':current_user' => $user->uid));
		$f3->set('active_players', $active_players);

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

		$q = "
			SELECT g.*, p.pick_team_name FROM game g
			LEFT OUTER JOIN pick p ON p.eid = g.eid AND playername = :playername
			WHERE 
				g.week = :week 
				AND hide_from_pickem = 0 
			ORDER BY event_date
		";
		$pickem_slate = $this->db->exec($q, array(':week' => $current_week, ':playername' => $user->name));
		
		$q2 = "SELECT MIN(event_date) as first_sat_kickoff 
					 FROM game WHERE week = :week and DAYNAME(FROM_UNIXTIME(event_date)) = 'Saturday'";
		$res = $this->db->exec($q2, array(':week' => $current_week));
		$earliest_sat_game = $res[0]['first_sat_kickoff'];

		for ($i = 0; $i < count($pickem_slate); $i++) {
				
			$allofem = $all_picks->find( array('eid=?',$pickem_slate[$i]['eid']) );
			$pickem_slate[$i]['picks'] = array('total' => 0);
			foreach ($allofem as $p) {
				$pickem_slate[$i]['picks']['total']++;
				if (!isset($pickem_slate[$i]['picks'][$p->pick_team_name])) {
					$pickem_slate[$i]['picks'][$p->pick_team_name] = array('total' => 0);
				}
				$pickem_slate[$i]['picks'][$p->pick_team_name]['total']++;
			}
			if ($pickem_slate[$i]['picks'][$picks[$pickem_slate[$i]['eid']]->pick_team_name]['total'] > 1) {
				
				$pickem_slate[$i]['pick_count_msg'] = sprintf("You and <span class='pick_count'>%s</span> other(s) made this pick.",
					$pickem_slate[$i]['picks'][$picks[$pickem_slate[$i]['eid']]->pick_team_name]['total'] - 1);
					
			} elseif ($pickem_slate[$i]['picks'][$picks[$pickem_slate[$i]['eid']]->pick_team_name]['total'] == 0) {
				$pickem_slate[$i]['pick_count_msg'] = 'Noone has selected this team yet.';
				
			} else {
				$pickem_slate[$i]['pick_count_msg'] = 'Only you have made this pick.';
				
			}
			
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

			$pickem_slate[$i]['is_open'] = \PickemSlateHelper::game_is_open( (object) $pickem_slate[$i], $earliest_sat_game );
			$pickem_slate[$i]['cutoff_time'] = \PickemSlateHelper::cutoff_time( (object) $pickem_slate[$i], $earliest_sat_game );
		}

		$f3->set('pickem_week', $pickem_week);
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

		$POST = $f3->get('POST');

		// We need to know the earliest saturday kickoff time		
		$q2 = "SELECT MIN(event_date) as first_sat_kickoff 
					 FROM game WHERE week = :week and DAYNAME(FROM_UNIXTIME(event_date)) = 'Saturday'";
		$res = $this->db->exec($q2, array(':week' => $POST['week']));
		$earliest_sat_kickoff = $res[0]['first_sat_kickoff'];
				
		#header("Content-type: application/json", TRUE);
		#$this->use_json = TRUE;
		$pick = new \DB\SQL\Mapper($this->db, 'pick');
		$game = new \DB\SQL\Mapper($this->db, 'game');
		
		$pickem_id = $this->pickem_id;
		$user = $f3->get('user');
		$no_error = TRUE;
		
		$invalid_picks = 0;	// track number of picks made after cutoff
		foreach ($POST['pick_team_name'] as $eid => $pick_team_name) {
			$pick->load(array('uid=? and eid=? and pickem_id=?', $user->uid, $eid, $pickem_id));
			$game->load(array('eid=?', $eid));
			
			// ONLY SAVE if posted within a game's cutoff time
			if (\PickemSlateHelper::game_is_open( (object) $game->cast(), $earliest_sat_kickoff )) {
				if (!$pick->dry()) {
					$pick->pick_team_name = $pick_team_name;
					$pick->week = ($pick->week == 0) ? $POST['week'] : $pick->week;
				} else {
					$pick->reset();
					$pick->eid = $eid;
					$pick->pick_team_name = $pick_team_name;
					$pick->pickem_id = $pickem_id;
					$pick->uid = $user->uid;
					$pick->playername = $user->name;
					$pick->week = $POST['week'];
				}
				$res = $pick->save();
			} else {
				$invalid_picks++;
				$f3->push('SESSION.warning_msg', 
					sprintf(
						"Could not save pick for <strong>%s %s %s</strong> because you submitted after the deadline.",
						$game->visiting_team, $game->vs_at_symbol, $game->host_team
						)
					);
			}
		}
		
		if ($invalid_picks < $POST['slate_count']) {
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
			
			$f3->push("SESSION.success_msg", 'Your changes have been saved.  Good job!');
		}
		
		$f3->clear('CACHE');
		$f3->reroute('@current_slate');
	}

	function compare_picks($f3, $args) {
		$p1 = 3;
		$p2 = 6;
		
		$p1 = $args['p1'];
		$p2 = $args['p2'];

		$player_1 = new \DB\SQL\Mapper($this->db, 'users');
		$player_2 = new \DB\SQL\Mapper($this->db, 'users');
		$player_1->load(array('uid=?', $p1));		
		$player_2->load(array('uid=?', $p2));		
		
		$current_week = $f3->get('pickem.current_week');
		if (isset($args['week'])) {
			$current_week = $args['week'];
		}
		
		$q = "
			SELECT g.* FROM game g
			WHERE 
				g.week = :week 
				AND hide_from_pickem = 0 
			ORDER BY event_date
		";
		
		$pickem_slate = $this->db->exec($q, array(':week' => $current_week));
		
		$pick_1 = new \DB\SQL\Mapper($this->db, 'pick');
		$pick_2 = new \DB\SQL\Mapper($this->db, 'pick');
		
		for ($i = count($pickem_slate)-1; $i >= 0; $i--) {
			$pick_1->load(array('uid=? and eid=?', $p1, $pickem_slate[$i]['eid']));
			$pick_2->load(array('uid=? and eid=?', $p2, $pickem_slate[$i]['eid']));
			
			$pickem_slate[$i]['pick_1'] = $pick_1->pick_team_name;
			$pickem_slate[$i]['pick_2'] = $pick_2->pick_team_name;			
		}
		
		$f3->set('player_1', $player_1);
		$f3->set('player_2', $player_2);
		$f3->set('pickem_slate', $pickem_slate);
		$f3->set('inc', 'compare_picks.htm');
	}
}
