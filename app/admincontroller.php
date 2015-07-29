<?php

class AdminController extends Controller {

	//! HTTP route pre-processor
	function beforeroute($f3) {
		parent::beforeroute($f3);
		if (!$f3->exists('SESSION.user_id')) {
			$f3->reroute('/login');
			return;
		}

		$db = $this->db;
		$user = new \DB\SQL\Mapper($db, 'users');
		$user->load(array('name=?', $f3->get('SESSION.user_id')));
		$f3->set('user', $user);
		
		if ($user->isadmin == 'N') {
			$f3->set('SESSION.error_message', 'You do not have permission to visit this section');
			$f3->reroute('/');
		}
	}

	//! HTTP route post-processor
	function afterroute($f3) {
		// Render HTML layout
		// 
		parent::afterroute($f3);
	}

	public function do_render($f3) {
		echo \Template::instance()->render('dashboard.htm');
	}
	
	//! Instantiate class
	function __construct() {
		parent::__construct();
		$this->default_template = 'dashboard.htm';
	}
	
	function home($f3) {
		$f3->set('inc', 'admin.htm');
	}
}
