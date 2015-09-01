<?php

class AdminController extends DashboardController {

	//! HTTP route pre-processor
	function beforeroute($f3) {
		parent::beforeroute($f3);
		
		if ($user->isadmin == 'N') {
			$f3->set('SESSION.error_message', 'You do not have permission to visit this section');
			$f3->reroute('/');
		}
	}

	//! HTTP route post-processor
	function afterroute($f3) {
		// Render HTML layout
		// 
		$f3->set('use_footer', TRUE);
		parent::afterroute($f3);
	}

	public function do_render($f3) {
		//echo \Template::instance()->render('dashboard.htm');
		parent::do_render($f3);
	}
	
	//! Instantiate class
	function __construct() {
		parent::__construct();
		$this->default_template = 'dashboard.htm';
	}
	
	function home($f3, $args) {
		$f3->set('inc', 'admin.htm');
	}
}
