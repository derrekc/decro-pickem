<?php

class DashboardController extends Controller {
	function beforeroute($f3) {
		// prepare success_msg, warning_msg, error_msg HIVE variables
		$f3->set('success_msg', array());
		$f3->set('warning_msg', array());
		$f3->set('error_msg', array());
		$f3->set('info_msg', array());
	
		$path = $f3->get('PATH');
		if (preg_match('/user\/(create|register)/', $path)) {
			return;
		}	
		
		if (!$f3->exists('SESSION.user_id')) {
			$f3->reroute('/login');
			return;
		}
		parent::beforeroute($f3);
		
		$this->prepareUserMenu($f3);
	}

	function home($f3, $args) {
		$q = "SELECT * FROM game WHERE week = :week AND hide_from_pickem = 0 ORDER BY event_date";
		$pickem_slate = $this->db->exec($q, array(':week' => $f3->get('pickem.current_week')));

		$f3->set('pickem_slate', $pickem_slate);
		$f3->set('inc', 'overview.htm');
	}
	
	public function do_render($f3) {
		
		// TODO deprecate soon
		$f3->clear('success_message');
		$f3->clear('warning_message');
		$f3->clear('error_message');
				
		if ($f3->exists('SESSION.success_message')) {
			$f3->set('success_message', $f3->get('SESSION.success_message'));
			$f3->clear('SESSION.success_message');
		}
		
		if ($f3->exists('SESSION.warning_message')) {
			$f3->set('warning_message', $f3->get('SESSION.warning_message'));
			$f3->clear('SESSION.warning_message');
		}
		
		if ($f3->exists('SESSION.error_message')) {
			$f3->set('error_message', $f3->get('SESSION.error_message'));
			$f3->clear('SESSION.error_message');
		}
		
		$f3->clear('success_msg');
		$f3->clear('warning_msg');
		$f3->clear('error_msg');
		$f3->clear('info_msg');
				
		if ($f3->exists('SESSION.success_msg')) {
			$f3->set('success_msg', $f3->get('SESSION.success_msg'));
			$f3->clear('SESSION.success_msg');
		}
		
		if ($f3->exists('SESSION.warning_msg')) {
			$f3->set('warning_msg', $f3->get('SESSION.warning_msg'));
			$f3->clear('SESSION.warning_msg');
		}
		
		if ($f3->exists('SESSION.error_msg')) {
			$f3->set('error_msg', $f3->get('SESSION.error_msg'));
			$f3->clear('SESSION.error_msg');
		}
		
		if ($f3->exists('SESSION.info_msg')) {
			$f3->set('info_msg', $f3->get('SESSION.info_msg'));
			$f3->clear('SESSION.info_msg');
		}
		echo Template::instance()->render('dashboard.htm');	
	}
	
	//! Instantiate class
	function __construct() {
		parent::__construct();
	}
	
	public function prepareUserMenu($f3) {
		$menu_all_users = array();
		$menu_all_users[] = array('path' => '/', 'label' => 'Overview', 'active' => '/' == $f3->get('PATH'));
		$menu_all_users[] = array('path' => '/picks', 'label' => 'Picks', 'active' => '/picks' == $f3->get('PATH'));
		$menu_all_users[] = array('path' => '/week-standings', 'label' => 'Standings (Week)', 'active' => '/week-standings' == $f3->get('PATH'));
		//$menu_all_users[] = array('path' => '/standings', 'label' => 'Standings (Overall)', 'active' => '/standings' == $f3->get('PATH'), 'disabled' => TRUE);
		$menu_all_users[] = array('path' => '/players', 'label' => 'Players', 'active' => '/players' == $f3->get('PATH'));
		$f3->set('menu_all_users', $menu_all_users);
		
		$menu_admin_users = array();
		if ($f3->get('user')->isadmin) {
			$menu_admin_users[] = array('path' => '/admin/games', 'label' => 'Manage Games', 'active' => preg_match('/^\/admin\/games/', $f3->get('PATH')) == 1);
			$menu_admin_users[] = array('path' => '/admin/teams', 'label' => 'Manage Teams', 'active' => preg_match('/^\/admin\/teams/', $f3->get('PATH')) == 1);
			$menu_admin_users[] = array('path' => '/admin/pickem', 'label' => 'Pickem Stuff', 'active' => preg_match('/^\/admin\/pickem(\/)?$/', $f3->get('PATH')) == 1);
			$menu_admin_users[] = array('path' => '/admin/pickem/players', 'label' => 'Player Status', 'active' => preg_match('/^\/admin\/pickem\/players/', $f3->get('PATH')) == 1);
			$menu_admin_users[] = array('path' => '/admin/importpoll', 'label' => 'Import AP Poll', 'active' => preg_match('/^\/admin\/importpoll/', $f3->get('PATH')) == 1);
		}
		$f3->set('menu_admin_users', empty($menu_admin_users) ? FALSE : $menu_admin_users);
	}

	//! Custom error page
	function error($f3) {
		$content = $f3->get('ERROR.text') . '<br /><br />';
		#echo $f3->get('ERROR.text') . '<br /><br />';
		
		foreach ($f3->get('ERROR.trace') as $frame) {
			$content .= '<div>' . print_r($frame, true) . "</div><br />";
		}
		$f3->set('content', $content);
		/*
		$log=new Log('error.log');
		$log->write($f3->get('ERROR.text'));
		foreach ($f3->get('ERROR.trace') as $frame)
			if (isset($frame['file'])) {
				// Parse each backtrace stack frame
				$line='';
				$addr=$f3->fixslashes($frame['file']).':'.$frame['line'];
				if (isset($frame['class']))
					$line.=$frame['class'].$frame['type'];
				if (isset($frame['function'])) {
					$line.=$frame['function'];
					if (!preg_match('/{.+}/',$frame['function'])) {
						$line.='(';
						if (isset($frame['args']) && $frame['args'])
							$line.=$f3->csv($frame['args']);
						$line.=')';
					}
				}
				// Write to custom log
				$log->write($addr.' '.$line);
			}
		*/
		#$f3->set('inc','error.htm');
	}
}
