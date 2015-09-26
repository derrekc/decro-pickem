<?php
namespace Admin;

class PickemController extends \AdminController {

	function home($f3, $args) {
		$f3->set('page_title', "Pickem Management, etc");
		$f3->set('inc', 'admin/pickem_stuff.htm');
	}

	function clearPicks($f3, $args) {
		$target_week = '';
		if (!$f3->exists('POST.target_week', $target_week)) {
			$target_week = $f3->get('pickem.current_week');
		}
		
		$target_pickem_id = '';
		if (!$f3->exists('POST.pickem_id', $target_pickem_id)) {
			$target_week = $f3->get('pickem.default_pickem_id');
		}
		
		$this->db->exec('DELETE FROM pick WHERE week = ? and pickem_id = ?', $target_week, $target_pickem_id);
		$this->db->exec('DELETE FROM pickem_player_data WHERE week = ? and pickem_id = ?', $target_week, $target_pickem_id);
		
		$f3->set('SESSION.success_message', sprintf("Picks have been cleared for week %s", $target_week));
		$f3->set('inc', 'admin/pickem_stuff.htm');
	}

	function playerStatus($f3) {
		$q = "
			SELECT *, 
				DATE_FORMAT(FROM_UNIXTIME(completed_stamp), '%b %e - %r') AS completed_datetime,
				DATE_FORMAT(FROM_UNIXTIME(updated_stamp), '%b %e - %r') AS updated_datetime
				FROM pickem_player_data WHERE pickem_id = ? AND week = ? AND completed_stamp > 0
				ORDER BY completed_stamp DESC, playername";
		$players = $this->db->exec($q, array('1' => $f3->get('pickem.default_pickem_id'), '2' => $f3->get('pickem.current_week')));
		
		for ($i = 0; $i < count($players); $i++) {
			$r = $this->db->exec(
				'SELECT COUNT(*) AS pick_count FROM pick WHERE week = ? AND playername = ?', 
				array('1' => $players[$i]['week'], '2' => $players[$i]['playername']));
				
			$players[$i]['pick_count'] = $r[0]['pick_count'];
		}
		#echo print_r($players, true);
		
		$f3->set('current_week', $f3->get('pickem.current_week'));
		$f3->set('players', $players);
		$f3->set('player_count', count($players));
		$f3->set('inc', 'admin/player_status.htm');
	}

	function closePickemWeek($f3) {
		if (!$f3->exists('POST', $POST)) {
			$f3->reroute('@pickem_stuff');
		}
		
		$pickem_id = $POST['pickem_id'];
		$week = $POST['week'];
		$previous_week = ($week - 1 >= 0) ? $week - 1 : 0;
		
		$standings = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.standings'));
				
		$pickem_player = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_player_data'));
		$players = $pickem_player->find(array('week=? and pickem_id=?', $week, $pickem_id));
		
		foreach ($players as $player) {
			# make a new standings record, copying the previous week's entry
			# when applicable
			$standings->load(array('week=? AND pickem_id=? AND uid=?', $previous_week, $pickem_id, $player->uid));
			if ($standings->dry()) {
				// new entry, w/o having to add correct/incorrect
				$standings->reset();
				$standings->correct = $player->correct;
				$standings->incorrect = $player->incorrect;
				$standings->playername = $player->playername;
				$standings->pickem_id = $pickem_id;
				$standings->uid = $player->uid;
				$standings->week = $week;
				$standings->season = $f3->get('pickem.default_season');
				$standings->save();
				//error_log(sprintf('saved [%s] at %s - %s for pickem [%s]', $player->playername, $player->correct, $player->incorrect, pickem_id));
			} else {
				// previous week located
				// change the week and add to the correct and incorrect
				$tmp_standings = $standings->cast();
				unset($tmp_standings['stid']);
				unset($tmp_standings['previous_rank']);
				unset($tmp_standings['rank_delta']);
				$previous_rank = $tmp_standings['rank'];
				unset($tmp_standings['rank']);

				$new_standings = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.standings'));
				$new_standings->copyFrom($tmp_standings);
				$new_standings->pickem_id = $pickem_id;
				$new_standings->uid = $player->uid;
				$new_standings->playername = $player->playername;
				$new_standings->correct += $player->correct;
				$new_standings->incorrect += $player->incorrect;
				$new_standings->week = $week;
				$new_standings->previous_rank = $previous_rank;
				$new_standings->save();
			}
			
			$player->complete = 'Y';
			$player->save();
		}
	
		$rank = 0;
		$current_rank = 0;
		$last_correct = FALSE;
		$standings->reset();
		$entries = $standings->find(array('week=?', $week), array('order' => 'correct DESC'));
	
		foreach($entries as $s) {
			$current_rank++;
			if ($s->correct != $last_correct) {
				$rank = $current_rank;
				$last_correct = $s->correct;
			}
			$s->rank = $rank;
			if ($s->previous_rank != '' && $s->previous_rank != 0) {
				$s->rank_delta = $s->previous_rank - $s->rank;
			}
			$s->save();
		}
		$f3->push('SESSION.success_msg', 'Ranked the standings for week ' . $week);
		
		#$week = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.week'));
		#$week->load(array('week=?', $previous_week));
		#$week->open = 'N';
		#$week->save();
		#$f3->push('SESSION.success_msg', 'Closed week ' . $previous_week);
		
		#$week->load(array('week=?', $week));
		#$week->open='Y';
		#$week->save();
		#$f3->push('SESSION.success_msg', 'Opened week ' . $week . ' for picks');
		
		$f3->push('SESSION.success_msg', 'Finished with standings.');
		$f3->reroute('@pickem_stuff');		
	}
	
	function setWinnersForWeek($f3) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;

		$current_week = $f3->get('pickem.current_week');
		$pickem_id = $f3->get('pickem.default_pickem_id');

		if ($f3->exists('REQUEST.week')) {
			$current_week = $f3->get('REQUEST.week');
		}

		$query = sprintf("SELECT MAX(correct) AS max_correct FROM %s WHERE week = :week AND pickem_id = :pickem_id", $f3->get('pickem.table.pickem_player_data'));
		$max_correct = $this->db->exec($query, array(':week' => $current_week, ':pickem_id' => $pickem_id));
		$max_correct = $max_correct[0]['max_correct'];

		$pickem_player_data = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_player_data'));
		$players = $pickem_player_data->find(array('correct=? AND pickem_id=? AND week=?', $max_correct, $pickem_id, $current_week));

		$pickem_winner = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_winner'));

		$tmp = array();
		foreach ($players as $p) {
			$pickem_winner->load(array('uid=? AND pickem_id=?', $p->uid, $pickem_id));
			if ($pickem_winner->dry()) {
				$pickem_winner->reset();
				$pickem_winner->playername = $p->playername;
				$pickem_winner->uid = $p->uid;
				$pickem_winner->pickem_id = $pickem_id;
				$pickem_winner->weeks_won = 1;
			} else {
				$pickem_winner->weeks_won++;
			}
			$pickem_winner->save();
		}
		echo json_encode($tmp);
	}

	function refreshPickemStandings($f3) {
		$now = time();
		$POST = $f3->get('POST');
		$current_week = $POST['week'];
		$pickem_id = $POST['pickem_id'];
		
		// create MAPPER for pickem_player_data
		$db_pickem_player = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_player_data'));
		
		$db_standings = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.standings'));
		$last_stamp = $this->db->exec('SELECT MAX(stamp) AS last_stamp FROM ' . $f3->get('pickem.table.standings'));
		$last_stamp = $last_stamp[0]['last_stamp'];
		
		// get the last timestamp'ed standings set from the previous week
		// if available
		$first_stamp = $this->db->exec('SELECT MAX(stamp) AS first_stamp FROM ' . $f3->get('pickem.table.standings') . ' WHERE pickem_id = ? AND week = ?',
			array('1' => $pickem_id, '2' => $current_week - 1));
		$first_stamp = $first_stamp[0]['first_stamp'];
		
		$standings = $db_standings->find(array('stamp=?', $first_stamp));
		if (sizeof($standings) > 0) {
			foreach ($standings as $s) {
				$db_standings->reset();
				$db_pickem_player->load(array('uid=? AND week=? AND pickem_id=?', $s->uid, $current_week, $pickem_id));
				
				$db_standings->week = $current_week;
				$db_standings->season = $s->season;
				$db_standings->stamp = $now;
				$db_standings->uid = $s->uid;
				$db_standings->playername = $s->playername;
				$db_standings->pickem_id = $s->pickem_id;
				$db_standings->rank = 0;
				$db_standings->correct = $s->correct + $db_pickem_player->correct;
				$db_standings->incorrect = $s->incorrect + $db_pickem_player->incorrect;
				$db_standings->previous_rank = $s->rank;
				$db_standings->rank_delta = 0;
				$db_standings->save();
			}			
		} else {
			// TODO handle the first week of a PICKEM
		}

		$rank = 0;
		$current_rank = 0;
		$last_correct = FALSE;
		$db_standings->reset();
		$entries = $db_standings->find(array('stamp=?', $now), array('order' => 'correct DESC'));
		foreach($entries as $s) {
			$current_rank++;
			if ($s->correct != $last_correct) {
				$rank = $current_rank;
				$last_correct = $s->correct;
			}
			$s->rank = $rank;
			if ($s->previous_rank != '' && $s->previous_rank != 0) {
				$s->rank_delta = $s->previous_rank - $s->rank;
			}
			$s->save();
		}
		$f3->push('SESSION.success_msg', 'Ranked the standings for week ' . $current_week);
		$f3->reroute('/admin/pickem');		
	}
	
	function initPickemStandings($f3) {
		$POST = $f3->get('POST');

		$now = time();
		$current_week = $POST['week'];
		$pickem_id = $POST['pickem_id'];

		$query = sprintf("DELETE FROM %s WHERE pickem_id = ? AND week = ?", $f3->get('pickem.table.standings'));
		$res = $this->db->exec($query, array('1' => $pickem_id, '2' => $current_week));
		
		// copy the standings for the previous week
		// assuming the standings are present for that previous week
		$db_standings = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.standings'));
		$standings = $db_standings->find(array('week=?', $current_week-1));
		foreach ($standings as $s) {
			$db_standings->reset();
			$db_standings->week = $current_week;
			$db_standings->season = $s->season;
			$db_standings->stamp = $now;
			$db_standings->uid = $s->uid;
			$db_standings->playername = $s->playername;
			$db_standings->pickem_id = $s->pickem_id;
			$db_standings->rank = $s->rank;
			$db_standings->correct = $s->correct;
			$db_standings->incorrect = $s->incorrect;
			$db_standings->previous_rank = $s->previous_rank;
			$db_standings->rank_delta = $s->rank_delta;
			$db_standings->save();
		}
		
		$f3->push('SESSION.success_msg', 'Standings created and initialized for Week ' . $current_week);
		$f3->reroute('/admin/pickem');
		//$res = $this->db->exec('INSERT INTO standings')	
	}
	
	function createVsMatchups($f3, $args){
		$players = $this->db->exec("SELECT * FROM pickem_player_data WHERE week = 1");
		shuffle($players);
		$matchups = array();
				
		$max_games = 16;
		$vsgame_count = array();
		foreach ($players as $p) {
			$vsgame_count[$p['playername']] = 0;
		}
		
		$c_players = $players;
		$players_count = count($players);
		
		$j = 0;
		while ($j < $players_count) {
			$this_one = array_shift($c_players);
			
			error_log('this_one -- ' . $this_one['playername'] . ' -- has ' . $vsgame_count[$this_one['playername']] . ' games');
			if ($vsgame_count[$this_one['playername']] >= $max_games) {
				error_log(sprintf("done with %s as s/he has %s games", $this_one['playername'], $vsgame_count[$this_one['playername']]));
				$j++;
				continue; 
			}
			
			$i = 0;
			$a_index = 0;
			
			$assigned_games = $vsgame_count[$this_one['playername']];

			while ($assigned_games < $max_games) {

				if ($a_index >= count($players)) {
					$a_index = 0;
				}
				$opp = $players[$a_index++];
				
				if ($vsgame_count[$opp['playername']] >= $max_games) {
					#error_log($opp['playername'] . ' has 16 games');
					continue;
				}
				
				if ($opp['playername'] == $this_one['playername']) {
					continue;
				}
				
				$matchup = array();
				$matchup['opponent_1'] = $this_one['playername'];
				$matchup['opponent_2'] = $opp['playername'];
				
				$week = $assigned_games + 1;
				$matchup['week'] = $week;
								
				if (!isset($matchups[$week])) {
					$matchups[$week] = array();
				}
				if (!isset($matchups[$week][$this_one['playername']])) {
					$matchups[$week][$this_one['playername']] = array();
				}
				$matchups[$week][] = $matchup;
				
				$vsgame_count[$opp['playername']]++;
				$vsgame_count[$this_one['playername']]++;
				
				$assigned_games++;
			}

			#put 'this_one' at the back of the line
			$c_players = array_reverse($c_players);
			error_log('/this_one -- ' . $this_one['playername'] . ' -- has ' . $vsgame_count[$this_one['playername']] . ' games');
						
			$j++;
		}
		
		#$keep_looping = TRUE;
		#while ($keep_looping) {
		#	$matchup = array();
		#	$matchup[] = array_shift($players);
		#	$matchup[] = array_shift($players);
		#	$matchups[] = $matchup;
		#	
		#	$keep_looping = count($players) > 2;
		#}
		
		$f3->set('matchups', $matchups);
		$f3->set('players', $players);
		$f3->set('inc', 'admin/vs_matchups.htm');
	}
	
	function importAPPoll($f3) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;

		if ($f3->exists('FILES.upload_file', $upload_file)) {
			if (is_uploaded_file($upload_file['tmp_name'])) {
				if (($handle = fopen($upload_file['tmp_name'])) !== FALSE) {
					while (($data = fgetcsv($handle, 1024, ','))) {
						echo json_encode($data) . "\n";
					}
					fclose($handle);
				}
			}
		}
	}

	function import_games_form($f3) {
		$f3->set('inc', 'admin/import_games_form.htm');
	}
	
	function import_games_submit($f3) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$pickem_slate = new \DB\SQL\Mapper($this->db, 'pickem_slate');
		
		// ASSUME pickem_id = 1
		$pickem_id = $f3->get('pickem.default_pickem_id');
		$week = new \DB\SQL\Mapper($this->db, 'week');
		$host_team = new \DB\SQL\Mapper($this->db, 'team');
		$visiting_team = new \DB\SQL\Mapper($this->db, 'team');

		$POST = FALSE;
		if (!$f3->exists('POST', $POST)) {
			return;
		}

		if ($f3->exists('FILES.file_upload', $upload_file)) {
			if ($upload_file['error'] != UPLOAD_ERR_NO_FILE) {
				$fname = $upload_file['name'];
				$tmpname = $upload_file['tmp_name'];
	
				if (is_uploaded_file($upload_file['tmp_name'])) {
					
					// http://stackoverflow.com/questions/5674117/how-do-i-parse-a-csv-file-to-grab-the-column-names-first-then-the-rows-that-rela?answertab=active#tab-top
					$csv = array_map("str_getcsv", file($upload_file['tmp_name'],FILE_SKIP_EMPTY_LINES));
					$keys = array_shift($csv);
					foreach ($csv as $i=>$row) {
					  $csv[$i] = array_combine($keys, $row);
						
						// process each row (hopefully a game)
						$host_team->reset();
						$visiting_team->reset();
						$host_team->load(array('name=?', $csv[$i]['host_team_name']));
						$visiting_team->load(array('name=?', $csv[$i]['visiting_team_name']));
						
						if (($host_team->dry() || $visiting_team->dry()) && empty($csv[$i]['title'])) {
							$f3->push('SESSION.error_msg', 
								sprintf(
									"Skipped %s | %s, likely due to misspelled team id.",
									$csv[$I]['host_team_name'],
									$csv[$i]['visiting_team_name']
								)
							);
							contiune;
						}
						$date = new \DateTime($csv[$i]['event_date']);
						$stamp = $date->getTimestamp();
						
						$week->load(array("(start <= ? AND end >= ?) AND pickem_id=?", $stamp, $stamp, $pickem_id));
						
						$sportsevent = new \DB\SQL\Mapper($this->db, 'sportsevent');
						$sportsevent->load(array(
							'host_team_name=? AND visiting_team_name=? AND season=? AND sport=?',
							$host_team->name, $visiting_team->name, $csv[$i]['season'], $csv[$i]['sport']
						));
						if (!$sportsevent->dry()) {
							$f3->push('SESSION.warning_msg', sprintf("%s/%s appears to already exist.", $csv[$i]['host_team_name'], $csv[$i]['visiting_team_name']));
							continue;
						}
						if ($week->dry()) {
							$f3->push('SESSION.warning_msg', 'Could not find the week.');
							continue;
						}
						$sportsevent->week = $week->week;
						$sportsevent->event_date = $stamp;
						!$host_team->dry() && $sportsevent->host_team_name = $host_team->name;
						!$visiting_team->dry() && $sportsevent->visiting_team_name = $visiting_team->name;
						$sportsevent->season = $csv[$i]['season'];
						$sportsevent->sport = $csv[$i]['sport'];
						$sportsevent->location = $csv[$i]['location'];
						$sportsevent->neutral = $csv[$i]['neutral'];
						$sportsevent->hide_from_pickem = $csv[$i]['hide_from_pickem'];
						!empty($csv[$i]['title']) && $sportsevent->title = $csv[$i]['title'];
						!empty($csv[$i]['tv']) && $sportsevent->tv = $csv[$i]['tv'];
						!empty($csv[$i]['host_conf_name']) && $sportsevent->host_conf_name = $csv[$i]['host_conf_name'];
						!empty($csv[$i]['visiting_conf_name']) && $sportsevent->host_conf_name = $csv[$i]['visiting_conf_name'];
												
						#echo json_encode($sportsevent->cast(), JSON_PRETTY_PRINT) . "\n\n";
						$sportsevent->save();
						
						$pickem_ids = explode(',', $csv[$i]['pickem_id']);
						foreach ($pickem_ids as $pid) {
							$pickem_slate->reset();
							$pickem_slate->event_id = $sportsevent->eid;
							$pickem_slate->week = $sportsevent->week;
							$pickem_slate->pickem_id = $pid;
							$pickem_slate->slate_date = $sportsevent->event_date;
							$pickem_slate->season = $sportsevent->season;
							$pickem_slate->save();
						}
						$f3->push('SESSION.success_msg', 
							sprintf(
								"Saved %s for Week %s", 
								(!empty($sportsevent->title) ? $sportsevent->title : $sportsevent->host_team_name . ' / ' . $sportsevent->visiting_team_name),
								$sportsevent->week
							)
						);
						
						// add to Pickem(s)
						/*
						$db_pickem_slate = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_slate'));
						foreach (explode(',', $csv[$i]['pickem_id']) as $pickem_id) {
							$db_pickem_slate->reset();

						}
						*/
					}		
				}
			}
		}

		$f3->reroute('/admin/importgames');
	}
	
	public function create_backup_tables($f3) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;

		$this->db->exec(
			array(
				'DROP TABLE IF EXISTS pick_backup',
				'CREATE TABLE pick_backup LIKE pick',
				'INSERT INTO pick_backup SELECT * FROM pick',
				'DROP TABLE IF EXISTS pickem_player_data_backup',
				'CREATE TABLE pickem_player_data_backup LIKE pickem_player_data',
				'INSERT INTO pickem_player_data_backup SELECT * FROM pickem_player_data',
				'DROP TABLE IF EXISTS sportsevent_backup',
				'CREATE TABLE sportsevent_backup LIKE sportsevent',
				'INSERT INTO sportsevent_backup SELECT * FROM sportsevent',
				'DROP TABLE IF EXISTS standings_backup',
				'CREATE TABLE standings_backup LIKE standings',
				'INSERT INTO standings_backup SELECT * FROM standings',
				'DROP TABLE IF EXISTS pickem_slate_backup',
				'CREATE TABLE pickem_slate_backup LIKE pickem_slate',
				'INSERT INTO pickem_slate_backup SELECT * FROM pickem_slate',
				'DROP TABLE IF EXISTS users_backup',
				'CREATE TABLE users_backup LIKE users',
				'INSERT INTO users_backup SELECT * FROM users',
			)
		);

		echo json_encode(array());		
		//$f3->push("SESSION.success_msg", "Table backups successfully created.");
		//$f3->reroute('@pickem_stuff');
	}

}
