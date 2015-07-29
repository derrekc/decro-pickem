<?php

class DashboardController extends Controller {
	function beforeroute($f3) {		
		if (!$f3->exists('SESSION.user_id')) {
			$f3->reroute('/login');
			return;
		}
		parent::beforeroute($f3);
		
		$this->prepareUserMenu($f3);
	}

	function home($f3) {
		$f3->set('inc', 'pick-table.htm');
	}
	
	public function do_render($f3) {
		echo Template::instance()->render('dashboard.htm');	
	}
	
	//! Instantiate class
	function __construct() {
		parent::__construct();
	}
	
	protected function prepareUserMenu($f3) {
		$menu_all_users = array();
		$menu_all_users[] = array('path' => '/', 'label' => 'Overview', 'active' => '/' == $f3->get('PATH'));
		$menu_all_users[] = array('path' => '/standings', 'label' => 'Standings', 'active' => '/standing' == $f3->get('PATH'));
		$menu_all_users[] = array('path' => '/players', 'label' => 'Players', 'active' => '/players' == $f3->get('PATH'));
		$f3->set('menu_all_users', $menu_all_users);
		
		$menu_admin_users = array();
		if ($f3->get('user')->isadmin) {
			$menu_admin_users[] = array('path' => '/admin/games', 'label' => 'Games', 'active' => preg_match('/^\/admin\/games/', $f3->get('PATH')) == 1);
			$menu_admin_users[] = array('path' => '/admin/teams', 'label' => 'Teams', 'active' => preg_match('/^\/admin\/teams/', $f3->get('PATH')) == 1);
			$menu_admin_users[] = array('path' => '/admin/pickem', 'label' => 'Pickem Stuff', 'active' => preg_match('/^\/admin\/pickem/', $f3->get('PATH')) == 1);
		}
		$f3->set('menu_admin_users', empty($menu_admin_users) ? FALSE : $menu_admin_users);
	}
}
