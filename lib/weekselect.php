<?php

class WeekSelect
 {
	
	protected $all_weeks;
	private $target_path;
	private $show_all;
	private $show_all_label;
	private $route_arg;
	protected $template = 'widgets/week_btngroup.htm';
	
	public function __construct($pickem_id, $target_path, $route_arg=FALSE, $show_all=TRUE, $show_all_label="All Weeks") {
		$f3 = \Base::instance();
		if (!$f3->exists('db', $db)) {
			return;
		}

		// load weeks and select current week
		$res = $db->exec(
			"SELECT * FROM week WHERE pickem_id = :pickem_id 
			 ORDER BY start",
			 array(':pickem_id' => $pickem_id)
		);
		$this->all_weeks = $res;
		
		$this->route_arg = $route_arg;
		$this->show_all = $show_all;
		$this->show_all_label = $show_all_label;
		
		$f3->set('ws_current_week', $f3->get('pickem.current_week'));
		$f3->set('ws_default_week', $f3->get('pickem.current_week'));
		$this->target_path = $target_path;
	}
	
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
	
	public static function renderButtonGroup($args) {
		$attr = $args['@attrib'];
		$tmp = \Template::instance();
		$f3 = \Base::instance();
		
		$token_path = !empty($tmp->token($attr['path'])) ? $tmp->token($attr['path']) : $f3->get('PATH');
		$token_route_arg = !empty($tmp->token($attr['route_arg'])) ? $tmp->token($attr['route_arg']) : 'FALSE';
		$token_show_all = !empty($tmp->token($attr['show_all'])) ? $tmp->token($attr['show_all']) : 'TRUE';
		$token_show_all_label = !empty($tmp->token($attr['show_all_label'])) ? $tmp->token($attr['show_all_label']) : "All Weeks";
		$code = array();
		$code[] = '$ws = new \WeekSelect('.$tmp->token($attr['pickem_id']).',"'.$token_path.'",'.$token_route_arg.','.$token_show_all.',"'.$token_show_all_label.'");';
		error_log($code[0]);

		$current_week = isset($attr['current_week']) ? $tmp->token($attr['current_week']) : 'FALSE';
		$code[] = 'echo $ws->renderButtons(' . $current_week . ');';
		return '<?php ' . join(' ', $code) . '?>';
	}
	
	public function renderButtons($current_week) {
		// Assume the URL will be the current one with a 'week' param added
		if (empty($this->all_weeks)) {
			return '';
		}
		
		$f3 = \Base::instance();
		$f3->set('all_weeks', $this->all_weeks);
		$f3->set('ws_current_week', $current_week);
		$f3->set('target_path', $this->target_path);
		$f3->set('show_all', $this->show_all);
		$f3->set('show_all_label', $this->show_all_label);
		$f3->set('route_arg', $this->route_arg);
		$output = \Template::instance()->render($this->template);
		return $output;
	}
}
