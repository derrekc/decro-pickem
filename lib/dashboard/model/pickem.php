<?php

namespace Dashboard\Model;

class Pickem {
	
	public static function player_results($player, $pickem_id, $limit=3) {
		$f3 = \Base::instance();	
		if (!$f3->exists('db')) {
			return;
		}
		$lookup = sprintf("pickem.%s.current_week", $pickem_id);
		$current_week = $f3->get($lookup);
		
		$pickem_player_data = new \DB\SQL\Mapper($f3->get('db'), $f3->get('pickem.table.pickem_player_data'));
		$player_results = $pickem_player_data->find(
			array('uid=? AND week <= ? and pickem_id = ?', $player->uid, $current_week, $pickem_id),
			array('order' => 'week DESC', 'limit' => $limit)
		);
		
		return $player_results;
	}
	
	public static function current_standing($current_week, $player, $pickem_id) {
		$f3 = \Base::instance();	
		if (!$f3->exists('db')) {
			return;
		}
		$db = $f3->get('db');
		
		// get the last timestamp'ed standings set from the previous week
		// if available
		$last_stamp = $db->exec('SELECT MAX(stamp) AS last_stamp FROM ' . $f3->get('pickem.table.standings') . ' WHERE pickem_id = ? AND week = ?',
			array('1' => $pickem_id, '2' => $current_week));
		$last_stamp = $last_stamp[0]['last_stamp'];
		error_log("last stamp = " . $last_stamp);
		
		$standings = new \DB\SQL\Mapper($db, $f3->get('pickem.table.standings'));
		$player_standing = $standings->find(array('stamp=? AND uid=?', $last_stamp, $player->uid), array('limit' => 1));
		return !empty($player_standing) ? $player_standing[0] : FALSE;
	}
	
	public static function player_pickem_slate($current_week, $player, $pickem_id, $picks=FALSE) {
		$f3 = \Base::instance();
		if (!$f3->exists('db')) {
			return;
		}
		$all_picks = new \DB\SQL\Mapper($f3->get('db'), $f3->get('pickem.table.pick'));

		if ($picks == FALSE) {
			$pick = new \DB\SQL\Mapper($f3->get('db'), $f3->get('pickem.table.pick'));
			$pset = $pick->find(array('week=? and uid=?', $current_week, $player->uid), array('order'=>'eid'));
			$picks = array();
			foreach ($pset as $eid => $p) {
				$picks[$p->eid] = $p;
			}
		}

		$q2 = "SELECT MIN(event_date) as first_sat_kickoff 
					 FROM game 
					 WHERE week = :week AND 
					 			 DAYNAME(FROM_UNIXTIME(event_date)) = 'Saturday' AND 
					 			 hide_from_pickem = 0";
		$res = $f3->get('db')->exec($q2, array(':week' => $current_week));
		$earliest_sat_game = $res[0]['first_sat_kickoff'];

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
				CASE WHEN completed = 'Y' AND winning_team_name = host_team_name THEN 'winner'
				ELSE ''
				END as host_team_completed_css_class,
				CASE WHEN completed = 'Y' AND winning_team_name = visiting_team_name THEN 'winner'
				ELSE ''
				END as visiting_team_completed_css_class,
				p.pick_team_name, 
				p.correct 
			FROM game g
			INNER JOIN pickem_slate ps ON ps.event_id = g.eid AND pickem_id = :pickem_id
			LEFT JOIN team visiting_team on visiting_team.name = visiting_team_name 
			LEFT JOIN team host_team on host_team.name = host_team_name 
			LEFT OUTER JOIN pick p ON p.eid = g.eid AND playername = :playername
			WHERE 
				g.week = :week 

			ORDER BY event_date
		";
		$pickem_slate = $f3->get('db')->exec($q, array(':week' => $current_week, ':playername' => $player->name, ':pickem_id' => $pickem_id));
				
		for ($i = 0; $i < count($pickem_slate); $i++) {
			##### CHAMPIONSHIP GAMES ####
			$pickem_slate[$i]['championship'] = FALSE;
			if (preg_match('/Championship Game/', $pickem_slate[$i]['title']) == 1) {
				$pickem_slate[$i]['championship'] = TRUE;
			}
				
			##### BOWL GAMES #####
			$pickem_slate[$i]['bowlgame'] = FALSE;
			if ($pickem_slate[$i]['event_data'] == 'Bowl') {
				$pickem_slate[$i]['bowlgame'] = TRUE;
			}
			############ TV FORMATTING ##############################
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

		return $pickem_slate;
	}
	
	public static function pickem_slate($current_week) {
		$f3 = \Base::instance();
		if (!$f3->exists('db')) {
			return;
		}

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
				END as final_score
			FROM game g
			LEFT JOIN team visiting_team on visiting_team.name = visiting_team_name 
			LEFT JOIN team host_team on host_team.name = host_team_name 
			WHERE 
				g.week = :week 
				AND hide_from_pickem = 0 
			ORDER BY event_date
		";
		$pickem_slate = $f3->get('db')->exec($q, array(':week' => $current_week));
				
		$all_picks = new \DB\SQL\Mapper($f3->get('db'), $f3->get('pickem.table.pick'));
		for ($i = 0; $i < count($pickem_slate); $i++) {
				
			$allofem = $all_picks->find( array('eid=?',$pickem_slate[$i]['eid']) );
			$pickem_slate[$i]['picks'] = array('total' => 0, 'visiting_team' => 0, 'visiting_team_pct' => '-', 'host_team' => 0, 'host_team_pct' => '-');
			
			foreach ($allofem as $p) {
				$pickem_slate[$i]['picks']['total']++;
				if (!isset($pickem_slate[$i]['picks'][$p->pick_team_name])) {
					$pickem_slate[$i]['picks'][$p->pick_team_name] = array('total' => 0);
				}
				$pickem_slate[$i]['picks'][$p->pick_team_name]['total']++;
								
				if ($p->pick_team_name == $pickem_slate[$i]['host_team_name']) {
					$pickem_slate[$i]['picks']['host_team']++;
					$pickem_slate[$i]['picks']['host_team_pct'] = number_format(
						($pickem_slate[$i]['picks']['host_team'] / sizeof($allofem)) * 100.0
					);
										
				} else {
					$pickem_slate[$i]['picks']['visiting_team']++;
					$pickem_slate[$i]['picks']['visiting_team_pct'] = number_format(
						($pickem_slate[$i]['picks']['visiting_team'] / sizeof($allofem)) * 100.0
					);
				}								
			}
		}			

		return $pickem_slate;
	}
	
	public static function standings($pickem_id, $week) {
		$f3 = \Base::instance();
		
		$db_standings = new \DB\SQL\Mapper($f3->get('db'), $f3->get('pickem.table.standings'));
		
		// Determine the most recent standings "set" based on latest time stamp
		$last_stamp = $f3->get('db')->exec(
			'SELECT MAX(stamp) AS last_stamp FROM ' . $f3->get('pickem.table.standings') . ' WHERE WEEK = ? AND pickem_id = ?',
			array('1' => $week, '2' => $pickem_id)
		);
		$last_stamp = $last_stamp[0]['last_stamp'];
		$f3->set('standings_last_update', $last_stamp);
		
		$query = sprintf(
			"SELECT s.*, u.favorite_team_name, 
			 CASE WHEN pd.correct IS NULL THEN '&ndash;' ELSE pd.correct END AS correct_thisweek, 
			 CASE WHEN pd.incorrect IS NULL THEN '&ndash;' ELSE pd.incorrect END AS incorrect_thisweek,
			 CASE WHEN w.weeks_won IS NULL 
		   THEN '&ndash;' ELSE w.weeks_won
		   END as player_win_count
		   FROM %s s 
		   LEFT JOIN users u on s.uid = u.uid 
		   LEFT OUTER JOIN pickem_winner w on w.uid = s.uid 
		   LEFT OUTER JOIN " . $f3->get('pickem.table.pickem_player_data') . " pd ON pd.uid = s.uid AND pd.week = :pd_week 
		   WHERE s.week = :week AND s.pickem_id = :pickem_id AND stamp = :stamp
		   ORDER BY correct DESC, s.playername", $f3->get('pickem.table.standings'));		
	
		$standings = $f3->get('db')->exec($query, array(':week' => $week, ':pickem_id' => $pickem_id, ':stamp' => $last_stamp, ':pd_week' => $week));		
			 
		for($i = sizeof($standings) - 1; $i >= 0; $i--) {
			$standings[$i]['note'] = FALSE;
			if ($f3->exists('pickem.player_note.' . $standings[$i]['playername'])) {
				$standings[$i]['note'] = $f3->get('pickem.player_note.' . $standings[$i]['playername']);
			}
			$standings[$i]['show_delta'] = $standings[$i]['rank_delta'] != 0;
			$standings[$i]['delta_glyphicon'] = 'glyphicon-minus';
			$standings[$i]['delta_class'] = 'text-muted';
			if ($standings[$i]['rank_delta'] != 0) {
				$standings[$i]['delta_glyphicon'] = $standings[$i]['rank_delta'] > 0 ? 'glyphicon-arrow-up' : 'glyphicon-arrow-down';
				$standings[$i]['delta_class'] = $standings[$i]['rank_delta'] > 0 ? 'text-success' : 'text-danger';
			}
		}
		
		return $standings;
	}
}
