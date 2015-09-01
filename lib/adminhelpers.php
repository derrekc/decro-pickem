<?php

class AdminHelpers {
	
	public static function notify_new_user($user) {
		$f3 = \Base::instance();
		
		if ($f3->exists('db')) {
			
		}
		
		$msg = "The following Pick'em account has been created: " . $user->name . "\n" .
			"The associated email address is: " . $user->mail;
			
		$headers = 'From: derrek@decro.net <Derrek Croney>';
		$ret = mail('godecro@gmail.com', "New ACCbbs Pick'em Account Created", $msg, $headers);
	}
}
