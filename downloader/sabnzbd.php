<?php
	//----------------------------------------------------------------------------
	// Downloader: sabnzbd
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// This downloader works by poking the sabnzbd api
	//----------------------------------------------------------------------------

	/**
	 * Download the given NZB.
	 *
	 * @param $nzbid
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadNZB($nzbid) {
		global $config;

		$username = isset($config['downloader']['sabnzbd']['username']) ? $config['downloader']['sabnzbd']['username'] : '';
		$password = isset($config['downloader']['sabnzbd']['password']) ? $config['downloader']['sabnzbd']['password'] : '';
		$category = isset($config['downloader']['sabnzbd']['category']) ? $config['downloader']['sabnzbd']['category'] : 'Automatic';
		$server = isset($config['downloader']['sabnzbd']['server']) ? $config['downloader']['sabnzbd']['server'] : '127.0.0.1';
		$port = isset($config['downloader']['sabnzbd']['port']) ? $config['downloader']['sabnzbd']['port'] : '8080';
		$apikey = isset($config['downloader']['sabnzbd']['apikey']) ? $config['downloader']['sabnzbd']['apikey'] : '';
		
		$extra = (!empty($username) && !empty($password)) ? '&ma_username='.$username.'&ma_password='.$password : '';
		
		$url = 'http://'.$server.':'.$port.'/sabnzbd/api?mode=addid&apikey='.$apikey.'&name='.$nzbid.$extra.'&cat='.$category;
		$output = file_get_contents($url);
		
		return array('output' => $output, 'status' => ($output == "ok\n"));
	}
?>