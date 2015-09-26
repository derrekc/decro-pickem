<?php

namespace Dashboard\Model;

class Games {
	
	public static function team_appearances() {
		$f3 = \Base::instance();
		if (!$f3->exists('db')) {
			return;
		}
		
		$appearances = $f3->get('db')->exec(
			"
			SELECT count(*) as appearances, team.displaytitle as displaytitle, team_name
			FROM
			(SELECT host_team_name as team_name
			 FROM game
			 WHERE hide_from_pickem = 0 and host_team_name is not null
			
			 UNION ALL
			
			 SELECT visiting_team_name as team_name
			 FROM game
			 WHERE hide_from_pickem = 0 and visiting_team_name is not null
			) AS ta 
			
			LEFT JOIN team on ta.team_name = team.name
			WHERE team_name NOT IN ('coastal', 'atlantic', 'east', 'west')
			GROUP BY team.displaytitle
			ORDER BY team.displaytitle ASC
			"
		);
		
		return $appearances;
	}
}
