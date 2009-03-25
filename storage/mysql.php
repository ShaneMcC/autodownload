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
	 * Try to connect to the MySQL Database.
	 * If the connection works or a database connection already exists then this
	 * returns the mysqli instance, else die() is called.
	 *
	 * @return $mysqli instance for the database connection.
	 */
	function connectSQL() {
		global $mysqli, $config;
		
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
	 * @param $showname Show to get information for
	 * @return Array containing information from the database for the given show.
	 */
	function getShowInfo($showname) {
		global $config;
		
		$mysqli = connectSQL();
		
		$result = array();
		$result['name'] = (string)$showname;
		$result['automatic'] = false;
		$result['searchstring'] = '';
		$result['dirname'] = '';
		$result['attributes'] = '';
		$result['important'] = false;
		$result['size'] = '400';
		
		if ($stmt = $mysqli->prepare('SELECT name,automatic,searchstring,dirname,important,size,attributes FROM shows WHERE name=?')) {
			$stmt->bind_param('s', $result['name']);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows > 0) {
				$stmt->bind_result($result['name'], $result['automatic'], $result['searchstring'], $result['dirname'], $result['important'], $result['size'], $result['attributes']);
				$stmt->fetch();
			}
			$stmt->free_result();
		}

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
		
		if ($stmt = $mysqli->prepare('SELECT time FROM downloaded WHERE name=? and season=? and episode=?')) {
			$stmt->bind_param('sdd', $showname, $season, $episode);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows > 0) {
				$stmt->bind_result($result);
				$stmt->fetch();
			}
			$stmt->free_result();
		}
		
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
	}
?>