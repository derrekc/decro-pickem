<?php

class UserController extends \AdminController {
	
	protected
		$reset_pass_value = 'changeme';
		
  /**
   * reset a user's password and force a password change
   * @param $f3 object
   */
	public function reset_user_pass($f3, $args) {
		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('uid=?', $args['uid']));
		if (!$user->dry()) {
			$user->pass = md5($this->reset_pass_value);
			$user->changepass = 1;
			$user->save();
			$f3->reroute('@welcome');
		}
	}
}
