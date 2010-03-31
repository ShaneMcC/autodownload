<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');

	head('TV Downloader');
	
	$starttime = isset($_REQUEST['day']) ? strtotime($_REQUEST['day'] . " 00:00") : strtotime('yesterday');
	$endtime = $starttime;
	if (isset($_REQUEST['week'])) {
		$day = date('d', strtotime('-7 days'));
		$month = date('m', strtotime('-7 days'));
		$year = date('Y', strtotime('-7 days'));
		
		$starttime = strtotime($month.'/'.$day.'/'.$year);
	} elseif (isset($_REQUEST['month'])) {
		$day = '1';
		$month = date('m', strtotime('today'));
		$year = date('Y', strtotime('today'));
		
		$starttime = strtotime($month.'/'.$day.'/'.$year);
	}

	function getLink($show) {
		$result = serialize($show);
		if (strlen($result) < 512) {
			return 'info=' . urlencode($result);
		} else {
			// Split it.
			$bits = explode("\n", chunk_split($result, 510, "\n"));
			$result = 'is=i';
			$i = 1;
			foreach ($bits as $bit) {
				$result .= '&i['.($i++).']='.urlencode($bit);
			}
			return $result;
		}
	}
	
	echo '<pre>';
	if ($starttime != $endtime) {
		echo 'Listing shows aired between: <strong>', date('r', $starttime), '</strong> and <strong>', date('r', $endtime), '</strong>', CRLF;
	} else {
		echo 'Listing shows aired on: <strong>', date('r', $starttime), '</strong>', CRLF;
	}
	$source = (!isset($_REQUEST['source']) || empty($_REQUEST['source']) || !validSource($_REQUEST['source'])) ? $config['tv']['default_source'] : $_REQUEST['source'];
	echo 'Listing source: <strong>', $source, '</strong>',  EOL;
	
	foreach (getShows(true, $starttime, $endtime, isset($_REQUEST['forcereload']), $source) as $show) {
		if ($_REQUEST['showonlyauto'] && !$show['info']['automatic']) { continue; }
		if ($_REQUEST['showonlyimportant'] && !$show['info']['important']) { continue; }
		
		echo 'Found Show: ';
		echo '<a href="GetTV.php?', getLink($show), '">';
		
		$first = (((int)$show['season'] == 1 && (int)$show['episode'] == 1) && $config['daemon']['autotv']['allfirst']);

		if (($first || $show['info']['automatic']) && ($first || goodSource($show))) { echo '<strong>'; }
		if ($show['info']['important']) { echo '<em>'; }
		printf('%s %dx%02d', $show['name'], $show['season'], $show['episode']);
		if ($show['info']['important']) { echo '</em>'; }
		if (($first || $show['info']['automatic']) && ($first || goodSource($show))) { echo '</strong>'; }
		echo '</a>';
		
		if (hasDownloaded($show['name'], $show['season'], $show['episode']) > 0) {
			echo ' [<strong>Got</strong>]';
		}
		
		if (!isset($_REQUEST['noshowsource'])) { 
			echo ' {', implode(', ', $show['sources']), '}';
		}
		
		if (isset($_REQUEST['showtitle'])) { echo ' /* Title: "'.$show['title'].'" */'; }
		
		echo EOL;
	}
	echo '</pre>';
	
	foot();
?>