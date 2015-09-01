<?php

class PickemSlateHelper {
	
	public static function game_is_open(stdClass $pickem_game, $first_saturday_kickoff, $user = FALSE) {
		$now = time();
		$f3 = \Base::instance();
		
		$day = strtolower(date('l', $pickem_game->event_date));
		$cutoff_seconds = 60 * $f3->get('pickem.pick_cutoff_minutes.' . $day);
		
		//if ($user) {
		//	if ($day == 'saturday') {
		//		error_log(sprintf("First Saturday Kick = [%s]", $first_saturday_kickoff));
		//		error_log(print_r(array('user' => $user->name, 'delta' => ($first_saturday_kickoff - $now), 'cutoff' => $cutoff_seconds), TRUE));
		//	} else {
		//		error_log(print_r(array('user' => $user->name, 'delta' => ($pickem_game->event_date - $now), 'cutoff' => $cutoff_seconds), TRUE));				
		//	}	
		//}
		
		if ($day == 'saturday') {
			return ($first_saturday_kickoff - $now) > $cutoff_seconds;
		}
		return ($pickem_game->event_date - $now) > $cutoff_seconds; 
	}
	
	public static function cutoff_time(stdClass $pickem_game, $first_saturday_kickoff) {
		$now = time();
		$date_format = 'M j @ g:i A';
		
		$f3 = \Base::instance();
		
		$day = strtolower(date('l', $pickem_game->event_date));
		$cutoff_seconds = 60 * $f3->get('pickem.pick_cutoff_minutes.' . $day);
		
		if ($day == 'saturday') {
			return date($date_format, $first_saturday_kickoff - $cutoff_seconds);
		}
		return date($date_format, $pickem_game->event_date - $cutoff_seconds); 
	}
}
