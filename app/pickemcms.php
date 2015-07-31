<?php

//! Front-end processor
class PickemCMS extends Controller {

	//! Process comment form
	function comment($f3) {
		$slug=($f3->get('POST.slug')?:'');
		if (!$f3->exists('POST.name') || !strlen($f3->get('POST.name')))
			$f3->set('message','Name is required');
		elseif (!$f3->exists('POST.email') ||
			!strlen($email=$f3->get('POST.email')) ||
			!Audit::instance()->email($email))
			$f3->set('message','Invalid e-mail address');
		elseif (!$f3->exists('POST.contents') ||
			!strlen($f3->get('POST.contents')))
			$f3->set('message','Comment cannot be blank');
		else {
			$db=$this->db;
			$comment=new DB\SQL\Mapper($db,'comments');
			$comment->copyfrom('POST');
			$img=new Image;
			$comment->set('identicon',
				$f3->base64($img->identicon($f3->get('POST.email'),48)->
				dump(),'image/png'));
			$comment->set('slug',$slug);
			$comment->set('posted',time());
			$comment->save();
			$f3->reroute('/'.$slug);
		}
		$args=array('slug'=>$slug);
		$this->show($f3,$args);
	}

	//! Custom error page
	function error($f3) {
		echo $f3->get('ERROR.text') . '<br /><br />';
		
		foreach ($f3->get('ERROR.trace') as $frame) {
			echo '<div>' . print_r($frame, true) . "</div><br />";
		}
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

	function afterroute($f3) {}
	
	public function do_render($f3) {
		
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
		echo Template::instance()->render('login.htm');
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
			} elseif ($auth->login($username, $crypt)) {
				$f3->set('message', 'Invalid user ID or password');
			} else {
				$f3->clear('COOKIE.sent');
				$f3->clear('SESSION.captcha');

				$f3->set('SESSION.user_id',$f3->get('POST.user_id'));
				$f3->set('SESSION.crypt',$crypt);
				$f3->set('SESSION.lastseen',time());
				$f3->reroute('@welcome');
			}
		}
		$this->login($f3);
	}

	//! Terminate session
	function logout($f3) {
		$f3->clear('SESSION');
		$f3->reroute('/login');
	}

}
