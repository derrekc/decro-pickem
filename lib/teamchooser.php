<?php

class TeamChooser {
	
	protected
		$default_conf_name;
		
	function __construct($default_conf_name = NULL) {
		$this->default_conf_name = $default_conf_name;
	}
	
	function render($selected=NULL, $el_id = 'favoriteTeam') {
		$f3 = \Base::instance();
		
		$options = array();
		
		if ($this->default_conf_name != NULL) {
			$res = $f3->get('db')->exec("SELECT * FROM team WHERE conf_name = ? ORDER BY displaytitle", $this->default_conf_name);
			foreach ($res as $r) {
				$entry = array('value' => $r['name'], 'label' => $r['displaytitle'], 'selected' => ($selected == $r['name']));
				$options[] = $entry;
			}
			$options[] = array('value' => '', 'label' => '-----', 'selected' => FALSE);
		}
		$query = sprintf(
			"SELECT * FROM team%s ORDER BY displaytitle",
			($this->default_conf_name != NULL) ? ' WHERE conf_name <> \'' . $this->default_conf_name . '\'' : '' );
			
		$res = $f3->get('db')->exec($query);
		foreach ($res as $r) {
			$entry = array('value' => $r['name'], 'label' => $r['displaytitle'], 'selected' => ($selected == $r['name']));
			$options[] = $entry;
		}

		
		$return = '<SELECT class="form-control" id="'. $el_id . '" name="favorite_team_name"><option value=""> -- Choose --</option>';
		foreach ($options as $opt) {
			$return .= sprintf("<option%s value='%s'>%s</option>", ($opt['selected'] ? ' SELECTED' : ''), $opt['value'], $opt['label']);
		}
		$return .= '</SELECT>';

		return $return;
	}
}
