<?php
	/**
	 * Newzbin searching.
	 *
	 * This searches newzbin using by scraping the html for the search result
	 * page. It requires a valid username and password, is much slower than using
	 * the rss feed, occasionally may require a captcha but knows everything about
	 * all the reports and the results.
	 *
	 * Using this is against the terms of service of newzbin.
	 */
	define('CRLF', "\r\n");
	
	$global['default_username'] = 'unknown';
	$global['default_password'] = 'unknown';
	// $global['proxy'] = '192.168.0.5:23128';

	/**
	 * Remove slashes added by magic quotes if enabled.
	 * If magic quotes is not enabled, $text will be returned.
	 *
	 * @param $text Text to remove slashes from
	 * @return $text without any magic-quotes induced slashes.
	 */
	function unslash($text) {
		if (get_magic_quotes_gpc()) {
			if (is_array($text)) {
				foreach ($text as $key => $value) {
					$text[$key] = unslash($value);
				}
				return $text;
			} else {
				return stripslashes($text);
			}
		} else {
			return $text;
		}
	}

	
	$global['username'] = isset($_REQUEST['username']) ? $_REQUEST['username'] : $global['default_username'];
	$global['password'] = isset($_REQUEST['password']) ? $_REQUEST['password'] : $global['default_password'];

	@include_once('NZBMCookies.'.$global['username'].'.php');
	
	function storeCookies($cookies = array()) {
		global $global;
		
		if (empty($cookies)) {
			$cookies = $global[$global['username']]['cookies'];
		}
		
		$value = '<?php'.CRLF;

		foreach ($cookies as $k => $v) {
			$value .= "\t".'$global[\''.$global['username'].'\'][\'cookies\'][\''.$k.'\'] = \''.$v.'\';'.CRLF;
		}
		if (isset($global['scline'])) {
			$value .= '/*'.CRLF;
			foreach ($global['scline'] as $line) {
				$value .= "\t".'$global[\''.$global['username'].'\'][\'scline\'][] = \''.trim($line).'\';'.CRLF;	
			}
			$value .= '*/'.CRLF;
		}
		$value .= '?>';
		@file_put_contents('NZBMCookies.'.$global['username'].'.php', $value);
		
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
//		if (isset($global['proxy'])) { $contextArray['http']['proxy'] = 'tcp://'.$global['proxy']; }
		$context = stream_context_create($contextArray);
		return array('cookies' => $cookies, 'cookiestring' => $cookiestring, 'page' => @file_get_contents($page, FILE_TEXT, $context));
	}
	
	function searchFor($id) {
		global $global;
	
		$downloadURL = '/nzb-download.php?nozip=1&id='.urlencode($id);

		$loginurl = '/account-login.php';
		$address = 'nzbmatrix.com';
		$data = 'username='.$global['username'].'&password='.$global['password'];

		if (isset($_REQUEST['logincookies']) || isset($global[$global['username']]['cookies'])) {
			$cookies = isset($global[$global['username']]['cookies']) ? $global[$global['username']]['cookies'] : $_REQUEST['logincookies'];
			$result = get_page('http://'.$address.$downloadURL, $cookies);
			$global[$global['username']]['cookies'] = $cookies;
			$returnresult = $result['page'];
			
			if (preg_match('@<form method="post" action="/account-login.php">@U', $returnresult)) {
				unset($global[$global['username']]['cookies']);
				$returnresult = searchFor($id);
			}
			
			return $returnresult;
		} else {
			$fp = fsockopen($address, 80, $errno, $errstr, 30);
			if ($fp) {
				$global['scline'] = array();;
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
					$line = trim(fgets($fp));
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
				
				$result = get_page('http://'.$address.$downloadURL, $cookies);
				$global[$global['username']]['cookies'] = $result['cookies'];
				storeCookies($cookies);
				return $result['page'];
			}
		}
		return '';
	}

	$data = searchFor($_REQUEST['id']);

	if (isset($_REQUEST['getTitle'])) {
		// We also need a title, so request the page content.
		$cookies = isset($global[$global['username']]['cookies']) ? $global[$global['username']]['cookies'] : $_REQUEST['logincookies'];
		$result = get_page('http://nzbmatrix.com/nzb-details.php?hit=1&id=' . $_REQUEST['id'], $cookies);
		$pagecontent = $result['page'];

		// Get the title from the page of details.
		$titlepattern = '#<title>NZBMatrix: NZB Usenet Newsgroups Search Index : ([^<]+)</title>#';
		if (preg_match($titlepattern, $pagecontent, $matches)) {
			header('X-NZB-Title: ' . $matches[1]);
		}
	}
	
	if ($data === false) {
		die();
	} else if (preg_match('@<form method="post" action="/account/login/">@U', $data)) {
		die("\t".'<error message="Unable to login."/>'.CRLF);
	}
	header("Content-type: application/x-nzb");
	echo $data;
?>
