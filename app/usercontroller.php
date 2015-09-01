<?php

class UserController extends DashboardController {
	
	public function beforeroute($f3) {
		parent::beforeroute($f3);	
		
		if ($f3->exists('REQUEST.uid', $uid)) {
			$edit_user = new \DB\SQL\Mapper($this->db, 'users');
			$edit_user->load(array('uid=?', $uid));
			$f3->set('edit_user', $edit_user);
		}
		
		$f3->set('team_chooser', new \TeamChooser($f3->get('pickem.default_conf_name')));
	}
	
	public function home($f3, $args) {
		$uid = FALSE;
		if (!$f3->exists('edit_user.uid', $uid)) {
			$uid = $f3->get('user')->uid;
		}
		if ($uid == FALSE) {
			return;
		}
		
		if ($f3->exists('SESSION.user_error_msg', $user_error_msg)) {
			$f3->set('user_error_msg', $user_error_msg);
			$f3->clear('SESSION.user_error_msg');
		}
		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('uid=?', $uid));
		
		$schools = array();		
		$res = $this->db->exec("SELECT * FROM team WHERE conf_name = ? ORDER BY displaytitle", 'acc');
		foreach ($res as $r) {
			$schools[] = $r;
		}
		
		$f3->set('edit_user', $user);
		$f3->set('team_chooser', $f3->get('team_chooser')->render($user->favorite_team_name));
		$f3->set('schools', $schools);
		$f3->set('inc', 'userprofile.htm');
	}
	
	public function register_form($f3) {
		$img=new Image;
		$f3->set('captcha',$f3->base64(
			$img->captcha('fonts/thunder.ttf',18,5,'SESSION.captcha')->
				dump(),'image/png'));
		
		$f3->set('team_chooser', $f3->get('team_chooser')->render(NULL));
		$f3->set('inc', 'userreg.htm');
	}

	/**
	 * Default password
	 */
	public function change_password_form($f3, $args) {
		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('uid=?',$args['uid']));
		$f3->set('edit_user', $user);
		$f3->set('SESSION.csrf', $this->s->csrf());		
		$f3->set('inc', 'user_change_pass.htm');	
	}	
	
	public function change_password_submit($f3) {
		if (!$f3->exists('SESSION.csrf', $csrf)) {
			$f3->set('SESSION.warning_message', 'No security token found');
			$f3->reroute('@welcome');
			return;
		}
		if ($csrf != $f3->get('POST.token')) {
			$f3->set('SESSION.warning_message', 'Security Token did not match.');
			$f3->reroute('@welcome');
		}
		
		extract($f3->get('POST'));
		
		if ($pass != $pass_verify) {
			$f3->set('error_message', 'The passwords do not match');
			$f3->set('inc', 'user_change_pass.htm');
		} else {
			$user = $f3->get('edit_user');
			$user->pass = md5($pass);
			$user->changepass = 0;
			$user->status = 1;
			$user->save();
			$f3->set('SESSION.success_message', 'Password successfully changed.');
			$f3->reroute('@welcome');
		}
	}
	
	public function create($f3) {
		$captcha=$f3->get('SESSION.captcha');
		if (!$f3->exists('POST', $POST)) {
			$f3->reroute('@welcome');
		}
					
		if ($captcha && strtoupper($f3->get('POST.captcha'))!=$captcha) {
			$f3->push('SESSION.error_msg', 'Your captcha value is off -- please try again.');
			$f3->reroute('@user_register');
			return;
		}

		$f3->scrub($POST['user_id'],'');		
		$f3->scrub($POST['mail'],'');		
		
		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('name=?', $POST['user_id']));
		if (!$user->dry()) {
			$f3->push('SESSION.error_msg', 
				sprintf("A user with this User ID, <strong><em>%s</em></strong>, is already registered.  Please a different User ID.", $POST['user_id']));
			$f3->reroute('@user_register');	
			return;		
		}
		$user->load(array('mail=?', $POST['mail']));
		if (!$user->dry()) {
			$f3->push('SESSION.error_msg', sprintf("A user with the email address, <strong><em>%s</em></strong>, is already registered.  Please a different address.", $POST['mail']));
			$f3->reroute('@user_register');	
			return;		
		}

		if ($POST['pass'] != $POST['pass_verify']) {
			$f3->push('SESSION.error_msg', 'Passwords do not match.  Please check them and re-submit.');
			$f3->reroute('@user_register');	
			return;		
		}
		
		$user->reset();
		$user->name = $POST['user_id'];
		$user->mail = $POST['mail'];
		$crypt = md5($POST['pass']);
		$user->pass = $crypt;
		$user->status = 1;
		$user->visits = 1;
		$user->favorite_team_name = $POST['favorite_team_name'];
		$user->created = time();
		$user->save();
		\AdminHelpers::notify_new_user($user);

		$f3->set('SESSION.user_id',$POST['user_id']);
		$f3->set('SESSION.crypt',$crypt);
		$f3->set('SESSION.lastseen',time());
		$f3->reroute('@current_slate');				
	}
	
	public function save($f3) {
		$f3->clear('SESSION.user_error_msg');
		$uid = FALSE;
		if (!$f3->exists('POST.uid', $uid)) {
			$uid = $f3->get('user')->uid;
		}
		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('uid=?', $uid));
		
		if ($f3->exists('POST', $POST)) {
			if (!empty($POST['pass']) && !empty($POST['new_pass']) && !empty($POST['verify_pass'])) {
				$current_pass = $POST['pass'];
				if ($user->pass != md5($current_pass)) {
					$f3->set('user_error_msg', 'Please enter your correct password!');
					$f3->set('edit_user', $user);
					$f3->set('inc', 'userprofile.htm');
					return;
				}
				
				$new_pass = $POST['new_pass'];
				$verify_pass = $POST['verify_pass'];
				if($new_pass != $verify_pass) {
					$f3->set('user_error_msg', 'Your passwords do not match!');
					$f3->set('edit_user', $user);
					$f3->set('inc', 'userprofile.htm');
					return;
				}
			}
		}

		if ($f3->exists('FILES.file_upload', $upload_file)) {
			if ($upload_file['error'] != UPLOAD_ERR_NO_FILE) {
				$fname = $upload_file['name'];
				$tmpname = $upload_file['tmp_name'];
				$ftype = $upload_file['type'];
				if (in_array($ftype, $f3->get('allowed')) === FALSE) {
					$f3->set('user_error_msg', 'Please choose a JPEG, GIF or PNG.');
					$f3->set('edit_user', $user);
					$f3->set('team_chooser', $f3->get('team_chooser')->render($user->favorite_team_name));
					$f3->set('inc', 'userprofile.htm');
					return;				
				}
				if (is_uploaded_file($upload_file['tmp_name'])) {
					$user->picture = $upload_file['name'];
				}
				
				if (move_uploaded_file($upload_file['tmp_name'], sprintf("%s/%s/avatars/%s", $f3->get('ROOT'), $f3->get('UPLOADS'), $fname))) {
					#$f3->reroute('@user_profile(@uid=' . $user->uid . ')');
					#$f3->set("SESSION.success_message", 'Your changes have been saved.  Good job!');
				} else {
					
				}
			}
		}
				
		$user->favorite_team_name = $POST['favorite_team_name'] == '' ? NULL : $POST['favorite_team_name'];
		$user->save();
		$f3->set("SESSION.success_message", 'Your changes have been saved.  Good job!');
		$f3->reroute('@user_profile(@uid=' . $user->uid . ')');
		/*
		if ($f3->exists('FILES', $FILES)) {
			$file_upload = $FILES['file_upload'];
			if ($file_upload['error'] > 0) {
				if ($file_upload['error'] == UPLOAD_ERR_INI_SIZE)
				$f3->set('user_error_msg', 'Please specify a smaller file for your avatar.');
				$f3->set('edit_user', $user);
				$f3->set('inc', 'userprofile.htm');
				return;				
			}
		}
		*/
		#header("Content-type: application/json", TRUE);
		#$this->use_json = TRUE;
		
		#echo json_encode(file_upload_max_size());
		#echo json_encode($f3->get('POST'));
		#echo json_encode($f3->get('FILES'));
		#echo json_encode(array($f3->get('ROOT')));
	}
}
