<?php
	//----------------------------------------------------------------------------
	// Storage: MySQL Database
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// The MySQL Database is the reccomended storage to use.
	//----------------------------------------------------------------------------

	// Include the config incase it has not yet been included
	include_once(dirname(__FILE__).'/../config.php');
	
	// Make $mysqli null so we know that it hasn't been created yet.
	$mysqli = null;

	/**
	 * Check if there was an error and echo it.
	 */
	function checkLastError() {
		global $mysqli;
		if ($mysqli != null) {
			$error = $mysqli->error;
			if (!empty($error)) {
				echo '------------------', "\n";
				echo 'Mysql Error: ', $error, "\n";
				echo '------------------', "\n";
			}
		}
	}

	/**
	 * Try to connect to the MySQL Database.
	 * If the connection works or a database connection already exists then this
	 * returns the mysqli instance, else die() is called.
	 *
	 * @return $mysqli instance for the database connection.
	 */
	function connectSQL() {
		global $mysqli, $config;

		if ($mysqli != null) {
			// Try to ping and reconnect.
			$mysqli->ping();
			// Check if reconnect was successful.
			if (!$mysqli->ping()) {
				// Still not connected, set to null and we will reconnect below.
				$mysqli = null;
			}
		}

		if ($mysqli == null) {
			$mysqli = @new mysqli($config['storage']['mysql']['host'], $config['storage']['mysql']['user'], $config['storage']['mysql']['pass'], $config['storage']['mysql']['database'], $config['storage']['mysql']['port']);
			if (mysqli_connect_errno()) {
				die('Unable to connect to MySQL Database');
			}
		}
		
		return $mysqli;
	}
	
	/**
	 * Get information about the given show from the database.
	 *
	 * @param $showname Show to get information for. If this is an array then it
	 *                  is assumed that the data has already been taken from the
	 *                  database and this is used insted.
	 * @return Array containing information from the database for the given show.
	 */
	function getShowInfo($showname) {
		global $config;
		
		$mysqli = connectSQL();
		
		$askDB = !is_array($showname);
		
		$result = array();
		$result['request'] = (string)$showname;
		$result['name'] = (!$askDB) ? $showname['name'] : (string)$showname;
		$result['automatic'] = (!$askDB) ? $showname['automatic'] : false;
		$result['searchstring'] = (!$askDB) ? $showname['searchstring'] : '';
		$result['dirname'] = (!$askDB) ? $showname['dirname'] : '';
		$result['attributes'] = (!$askDB) ? $showname['attributes'] : '';
		$result['sources'] = (!$askDB) ? $showname['sources'] : '';
		$result['important'] = (!$askDB) ? $showname['important'] : false;
		$result['size'] = (!$askDB) ? $showname['size'] : '400';
		$result['known'] = (!$askDB) ? true : false;
		
		if ($askDB) {
			if ($stmt = $mysqli->prepare('SELECT shows.name,shows.automatic,shows.searchstring,shows.dirname,shows.important,shows.size,shows.attributes,shows.sources FROM shows, aliases WHERE (aliases.show = shows.name AND aliases.alias = ?) OR (shows.name = ?);')) {
				$stmt->bind_param('ss', $result['request'], $result['request']);
				$stmt->execute();
				$stmt->store_result();
				if ($stmt->num_rows > 0) {
					$stmt->bind_result($result['name'], $result['automatic'], $result['searchstring'], $result['dirname'], $result['important'], $result['size'], $result['attributes'], $result['sources']);
					$stmt->fetch();
					$result['known'] = true;
				}
				$stmt->free_result();
			}
		}
		
		checkLastError();
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
		$mysqli = connectSQL();
		$result = 0;
		$query = 'SELECT time FROM downloaded LEFT JOIN aliases ON aliases.show = downloaded.name WHERE (downloaded.name = ? OR aliases.alias = ?) AND season = ? AND episode = ? GROUP by season, episode;';
		if ($stmt = $mysqli->prepare($query)) {
			$stmt->bind_param('ssdd', $showname, $showname, $season, $episode);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows > 0) {
				$stmt->bind_result($result);
				$stmt->fetch();
			}
			$stmt->free_result();
		}
		
		checkLastError();
		return $result;
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
		$mysqli = connectSQL();
		
		if ($stmt = $mysqli->prepare('INSERT INTO downloaded (name, season, episode, time, title) VALUES (?, ?, ?, ?, ?)')) {
			$time = time();
			$stmt->bind_param('sddds', $showname, $season, $episode, $time, $title);
			$stmt->execute();
		}
		
		checkLastError();
	}
	
	/**
	 * Add a given show to the database download if it isn't already there.
	 *
	 * @param $show show name.
	 * @param $info Optional getShowInfo() result for this show.
	 * @param $automatic Set as Automatic?
	 * @param $sources Sources for this show.
	 * @return 0 if added, 1 if updated, 2 if no change made.
	 */
	function addShow($show, $info = null, $automatic = false, $sources = "") {
		$mysqli = connectSQL();
		if ($info == null) { $info = getShowInfo($show); }
		
		if (is_array($sources)) {
			$sources = implode(' ', $sources);
		} else { 
			$sources = str_replace(',', ' ', $sources);
			$sources = preg_replace('#\s+#', ' ', $sources);
		}
		
		// Check if show is already known.
		$exists = false;
		if ($stmt = $mysqli->prepare('SELECT shows.name FROM shows, aliases WHERE (aliases.show = shows.name AND aliases.alias = ?) OR (shows.name = ?);')) {
			$stmt->bind_param('ss', $show, $show);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows > 0) {
				$exists = true;
			}
			$stmt->free_result();
		}
		
		if ($exists) {
			if ($stmt = $mysqli->prepare('UPDATE shows SET automatic=? WHERE name=?')) {
				$autovalue = ($automatic ? 'true' : 'false');
				$stmt->bind_param('ss', $autovalue, $show);
				$stmt->execute();
				return 1;
			}
		} else {
			if ($stmt = $mysqli->prepare('INSERT INTO shows (name, automatic, important, size, sources) VALUES (?, ?, "false", ?, ?)')) {
				$autovalue = ($automatic ? "true" : "false");
				$stmt->bind_param('ssds', $show, $autovalue, $info['size'], $sources);
				$stmt->execute();
				return 0;
			}
		}
		
		checkLastError();
	}
	
	/**
	 * Add a shows airtime.
	 *
	 * @param $show show name.
	 * @param $season show season.
	 * @param $episode show episode.
	 * @param $title show title.
	 * @param $time time
	 * @param $source source
	 * @return 0 if added, 1 if updated, 2 if no change made.
	 */
	function addAirTime($show, $season, $episode, $title, $time, $source) {
		$mysqli = connectSQL();
		
		// Check if show is already known.
		$exists = false;
		if ($stmt = $mysqli->prepare('SELECT time FROM airtime WHERE name = ? and season = ? and episode = ? and source = ?;')) {
			$stmt->bind_param('sdds', $show, $season, $episode, $source);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows > 0) {
				$exists = true;
			}
			$stmt->free_result();
		}
		
		checkLastError();
		
		if ($exists) {
			if ($stmt = $mysqli->prepare('UPDATE airtime SET time=?,title=? WHERE name = ? and season = ? and episode = ? and source = ?;')) {
				$stmt->bind_param('dssdds', $time, $title, $show, $season, $episode, $source);
				$stmt->execute();
				
				checkLastError();
				return 1;
			}
		} else {
			if ($stmt = $mysqli->prepare('INSERT INTO airtime (name, season, episode, title, time, source) VALUES (?, ?, ?, ?, ?, ?)')) {
				$stmt->bind_param('sddsds', $show, $season, $episode, $title, $time, $source);
				$stmt->execute();
				
				checkLastError();
				return 0;
			}
		}
		
		checkLastError();
		return 2;
	}
	
	/**
	 * Get all shows.
	 *
	 * @return Array of show infos, automatic before manual.
	 */
	function getAllShows() {
		$mysqli = connectSQL();
		
		$output = array();
		
		$query = 'SELECT name,automatic,searchstring,dirname,important,size,attributes,sources FROM shows ORDER BY automatic, important, name';
		$result = $mysqli->query($query);
		while (($row = $result->fetch_array(MYSQLI_ASSOC)) != null) {
			$output[] = getShowInfo($row);
		}
		
		checkLastError();
		return $output;
	}
?>