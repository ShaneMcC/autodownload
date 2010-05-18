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
	 * @param $name Optional name to pass
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadNZB($nzbid, $name = '') {
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


	/**
	 * Download the given NZB local file.
	 *
	 * @param $file
	 * @param $name
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadFromFile($file, $name = '') {
		global $config;

		$username = isset($config['downloader']['sabnzbd']['username']) ? $config['downloader']['sabnzbd']['username'] : '';
		$password = isset($config['downloader']['sabnzbd']['password']) ? $config['downloader']['sabnzbd']['password'] : '';
		$category = isset($config['downloader']['sabnzbd']['category']) ? $config['downloader']['sabnzbd']['category'] : 'Automatic';
		$server = isset($config['downloader']['sabnzbd']['server']) ? $config['downloader']['sabnzbd']['server'] : '127.0.0.1';
		$port = isset($config['downloader']['sabnzbd']['port']) ? $config['downloader']['sabnzbd']['port'] : '8080';
		$apikey = isset($config['downloader']['sabnzbd']['apikey']) ? $config['downloader']['sabnzbd']['apikey'] : '';

		$extra = (!empty($username) && !empty($password)) ? '&ma_username='.$username.'&ma_password='.$password : '';

		$url = 'http://'.$server.':'.$port.'/sabnzbd/api?mode=addfile&apikey='.$apikey.'&name='.$file.$extra.'&cat='.$category;

		if ($name != '') {
			// Rename the file for upload.
			$newFile = '/tmp/'.$name.'.nzb';
			$i = 1;
			while (file_exists($newFile)) {
				$newFile = '/tmp/'.$name.'('.$i++.').nzb';
			}
			rename($file, $newFile);
			$file = $newFile;
		}
		
		$ch = curl_init();
		$data = array('nzbfile' => '@'.$file);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		@unlink($file);

		return array('output' => $output, 'status' => ($output == "ok\n"));
	}
?>