<?php

//! Front-end processor
class LoginController extends Controller {
	
	function beforeroute($f3) {
		$this->prepareUserMenu($f3);	
	}
	
	function afterroute($f3) {
		parent::afterroute($f3);
	}
	
	public function do_render($f3) {
		echo "do render\n";
		echo Template::instance()->render('dashboard.htm');			
	}

	//! Display login form
	function login($f3) {
		$f3->clear('SESSION');
		if ($f3->get('eurocookie')) {
			$loc=Web\Geo::instance()->location();
			if (isset($loc['continent_code']) && $loc['continent_code']=='EU')
				$f3->set('message',
					'The administrator pages of this Web site uses cookies '.
					'for identification and security. Without these '.
					'cookies, these pages would simply be inaccessible. By '.
					'using these pages you agree to this safety measure.');
		}
		$f3->set('COOKIE.sent',TRUE);
		if ($f3->get('message')) {
			$img=new Image;
			$f3->set('captcha',$f3->base64(
				$img->captcha('fonts/thunder.ttf',18,5,'SESSION.captcha')->
					dump(),'image/png'));
		}
		#echo Template::instance()->render('login.htm');
		$q = "SELECT * FROM game WHERE week = :week AND hide_from_pickem = 0 ORDER BY event_date";
		$pickem_slate = $this->db->exec($q, array(':week' => $f3->get('pickem.current_week')));
		
		$content = Template::instance()->render('login.htm');
		$f3->set('content', $content);
		$f3->set('pickem_slate', $pickem_slate);
		$f3->set('inc', 'overview.htm');
	}

	//! Process login form
	function auth($f3) {
		if (!$f3->get('COOKIE.sent'))
			$f3->set('message','Cookies must be enabled to enter this area');
		else {
			$crypt = md5($f3->get('POST.password'));
			$username = $f3->get('POST.user_id');
			
			$db = $this->db;
			$user = new \DB\SQL\Mapper($db, 'users');
			$auth = new \Auth($user, array('id' => 'name', 'pw' => 'pass'));
			
			$captcha=$f3->get('SESSION.captcha');
			
			if ($captcha && strtoupper($f3->get('POST.captcha'))!=$captcha) {
				$f3->set('message','Invalid CAPTCHA code');
			} elseif ($auth->login($username, $crypt) === FALSE) {
				$f3->set('message', 'Invalid user ID or password');
			} else {
				$user->load(array('name=?',$f3->get('POST.user_id')));
				$f3->clear('COOKIE.sent');
				$f3->clear('SESSION.captcha');

				$f3->set('SESSION.user_id',$f3->get('POST.user_id'));
				$f3->set('SESSION.crypt',$crypt);
				$f3->set('SESSION.lastseen',time());
				
				$user->visits++;
				$user->save();
				
				# force a password change if need be
				if ($user->changepass == '1') {
					$f3->reroute('@change_pass_form(@uid='.$user->uid.')');
					return;
				}
				
				$f3->reroute('@welcome');
				return;
			}
		}
		$this->login($f3);
	}

	function twitter_signin($f3) {
	}
	
	function oauth($f3) {
		
	}
	
	//! Terminate session
	function logout($f3) {
		$f3->clear('SESSION');
		$f3->reroute('/login');
	}
	
	public function prepareUserMenu($f3) {
		$menu_all_users = array();
		$menu_all_users[] = array('path' => '/', 'label' => 'Overview', 'active' => '/' == $f3->get('PATH'));
		$f3->set('menu_all_users', $menu_all_users);
	}

}
