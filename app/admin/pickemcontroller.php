<?php
namespace Admin;

class PickemController extends \AdminController {

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

	function playerStatus($f3) {
		$q = "SELECT *, DATE_FORMAT(FROM_UNIXTIME(completed_stamp), '%b %e - %r') AS completed_datetime FROM pickem_player_data WHERE pickem_id = ?";
		$players = $this->db->exec($q, $f3->get('pickem.default_pickem_id'));
		#echo print_r($players, true);

		$f3->set('players', $players);
		$f3->set('inc', 'admin/player_status.htm');
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
