<?php

class PickemController extends DashboardController {
	
	protected $pickem_id;
	
	function __construct() {
		parent::__construct();
		$f3 = \Base::instance();
		$this->pickem_id = $f3->get('pickem.default_pickem_id');	

		$plugins = $f3->get('pickemcontroller.plugins');
		foreach($plugins as $p) {
			$this->addDashboardPlugin(new $p);
		}
	}
	
	function beforeroute($f3) {
		parent::beforeroute($f3);

		$f3->set('current_week', $f3->get('pickem.current_week'));

	}
	
	function home($f3, $args) {		
		$pickem_id = $this->pickem_id;
		if ($f3->exists('PARAMS.pickem_id')) {
			$pickem_id = $f3->get('PARAMS.pickem_id');
		}
		
		$all_picks = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pick'));
		
		$default_current_week = $f3->get(sprintf("pickem.%s.current_week", $pickem_id));
		
		$current_week = $default_current_week;
		if (isset($args['week'])) {
			$current_week = $args['week'];
		}
		$pickem_week = new \DB\SQL\Mapper($this->db, 'week');
		$pickem_week->load(array('week=? AND pickem_id=?', $current_week, $pickem_id));
		
		$pickem = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem'));
		$pickem->load(array('pid=?', $pickem_id));
		
		$user = $f3->get('user');
		
		$active_players = $this->db->exec(
			"SELECT * FROM pickem_player_data WHERE week = :week AND uid <> :current_user ORDER BY playername", 
			array(':week' => $current_week, ':current_user' => $user->uid));
		$f3->set('active_players', $active_players);

		# does the user have picks for this week?
		$pick = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pick'));
		$pset = $pick->find(array('week=? and uid=?', $current_week, $user->uid), array('order'=>'eid'));

		if (sizeof($pset) == 0 && $current_week < $default_current_week) {
			// return the 'empty response' page and exit
			$f3->set('inc', 'no_picks_available.htm');
			return;
		}

		$picks = array();
		foreach ($pset as $eid => $p) {
			$picks[$p->eid] = $p;
		}
		
		$pickem_player_data = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_player_data'));
		$pickem_player_data->load(array('uid = ? and week = ? and pickem_id = ?', $user->uid, $current_week, $pickem_id));
		$f3->set('has_picks', !$pickem_player_data->dry());
		$f3->set('pickem_player_data', $pickem_player_data);

		$pickem_slate = FALSE;
		$pickem_slate = \Dashboard\Model\Pickem::player_pickem_slate($pickem_week->sport_week, $user, $pickem_id, $picks);
		/*
		if ($user->name == 'ecuacc4ever') {
			$pickem_slate = \Dashboard\Model\Pickem::player_pickem_slate($current_week, $user, $picks);
		} else {
			$q = "
				SELECT 
					g.*, 
					visiting_team.displaytitle AS visiting_team_title,
					host_team.displaytitle AS host_team_title,
					CASE WHEN (event_date = 0 OR event_date IS NULL or DATE_FORMAT(FROM_UNIXTIME(event_date), '%k') = '0') THEN 'TBA'
					ELSE DATE_FORMAT(FROM_UNIXTIME(event_date), '%l:%i %p') 
					END as event_kickoff_time,
					CASE WHEN completed = 'Y' THEN
						CASE 
							WHEN winning_team_name = host_team_name THEN CONCAT_WS('-',host_score,visiting_score)
							ELSE CONCAT_WS('-',visiting_score,host_score)
						END
					ELSE NULL
					END as final_score,
					CASE WHEN completed = 'Y' THEN 
						CASE 
							WHEN winning_team_name = host_team_name THEN host_team.displaytitle
							ELSE visiting_team.displaytitle
						END
					ELSE NULL
					END as winning_team,
					p.pick_team_name, 
					p.correct 
				FROM game g
				LEFT JOIN team visiting_team on visiting_team.name = visiting_team_name 
				LEFT JOIN team host_team on host_team.name = host_team_name 
				LEFT OUTER JOIN pick p ON p.eid = g.eid AND playername = :playername
				WHERE 
					g.week = :week 
					AND hide_from_pickem = 0 
				ORDER BY event_date
			";
			$pickem_slate = $this->db->exec($q, array(':week' => $current_week, ':playername' => $user->name));
	
			$q2 = "SELECT MIN(event_date) as first_sat_kickoff 
						 FROM game 
						 WHERE week = :week AND 
						 			 DAYNAME(FROM_UNIXTIME(event_date)) = 'Saturday' AND 
						 			 hide_from_pickem = 0";
			$res = $this->db->exec($q2, array(':week' => $current_week));
			$earliest_sat_game = $res[0]['first_sat_kickoff'];
	
			
			for ($i = 0; $i < count($pickem_slate); $i++) {
				# TV
			 	# if it's a combo, separated by '/', then break 'em up
			 	# and use the first one
			 	$tv = '';
			 	$pickem_slate[$i]['tv_main'] = '';
			 	$pickem_slate[$i]['tv_sec'] = '';
			 			 	if (!empty($pickem_slate[$i]['tv'])) {
			 		$tmp = explode('/', $pickem_slate[$i]['tv']);
			 		$tv = $tmp[0];
			 		if (sizeof($tmp) > 1) {
			 			$pickem_slate[$i]['tv_sec'] = $pickem_slate[$i]['tv'];
			 		}
			 		$pickem_slate[$i]['tv_main'] = $tv;
			 	}
					
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
				
				$active = FALSE;
				if ($pickem_slate[$i]['pick_team_name'] == '') {
					$active = $active || $user->default_pick == 'hostteam';
				} else {
					$active = $active || $pickem_slate[$i]['pick_team_name'] == $pickem_slate[$i]['host_team_name'];
				}
				
				$a[] = array(
					'label' => $pickem_slate[$i]['host_team'],
					'value' => $pickem_slate[$i]['host_team_name'],
					'active' => $active,
					//'active' => TRUE || ($picks[$pickem_slate[$i]['eid']]->pick_team_name == $pickem_slate[$i]['host_team_name']),
				);
				
				$active = FALSE;
				if ($pickem_slate[$i]['pick_team_name'] == '') {
					$active = $active || $user->default_pick == 'visitingteam';
				} else {
					$active = $active || $pickem_slate[$i]['pick_team_name'] == $pickem_slate[$i]['visiting_team_name'];
				}
				
				$a[] = array(
					'label' => $pickem_slate[$i]['visiting_team'],
					'value' => $pickem_slate[$i]['visiting_team_name'],
					'active' => $active,
					//'active' => FALSE || ($picks[$pickem_slate[$i]['eid']]->pick_team_name == $pickem_slate[$i]['visiting_team_name']),
				);
				$pickem_slate[$i]['pick_options'] = $a;
	
				$pickem_slate[$i]['is_open'] = \PickemSlateHelper::game_is_open( (object) $pickem_slate[$i], $earliest_sat_game );
				$pickem_slate[$i]['cutoff_time'] = \PickemSlateHelper::cutoff_time( (object) $pickem_slate[$i], $earliest_sat_game );
			}
		}
		*/
		// determine route pattern to send to button group
		$route_pattern = $f3->get('PATTERN');
		$btngroup_path = '/';
		if (preg_match('/^\/picks/', $route_pattern) == 1) {
			$btngroup_path = '/picks';
		} else {
			$btngroup_path = sprintf("/%s/picks", $pickem_id);
		}
		$f3->set('btngroup_path', $btngroup_path);
		
		$f3->set('page_title', sprintf("Week %s - %s", $current_week, $pickem->title));
		$f3->set('pickem_week', $pickem_week);
		$f3->set('pickem_slate', $pickem_slate);
		$f3->set('pickem_id', $pickem_id);
		
		$future_week = ($current_week > $default_current_week) && $pickem_week->open == 'N';
		$f3->set('future_week', $future_week);
		
		if ($pickem_week->completed && $pickem_week->open == 'N') {
			$f3->set('inc', 'pickem_slate_completed.htm');
		} else {
			$f3->set('inc', 'pickem_slate.htm');
		}

		if (!$f3->get('has_picks') && !$future_week) {
			$f3->push('SESSION.warning_msg', "<strong><em>Warning</em></strong><br />Make sure to submit your picks!");
		}

		$f3->set('SESSION.csrf', $this->s->csrf());
	}
	
	function post($f3, $args) {
		$now = time();
		
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
					 FROM game 
					 WHERE week = :week 
					 AND DAYNAME(FROM_UNIXTIME(event_date)) = 'Saturday' 
					 AND hide_from_pickem = 0";
		$res = $this->db->exec($q2, array(':week' => $POST['week']));
		$earliest_sat_kickoff = $res[0]['first_sat_kickoff'];
		error_log('POST: earliest_sat_kickoff = [' . $earliest_sat_kickoff . ']');
				
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
			$aresult = \PickemSlateHelper::game_is_open( (object) $game->cast(), $earliest_sat_kickoff );
			if (\PickemSlateHelper::game_is_open( (object) $game->cast(), $earliest_sat_kickoff ) !== FALSE) {
				if (!$pick->dry()) {
					// Only update the timestamp if the pick changed.
					if ($pick->pick_team_name != $pick_team_name) {
						$pick->pick_team_name = $pick_team_name;
						$pick->week = ($pick->week == 0) ? $POST['week'] : $pick->week;
						$pick->updated_stamp = $now;
					}
				} else {
					$pick->reset();
					$pick->eid = $eid;
					$pick->pick_team_name = $pick_team_name;
					$pick->pickem_id = $pickem_id;
					$pick->uid = $user->uid;
					$pick->playername = $user->name;
					$pick->week = $POST['week'];
					$pick->initial_stamp = $now;
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
			$pickem_player_data = new \DB\SQL\Mapper($this->db, 'pickem_player_data');
			$pickem_player_data->load(array('uid=? and pickem_id=? and week=?', $user->uid, $pickem_id, $POST['week']));
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
	
	function team_appearances($f3) {
		
		$appearances = \Dashboard\Model\Games::team_appearances();
		$f3->set('appearances', $appearances);
		$f3->set('page_title', 'Pick\'em Appearances, by Team');
		$f3->set('leadmsg', "Click a school's logo for Pick'em schedule.");
		$f3->set('inc', 'team_appearances.htm');		
		$f3->set('modals', array('modals/gen_modal.htm'));
	}
	
	function team_appearances_team($f3) {
		$teamname = $f3->get('PARAMS.teamname');
		
		$game = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.game'));
		$games = $game->find(
			array('(host_team_name=? OR visiting_team_name=?) AND hide_from_pickem=0', $teamname, $teamname),
			array('order'=>'week'));
		$f3->get('games', $games);
		
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$response = array();
		foreach ($games as $g) {
			$response[] = sprintf("<p>Week %s: %s %s %s</p>",
				$g->week,
				$g->visiting_team_name == $teamname ? '<strong>' . $g->visiting_team . '</strong>' : $g->visiting_team,
				$g->vs_at_symbol,
				$g->host_team_name == $teamname ? '<strong>' . $g->host_team . '</strong>' : $g->host_team
				);
		}
		echo json_encode(array('content' => join('', $response)));
	}
	
	function picks_breakdown($f3) {
		$current_week = $f3->get('pickem.current_week');
		$pickem_slate = \Dashboard\Model\Pickem::pickem_slate($current_week);
		
		$f3->set('pickem_slate', $pickem_slate);
		$f3->set('page_title', sprintf("Pick Breakdown, by Percentage (%%) - Week %s", $current_week));
		$f3->set('inc', 'picks_breakdown.htm');
	}
	
	function picks_breakdown_game($f3) {
		$game = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.game'));
		$game->load(array('eid=?', $f3->get('PARAMS.eid')));
		
		$pick = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pick'));
		$pick->team = 
			'SELECT displaytitle FROM team '.
			'WHERE team.name = pick.pick_team_name';
		
		$db_standings = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.standings'));
		$db_standings->last_stamp = 'MAX(stamp)';
		$db_standings->load(array('pickem_id=?', $f3->get('pickem.default_pickem_id')));
		$last_stamp = $db_standings->last_stamp;
		
		unset($db_standings->last_stamp);
		$db_standings->reset();
		$pickem_players = $db_standings->find(array('stamp=?', $last_stamp), array('order'=>'rank,playername'));
		
		#$pickem_player_data = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_player_data'));
		#$pickem_players = $pickem_player_data->find(array('week=?', $game->week), array('order'=>'playername'));
		
		$players = array();
		foreach ($pickem_players as $player) {
			$pick->load(array('uid=? AND eid=?', $player->uid, $game->eid));
			$players[] = array(
				'playername' => $player->playername, 
				'pick' => $pick->team, 
				'pick_team_name' => $pick->pick_team_name
			);
		}
		$f3->set('page_title', sprintf("Week %s: %s %s %s", $game->week, $game->visiting_team, $game->vs_at_symbol, $game->host_team));
		$f3->set('players', $players);
		$f3->set('inc', 'pick_breakdown_game.htm');
	}
}
