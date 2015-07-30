<?php

//! Base controller
abstract class Controller {

	protected
		$db;
	
	protected
		$default_template = 'layout.htm';
		
	protected
		$use_json = FALSE;
		
	abstract protected function do_render($f3);
		
	//! HTTP route pre-processor
	function beforeroute($f3) {		
		if ($f3->exists('REQUEST.format') && $f3->get('REQUEST.format' == 'json')) {
			$this->use_json = TRUE;
			header('Content-type: application/json', TRUE);
		}

		$db = $this->db;
		$user = new \DB\SQL\Mapper($db, 'users');
		$user->load(array('name=?', $f3->get('SESSION.user_id')));
		$f3->set('user', $user);

		// $db=$this->db;
		// Prepare user menu
		//$f3->set('menu',
		//	$db->exec('SELECT slug,title FROM pages ORDER BY position;'));
	}

	//! HTTP route post-processor
	function afterroute($f3) {
		// Render HTML layout
		if ($this->use_json === TRUE) {
			return;
		}

		$f3->set('error_message', false);
		if ($f3->exists('SESSION.error_message')) {
			$f3->set('error_message', $f3->get('SESSION.error_message'));
		}

		$f3->clear('SESSION.error_message');
		$this->do_render($f3);
	}
	
	//! Instantiate class
	function __construct() {
		$f3=Base::instance();
		// Connect to the database
		$db=new DB\SQL($f3->get('db.0'), $f3->get('db.1'), $f3->get('db.2'));
		if (file_exists('setup.sql')) {
			// Initialize database with default setup
			$db->exec(explode(';',$f3->read('setup.sql')));
			// Make default setup inaccessible
			rename('setup.sql','setup.$ql');
		}
		// Use database-managed sessions
		new DB\SQL\Session($db);
		
		// Save frequently used variables
		$this->db=$db;
	}

}