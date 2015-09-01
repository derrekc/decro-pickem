<?php

class WeekSelect
 {
	
	static public function renderWeekSelect() {
		$f3 = \Base::instance();

    $week = $f3->exists('REQUEST.week') ?
        preg_replace("/[^0-9]/", "", $f3->get('REQUEST.week')) : $f3->get('pickem.current_week');
				
		$select_weeks = array();
		$weeks = $f3->get('db')->exec('SELECT * FROM week WHERE pickem_id = ?', $f3->get('pickem.default_pickem_id'));
		foreach ($weeks as $w) {
			
			if ($week == $w['week']) {
				$w['active'] = TRUE;
			}
			$select_weeks[] = $w;			
		}
		$f3->set('select_weeks', $select_weeks);
		$f3->set('active_week', $week);
		
		$view = new View();
		$render = $view->render('widgets/week_dropdown.htm');
		
		return $render;
	}
}
