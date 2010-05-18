<?php
	/**
	 * Newzbin bookmarking.
	 *
	 * This downloads NZBs by bookmarking them, which some clients then can use
	 * to download the NZBs.
	 *
	 * This will fail to work if newzbin randomly logs you out and decides it
	 * needs a captcha.
	 *
	 * Using this is against the terms of service of newzbin.
	 */
	
	// This requires config settings for:
	//     $global['bookmarker']['username'] = '';
	//     $global['bookmarker']['pasword'] = '';
	
	function checkForCaptcha() {
		global $global;
		$page = implode("\n", get_page('http://v3.newzbin.com/', $global[$global['bookmarker']['username']]['cookies']));
		
		$pattern = '/<input type="hidden" name="CaptchaID" value="([0-9]+)" \/>/';
		
		if (preg_match($pattern, $page, $matches)) {
			$global['captcha'] = '';
			$global['captchaid'] = $matches[1];
			return false;
		} else {
			return true;
		}
	}

	@include_once('NZBCookies.'.$global['bookmarker']['username'].'.php');	

	function storeCookies($cookies = array()) {
		global $global;
		
		if (empty($cookies)) {
			$cookies = $global[$global['bookmarker']['username']]['cookies'];
		}
		
		$value = '<?php'.CRLF;
		$value .= "\t".'$global[\''.$global['bookmarker']['username'].'\'][\'cookies\'][\'NzbSessionID\'] = \''.$cookies['NzbSessionID'].'\';'.CRLF;
		$value .= "\t".'$global[\''.$global['bookmarker']['username'].'\'][\'cookies\'][\'NzbSmoke\'] = \''.$cookies['NzbSmoke'].'\';'.CRLF;
		if (isset($global['scline'])) {
			$value .= '/*'.CRLF;
			foreach ($global['scline'] as $line) {
				$value .= "\t".'$global[\''.$global['bookmarker']['username'].'\'][\'scline\'][] = \''.trim($line).'\';'.CRLF;	
			}
			$value .= '*/'.CRLF;
		}
		$value .= '?>';
		@file_put_contents('NZBCookies.'.$global['bookmarker']['username'].'.php', $value);
		
		// echo $value;
	}
	
	function get_page($page, $cookies) {
		global $global;
		
		if (is_array($cookies)) {
			$cookiestring = '';
			foreach ($cookies as $name => $value) {
				if ($cookiestring != '') { $cookiestring .= '; '; }
				$cookiestring .= $name;
				$cookiestring .= '=';
				$cookiestring .= $value;
			}
		} else {
			$cookiestring = $cookies;
		}
		
		// Now that we have the cookies, lets search.
		$contextArray = array('http' => array('header' => 'Cookie: '.$cookiestring.CRLF));
		$context = stream_context_create($contextArray);
		return array('cookies' => $cookies, 'cookiestring' => $cookiestring, 'page' => @file_get_contents($page, FILE_TEXT, $context));
	}

	/**
	 * Download the given NZB.
	 *
	 * @param $nzbid
	 * @param $name Optional name to pass
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadNZB($nzbid, $name = '') {
		global $global;
		
		if (!checkForCaptcha()) { return array('output' => 'Need Captcha.', 'status' => false); }
		$searchurl = '/account/bookmarks/add/?ps_id='.urlencode($nzbid).'&popup=0';
		
		$loginurl = '/account/login/';
		$address = 'v3.newzbin.com';
		$data = 'ret_url='.urlencode('/').'&username='.$global['bookmarker']['username'].'&password='.$global['bookmarker']['pasword'];

		if (isset($_REQUEST['logincookies']) || isset($global[$global['bookmarker']['username']]['cookies'])) {
			$cookies = isset($global[$global['bookmarker']['username']]['cookies']) ? $global[$global['bookmarker']['username']]['cookies'] : $_REQUEST['logincookies'];
			$result = get_page('http://'.$address.$searchurl, $cookies);
			$global[$global['bookmarker']['username']]['cookies'] = $cookies;
			
			$returnresult = array('output' => 'Success', 'status' => (bool)preg_match('#The Report was added to your Bookmarks#', $result['page']));
			
			if (preg_match('@<form method="post" action="/account/login/">@U', $result['page'])) {
				unset($global[$global['bookmarker']['username']]['cookies']);
				$returnresult = downloadNZB($nzbid);
			}
			
			return $returnresult;
		} else {
			$fp = fsockopen($address, 80, $errno, $errstr, 30);
			if ($fp) {
				$global['scline'] = array();
				$out = 'POST '.$loginurl.' HTTP/1.0'.CRLF;
				$out .= 'Host: '.$address.CRLF;
				$out .= 'Connection: Close'.CRLF;
				$out .= 'Content-type: application/x-www-form-urlencoded'.CRLF;
				$out .= 'Content-Length: '.strlen($data).CRLF;
				$out .= CRLF;
				$out .= $data;
	
				$cookies = array();

				fwrite($fp, $out);
				while (!feof($fp)) {
					$line = fgets($fp);
					if ($line == '') {
						break;
					} else {
						$bits = explode(':', $line, 2);
						if (strtolower($bits[0]) == 'set-cookie') {
							$global['scline'][] = $line;
							$bits = explode(';', $bits[1], 2);
							$bits = explode('=', $bits[0], 2);
							$cookies[trim($bits[0])] = trim($bits[1]);
						} else if (strtolower($bits[0]) == 'location') {
							get_page(trim($bits[1]), $cookies);
						}
					}
				}
				fclose($fp);
				
				$res = get_page('http://'.$address.$searchurl, $cookies);
				$global[$global['bookmarker']['username']]['cookies'] = $result['cookies'];
				storeCookies($cookies);
				
				return array('output' => 'Success', 'status' => (bool)preg_match('#The Report was added to your Bookmarks#', $res['page']));
			}
		}
		return array('output' => 'Nothing Worked.', 'status' => false);
	}

	/**
	 * Download the given NZB local file.
	 *
	 * @param $file
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadFromFile($file) {
		$result['output'] = array();
		$result['status'] = false;
		return $result;
	}
?>