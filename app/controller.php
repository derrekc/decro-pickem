<?php

//! Base controller
abstract class Controller {

	protected
		$db;
	
	protected
		$default_template = 'layout.htm';
		
	protected
		$use_json = FALSE;
		
	protected
		$user = FALSE;
		
	abstract protected function do_render($f3);
	abstract protected function prepareUserMenu($f3);
		
	//! HTTP route pre-processor
	function beforeroute($f3) {		
		if ($f3->exists('REQUEST.format') && $f3->get('REQUEST.format' == 'json')) {
			$this->use_json = TRUE;
			header('Content-type: application/json', TRUE);
		}

		$db = $this->db;
		
		$user = FALSE;
		if ($f3->exists('SESSION.user_id')) {
			$user = new \DB\SQL\Mapper($db, 'users');
			$user->load(array('name=?', $f3->get('SESSION.user_id')));
		} else {
			$user = FALSE;
		}
		$f3->set('user', $user);
		$this->user = $user;
	}

	//! HTTP route post-processor
	function afterroute($f3) {
		// Render HTML layout
		if ($this->use_json === TRUE) {
			return;
		}
		$this->do_render($f3);
	}
	
	//! Instantiate class
	function __construct() {
		$f3=Base::instance();
		// Connect to the database
		$db = new DB\SQL($f3->get('db.0'), $f3->get('db.1'), $f3->get('db.2'));
		$f3->set('db', $db);
		if (file_exists('setup.sql')) {
			// Initialize database with default setup
			$db->exec(explode(';',$f3->read('setup.sql')));
			// Make default setup inaccessible
			rename('setup.sql','setup.$ql');
		}
		// Use database-managed sessions
		$this->s = new DB\SQL\Session($db);
		
		// Save frequently used variables
		$this->db=$db;
	}

}
