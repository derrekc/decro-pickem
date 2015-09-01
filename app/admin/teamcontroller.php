<?php
namespace Admin;

class TeamController extends \AdminController {

	public function teams_home($f3) {
		$team = new \DB\SQL\Mapper($this->db, 'team');
		$limit = 25;
		$page = \Pagination::findCurrentPage();
		
		$filter = array("conf_name IN ('acc','sec','big10','big12','pac12')");
		$option = array('order' => 'conf_name ASC, name ASC');
		
		$subset = $team->paginate($page-1, $limit, $filter, $option);
		
		$f3->set('teamList', $subset);
		$f3->set('inc', 'admin/teamlist.htm');
		
		#$modals = array(
		#	'modal-gameedit.htm',
		#);
		#$f3->set('modals', $modals);
	}
	
}
