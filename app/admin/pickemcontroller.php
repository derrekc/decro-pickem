<?php
namespace Admin;

class PickemController extends \DashboardController {

	function home($f3) {
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
}
