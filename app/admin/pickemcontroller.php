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
				FROM pickem_player_data WHERE pickem_id = ? AND week = ?
				ORDER BY playername";
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
		
	}
	
	function resetPickemStandings($f3) {
		$res = $this->db->exec('DELETE FROM standings WHERE pickem_id = ? and week = ?', array('1' => $f3->get('POST.pickem_id'), '2' => $f3->get('POST.week')));
		
		$POST = $f3->get('POST');
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

}
