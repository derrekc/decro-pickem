<?php
namespace CLI;

// 50 items per page.
define('TOTAL_SPORTSEVENT_PER_PAGE', 50);

class SportsEvent {
	
	static $tvNetwork = array(
		'espn' => 'ESPN',
		'espn2' => 'ESPN2',
		'espn3' => 'ESPN3',
		'espnu' => 'ESPNU',
		'espnews' => 'ESPNews',
		'espnnew' => 'ESPNews',
		'accn' => 'ACC Network',
		'fs1' => 'Fox Sports 1',
		'fs2' => 'Fox Sports 2',
		'fox' => 'Fox',
		'fsn' => 'Fox Sports Network',
		'rsn' => 'ACC RSN',
		'abc' => 'ABC',
		'cbs' => 'CBS',
		'cbssn' => 'CBS Sports Network',
		'nbc' => 'NBC',
		'mwc' => 'Mountain West Network',
		'sec' => 'SEC Network',
		'secn' => 'SEC Network',
		'btn' => 'B1G Network',
		'pac12' => 'PAC 12 Network',
	);
	
	static $ncaaScoreboardURLBase = array(
		'ncaaf' => 'http://www.ncaa.com/scoreboard/football/fbs/2015',
		'ncaam' => 'http://www.ncaa.com/scoreboard/basketball-men/d1',
	);
	
	public static function scoreboardUrls($sport, $week, $week_start, $game_date=FALSE, $game_date_start=FALSE) {
		$urls = array();
		$url_base = SportsEvent::$ncaaScoreboardURLBase[$sport];

		if ($sport == 'ncaaf') {
			if ($week_start) {
				for($i = (int)$week_start; $i <= (int)$week; $i++) {
					$new_url = $url_base . '/' . sprintf("%02d", $i);
					$urls[] = $new_url;
				}
			}
			else if ($week) {
				$urls[] = $url_base . '/' . sprintf("%02d", (int)$week);
			}
		}
		if ($sport == 'ncaam') {
			if ($game_date_start) {
				$t = $game_date_start;
				while ($t <= $game_date) {
					$urls[] = $url_base . '/' . date('Y/m/d', $t);
					$t += 86400;
				}
			}
			else if ($game_date) {
				$urls[] = $url_base . '/' . date('Y/m/d', $game_date);
			}
		}
		return $urls;
	}
	
	static $sport = array(
		'ncaaf' => 'College Football',
		'ncaam' => 'Men\'s College Basketball',
		'ncaaw' => 'Women\'s College Basketball',
	);
	
	public static function tvNetworkByAbbr($abbr) {
		$match = array();
		$pieces = array();
		
		preg_match('/^(?P<network1>\w+)((\sor\s)(?P<network2>\w+))?$/', $abbr, $match);
		
		$pieces[] = self::$tvNetwork[ $match['network1'] ];
		if (isset($match['network2']) && !empty($match['network2'])) {
			$pieces[] = self::$tvNetwork[ $match['network1'] ];
		}
		return implode(' or ', $pieces);
	}
	
	public function __construct() {
	}
	
	public function completed() {
		return in_array($this->completed, array('Y', 'y'));
	}

	public function title() {
		return $this->displayTitle();
	}

	public function getDisplayTitle() {
		return $this->displayTitle();
	}
	
	public function displayTitle() {
		# build a string
		$display_title = '';
		$display_title .= !empty($this->visiting_team_name) ? $this->visiting_team->displaytitle : 'TBA';
		$display_title .= ' ' . ('Y' ? 'vs.' : 'at') . ' ';
		$display_title .= !empty($this->host_team_name) ? $this->host_team->displaytitle : 'TBA';
		$display_title .= !empty($this->title) ? ' | (' . $this->title . ')' : '';
		return $display_title;
		
		/*
		if (!empty($this->title) && (empty($this->visiting_team_name) && empty($this->host_team_name))) {
			return $this->title;
		}
		if ($this->visiting_team && $this->host_team) {
		return sprintf('%s %s %s',
			$this->visiting_team->displaytitle,
			$this->neutral == 'Y' ? 'vs.' : 'at',
			$this->host_team->displaytitle
			);
		}
		return '';
		*/
	}
	
	public function tweetScoreMessageForPopup() {
		return sprintf("@sportsched %s %s|%s, 00-00", $this->twitter_hashtag, $this->visiting_team->name, $this->host_team->name);
	}
	
	public function displayTitleWithLines() {
		if ($this->completed()) {
			return $this->eventResult('long');
		}

		$str = '';
		if (isset($this->visiting_team->rank[$this->week])) {
			$str .= '<span class="poll-rank">' . '#' . $this->visiting_team->rank[$this->week] . '</span> ';
		}
		$str .= $this->visiting_team->displaytitle;
		$str .= $this->neutral == 'Y' ? ' vs. ' : ' at ';
		if (isset($this->host_team->rank[$this->week])) {
			$str .= '<span class="poll-rank">' . '#' . $this->host_team->rank[$this->week] . '</span> ';
		}
		$str .= $this->host_team->displaytitle;
		if ($this->betting_line != '') {
			$str .= sprintf(" <span class=\"ptspread\">(%s)</span>", $this->betting_line == '0' ? 'PK' : $this->betting_line);
		}
		
		return $str;
		
		//return sprintf('%s %s %s (%s)',
		//	$this->visiting_team->displaytitle,
		//	$this->neutral == 'Y' ? 'vs.' : 'at',
		//	$this->host_team->displaytitle,
		//	$this->betting_line
		//	);	
	}
	
	public function displayTitleMobile() {
		if ($this->completed()) {
			return $this->eventResult('long');
		}
		if (!empty($this->title)) {
			return $this->title;
		}
		$betting_line = trim($this->betting_line);
		if (!empty($betting_line)) {
			return sprintf('%s %s<br />%s (%s)',
				$this->visiting_team->displaytitle,
				$this->neutral == 'Y' ? 'vs.' : 'at',
				$this->host_team->displaytitle,
				$this->betting_line
				);

		}
		return sprintf('%s %s<br />%s',
			$this->visiting_team->displaytitle,
			$this->neutral == 'Y' ? 'vs.' : 'at',
			$this->host_team->displaytitle
			);
	}
		
	public function vs_at_label() {
		return $this->neutral == 'Y' ? 'vs.' : 'at';
	}
	
	public function gameDate($type='medium') {
		return $this->eventDate($type);
	}
	
	public function eventDate($type='medium') {
		return format_date($this->event_date, $type);
	}

	public function eventDateAndTV($dateType='medium') {
		if (empty($this->tv)) {
			return $this->eventDate($dateType);
		}
		return sprintf('%s - %s', $this->eventDate($dateType), SportsEvent::tvNetworkByAbbr($this->tv));
	}
	
	public function opponentsForSelectElement() {
		$options = array();
		if (!empty($this->visiting_team_name)) {
			$options[$this->visiting_team_name] = $this->visiting_team;
		}
		if (!empty($this->host_team_name)) {
			$options[$this->host_team_name] = $this->host_team;
		}
		return $options;
	}
	
	public function opponents() {
		$opponents = array();
		
		/**
		 * TODO include rank of school
		 */
		$o = new stdClass();
		$o->name = $this->visiting_team_name;
		$o->displaytitle = $this->visiting_team->displaytitle;
		$opponents['visitor'] = $this->visiting_team;
		
		$o = new stdClass();
		$o->name = $this->host_team_name;
		$o->displaytitle = $this->host_team->displaytitle;
		$opponents['host'] = $this->host_team;
		
		return $opponents;
	}
	
	public function opponentsAsArray() {
			
	}
	
	public function eventResult($format='short', $target_team_name = NULL) {
		if (!$this->completed()) {
			return '';
		}
		
		$out = '';
		if ($format == 'short') {
			$out = sprintf("%s, %s F", $this->winning_team->displaytitle, $this->final_score);
			if (!is_null($this->overtimes)) {
				if ($this->overtimes == '1') {
					$out .= " (OT)";
				} else {
					$out .= " (" . $this->overtimes . "OT)";
				}
			} 
		} elseif ($format == 'short-with-win-loss') {
			if (is_null($target_team_name)) {
				return '';
			}
			$scores = array($this->visiting_score, $this->host_score);
			$out = ($target_team_name == $this->winning_team_name) ? 'W' : 'L';
			$out .= '&nbsp;';
			$out .= max($scores) . '-' . min($scores);
			if (!is_null($this->overtimes)) {
				if ($this->overtimes == '1') {
					$out .= " (OT)";
				} else {
					$out .= " (" . $this->overtimes . "OT)";
				}
			} 
		}
		else {
			$out = sprintf("%s, %s",
				$this->winning_team_name == $this->visiting_team_name
					? sprintf("%s - %s", $this->visiting_team->displaytitle, $this->visiting_score)
					: sprintf("%s - %s", $this->host_team->displaytitle, $this->host_score),
				$this->winning_team_name == $this->visiting_team_name
					? sprintf("%s - %s", $this->host_team->displaytitle, $this->host_score)
					: sprintf("%s - %s", $this->visiting_team->displaytitle, $this->visiting_score)
				);
		}
		return $out;
	}
	
	public function hasFavorite() {
		return !empty($this->favorite);
	}
}
