<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');

	head('TV Downloader');
	
	$starttime = strtotime('yesterday');
	$endtime = $starttime;
	if (isset($_REQUEST['week'])) {
		$day = date('d', strtotime('-7 days'));
		$month = date('M', strtotime('-7 days'));
		$year = date('Y', strtotime('-7 days'));
		
		$starttime = strtotime($month.'/'.$day.'/'.$year);
	} elseif (isset($_REQUEST['month'])) {
		$day = '1';
		$month = date('M', strtotime('today'));
		$year = date('Y', strtotime('today'));
		
		$starttime = strtotime($month.'/'.$day.'/'.$year);
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
		echo '<a href="GetTV.php?info=', urlencode(serialize($show)), '">';
		if ($show['info']['automatic']) { echo '<strong>'; }
		if ($show['info']['important']) { echo '<em>'; }
		printf('%s %dx%02d', $show['name'], $show['season'], $show['episode']);
		if ($show['info']['important']) { echo '</em>'; }
		if ($show['info']['automatic']) { echo '</strong>'; }
		echo '</a>';
		
		if (hasDownloaded($show['name'], $show['season'], $show['episode']) > 0) {
			echo ' [<strong>Got</strong>]';
		}
		
		if (isset($_REQUEST['extrainfo'])) { echo ' /* Title: "'.$show['title'].'" */'; }
		
		echo EOL;
	}
	echo '</pre>';
	
	foot();
?>