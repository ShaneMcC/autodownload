<?php
	//----------------------------------------------------------------------------
	// Source: Merges the results from other sources.
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// This source gets the output from all the other sources and merges it.
	//----------------------------------------------------------------------------
	include_once(dirname(__FILE__).'/../../config.php');
	include_once(dirname(__FILE__).'/../../functions.php');

	if (!function_exists('connectSQL')) { die(); }
	
	$db_savefile = __FILE__.'.cache';
	$db_cachetime = $config['tv']['cachetime'];
	
	// If the cache is out of date, or forcereload was requested then get merge.php
	// to work its magic and get all the sources to update themselves so that the
	// database is up to date.
	if (isset($_REQUEST['forcereload']) || !file_exists($db_savefile) || !is_file($db_savefile) || filemtime($db_savefile) < time()-$db_cachetime) {
		$cache = 'No';
		getTV(true, 'merge', '');
		@file_put_contents($db_savefile, date('r'));
	}

	// Now we can do magic!
	$shows = array();
	$mysqli = connectSQL();
	$query = 'SELECT COALESCE(aliases.show, airtime.name) as showname, season, episode, title, time, group_concat(source separator \', \') as source FROM `airtime` LEFT JOIN aliases ON (aliases.show = airtime.name or aliases.alias = airtime.name) WHERE time > '.(time() - 2764800).' GROUP By showname,season,episode,time ORDER by time';
	// $query = 'SELECT name, season, episode, title, time, group_concat(source separator \', \') as source FROM `airtime` WHERE time > '.(time() - 2764800).' GROUP By name,season,episode,time ORDER by time';
	if ($stmt = $mysqli->prepare($query)) {
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$stmt->bind_result($name, $season, $episode, $title, $time, $sources);
			while ($stmt->fetch()) {
				$show['name'] = cleanName((string)$name, false);
				$show['time'] = (int)$time;
				$show['date'] = date('j-n-Y', $time);
				$show['season'] = (int)$season;
				$show['episode'] = (int)$episode;
				$show['title'] = (string)$title;
				$show['sources'] = explode(',', $sources);
				
				$shows[] = $show;
			}
		}
		$stmt->free_result();
	}
	
	/**
	 * Used to sort shows by time so that the output XML is ordered correctly.
	 *
	 * @param $a Show 1 to compare
	 * @param $a Show 2 to compare
	 */
	function sortDBShows($a, $b) {
		if ($a['time'] == $b['time']) {
				return 0;
		}
		return ($a['time'] < $b['time']) ? -1 : 1;
	}

	usort($shows, 'sortDBShows');
	
	header('Content-type: text/xml');
	echo '<?xml version="1.0" encoding="UTF-8" ?>', CRLF;
	echo '<tvcal>', CRLF;
/*	foreach ($cacheInfo as $cache) {
		echo '<cache source="', $cache['source'], '">', $cache['cache'], '</cache>', CRLF;
	}*/
	foreach ($shows as $show) {
		echo "\t", '<show>', CRLF;
		echo "\t\t", '<date time=\'', $show['time'], '\'>', $show['date'], '</date>', CRLF;
		echo "\t\t", '<name>', htmlspecialchars(html_entity_decode($show['name']), ENT_QUOTES, 'UTF-8'), '</name>', CRLF;
		echo "\t\t", '<title>', htmlspecialchars(html_entity_decode($show['title']), ENT_QUOTES, 'UTF-8'), '</title>', CRLF;
		echo "\t\t", '<season>', $show['season'], '</season>', CRLF;
		echo "\t\t", '<episode>', $show['episode'], '</episode>', CRLF;
		foreach ($show['sources'] as $source) {
			echo "\t\t", '<source>', $source, '</source>', CRLF;	
		}
		echo "\t", '</show>', CRLF;
	}
	echo '</tvcal>';

?>