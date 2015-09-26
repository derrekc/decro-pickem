<?php

namespace Dashboard\Admin;

class PickemManager implements \Dashboard\PluginInterface {
	
	public function doHook($hook_name) {
		$args = func_get_args();
		array_shift($args);
		
		$f3 = \Base::instance();
		
		if (!$f3->exists('user', $user)) {
			return;
		}

		if ($hook_name == 'update_score') {
			list($sportsevent) = $args;
			$this->update_picks($sportsevent);
			$this->update_pickem_results();
		}
		if ($hook_name == 'prepare_pickem_stats') {
			#list($args) = $args;
			list($params_arr) = $args;
			
			$this->prepare_pickem_stats(array_shift($params_arr));
		}
	}
	
	private function prepare_pickem_stats($params_arr) {
		#error_log(print_r($params_arr, TRUE));
		#$params_arr['pickem_slate']['tmp'] = 'I added this.';
	}
	
	private function update_picks($sportsevent) {
		$sportsevent = $sportsevent[1];

		$f3 = \Base::instance();
		
		$db = $f3->get('db');
		
		$player = new \DB\SQL\Mapper($db, $f3->get('pickem.table.pickem_player_data'));
		$pick = new \DB\SQL\Mapper($db, $f3->get('pickem.table.pick'));
		$user = new \DB\SQL\Mapper($db, 'users');
		$pickem_slate = new \DB\SQL\Mapper($db, $f3->get('pickem.table.pickem_slate'));
		
		$assigned_pickems = $pickem_slate->find(array('event_id=?', $sportsevent->eid));
		
		foreach ($assigned_pickems as $pickem) {
			$lookup = sprintf('pickem.%s.current_week', $pickem->pickem_id);
			$current_week = $f3->get($lookup);
			
			$players = $player->find(array('week=? AND pickem_id=?',$current_week, $pickem->pickem_id));
			
			foreach ($players as $player) {
				$pick->load(array('uid=? and eid=?', $player->uid, $sportsevent->eid));
				
				if ($pick->dry()) {
					$now = time();
					$pick->reset();
					$pick->uid = $player->uid;
					$pick->pickem_id = $pickem->pickem_id;
					$pick->eid = $sportsevent->eid;
					$pick->playername = $player->playername;
					$pick->week = $sportsevent->week;
					$pick->initial_stamp = $now;
					
					if ($player->completed_stamp == 0) {
						$user->load(array('uid=?', $player->uid));
						// player hasn't submitted picks yet
						$player->completed_stamp = $now;
						
						// the player didn't make a pick, so create one for him/her
						if ($user->bye_weeks < $f3->get('pickem.max_bye_weeks')) {
							$pick->pick_team_name = $user->default_pick == 'hostteam' 
							? $sportsevent->host_team_name
							: $sportsevent->visiting_team_name;
		
						} else {
							// player has no byes left and takes the "L"
							$pick->pick_team_name = $sportsevent->winning_team_name == $sportsevent->host_team_name 
								? $sportsevent->visiting_team_name
								: $sportsevent->host_team_name;
								
						}
						$user->bye_weeks++;
						$user->save();
					}
				}
				$pick->correct = ($sportsevent->winning_team_name == $pick->pick_team_name) ? 'Y' : 'N';
				$pick->save();
				if ($pick->correct == 'Y') {
					$player->correct++;
				} else {
					$player->incorrect++;
				}
				$player->save();
			}
		}

		# find players who picked last week but not this week
		/*
		$missing_players = $db->exec(
			"SELECT * FROM `pickem_player_data` 
			 WHERE WEEK = 2 AND playername NOT IN 
			 (SELECT playername from `pickem_player_data` WHERE week = 3)"
		);
		foreach ($missing_players as $p) {

			$user->load(array('uid=?', $p['uid']));
			$pick->reset();
			$player->reset();
			if (!$user->dry()) {
				$player->playername = $user->name;
				$player->uid = $user->uid;
				$player->pickem_id = $f3->get('pickem.default_pickem_id'); #FIX THIS;
				$player->week = $sportsevent->week;
				$player->completed_stamp = 0;
				$player->complete = 'N';

				$pick->uid = $p['uid'];
				$pick->eid = $sportsevent->eid;
				$pick->playername = $p['playername'];
				$pick->week = $sportsevent->week;

				// the player didn't make a pick, so create one for him/her
				if ($user->bye_weeks < (int) $f3->get('pickem.max_bye_weeks')) {
					$pick->pick_team_name = $user->default_pick == 'hostteam' 
					? $sportsevent->host_team_name
					: $sportsevent->visiting_team_name;

				} else {
					// player has no byes left and takes the "L"
					$pick->pick_team_name = $sportsevent->winning_team_name == $sportsevent->host_team_name 
						? $sportsevent->visiting_team_name
						: $sportsevent->host_team_name;
						
				}
				$pick->correct = ($sportsevent->winning_team_name == $pick->pick_team_name) ? 'Y' : 'N';
				$pick->save();

				if ($pick->correct == 'Y') {
					$player->correct++;
				} else {
					$player->incorrect++;
				}

				$player->save();
				$user->bye_weeks++;
				$user->save();
			}
		}
		*/
	}
	
	private function update_pickem_results() {
		
	}
}
