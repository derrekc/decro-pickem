<?php

class CLI extends Controller {
	
	public function do_render($f3) {}
	public function prepareUserMenu($f3) {}
	
	function __construct() {
		parent::__construct();
	}
	
	function list_users($f3) {
		$users = new \DB\SQL\Mapper($this->db, 'users');
		$all_users = $users->find();
		foreach ($all_users as $user) {
			printf("%s | %s\n", $user->uid, $user->name);
		}
	}
	
	function process_newbies($f3) {
		$pickem_id = FALSE;
		if (!$f3->exists('REQUEST.pickem_id', $pickem_id)) {
			$pickem_id = 1;
		}
		
		$current_week = $f3->get(sprintf("pickem.%s.current_week", $pickem_id));
		
		$users = new \DB\SQL\Mapper($this->db, 'users');
		$new_users = $users->find(
			array(
				'status=1 AND name NOT IN (SELECT playername FROM pickem_player_data WHERE week = ?)',
				1
			)
		);
		printf("size of new_users = [%s]\n", sizeof($new_users));
		if (sizeof($new_users) == 0) {
			printf("No users have signed up recently...\n");
			exit;
		}
		foreach ($new_users as $u) {
			printf("%s | %s\n", $u->uid, $u->name);
		}
	}
	
	function rank_previous_weeks($f3) {
		$pickem_player_data = new \DB\SQL\Mapper($this->db, 'pickem_player_data');
		$start_week = 4;
		$end_week = 4;
		
		for($i = $start_week; $i <= $end_week; $i++) {
			
			$rank = 0;
			$current_rank = 0;
			$last_correct = FALSE;
			#$db_standings->reset();
			$players = $pickem_player_data->find(array('week=? and pickem_id=1', $i), array('order'=>'correct DESC'));

			foreach ($players as $s) {
				$current_rank++;
				if ($s->correct != $last_correct) {
					$rank = $current_rank;
					$last_correct = $s->correct;
				}
				$s->rank = $rank;
				$s->save();
			}
			
		}
	}
	
	function test_query($f3) {
		$current_week = 4;
		$pickem_id = 1;
		
		$db_users = new \DB\SQL\Mapper($this->db, 'users');
		$db_pickem_player = new \DB\SQL\Mapper($this->db, 'pickem_player_data');
		$db_pick = new \DB\SQL\Mapper($this->db, 'pick');
		$users = $db_users->find('status = 1');
		
		foreach ($users as $user) {
			$db_pickem_player->load(array('uid=? AND week=?', $user->uid, $current_week));
			if ($db_pickem_player->dry()) {
				echo $user->name . "(" . $user->uid . "): this player doesn't has yet to submit picks for Weeek " . $current_week . "\n";
			} else {
				
			}
		}
		
		echo "### GAMES test query\n";
		
		$game = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.game'));
		$games = $game->find(array('week=4'));
		for($i = 0; $i < sizeof($games); $i++) {
			echo $games[$i]->eid . "\n";
			$pickems = $this->db->exec(
				"SELECT * 
				 FROM pickem_slate ps 
				 JOIN pickem p ON ps.pickem_id = p.pid
				 WHERE ps.event_id = ?", array("1" => $games[$i]->eid)
			);
			#print_r($pickems);
			$games[$i]['assigned_pickems'] = $pickems;
			print_r($games[$i]['assigned_pickems']);
		}
		
		$db_standings = new \DB\SQL\Mapper($this->db, 'standings');
		$last_stamp = $this->db->exec('SELECT MAX(stamp) AS last_stamp FROM standings');
		$last_stamp = $last_stamp[0]['last_stamp'];
		printf("Last stamp for standings was: %s\n", $last_stamp);

		$first_stamp = $this->db->exec('SELECT MIN(stamp) AS first_stamp FROM standings WHERE pickem_id = ? AND week = ?',
			array('1' => $pickem_id, '2' => $current_week));
			
		$first_stamp = $first_stamp[0]['first_stamp'];
		printf("First stamp for standings was: %s\n", date('r', $last_stamp));
		
		$players = $db_pickem_player->find(array('week=? AND pickem_id=?', $current_week, $pickem_id));
		$hash = array();
		foreach ($players as $p) {
			$picks = $db_pick->find(array('uid=? AND week=?', $p->uid, $current_week),array('order'=>'eid'));
			$str = array();
			foreach ($picks as $pick) {
				$str[] = $pick->pick_team_name;
			}
			$encoded = base64_encode(join('', $str));
			$hash[$encoded]++;
		}
		printf("Unique pick sets = %s (out of %s)\n", sizeof(array_keys($hash)), sizeof($players));
	}

	function restore_week_3($f3) {
		// ASSUMES I have already copied week 3 data
		// from the pickem_player_backup table

		$pick = new \DB\SQL\Mapper($this->db, 'pick');
		$pplayer = new \DB\SQL\Mapper($this->db, 'pickem_player_data');

		$players = $pplayer->find(array('week=3 AND pickem_id=1'));
		foreach ($players as $player) {
			// LOAD all the picks and refresh correct and incorrect for each player
			$picks = $pick->find(array('week=3 and uid=?', $player->uid));
			foreach ($picks as $p) {
				printf("player: %s, pick_id = %s, pick_team_name = %s (%s)\n", $player->playername, $p->pkid, $p->pick_team_name, $p->correct);
				if ($p->correct == 'Y') {
					$player->correct++;
				} else {
					$player->incorrect++;
				}
			}
			printf("%s: %s - %s\n", $player->playername, $player->correct, $player->incorrect);
			$player->save();
		}
		printf("\n");
	}
	
	function fetch_ncaa_games($f3) {
		$sportsevent = new \DB\SQL\Mapper($this->db, 'sportsevent_test');
		
		include_once('/usr/www/users/delemarc/stage/pickem.decro.net/sites/all/modules/sportsfan/data/teams_appendix.inc');
		if (!function_exists('curl_init')) {
			echo "Can't cURL -- UGH!!!\n";
			exit;
		}
		$sport = 'ncaaf';
		$week = 4;
		
		echo "check point 1\n";
		
		$urls = \CLI\SportsEvent::scoreboardUrls($sport, $week, $week_start);
		echo print_r($urls, TRUE);

		libxml_use_internal_errors( FALSE );
		foreach ($urls as $url) {
			# open the file with file_get_contents and set a timeout context
			#ctx = stream_context_create(array('http' => array('timeout' => 15.0)));
			#$file = file_get_contents($url, 0, $ctx);
			$file = $this->_curl_load_file($url);
			
			$doc = new DOMDocument();
			// supress DOM parsing errors
			@$doc->loadHTML( $file );
			echo "new DOC created...\n";
			$xpath = new DOMXPath( $doc );
			echo "TESTING elements\n";
			$elements = $xpath->query("//section[contains(concat(' ', @class, ' '), ' game pre')]");
			echo print_r($elements, TRUE);
			if ($elements->length == 0) {
				// either a timeout occurred or there were no events for this date
				continue;
			}


			// PROCESS each event
			foreach ($elements as $element) {
				$headerElement = $xpath->query('h3', $element);

				$match = array();
				preg_match('/^(?P<visitor>[\w\s\(\)\.]+) vs (?P<host>[\w\s\(\)\.]+)$/', $headerElement->item(0)->nodeValue, $match);
				

				$schoolElements = $xpath->query("div[@class='game-contents']//td[@class='school']", $element);
				//$scoreElements = $xpath->query("div[@class='game-contents']//td[@class='final score']", $element);
				$linkElements = $xpath->query("div[@class='game-contents']//ul[contains(concat(' ', @class, ' '), ' linklist')]/li/a", $element);
				$gameSummaryLink = (string) $linkElements->item(0)->getAttribute('href');

				$v = $schoolElements->item(0);
				$h = $schoolElements->item(1);
				
				$visitor = $xpath->query("div[@class='team']/a", $v);
				$host = $xpath->query("div[@class='team']/a", $h);
				
				#$visitor_score = $scoreElements->item(0)->nodeValue;
				#$host_score = $scoreElements->item(1)->nodeValue;
				
				$visitor_title = $visitor->item(0)->nodeValue;
				$host_title = $host->item(0)->nodeValue;
				
				$z = TRUE;
				if (!isset($teams[$visitor_title])) {
					$unknowns["'" . $visitor_title . "'"] = "'" . $visitor_title . "'";
					$z = FALSE;
				}
				if (!isset($teams[$host_title])) {
					$unknowns["'" . $host_title . "'"] = "'" . $host_title . "'";
					$z = FALSE;
				}
				
				printf("%s vs %s \n", $visitor_title, $host_title);
				$event_info = $this->_parse_event_info("http://ncaa.com" . $gameSummaryLink);
				echo print_r($event_info, TRUE);
				if ($z) {
					$visiting_team_name = $teams[$visitor_title];
					$host_team_name = $teams[$host_title];
					echo print_r(array('visitor' => $visiting_team_name, 'host' => $host_team_name), TRUE);
				}
			}
		}
	}

	function _curl_load_file($url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15.0);
		$result = curl_exec($curl);
		curl_close($curl);
		return $result;
	}
	
	function _parse_event_info($summary_url) {
		$ctx = stream_context_create(array('http' => array('timeout' => 15.0)));
		$file = $this->_curl_load_file($summary_url);
		
		$doc = new DOMDocument();
		// supress DOM parsing errors
		@$doc->loadHTML( $file );
		
		# need to parse the date out of the URL
		$datematch = array();
		$date_info = array();
		if (preg_match('/^.+\/(fbs|d1)\/(?P<year>\d{4})\/(?P<month>\d{2})\/(?P<day>\d{2}\/.+$)/', $summary_url, $datematch) == 1) {
			$date_info['month'] = $datematch['month'];
			$date_info['year'] = $datematch['year'];
			$date_info['day'] = $datematch['day'];
		}
		
		$xpath = new DOMXPath( $doc );
		$locationElements = $xpath->query("//p[@class='location']");
		$index = $locationElements->length == 1 ? 0 : 1;
		
		$game_state_elements = $xpath->query("//div[contains(concat(' ', @class, ' '), ' game-state')]//span[@class='state']");
		$game_state = $game_state_elements->item(0)->nodeValue;
		
		$schoolElements = $xpath->query("//table[@id='linescore']//td[@class='school']/a");
		$host_school = $schoolElements->item(1)->nodeValue;
		$visiting_school = $schoolElements->item(0)->nodeValue;
		
		$m = array();
		$r = preg_match("/^(?P<venue_name>[\w\s\-.&']+),\s(?P<location_city>[\w\s-.]+),\s(?P<location_state>\w{2})$/", $locationElements->item($index)->nodeValue, $m);
		if ($r == 1) {		
			return array(
				'host_team' => $host_school,
				'visiting_team' => $visiting_school,
				'location_state' => $m['location_state'],
				'location_city' => $m['location_city'],
				'location' => sprintf("%s, %s", $m['location_city'], $m['location_state']),
				'venue_name' => $m['venue_name'],
			) + $date_info;
		}
		return FALSE;
	}

}
