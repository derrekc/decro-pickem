<?php 

namespace Dashboard;

class UserProfileMessage implements \Dashboard\PluginInterface {
	
	public function doHook($hook_name) {
		$f3 = \Base::instance();
		
		if (!$f3->exists('user', $user)) {
			return;
		}

		if ($hook_name == 'add_info_message') {
			if ($user->favorite_team_name == '') {
				$message = \Template::instance()->render('userprofile/favorite_team.htm');
				$f3->push('SESSION.info_msg', $message);
			}
			
			$message = \Template::instance()->render('userprofile/default_pick_team.htm');
			$f3->push('SESSION.info_msg', $message);
		}
	}
}
