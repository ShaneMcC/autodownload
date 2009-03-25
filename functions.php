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
				$thisShow['name'] = (string)$show->name;
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
	 * @return The index of the best match.
	 */
	function GetBestOptimal($matches,$optimal,$allownegative = false,$allownegativeifdouble = false) {
		// global $config;
		// $target_optimal = ($config['download']['highdef']) ? $optimal * 2 : $optimal;
		$target_optimal = $optimal;
		$result = 0;
		$dev = -1;
		for ($i = 0; $i != count($matches); $i++) {
			$val = (float)str_replace(',', '', (string)$matches[$i]['sizemb']);
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
				$result = GetBestOptimal($matches,$optimal,true);
			} else if ($allownegativeifdouble === true && $cdev > ($target_optimal * 2)) {
				$currentResult = $result;
				$possibleResult = GetBestOptimal($matches,$optimal,true);
				
				$minumum = $target_optimal * 0.8;
				$val = (float)str_replace(',', '', (string)$matches[$possibleResult]['sizemb']);
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
		$info['automatic'] = (strtolower($info['automatic']) == 'true');
		$info['important'] = (strtolower($info['important']) == 'true');
		
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
	include_once(dirname(__FILE__).'/storage/'.$config['storage']['type'].'.php');
?>