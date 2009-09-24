<?php
	//----------------------------------------------------------------------------
	// Storage: Text Files
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// The Text file storage is for use when a mysql database is not available.
	//
	// The text file storage should not be used by the majority of people, the
	// code is quite horrible as it is mainly a port of some older horrible code
	// designed to allow the reuse of the config for an older version rather than
	// needing to convert everything immediatly
	//----------------------------------------------------------------------------

	// Include the config incase it has not yet been included
	include_once(dirname(__FILE__).'/../config.php');
	
	// Include the files we use to store data in.
	@include_once(dirname(__FILE__).'/'.$config['storage']['text']['settings']);
	@include_once(dirname(__FILE__).'/'.$config['storage']['text']['got']);
	
	/**
	 * Get information about the given show from the database.
	 *
	 * @param $showname Show to get information for
	 * @return Array containing information from the database for the given show.
	 */
	function getShowInfo($showname) {
		global $Auto, $config, $Search, $Attributes, $important, $Alias, $Sources;
		
		$name = (string)$showname;
		if (isset($Alias[$name])) { $name = $Alias[$name]; }
		
		$result = array();
		$result['request'] = (string)$showname;
		$result['name'] = $name;
		$result['automatic'] = isset($Auto[$name]);
		$result['searchstring'] = (isset($Search[$name])) ? $Search[$name] : '' ;
		$result['dirname'] = (isset($Auto[$name])) ? $Auto[$name] : '' ;
		$result['sources'] = (isset($Sources[$name])) ? $Sources[$name] : '' ;
		$result['attributes'] = (isset($Attributes[$name])) ? $Attributes[$name] : '' ;
		$result['important'] = isset($important[$name]);
		$result['size'] = (isset($optimalsize[$name])) ? $optimalsize[$name] : '400';
		$result['known'] = isset($Auto[$name]);
		
		return getShowInfo_process($result);
	}
	
	/**
	 * Check if the given episode of a show has been downloaded.
	 *
	 * @param $showname Show name to check
	 * @param $season Season to check
	 * @param $episode Episode to check
	 * @return The time that the episode was downloaded at, or 0.
	 */
	function hasDownloaded($showname, $season, $episode) {
		global $Got;
		
		if ($showname == '' || $season == '' || $episode == '') { return 0; }
		
		$showname = preg_replace("/'/", "\\'", $showname);
		$season = trim($season);
		$episode = trim($episode);
		$episode = ltrim($episode, '0');
		
		if (!is_numeric($episode)) {
			preg_match('/([0-9]+)-'.$season.'x([0-9]+)/', $episode, $episodes);
			for ($i = $episodes[1]; $i != $episodes[2]; $i++) {
				if (!hasGot($showname, $season, $i, $name)) {
					return 0;
				}
			}
		}
		
		return $Got[$showname][$season][$episode]['time'];
	}
	
	/**
	 * Set a given episode of a show as already-downloaded.
	 *
	 * @param $showname Show name to set
	 * @param $season Season to set
	 * @param $episode Episode to set
	 * @param $title Title of the episode if known.
	 */
	function setDownloaded($showname, $season, $episode, $title = '') {
		global $Got, $config;
		if ($showname == '' || $season === '' || $episode == '') { return; }
		
		$title = preg_replace("/'/", "\\'", $title);
		$show = preg_replace("/'/", "\\'", $showname);
		$season = trim($season);
		$episode = trim($episode);
		$episode = ltrim($episode, '0');
		
		if (!is_numeric($episode)) {
			preg_match('/([0-9]+)-'.$season.'x([0-9]+)/', $episode, $episodes);
			for ($i = $episodes[1]; $i != $episodes[2]; $i++) {
				setDownloaded($showname, $season, $i, $title);
			}
			return;
		}
		
		if (file_exists(dirname(__FILE__).'/'.$config['storage']['text']['got'])) {
			$file = file_get_contents(dirname(__FILE__).'/'.$config['storage']['text']['got']);
			$lines = explode("\n", $file);
		} else {
			$lines = array("<?php", "?>");
		}
		
		if (isset($Got[$show][$season][$episode]['time'])) { return; }
		
		foreach ($lines as $line) {
			if ($line == '?>') {
				$newlines[] = "\t".'$Got[\''.$show.'\'][\''.$season.'\'][\''.$episode.'\'][\'time\'] = '.time().'; /* Added at: '.date('r').' */';
				$newlines[] = "\t".'$Got[\''.$show.'\'][\''.$season.'\'][\''.$episode.'\'][\'name\'] = \''.$title.'\';';
			}
			$newlines[] = $line;
		}
		$file = implode("\n", $newlines);
		file_put_contents(dirname(__FILE__).'/'.$config['storage']['text']['got'], $file);
	}
	
	
	/**
	 * Add a given show to the database download if it isn't already there.
	 *
	 * @param $show show name.
	 * @param $info Optional getShowInfo() result for this show.
	 * @param $automatic Set as Automatic?
	 * @return 0 if added, 1 if updated, 2 if no change made.
	 */
	function addShow($show, $info = null, $automatic = false) {
		return 2;
	}
?>
