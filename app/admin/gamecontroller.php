<?php
namespace Admin;

class GameController extends \AdminController {
	
	function games_home($f3) {
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$limit = 25;
		$page = \Pagination::findCurrentPage();
		
		if ($f3->exists('REQUEST.week', $week)) {
			$filter = array('season = ? and week = ?', 2015, $week);
		} else {
			$filter = array('season = ?', 2015);
		}
		$option = array('order' => 'event_date ASC');
		
		$subset = $game->paginate($page-1, $limit, $filter, $option);
		
		$f3->set('gameList', $subset);
		$f3->set('inc', 'admin/gamelist.htm');
		
		$week_list = \WeekSelect::renderWeekSelect();
	
		$modals = array(
			'modal-gameedit.htm',
		);
		$f3->set('modals', $modals);
		$f3->set('footer_inc', 'admin/gameadd_quick.htm');
	}
	
	function game_edit($f3, $args) {
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $args['eid']));
		
		$f3->set('return_to', $f3->get('SERVER.HTTP_REFERER'));
		$f3->set('edit_game', $game);
		$f3->set('inc', 'admin/gameedit.htm');
	}
	
	function create($f3) {
		if (!$f3->exists('POST', $POST)) {
			$f3->reroute('@edit_games');
		}
		$game = new \DB\SQL\Mapper($this->db, 'sportsevent');
		$week = new \DB\SQL\Mapper($this->db, 'week');
		
		if ($POST['create_method'] == 'quick') {
			$game->host_team_name = $POST['host_team_name'];
			$game->visiting_team_name = $POST['visiting_team_name'];
			$game->season = $POST['season'];
			$game->sport = $POST['sport'];
			$game->location = $POST['location'];
			$game->neutral = isset($POST['neutral']) ? 'Y' : '';
			
			$date = new \DateTime($POST['game_date']);
			$game->event_date = $date->getTimestamp();
			
			$week->load(array("start <= ? and end >= ?", $game->event_date, $game->event_date));
			if (!$week->dry()) {
				$game->week = $week->week;
			}
			$game->save();
			
			if (!isset($POST['hide_from_pickem'])) {			
				
				# clear pick data, forcing everyone to have be reminded to re-submit
				$res = $this->db->exec('DELETE FROM pickem_player_data WHERE week = ?', $week->week);				
			}

			$f3->set('SESSION.success_message', sprintf("%s vs %s has been added.", $POST['host_team'], $POST['visiting_team']));
			$f3->reroute('/admin/games');
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
		
		$sportsevent = new \DB\SQL\Mapper($this->db, 'sportsevent');
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
		$sportsevent->save();
		
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $eid));
		$f3->set('edit_game', $game);
		
		$f3->set('SESSION.success_message', 'Your changes have been saved.  Yay!');
		#$f3->reroute(html_entity_decode($f3->get('POST.return_to')));
		$f3->set('inc', 'admin/gameedit.htm');	
	}
	
	function game_editscore($f3, $args) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$game = new \DB\SQL\Mapper($this->db, 'game');
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
		
		$game = new \DB\SQL\Mapper($this->db, 'game');
		$game->load(array('eid=?', $args['eid']));

		echo json_encode(array(
			'modal_title' => sprintf("%s vs %s", $game->visiting_team, $game->host_team),
			'event_date' => $game->event_date,
			'event_date_string' => $game->event_date_string,
			'game_date_moment' => $game->game_date_moment,
			'eid' => $game->eid,
		));
	}
		
	function game_postdata($f3) {
		header("Content-type: application/json", TRUE);
		$this->use_json = TRUE;
		
		$update_pickem_results = FALSE;

		$POST = $f3->get('POST');
		echo json_encode($POST);
		
		$sportsevent = new \DB\SQL\Mapper($this->db, 'sportsevent');
		$sportsevent->load(array('eid=?', $f3->get('POST.eid')));
		
		if (!$sportsevent->dry()) {
			if ($POST['section'] == 'game-date') {
				$date = new \DateTime(html_entity_decode($f3->get('POST.game_date')));
				$sportsevent->event_date = $date->getTimestamp();
				
			} else if ($POST['section'] == 'game-score') {
				$sportsevent->winning_team_name = $POST['host_score'] > $POST['visiting_score'] 
					? $sportsevent->host_team_name 
					: $sportsevent->visiting_team_name;
					
				$sportsevent->visiting_score = $POST['visiting_score'];
				$sportsevent->host_score = $POST['host_score'];
				$sportsevent->completed = 'Y';
				
				$update_pickem_results = !$update_pickem_results && TRUE;
				
				# TODO update pickem results for each player
			}
			$sportsevent->save();
		}

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
		
		$f3->reroute(html_entity_decode($f3->get('POST.redirect_to')));		
	}
	
	
	public function toggle_game_status($f3) {
		if (!$f3->exists('POST', $POST)) {
			return;
		}

		$this->use_json = TRUE;
		header('Content-type: application/json', TRUE);
		
		$game = new \DB\SQL\Mapper($this->db, 'sportsevent');
		
		# mark the game as 'hidden'
		$game->load(array('eid=?',$POST['eid']));
		if (!$game->dry()) {
			
			$game->hide_from_pickem = $POST['hide_from_pickem'];
			$game->save();
			
			# remove any previous picks on this game	
			$res = $this->db->exec('DELETE FROM pick WHERE eid = ?', $game->eid);
			
			# clear pick data, forcing everyone to have be reminded to re-submit
			$res = $this->db->exec('DELETE FROM pickem_player_data WHERE week = ?', $game->week);
		}
		
		echo json_encode(array('gamestatus' => $POST['hide_from_pickem']));
	}

	
	//! Instantiate class
	function __construct() {
		parent::__construct();
		$this->default_template = 'dashboard.htm';
	}
}
