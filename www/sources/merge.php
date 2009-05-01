<?php
	//----------------------------------------------------------------------------
	// Source: Merges the results from other sources.
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// This source gets the output from all the other sources and merges it.
	//----------------------------------------------------------------------------
	include_once(dirname(__FILE__).'/../../config.php');
	include_once(dirname(__FILE__).'/../../functions.php');

	$urls = array();
	$extra = isset($_REQUEST['extra']) ? $_REQUEST['extra'] : '';
	$shows = array();
	$cacheInfo = array();
	$sources = directoryToArray($config['tv']['filebase'], false, false, false);
	foreach ($sources as $source) {
		if (realpath($config['tv']['filebase'].'/'.$source['name']) == realpath(__FILE__)) { continue; }
		$filename = explode('.', $source['name']);
		$fileext = strtolower(array_pop($filename));
		$filename = implode('.', $filename);
		if ($fileext == 'php' && validSource($filename)) {
			$tv = getTV(isset($_REQUEST['forcereload']), $filename, $extra);
			foreach ($tv->cache as $cache) {
				$cacheInfo[] = array('source' => (empty($tv->cache['source'])) ? $filename : $tv->cache['source'],
				                     'cache' => (string)$tv->cache
				                    );
			}
			foreach ($tv->show as $showInfo) {
				$show = array();
				$show['name'] = cleanName((string)$showInfo->name, false);
				$show['time'] = (int)$showInfo->date['time'];
				$show['date'] = (string)$showInfo->date;
				$show['season'] = (int)$showInfo->season;
				$show['episode'] = (int)$showInfo->episode;
				$show['title'] = (string)$showInfo->title;
				$show['sources'] = array($filename);
				
				$id = sprintf('%s %dx%02d', $show['name'], $show['season'], $show['episode']);
				
				// If we already know about this show, add this source and correct any
				// problems, otherwise just add it.
				if (isset($shows[$id])) {
					$shows[$id]['sources'][] = $filename;
					// Update the title if we have it and it wasn't already known.
					if (!empty($show['title']) && (empty($shows[$id]['title']) || strtolower($shows[$id]['title']) == strtolower($id))) {
						$shows[$id]['title'] = $show['title'];
					}
					// Use the LATEST time, as sometimes on-my.tv gives a wrong time...
					$shows[$id]['time'] = max($shows[$id]['time'], $show['time']);
				} else {
					$shows[$id] = $show;
				}
			}
		}
	}
	
	/**
	 * Used to sort shows by time so that the output XML is ordered correctly.
	 *
	 * @param $a Show 1 to compare
	 * @param $a Show 2 to compare
	 */
	function sortShows($a, $b) {
		if ($a['time'] == $b['time']) {
				return 0;
		}
		return ($a['time'] < $b['time']) ? -1 : 1;
	}

	usort($shows, 'sortShows');
	
	header('Content-type: text/xml');
	echo '<?xml version="1.0" encoding="UTF-8" ?>', CRLF;
	echo '<tvcal>', CRLF;
	foreach ($cacheInfo as $cache) {
		echo '<cache source="', $cache['source'], '">', $cache['cache'], '</cache>', CRLF;
	}
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