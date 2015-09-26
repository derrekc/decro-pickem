<?php
namespace Admin;

class GameController extends \AdminController {
	private $game_table = 'game';
	private $sportsevent_table = 'sportsevent';
	
	function __construct() {
		parent::__construct();
		$this->default_template = 'dashboard.htm';
		
		$f3 = \Base::instance();
		$this->pickem_id = $f3->get('pickem.default_pickem_id');	

		$plugins = $f3->get('admin.gamecontroller.plugins');
		foreach($plugins as $p) {
			$this->addDashboardPlugin(new $p);
		}
	}
	
	function beforeroute($f3) {
		parent::beforeroute($f3);
	}
	
	function games_home($f3) {
		$game = new \DB\SQL\Mapper($this->db, $this->game_table);
		$limit = 25;
		$page = \Pagination::findCurrentPage();
		
		$week = $f3->get('pickem.current_week');
		if ($f3->exists('GET.week')) {
			$week = $f3->get('GET.week');
			$filter = array('season = ? and week = ?', 2015, $week);
		} else {
			$filter = array('season = ?', 2015);
		}
		$option = array('order' => 'event_date ASC');
		
		$subset = $game->paginate($page-1, $limit, $filter, $option);
		
		$assigned_pickems = array();
		foreach ($subset['subset'] as $g) {
			$pickems = $this->db->exec(
				"SELECT * 
				 FROM pickem_slate ps 
				 JOIN pickem p ON ps.pickem_id = p.pid
				 WHERE ps.event_id = ?", array("1" => $g->eid)
			);
			$assigned_pickems[$g->eid] = array();
			foreach ($pickems as $p) {
				$assigned_pickems[$g->eid][$p->pickem_id] = $p;
			}
		}
		$f3->set('assigned_pickems', $assigned_pickems);
		
		$f3->set('gameList', $subset);
		$f3->set('inc', 'admin/gamelist.htm');
	
		$modals = array(
			'modal-gameedit.htm',
		);
		$f3->set('week', $week);
		$f3->set('pickem_id', $f3->get('pickem.default_pickem_id'));
		$f3->set('modals', $modals);
		$f3->set('footer_inc', 'admin/gameadd_quick.htm');
	}
	
	function game_edit($f3, $args) {
		$pickem_slate = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_slate'));
		
		$game = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.game'));
		$game->load(array('eid=?', $args['eid']));
		$game_pickems = $pickem_slate->find(array('event_id=?', $args['eid']));
		
		$pickem = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem'));
		$pickems = $pickem->find(array('active=1'));
		
		$assigned_pickems = array();
		foreach ($game_pickems as $p) {
			$assigned_pickems[] = $p->pickem_id;
		}

		$f3->set('return_to', $f3->get('SERVER.HTTP_REFERER'));
		$f3->set('edit_game', $game);
		$f3->set('pickems', $pickems);
		$f3->set('assigned_pickems', $assigned_pickems);
		$f3->set('inc', 'admin/gameedit.htm');
	}
	
	function create($f3) {
		if (!$f3->exists('POST', $POST)) {
			$f3->reroute('@edit_games');
		}
		$game = new \DB\SQL\Mapper($this->db, $this->sportsevent_table);
		$week = new \DB\SQL\Mapper($this->db, 'week');
		
		if ($POST['create_method'] == 'quick') {
			$game->host_team_name = $POST['host_team_name'];
			$game->visiting_team_name = $POST['visiting_team_name'];
			$game->season = $POST['season'];
			$game->sport = $POST['sport'];
			$game->location = $POST['location'];
			$game->neutral = isset($POST['neutral']) ? 'Y' : '';
			$game->hide_from_pickem = isset($POST['hide_from_pickem']) ? 1 : 0;
			$game->tv = $POST['tv'];
			
			$date = new \DateTime($POST['game_date']);
			$game->event_date = $date->getTimestamp();
			
			$week->load(array("start <= ? and end >= ?", $game->event_date, $game->event_date));
			if (!$week->dry()) {
				$game->week = $week->week;
			}
			$game->save();
			
			#if (!isset($POST['hide_from_pickem'])) {			
				
			#	# clear pick data, forcing everyone to have be reminded to re-submit
			#	$res = $this->db->exec('DELETE FROM pickem_player_data WHERE week = ?', $week->week);				
			#}

			$f3->push('SESSION.success_msg', sprintf("%s vs %s has been added.", $POST['host_team'], $POST['visiting_team']));
			$f3->reroute($POST['return_to']);
		}
	}
	
	function game_save($f3) {
		if (!$f3->exists('SESSION.csrf', $csrf)) {
			$f3->reroute('@welcome');
			return;
		}
		if ($csrf != $f3->get('POST.token')) {
			$f3->reroute('@welcome');
		}
		
		extract($f3->get('POST'));
		
		# ensure the pickem arrays are valid
		empty($assigned_pickem) && $assigned_pickem = array();
		empty($current_pickems) && $current_pickems = array();
		
		$sportsevent = new \DB\SQL\Mapper($this->db, $this->sportsevent_table);
		$sportsevent->load(array('eid=?', $eid));
		if ($sportsevent->dry()) {
			// go somewhere else
		}
		#header("Content-type: application/json", TRUE);
		#$this->use_json = TRUE;

		$sportsevent->neutral = isset($neutral) ? 'Y' : '';

		$date = new \DateTime(html_entity_decode($game_date));
		$sportsevent->event_date = $date->getTimestamp();
		
		$sportsevent->visiting_team_name = $visiting_team_name;
		$sportsevent->host_team_name = $host_team_name;
		$sportsevent->location = $location;
		
		if (!empty($tv_secondary)) {
			$sportsevent->tv = $tv_secondary;
		} else {
			if (!empty($tv)) {
				$sportsevent->tv = $tv;
			}
		}
		
		$sportsevent->save();
		
		// address the assigned pickems portion
		$pickem_slate = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_slate'));
		$pickem = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem'));
		
		foreach ($current_pickems as $pid => $pvalue) {
			if (!isset($assigned_pickem[$pid])) {
				$lookup = 'pickem.' . $pid . '.current_week';
				if ((int)$f3->get($lookup) >= $sportsevent->week) {
					$f3->push('SESSION.error_msg', sprintf("This game cannot be removed from <strong>%s</strong> this is not a valid week.", $pickem->title));
				} else {
					$pickem->load(array('pid=?', $pid));
					$pickem_slate->load(array('pickem_id=? AND event_id=?', $pid, $sportsevent->eid));
					$pickem_slate->erase();
					$f3->push('SESSION.info_msg', sprintf("This game was removed from <strong>%s</strong>.", $pickem->title));
				}
			}
		}
		
		foreach ($assigned_pickem as $pid => $pvalue) {
			if (!isset($current_pickems[$pid])) {
				$lookup = 'pickem.' . $pid . '.current_week';
				$f3->push('SESSION.info_msg', sprintf("%s = [%s] (compared to %s)", $lookup, $f3->get($lookup), $sportsevent->week));
				if ((int)$f3->get($lookup) >= $sportsevent->week) {
					$f3->push('SESSION.error_msg', sprintf("Sportsfan would add this pickem assignment (%s => %s) in production mode, but this is not a valid week.", $pid, $eid));					
				} else {
					$pickem->load(array('pid=?', $pid));
					$current_slate = $pickem_slate->find(array('pickem_id=? AND week=?', $pid, $sportsevent->week));
					$max_games_per_week = (int) $f3->get(sprintf('pickem.%s.max_games_per_week', $pid));
					$week_ignore_max_games = (int) $f3->get(sprintf('pickem.%s.week_ignore_max_games', $pid));
					
					if (((sizeof($current_slate) + 1) < $max_games_per_week) || $sportsevent->week == $week_ignore_max_games) {
						$pickem_slate->reset();
						$pickem_slate->pickem_id = $pid;
						$pickem_slate->event_id = $sportsevent->eid;
						$pickem_slate->slate_date = $sportsevent->event_date;
						$pickem_slate->week = $sportsevent->week;
						$pickem_slate->season = $sportsevent->season;
						$pickem_slate->save();
						$f3->push('SESSION.info_msg', sprintf("This game was added to the Pick'em, <strong>%s</strong>.", $pickem->title));					
					} else {
						$f3->push('SESSION.info_msg', sprintf("Adding this game to <strong>%s</strong> would exceed the max number of games allowed per week (%s).", $pickem->title, $max_games_per_week));	
					}
				}
			}
		}

		$f3->push('SESSION.success_msg', 'Your changes have been saved.  Yay!');
		$f3->reroute('@game_edit(@eid=' . $eid . ')');
		
		##$game = new \DB\SQL\Mapper($this->db, $this->game_table);
		##$game->load(array('eid=?', $eid));
		##$f3->set('edit_game', $game);
		
		#$f3->reroute(html_entity_decode($f3->get('POST.return_to')));
		##$f3->set('inc', 'admin/gameedit.htm');	
	}
	
	function game_editscore($f3, $args) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$game = new \DB\SQL\Mapper($this->db, $this->game_table);
		$game->load(array('eid=?', $args['eid']));

		echo json_encode(array(
			'host_team' => $game->host_team,
			'host_score' => $game->host_score,
			'visiting_team' => $game->visiting_team,
			'visiting_score' => $game->visiting_score,
			'eid' => $game->eid,
		));
	}
	
	function game_editdate($f3, $args) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$game = new \DB\SQL\Mapper($this->db, $this->game_table);
		$game->load(array('eid=?', $args['eid']));

		$game->tv_secondary = '';
		if (strpos($game->tv, '/') != -1) {
			$game->tv_secondary = $game->tv;
		}
		echo json_encode(array(
			'modal_title' => sprintf("%s vs %s", $game->visiting_team, $game->host_team),
			'event_date' => $game->event_date,
			'event_date_string' => $game->event_date_string,
			'game_date_moment' => $game->game_date_moment,
			'game_tv' => strtolower($game->tv),
			'game_tv_secondary' => strtolower($game->tv_secondary),
			'eid' => $game->eid,
		));
	}
		
	function game_postdata($f3) {
		#header("Content-type: application/json", TRUE);
		#$this->use_json = TRUE;
		
		$update_pickem_results = FALSE;

		$POST = $f3->get('POST');
		#echo json_encode($POST);
		#echo json_encode($this->sportsevent_table);
		
		$sportsevent = new \DB\SQL\Mapper($this->db, $this->sportsevent_table);
		$sportsevent->load(array('eid=?', $f3->get('POST.eid')));
		
		$hook = FALSE;
		
		if (!$sportsevent->dry()) {
			if ($POST['section'] == 'game-date') {
				$date = new \DateTime(html_entity_decode($f3->get('POST.game_date')));
				$sportsevent->event_date = $date->getTimestamp();
				$hook = 'update_event_date';
						
				if (!empty($POST['tv_secondary'])) {
					$sportsevent->tv = $POST['tv_secondary'];
				} else {
					if (!empty($POST['tv'])) {
						$sportsevent->tv = $POST['tv'];
					}
				}
				
			} else if ($POST['section'] == 'game-score') {
				$sportsevent->winning_team_name = $POST['host_score'] > $POST['visiting_score'] 
					? $sportsevent->host_team_name 
					: $sportsevent->visiting_team_name;
					
				$sportsevent->visiting_score = $POST['visiting_score'];
				$sportsevent->host_score = $POST['host_score'];
				$sportsevent->completed = 'Y';
				
				$update_pickem_results = !$update_pickem_results && TRUE;
				
				$update_pickem_results && $hook = 'update_score';
				
				# TODO update pickem results for each player
			}
			$sportsevent->save();
			
			if ($hook) {
				$this->invokeHook($hook, $sportsevent);
			}
		}

		/*
		if ($update_pickem_results) {
			$player = \DB\SQL\Mapper($this->db, 'users');
			$pick = \DB\SQL\Mapper($this->db, 'pick');
			
			// TODO put this in a separate function in a different class
			$players = $this->db->exec('SELECT * FROM users WHERE status = 1');
			foreach ($players as $player) {
				$pick->load(array('uid=?', $player->uid));
				if ($pick->dry()) {
					$pick->reset();
					$pick->uid = $player->uid;
					$pick->eid = $sportsevent->eid;
					$pick->playername = $player->name;
					$pick->week = $sportsevent->week;
					
					// the player didn't make a pick, so create one for him/her
					if ($player->bye_weeks < $f3->get('pickem.max_bye_weeks')) {
						$pick->pick_team_name = $player->default_pick == 'hostteam' 
						? $sportsevent->host_team_name
						: $sportsevent->host_team_name;

					} else {
						// player has no byes left and takes the "L"
						$pick->pick_team_name = $sportsevent->winning_team_name == $sportsevent->host_team_name 
							? $sportsevent->visiting_team_name
							: $sportsevent->host_team_name;
							
					}
					$player->bye_weeks++;
					$player->save();
				}
				$pick->correct = $sportsevent->winning_team_name == $pick->pick_team_name ? 'Y' : 'N';
			}
			
			
			
			$picks = $this->db->exec('SELECT * FROM pick WHERE eid = ' . $sportsevent->eid);
			foreach ($picks as $pick) {
				$player->load(array('uid=?', $pick->uid));
				if ($pick)
				if (!$player->dry() && ($player->bye_weeks >= $f3->get('pickem.max_bye_weeks'))) {
					
				}
			}
		}
		*/
		
		$f3->reroute(html_entity_decode($f3->get('POST.redirect_to')));		
	}
	
	
	public function toggle_game_status($f3) {
		if (!$f3->exists('POST', $POST)) {
			return;
		}

		$this->use_json = TRUE;
		header('Content-type: application/json', TRUE);
		
		$game = new \DB\SQL\Mapper($this->db, $this->sportsevent_table);
		$db_pickem_slate = new \DB\SQL\Mapper($this->db, $f3->get('pickem.table.pickem_slate'));
		
		# mark the game as 'hidden'
		$game->load(array('eid=?',$POST['eid']));
		if (!$game->dry()) {
			
			$game->hide_from_pickem = $POST['hide_from_pickem'];
			$game->save();
			
			if ($POST['hide_from_pickem'] == 0) {
				# add this game to the pickem_slate table
				# $db_pickem_slate->reset();

				# remove any previous picks on this game	
				$res = $this->db->exec('DELETE FROM pick WHERE eid = ?', $game->eid);
				
			} else {
				# clear pick data, forcing everyone to have be reminded to re-submit
				$res = $this->db->exec('DELETE FROM pickem_player_data WHERE week = ?', $game->week);
			}
		}
		
		echo json_encode(array('gamestatus' => $POST['hide_from_pickem']));
	}
}
