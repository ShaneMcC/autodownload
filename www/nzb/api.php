<?php
	/**
	 * Newzbin searching.
	 *
	 * This searches newzbin using the RSS Feeds rather than scraping the html for
	 * the search result page. It is quicker than scraping, but does not know the
	 * attributes associated with a report, or how many results in total there
	 * are.
	 */
	define('CRLF', "\r\n");
	$global['default_username'] = 'unknown';
	$global['default_password'] = 'unknown';
	$global['username'] = isset($_REQUEST['username']) ? $_REQUEST['username'] : $global['default_username'];
	$global['password'] = isset($_REQUEST['password']) ? $_REQUEST['password'] : $global['default_password'];

	/**
	 * Search using the newzbin API.
	 *
	 * @param $search String to search for.
	 */
	function searchFor($search) {
		global $config, $global;
		$ch = curl_init();
		$data = array();
		$data['username'] = $global['username'];
		$data['password'] = $global['password'];
		$data['query'] = $search;

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

			$data['sortfield'] = $type;
			$data['sortorder'] = $direction;
		}

		$data['retention'] = isset($_REQUEST['retention']) ? trim($_REQUEST['retention']) : 300;
		$data['limit'] = isset($_REQUEST['limit']) ? trim($_REQUEST['limit']) : 500;
		$data['offset'] = isset($_REQUEST['page']) ? $page * $data['limit'] : 0;

		curl_setopt($ch, CURLOPT_URL, 'http://www.newzbin.com/api/reportfind/');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$page = curl_exec($ch);

		return $page;
	}

	/**
	 * Get the ext description for the given status code if known, else return
	 * the status code.
	 *
	 * @param $code The code to get the description for
	 * @return Description, or code.
	 */
	function getStaus($code) {
		switch ($code) {
			case 1:
				return "Completed";
			default:
				return $code;
		}
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
		echo "\t".'</search>'.CRLF;
	}

	$limit = (isset($_REQUEST['limit'])) ? $_REQUEST['limit'] : 10 ;

	$data = searchFor($_REQUEST['search']);

	if ($data === false) {
		echo "\t".'<error message="Unable to access newzbin"/>'.CRLF;
		die('</nzb>'.CRLF);
	}

	$data = explode("\n", $data);
	$total = array_shift($data);
	$total = explode("=", $total);
	
	$count = $total[1];

	echo "\t".'<results>'.CRLF;
	echo "\t\t".'<count>'.$count.'</count>'.CRLF;
	echo "\t\t".'<displayed>'.min($count, $limit).'</displayed>'.CRLF;
	echo "\t\t".'<pagecount>-1</pagecount>'.CRLF;
	echo "\t\t".'<totalresults>-1</totalresults>'.CRLF;
	echo "\t".'</results>'.CRLF;

	foreach ($data as $item) {
		$bits = explode("\t", $item);
		
		$size = (int)$bits[1];
		$sizemb = $size/1024.0/1024.0;
		$mbcalc = "true";

		// Check requested sizes.
		if ((isset($_REQUEST['maxmb']) && $sizemb > $_REQUEST['maxmb']) || (isset($_REQUEST['minmb']) && $sizemb < $_REQUEST['minmb'])) {
			continue;
		}

		$nzbid = (int)$bits[0];
		if ($nzbid > 0) {
			echo "\t".'<item>'.CRLF;
			echo "\t\t".'<nzbid>'.$nzbid.'</nzbid>'.CRLF;
			echo "\t\t".'<name>'.htmlspecialchars($bits[2]).'</name>'.CRLF;
			echo "\t\t".'<sizemb calculated="'.$mbcalc.'">'.$sizemb.'</sizemb>'.CRLF;
			echo "\t\t".'<url>http://v3.newzbin.com/browse/post/'.$nzbid.'/</url>'.CRLF;
			echo "\t\t".'<reportinfo url="http://v3.newzbin.com/backend/reportinfo/'.$nzbid.'" />'.CRLF;
			echo "\t".'</item>'.CRLF;
		}
	}

	die('</nzb>'.CRLF);
?>