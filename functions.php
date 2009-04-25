<?php
	include_once(dirname(__FILE__).'/config.php');

	/**
	 * Print a basic html page-header.
	 *
	 * @param $title Title of page.
	 * @param $subtitle (Default = '') Sub-Title of page.
	 * @param $css (Default = '') css for page
	 */
	function head($title, $subtitle = '', $css = '') {
		global $global;
		if (isset($global['senthead'])) { return; }
		if (isset($global['nohead'])) { return; }
		$global['senthead'] = true;
		
		header("Content-Type: text/html; charset=utf-8");
		
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">'.CRLF;
		echo '<html><head><title>'.$title.'</title>';
		if (!empty($css)) {
			echo CRLF;
			echo '<style type="text/css">';
			echo $css;
			echo '</style>';
			echo CRLF;
		}
		echo '</head><body><div>'.CRLF;
		if (!empty($subtitle)) {
			echo '<h2 style="margin: 0">'.$title.'</h2>';
			echo '<h3 style="margin-top: 0">'.$subtitle.'</h3>';
		} else {
			echo '<h2 style="margin-top: 0">'.$title.'</h2>';
		}
	}
	
	/**
	 * Print a basic html page-footer.
	 *
	 * @param $die (Default = false) Should die() be called immediately after the
	 *             footer?
	 */
	function foot($die = false) {
		if (isset($global['sentfoot'])) { return; }
		if (isset($global['nofoot'])) { return; }
		$global['sentfoot'] = true;
	
		echo '</div></html>';
		if ($die) { die(); }
	}
	
	/**
	 * Remove slashes added by magic quotes if enabled.
	 * If magic quotes is not enabled, $text will be returned.
	 *
	 * @param $text Text to remove slashes from
	 * @return $text without any magic-quotes induced slashes.
	 */
	function unslash($text) {
		if (get_magic_quotes_gpc()) {
			return stripslashes($text);
		} else {
			return $text;
		}
	}
	
	/**
	 * Remove slashes added by magic quotes if enabled.
	 * If magic quotes is not enabled, $text will be returned.
	 *
	 * @param $searchTerm Term to search for
	 * @param $debug (Default = false) Print the page as returned by the search
	 *               provider before passing it to simplexml_load_string()
	 * @return simplexml instance representing the search results, or false if the
	 *         search page was unable to be opened.
	 */
	function searchFor($searchTerm, $debug = false) {
		global $config;
		
		$url = $config['search']['provider'];
		$url .= '?username='.urlencode($config['search']['username']);
		$url .= '&password='.urlencode($config['search']['password']);
		$url .= '&sizesort';
		$url .= '&limit=100';
		$url .= '&search='.urlencode($searchTerm);
		
		$page = file_get_contents($url);
		
		if ($debug) { echo '<pre>'.htmlspecialchars($page).'</pre>'; }
		return ($page === false) ? false : simplexml_load_string($page);
	}

	/**
	 * Check if a given source is a valid source of TV Listings.
	 *
	 * @param $source Source to check.
	 * @return true if source is valid
	 */
	function validSource($source) {
		global $config;
		
		$source = str_replace('../', '', $source);
		return file_exists($config['tv']['filebase'].'/'.$source.'.php');
	}

	/**
	 * Get all the shows that the given source returns.
	 *
	 * @param $forcereload (Default = false) Should the source clear its cache?
	 * @param $source (Default = '') Source to use. If blank, the default source
	 *                specified in the config is used.
	 * @param $source (Default = '') Some sources allow an extra param to be
	 *                passed to them, give that here.
	 * @return simplexml object representing all the TV the source knows about.
	 */
	function getTV($forcereload = false, $source = '', $extra = '') {
		global $config;
		
		$source = (empty($source) || !validSource($source)) ? $config['tv']['default_source'] : $source;
		
		$url = $config['tv']['urlbase'].$source.'.php';
		$sep = '?';
		if (!empty($extra)) { $url .= $sep.'extra='.$extra; $sep = '&'; }
		if ($forcereload) { $url .= $sep.'forcereload'; $sep = '&'; }
		
		$page = file_get_contents($url);
		
		if ($debug) { echo '<pre>'.htmlspecialchars($page).'</pre>'; }
		return simplexml_load_string($page);
	}
	
	/**
	 * Get all the shows that air between the given times.
	 *
	 * @param $getInfo (Default = false) Should $show['info'] be populated with
	 *                 information about the show from the data-storage? (such as
	 *                 if the show is important, or automatically downloaded)
	 * @param $starttime (Default = -1) Earliest time a show can start for it to
	 *                   be returned. -1 will use 00:00 "yesterday".
	 * @param $endtime (Default = -1) Latest time a show can start for it to be
	 *                 returned. -1 will use 00:00 "yesterday".
	 * @param $forcereload (Default = false) Should the source clear its cache?
	 * @param $source (Default = '') Source to use. If blank, the default source
	 *                specified in the config is used.
	 * @return simplexml object representing all the TV the source knows about
	 *         that airs between the given times.
	 */
	function getShows($getInfo = false, $starttime = -1, $endtime = -1, $forcereload = false, $source = '') {
		$starttime = ($starttime == -1) ? strtotime("yesterday") : $starttime;
		$endtime = ($endtime == -1) ? strtotime("yesterday") : $endtime;
		
		// Pass the month as the parameter, this allows calendar-based sources to
		// use the correct month.
		$extra = date('n-Y', $endtime);
		$TV = getTV($forcereload, $source, $extra);
		$shows = array();
		
		foreach ($TV->show as $show) {
			if ($show->date['time'] >= $starttime && $show->date['time'] <= $endtime) {
				$thisShow['name'] = cleanName((string)$show->name, false);
				$thisShow['time'] = (int)$show->date['time'];
				$thisShow['season'] = (int)$show->season;
				$thisShow['episode'] = (int)$show->episode;
				$thisShow['title'] = (string)$show->title;
				if ($getInfo) {
					$thisShow['info'] = getShowInfo((string)$show->name);
				}
				$shows[] = $thisShow;
			}
		}
		
		return $shows;
	}
	
	/**
	 * Get the search string for the given show.
	 *
	 * @param $show Show array to get searchstring for.
	 * @param $info (Defaults = empty array) $info array to use. If empty, 
	 *              $show['info'] will be used, else getShowInfo() will be called
	 * @return Final Search String for $show after replacements and attributes.
	 */
	function getSearchString($show, $info = array()) {
		if ($info == null || empty($info)) {
			$info = ($show['info'] == null || empty($show['info'])) ? getShowInfo($show['name']) : $show['info'];
		}
		
		$bit_search = array("{series}", "{season}", "{episode}", "{title}", "{time}");
		$bit_replace = array($show['name'], $show['season'], sprintf('%02d', $show['episode']), $show['title'], $show['time']);
		
		$searchString = $info['searchstring'];
		$searchString = str_replace($bit_search, $bit_replace, $searchString);
		
		if (trim($info['attributes']) != '') {
			$searchString .= ' ';
			$searchString .= $info['attributes'];
		}
		
		return $searchString;
	}
	
	/**
	 * Extract Episode info from the given name, using any of the given patterns.
	 *
	 * @param $patterns Patterns to check for matches
	 * @param $name Name to compare patterns to
	 * @return Array containing show name, season, episode and title, or null.
	 */
	function getEpisodeInfo($patterns, $name) {
		global $config;
		
		foreach ($patterns as $pattern => $info) {
			if (function_exists('doEcho')) { doEcho('Trying: ', $pattern, CRLF); }
			if (preg_match($pattern, $name, $matches)) {
				if (function_exists('doPrintR')) { doPrintR($matches); }
				$result['pattern'] = $pattern;
				$result['name'] = isset($info['name']) ? $matches[$info['name']] : 'Unknown';
				$result['season'] = isset($info['season']) ? $matches[$info['season']] : '00';
				$result['episode'] = isset($info['episode']) ? $matches[$info['episode']] : '00';
				$result['title'] = isset($info['title']) ? $matches[$info['title']] : 'Episode '.$result['season'].'x'.$result['episode'];
				$result['usefilepattern'] = isset($info['usefilepattern']) ? $info['usefilepattern'] : $config['daemon']['reindex']['usefilepatterns'];
				
				$result['name'] = cleanName($result['name']);
					
				return $result;
			}
		}
		
		return null;
	}
	
	/**
	 * Check if the given search result is a good match for the given show?
	 * (Sometimes generic named shows "life" "house" "heroes" etc can return
	 * results for other shows that are similar, this is used to detect the
	 * likelyhood of this.)
	 * AutoTV won't download any unlikely shows, GetTV.php will show them as red,
	 * getOptimal will ignore them.
	 * This uses $config['daemon']['reindex']['dirpatterns'] to extract the show
	 * name and then checks if it matches the given show name.
	 *
	 * @param $searchresult Result to check.
	 * @param $show Show to check. (if no 'name' param exists, returns true)
	 * @return true of false if this is considered a good match.
	 */
	function isGoodMatch($searchresult, $show) {
		global $config;
		if (!isset($show['name'])) { return true; }
		
		$info = getEpisodeInfo($config['daemon']['reindex']['dirpatterns'], $searchresult);
		if ($info != null) {
			return (cleanName($info['name']) == cleanName($show['name']));
		}
		return false;
	}
	
	/**
	 * Clean up the given episode name.
	 *   - Removes . or _ between words
	 *   - Removes non alphanumeric or () ' : symbols from name
	 *   - Replaces multiple spaces in a row with a single space
	 *   - Make the first letter of everyword uppercase
	 *   - Get the real case of the name from the database
	 *
	 * @param $name Name to clean up
	 * @param $askDatabase (Default = true) Check with the database to get the
	 *                     real case for the name?
	 * @return Cleaned up name.
	 */
	function cleanName($name, $askDatabase = true) {
		// replace any .s or _s that were used in place of spaces, with spaces.
		$name = preg_replace('@([a-zA-Z0-9])\.([a-zA-Z0-9])@', '\1 \2', $name);
		
		// Replace any non alphanumerics or some special characters with spaces.
		$name = preg_replace('@[^a-zA-Z0-9\(\):\']@', ' ', $name);
		
		// Replace multiple spaces in a row with a single space.
		$name = preg_replace('@[\s]+@', ' ', $name);
		
		// Make words have uppercase first letters
		$name = ucfirst($name);
		
		// Check with the database for the real case of the name.
		if($askDatabase) {
			$showinfo = getShowInfo($name);
			$name = $showinfo['name'];
		}
		
		return $name;
	}
	
	/**
	 * Check if a time array is a time in the time array given.
	 *
	 * @param $check Time array to check
	 * @param $thisTime (Default = array()) Time to check. If empty or null, "now" is
	 *              assumed. If a time array is incomplete, values from "now" will
	 *              be used to complete it.
	 * @return True if the time array and check match.
	 */
	function checkTimeArray($check, $thisTime = array()) {
		global $config;
		
		if ($thisTime == null) { $thisTime = array(); }
		
		// Loop through every time array in the check array
		foreach ($check as $time) {
			$result = true;
			// Loop through every time type, timename is 'hour', 'day' etc
			foreach ($config['times'] as $timename => $format) {
				// Check if the time array cares about matching this timename
				if (isset($time[$timename])) {
					// If it does, check that the value of the current time is in the
					// array.
					$array = is_array($time[$timename]) ? $time[$timename] : preg_split('@[^0-9]+@', $time[$timename]);
					if (!isset($thisTime[$timename])) { $thisTime[$timename] = date($format); }
					if (!in_array($thisTime[$timename], $array)) {
						// Not in the array, set the result to false, and break this loop,
						// no point checking any further.
						$result = false;
						break;
					}
				}
			}
			
			// Either this time array had no times, or they all matched.
			// Either way, its technically a match.
			if ($result) { return true; }
		}
		
		// If we get here, then none of the time arrays matched, so return false.
		return false;
	}
	
	/**
	 * Get a listing of a directory as an array.
	 * Based on http://snippets.dzone.com/posts/show/155
	 *
	 * @param $directory Directory to list
	 * @param $recursive (Default = true) Should subdirectories be recursed into?
	 * @param $fullpath (Default = true) Should full path be stored for each entry
	 *                  or just relative to the above entry?
	 * @param $includedirectories (Default = true) If recursive is false, should
	 *                            directories be included in the list?
	 */
	function directoryToArray($directory, $recursive = true, $fullpath = true, $includedirectories = true) {
		$array_items = array();
		if ($handle = opendir($directory)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != '.' && $file != '..') {
					$nicefile = (($fullpath) ? $directory.'/' : '') . $file;
					$nicefile = preg_replace('@//@si', '/', $nicefile);
					$fullfile = $directory.'/'.$file;
					$array_item = array();
					
					if (is_dir($fullfile)) {
						if ($includedirectories || $recursive) {
							$array_item['name'] = $nicefile;
							$array_item['contents'] = ($recursive) ? directoryToArray($fullfile, $recursive) : array();
							$array_items[] = $array_item;
						}
					} else {
						$array_item['name'] = $nicefile;
						
						$array_items[] = $array_item;
					}
				}
			}
			closedir($handle);
		}
		return $array_items;
	}
	
	/**
	 * Recursively remove all the files/directories in the given directory and
	 * then remove the given directory itself.
	 *
	 * @param $dir Directory to remove.
	 * @return True if the directory was removed, else false.
	 */
	function rmdirr($dir) {
		if (substr($dir,-1) != '/') $dir .= '/';
		if (!is_dir($dir)) return false;
	
		if (($dh = opendir($dir)) !== false) {
			while (($entry = readdir($dh)) !== false) {
				if ($entry != '.' && $entry != '..') {
					if (is_file($dir . $entry) || is_link($dir . $entry)) {
						unlink($dir . $entry);
					} else if (is_dir($dir . $entry)) rmdirr($dir . $entry);
				}
			}
			closedir($dh);
			rmdir($dir);
		
			return true;
		}
		return false;
	}
	
	/**
	 * Get the index for the optimal download from the given list.
	 *
	 * @param $matches List of search results.
	 * @param $optimal Size we are looking to match.
	 * @param $allownegative (Default = false) Should sizes lower than the optimal
	 *                       be considered? If true, 99 is considered optimal over
	 *                       102 if the target is 100. Given 99 and 101, the first
	 *                       one in the list is chosen. If this is false and no
	 *                       optimal matches are found, then this is assumed to
	 *                       be true.
	 * @param $allownegativeifdouble (Default = false) If $allownegative is false
	 *                               and the best possible match is more than
	 *                               double the optimal value, should we look for
	 *                               a better negative match? The negative match
	 *                               will only be considered better if it is
	 *                               over 80% of the optimal.
	 * @param $show If this parameter is given, isGoodMatch will be used to ignore
	 *              bad shows.
	 * @return The index of the best match, or -1 if none was found.
	 */
	function GetBestOptimal($matches,$optimal,$allownegative = false,$allownegativeifdouble = false, $show = array()) {
		// global $config;
		// $target_optimal = ($config['download']['highdef']) ? $optimal * 2 : $optimal;
		$target_optimal = $optimal;
		$result = -1;
		$dev = -1;
		for ($i = 0; $i != count($matches); $i++) {
			if (!isGoodMatch((string)$matches[$i]->name, $show)) { continue; }
			$val = (float)str_replace(',', '', (string)$matches[$i]->sizemb);
			if ($val == $target_optimal) {
				$result = $i;
				break;
			} else {
				$cdev = $val - $target_optimal;
				if ($allownegative) { $cdev = abs($cdev); }
				if (($cdev < $dev || $dev < 0) && ($cdev > 0)) {
					$dev = $cdev;
					$result = $i;
				}
			}
		}
		if ($allownegative === false) {
			if ($dev < 0) {
				$result = GetBestOptimal($matches,$optimal,true, false, $show);
			} else if ($allownegativeifdouble === true && $dev > ($target_optimal * 2)) {
				$currentResult = $result;
				$possibleResult = GetBestOptimal($matches,$optimal,true, false, $show);
				
				$minumum = $target_optimal * 0.8;
				$val = (float)str_replace(',', '', (string)$matches[$possibleResult]->sizemb);
				print_r($matches[$possibleResult]);
				if ($val >= $minumum) {
					$result = $possibleResult;
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Called by getShowInfo after the data has been obtained, and before it has
	 * been returned.
	 * This function can manipulate the data before the calling function gets to
	 * see it. This is used to apply common changes.
	 *
	 * @param $info Info that will be returned
	 * @return Output to actually return to the user.
	 */
	function getShowInfo_process($info) {
		global $config;
		
		// Apply defaults if nothing special is specified
		$info['searchstring'] = ($info['searchstring'] == null || empty($info['searchstring'])) ? $config['default']['searchstring'] : $info['searchstring'];
		$info['dirname'] = ($info['dirname'] == null || empty($info['dirname'])) ? $config['default']['dirname'] : $info['dirname'];
		$info['attributes'] = ($info['attributes'] == null || empty($info['attributes'])) ? $config['default']['attributes'] : $info['attributes'];
		
		// Make sure automatic and important are booleans
		if ($info['automatic'] !== true && $info['automatic'] !== false) { $info['automatic'] = (strtolower($info['automatic']) == 'true'); }
		if ($info['important'] !== true && $info['important'] !== false) { $info['important'] = (strtolower($info['important']) == 'true'); }
		
		// Double the optimal size if using highdef.
		if ($config['download']['highdef']) {
			$info['size_original'] = $info['size'];
			$info['size'] = $info['size'] * 2;
		}
		
		return $info;
	}

	// Include the data-storage methods specified by the config file here so that
	// they can be used without anything else needing to know where the data is
	// actually being retrieved from.
	// Defaults to dummy methods if the file doesn't exist.
	$storage_file = dirname(__FILE__).'/storage/'.$config['storage']['type'].'.php';
	if (file/*_exists*/($storage_file)) {
		include_once($storage_file);
	} else {
		/**
		 * Get information about the given show.
		 *
		 * @param $showname Show to get information for
		 * @return Array containing information from the database for the given show.
		 */
		function getShowInfo($showname) {
			$name = (string)$showname;
			
			$result = array();
			$result['name'] = $name;
			$result['automatic'] = false;
			$result['searchstring'] = '';
			$result['dirname'] = '' ;
			$result['attributes'] = '' ;
			$result['important'] = false;
			$result['size'] = '400';
			
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
			return false;
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
			return;
		}
	}
	
	// Include the downloader methods specified by the config file here so that
	// they can be used without anything else needing to know what is actually
	// doing the downloading.
	// Defaults to dummy methods if the file doesn't exist.
	$downloader_file = dirname(__FILE__).'/downloader/'.$config['downloader']['type'].'.php';
	if (file_exists($downloader_file)) {
		include_once($downloader_file);
	} else {
		/**
		 * Download the given NZB.
		 *
		 * @param $nzbid
		 * @return Array containing the output from the downloader, and the status code.
		 */
		function downloadNZB($nzbid) {
			$result['output'] = array();
			$result['status'] = true;
			return $result;
		}
	}
	
	// Include functions.report.php if it exists.
	// This should provide a function: doReport($info) { } which can then
	// notify the user however they want about events.
	// $info is an array containing keys related to the event to report.
	//   * source - Source of event
	//   * message - Message to report
	$doreport_file = dirname(__FILE__).'/functions.report.php';
	if (file_exists($doreport_file)) {
		include_once($doreport_file);
	} else {
		/**
		 * Report an event to the user.
		 *
		 * @param $info Info array to report
		 */
		function doReport($info) { }
	}
	
	// Fix any _REQUEST items.
	if (isset($_REQUEST)) {
		foreach ($_REQUEST as $key => $value) {
			$_REQUEST[$key] = unslash($_REQUEST[$key]);
		}
	}
?>