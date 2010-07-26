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
	$global['proxy'] = '192.168.0.5:23128';
	
	if (isset($_REQUEST['help'])) {
		header("Content-Type: text/html; charset=utf-8");
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">'.CRLF;
		echo '<html><head><title>Newzbin Search</title></head><body><div>'.CRLF;
		echo '<h2>Newzbin Search</h2>'.CRLF;
		echo 'This page allows you to search newzbin and get the results back in a nice XML Format.<br><br>'.CRLF;
		echo 'This page accepts the following parameters (* is required):<br>'.CRLF;
		echo '<ul>'.CRLF;
		echo '	<li> <strong>search</strong>* - String to search for'.CRLF;
		echo '	<li> <strong>limit</strong> - Limit to number of results. (default: 10)'.CRLF;
		echo '	<li> <strong>page</strong> - Request a specific result page number from newzbin.'.CRLF;
		echo '	<li> <strong>maxmb</strong> - Maximum MB to return in output.'.CRLF;
		echo '	<li> <strong>minmb</strong> - Minimum MB to return in output.'.CRLF;
		echo '	<li> <strong>getinfo</strong> - Should the reportinfo be parsed aswell? (will slow down output, as it requires 1 additional http query per result) [no value required]'.CRLF;
		echo '	<li> <strong>getinfolimit</strong> - Only parse the reportinfo for the first getinfolimit results (default: 5)'.CRLF;
		echo '	<li> <strong>nosort</strong> - Return the results in what ever order newzbin decides (what ever is the default for the account that is being used) [no value required]'.CRLF;
		echo '	<li> <strong>timesort</strong> - Sort results by time, with newest first (same as sorttype=ps_edit_date, sortdirection=desc) [no value required]'.CRLF;
		echo '	<li> <strong>sizesort</strong> - (Default) Sort results by size, with smallest first (same as sorttype=ps_totalsize, sortdirection=asc) [no value required]'.CRLF;
		echo '	<li> <strong>sorttype</strong> - Newzbin search type. If none of the *sort parameters have been given, this will be used to sort the results (default: ps_totalsize)'.CRLF;
		echo '	<li> <strong>sortdirection</strong> - Newzbin search type. If none of the *sort parameters have been given, this will be used to sort the results (default: asc)'.CRLF;
		echo '	<li> <strong>username</strong> - Specify a username to login with other than default.'.CRLF;
		echo '	<li> <strong>password</strong> - Specify a password to login with other than default.'.CRLF;
		echo '</ul>'.CRLF;
		echo '<h2>Login</h2>'.CRLF;
		echo 'After a successful login, the cookies used are retained for future requests to prevent needing to login again for every search. Cookies will be kept untill expiry, or a new login is required.'.CRLF;
		echo '<br><br>'.CRLF;
		echo 'Cookies are saved per-username, and the username/password pair will not used unless a login is required.'.CRLF;
		echo '<br><br>'.CRLF;
		echo 'Sometimes newzbin requires a captcha when trying to login. If a captcha is required, the captcha will be presented using a HTML form that needs to be submitted in order to continue the search.'.CRLF;
		echo '<br>'.CRLF;
		echo 'Submission using either GET or POST is accepted.'.CRLF;
		echo '</div></html>';
		die();
	}
	
	$global['username'] = isset($_REQUEST['username']) ? $_REQUEST['username'] : $global['default_username'];
	$global['password'] = isset($_REQUEST['password']) ? $_REQUEST['password'] : $global['default_password'];
	
	function checkForCaptcha() {
		global $global;
		$page = implode("\n", get_page('http://v3.newzbin.com/', $global[$global['username']]['cookies']));
		
		$pattern = '/<input type="hidden" name="CaptchaID" value="([0-9]+)" \/>/';
		
		if (preg_match($pattern, $page, $matches)) {
			$global['captcha'] = '';
			$global['captchaid'] = $matches[1];
			return false;
		} else {
			return true;
		}
	}
	
	if (isset($_REQUEST['captcha'])) {
		$global['captcha'] = $_REQUEST['captcha'];
		$global['captchaid'] = $_REQUEST['captchaid'];
	} else {
		@include_once('NZBCookies.'.$global['username'].'.php');
		
		if (!checkForCaptcha()) {
			header("Content-Type: text/html; charset=utf-8");
			echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">'.CRLF;
			echo '<html><head><title>Newzbin Search - Captcha Required</title></head><body><div>'.CRLF;
			echo "<h2>Captcha Required</h2>";
			echo "<img alt=\"Captcha ID: ".$global['captchaid']."\" src=\"http://v3.newzbin.com/keyword/?id=".$global['captchaid']."\">";
			
			echo '<form method="POST" action=""><div>';
			echo '	Captcha: <input name="captcha" size=25><br>';
			
			function showHiddenParam($param, $default = '') {
				if ($name == '') { $name = $param; }
				if (isset($_REQUEST[$param])) {
					if ($default != '' && $_REQUEST[$param] != $default) {
						echo "\t".'<input type="hidden" name="'.$param.'" value="'.htmlspecialchars($_REQUEST[$param]).'">'.CRLF;
					}
				}
			}
			
			showHiddenParam($_REQUEST['search']);
			showHiddenParam($_REQUEST['limit']);
			showHiddenParam($_REQUEST['page']);
			showHiddenParam($_REQUEST['maxmb']);
			showHiddenParam($_REQUEST['minmb']);
			showHiddenParam($_REQUEST['getinfo']);
			showHiddenParam($_REQUEST['getinfolimit']);
			showHiddenParam($_REQUEST['nosort']);
			showHiddenParam($_REQUEST['timesort']);
			showHiddenParam($_REQUEST['sizesort']);
			showHiddenParam($_REQUEST['sorttype']);
			showHiddenParam($_REQUEST['sortdirection']);
			showHiddenParam($_REQUEST['username'], $global['default_username']);
			showHiddenParam($_REQUEST['password'], $global['default_password']);
			
			echo '	<input type="hidden" name="captchaid" value="'.htmlspecialchars($global['captchaid']).'">';
			echo '	<input class="submit" type="Submit" value="Try Again">';
			echo '</div></form>';
			echo '</div></html>';
			
			die();
		}
	}
	
	function storeCookies($cookies = array()) {
		global $global;
		
		if (empty($cookies)) {
			$cookies = $global[$global['username']]['cookies'];
		}
		
		$value = '<?php'.CRLF;
		$value .= "\t".'$global[\''.$global['username'].'\'][\'cookies\'][\'NzbSessionID\'] = \''.$cookies['NzbSessionID'].'\';'.CRLF;
		$value .= "\t".'$global[\''.$global['username'].'\'][\'cookies\'][\'NzbSmoke\'] = \''.$cookies['NzbSmoke'].'\';'.CRLF;
		if (isset($global['scline'])) {
			$value .= '/*'.CRLF;
			foreach ($global['scline'] as $line) {
				$value .= "\t".'$global[\''.$global['username'].'\'][\'scline\'][] = \''.trim($line).'\';'.CRLF;	
			}
			$value .= '*/'.CRLF;
		}
		$value .= '?>';
		@file_put_contents('NZBCookies.'.$global['username'].'.php', $value);
		
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
	
	function searchFor($search) {
		global $global;
		
		$searchurl = '/search/?searchaction=Go&q='.urlencode($search);
		if (!isset($_REQUEST['nosort'])) {
			$type = isset($_REQUEST['sorttype']) ? trim($_REQUEST['sorttype']) : 'ps_totalsize';
			$direction = isset($_REQUEST['sortdirection']) ? trim($_REQUEST['sortdirection']) : 'asc';
			
			if (isset($_REQUEST['timesort'])) {
				$type = 'ps_edit_date';
				$direction = 'desc';
			} else if (isset($_REQUEST['sizesort'])) {
				$type = 'ps_totalsize';
				$direction = 'asc';
			}
			
			$searchurl .= '&sort='.$type.'&order='.$direction;
		}
		if (isset($_REQUEST['page'])) {
			$searchurl .= '&page='.$_REQUEST['page'];
		}
		
		$loginurl = '/account/login/';
		$address = 'www.newzbin.com';
		$captcha = $global['captcha'];
		$captchaid = $global['captchaid'];
		$data = 'ret_url='.urlencode($searchurl).'&username='.$global['username'].'&password='.$global['password'].'&keyword='.$captcha.'&CaptchaID='.$captchaid;

		if (isset($_REQUEST['logincookies']) || isset($global[$global['username']]['cookies'])) {
			$cookies = isset($global[$global['username']]['cookies']) ? $global[$global['username']]['cookies'] : $_REQUEST['logincookies'];
			$result = get_page('http://'.$address.$searchurl, $cookies);
			$global[$global['username']]['cookies'] = $cookies;
			
			$returnresult = $result['page'];
			
			if (preg_match('@<form method="post" action="/account/login/">@U', $returnresult)) {
				unset($global[$global['username']]['cookies']);
				$returnresult = searchFor($search);
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
				
				$result = get_page('http://'.$address.$searchurl, $cookies);
				$global[$global['username']]['cookies'] = $result['cookies'];
				storeCookies($cookies);
				return $result['page'];
			}
		}
		return '';
	}
	
	header("Content-type: text/xml");
	echo '<?xml version="1.0" encoding="ISO-8859-1" ?>'.CRLF;
	echo '<nzb>'.CRLF;

	if (!isset($_REQUEST['search'])) {
		echo "\t".'<error message="No Search String"/>'.CRLF;
		die('</nzb>'.CRLF);
	} else {
		function showParam($param, $name = '') {
			if ($name == '') { $name = $param; }
			if (isset($_REQUEST[$param])) {
				if (empty($_REQUEST[$param])) {
					echo "\t\t".'<'.$name.'/>'.CRLF;
				} else {
					echo "\t\t".'<'.$name.'>'.$_REQUEST[$param].'</'.$name.'>'.CRLF;
				}
			}
		}
		echo "\t".'<search>'.CRLF;
		showParam('searchid');
		showParam('search', 'request');
		showParam('limit');
		showParam('minmb');
		showParam('maxmb');
		showParam('getinfo');
		showParam('getinfolimit');
		showParam('page');
		showParam('nosort');
		showParam('timesort');
		showParam('sizesort');
		showParam('sorttype');
		showParam('sortdirection');

//		showParam('username');
//		showParam('password');
//		showParam('logincookies');
		echo "\t".'</search>'.CRLF;
	}
	
	$limit = (isset($_REQUEST['limit'])) ? $_REQUEST['limit'] : 10 ;
	$getinfolimit = (isset($_REQUEST['getinfolimit'])) ? $_REQUEST['getinfolimit'] : 5 ;
	
	$data = searchFor($_REQUEST['search']);
	if ($data === false) {
		echo "\t".'<error message="Unable to access newzbin"/>'.CRLF;
		die('</nzb>'.CRLF);
	} else if (preg_match('@<form method="post" action="/account/login/">@U', $data)) {
		echo "\t".'<error message="Unable to login."/>'.CRLF;
		die('</nzb>'.CRLF);
	}
	$data = str_replace("\r", '', $data);
	$data = str_replace("\n", '', $data);
	
//	echo "<!--";
//	print_r($data);
//	echo "-->";
	
	$metapattern = '<h3>.*of (.*) reports</h3>';
	$metapattern .= '(?:.*<a rel="last" href=".*page=([0-9]+).*">last</a>)?';

	preg_match('@'.$metapattern.'@U', $data, $metamatches);

	$totalresults = str_replace(',', '', $metamatches[1]);
	$pagecount = (isset($metamatches[2]) && !empty($metamatches[2])) ? $metamatches[2] : 1;

//	echo "<!--";
//	print_r($metamatches);
//	echo "-->";

	$pattern = '<tr class="borderTop">';
	$pattern .= '.*href="/browse/category/[^/]+/[^/]+/">([^<]+)</a>'; // Category
	$pattern .= '.*<a href="/browse/post/(.*)/">(.*)</a>'; // Post ID and Name
	$pattern .= '.*<span title="Exact date/time: ([^"]+)" class="[^"]+">([^<]+)</span>'; // Post Age
	$pattern .= '.*<span title="Exact date/time: ([^"]+)" class="[^"]+">([^<]+)</span>'; // Report Age
	$pattern .= '.*src="/m/i/i/progress/(.*).png" title="(.*)" alt="(.*)" />'; // Status Type, Text, Value
	$pattern .= '.*class="fileSize".*<span>(.*)?([K|M]B)</span>.*</td>'; // Size and SizeType
	
	$grouppattern = 'Please confirm you want to exclude ([^?]+)\?';
	$langpattern = 'alt="([^"]+)" src="/m/i/flags/(.+).gif"';
	$commentpattern = 'onmouseout="hideInfoBox\(\'comments[0-9]+\'\)">(.*) comments?';
	$urlpattern = 'href="/r/\?([^"]+)"';
	$nfopattern = '/nfo/view/(.*)/(.*)/';
	
	$i = preg_match_all('@'.$pattern.'@U', $data, $matches);
	
	$id = 0;
	$matchid['all'] = $id++;
	$matchid['category'] = $id++;
	$matchid['id'] = $id++;
	$matchid['name'] = $id++;
	$matchid['postexactage'] = $id++;
	$matchid['postage'] = $id++;
	$matchid['reportexactage'] = $id++;
	$matchid['reportage'] = $id++;
	$matchid['statustype'] = $id++;
	$matchid['statustext'] = $id++;
	$matchid['statusvalue'] = $id++;
	$matchid['size'] = $id++;
	$matchid['sizetype'] = $id++;
	
//	echo "<!--";
//	print_r($matches);
//	echo "-->";

	echo "\t".'<results>'.CRLF;
//	echo "\t\t".'<url>'.htmlentities($nzburl).'</url>'.CRLF;
	echo "\t\t".'<count>'.$i.'</count>'.CRLF;
//	echo "\t\t".'<cookies>'.$global[$global['username']]['cookies'].'</cookies>'.CRLF;
	echo "\t\t".'<displayed>'.min($i, $limit).'</displayed>'.CRLF;
	echo "\t\t".'<pagecount>'.$pagecount.'</pagecount>'.CRLF;
	echo "\t\t".'<totalresults>'.$totalresults.'</totalresults>'.CRLF;
	echo "\t".'</results>'.CRLF;

	for ($j = 0; $j != min($i, $limit); $j++) {
		if ($matches[$matchid['sizetype']][$j] == "KB") {
			$sizemb = $matches[$matchid['size']][$j]/1024.0;
			$mbcalc = "true";
		} elseif ($matches[$matchid['sizetype']][$j] == "MB") {
			$sizemb = $matches[$matchid['size']][$j];
			$mbcalc = "false";
		}
	
		// Check requested sizes.
		if ((isset($_REQUEST['maxmb']) && $sizemb > $_REQUEST['maxmb']) || (isset($_REQUEST['minmb']) && $sizemb < $_REQUEST['minmb'])) {
			continue;
		}
		
		$nzbid = trim($matches[$matchid['id']][$j]);
		
		echo "\t".'<item>'.CRLF;
		echo "\t\t".'<nzbid>'.$nzbid.'</nzbid>'.CRLF;
		echo "\t\t".'<name>'.trim($matches[$matchid['name']][$j]).'</name>'.CRLF;
		echo "\t\t".'<category>'.trim($matches[$matchid['category']][$j]).'</category>'.CRLF;
		
		$k = preg_match_all('@'.$grouppattern.'@U', $matches[$matchid['all']][$j], $groupmatches);
		for ($l = 0; $l != $k; $l++) {
			echo "\t\t".'<group>'.trim($groupmatches[1][$l]).'</group>'.CRLF;
		}
		
		$k = preg_match_all('@'.$langpattern.'@U', $matches[$matchid['all']][$j], $langmatches);
		for ($l = 0; $l != $k; $l++) {
			echo "\t\t".'<language>'.trim($langmatches[1][$l]).'</language>'.CRLF;
		}
		
		echo "\t\t".'<sizemb calculated="'.$mbcalc.'">'.$sizemb.'</sizemb>'.CRLF;
		echo "\t\t".'<url>http://v3.newzbin.com/browse/post/'.$nzbid.'/</url>'.CRLF;
		echo "\t\t".'<posted exact="'.trim($matches[$matchid['postexactage']][$j]).'">'.trim($matches[$matchid['postage']][$j]).'</posted>'.CRLF;
		echo "\t\t".'<reported exact="'.trim($matches[$matchid['reportexactage']][$j]).'">'.trim($matches[$matchid['reportage']][$j]).'</reported>'.CRLF;
		echo "\t\t".'<status description="'.trim($matches[$matchid['statustext']][$j]).'">'.trim($matches[$matchid['statusvalue']][$j]).'</status>'.CRLF;
		
		$commentnumber = 0;
		if (preg_match('@'.$commentpattern.'@U', $matches[$matchid['all']][$j], $commentmatches)) {
			$commentnumber = trim($commentmatches[1]);
		}
		echo "\t\t".'<comments count="'.$commentnumber.'">http://v3.newzbin.com/browse/post/'.$nzbid.'/comments/</comments>'.CRLF;
		
		if (preg_match('@'.$urlpattern.'@U', $matches[$matchid['all']][$j], $urlmatches)) {
			echo "\t\t".'<infolink>'.trim($urlmatches[1]).'</infolink>'.CRLF;
		}
		
		if (preg_match('@'.$nfopattern.'@U', $matches[$matchid['all']][$j], $nfomatches)) {
			echo "\t\t".'<nfo>http://v3.newzbin.com/nfo/view/'.trim($nfomatches[1]).'/'.trim($nfomatches[2]).'</nfo>'.CRLF;
		}
		
		if (isset($_REQUEST['getinfo']) && $getinfolimit > 0) {
			$getinfolimit--;
			echo "\t\t".'<reportinfo url="http://v3.newzbin.com/backend/reportinfo/'.$nzbid.'">'.CRLF;
			$reportinfo = get_page('http://v3.newzbin.com/backend/reportinfo/'.$nzbid, $global[$global['username']]['cookies']);
			$reportinfo = $reportinfo['page'];
			
			if ($reportinfo === false) {
				echo "\t\t\t".'<error message="Unable to access reportinfo"/>'.CRLF;
			} else {
				$reportinfo = str_replace("\r", '', $reportinfo);
				$reportinfo = str_replace("\n", '', $reportinfo);
				
//				echo "<!--";
//				print_r($reportinfo);
//				echo "-->";
				
				$k = preg_match_all('@<dd><a href="\?ps_rb_(.*)=(.*)">(.*)</a></dd>@U', $reportinfo, $attributes);
				for ($l = 0; $l != $k; $l++) {
					$type = trim($attributes[1][$l]);
					$id = trim($attributes[2][$l]);
					$name = trim($attributes[3][$l]);
					
					echo "\t\t\t".'<'.$type.' id="'.$id.'">'.$name.'</'.$type.'>'.CRLF;
				}
				
				preg_match_all('@<dd>([^<].*)</dd>@U', $reportinfo, $samplefiles);
				
				foreach ($samplefiles[1] as $sample) {
					echo "\t\t\t".'<samplefile>'.$sample.'</samplefile>'.CRLF;
				}
			}
			echo "\t\t".'</reportinfo>'.CRLF;
		} else {
			echo "\t\t".'<reportinfo url="http://v3.newzbin.com/backend/reportinfo/'.$nzbid.'"/>'.CRLF;
		}
		echo "\t".'</item>'.CRLF;
	}

	die('</nzb>'.CRLF);
?>