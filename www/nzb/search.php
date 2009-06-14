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

	function searchFor($search) {
		global $global;
		$searchurl = 'http://v3.newzbin.com/search/query/?q='.urlencode($search);
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
		
		$searchurl .= '&searchaction=Go&feed=rss';
		
		if (isset($_REQUEST['fauth'])) {
			$searchurl .= '&fauth='.$_REQUEST['fauth'];
		}
		
		$page = file_get_contents($searchurl);
		$page = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2_$3", $page);
		$xml = simplexml_load_string($page);
		return $xml->channel;
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
	
	$count = count($data->item);
	
	echo "\t".'<results>'.CRLF;
	echo "\t\t".'<count>'.$count.'</count>'.CRLF;
	echo "\t\t".'<displayed>'.min($count, $limit).'</displayed>'.CRLF;
	echo "\t\t".'<pagecount>-1</pagecount>'.CRLF;
	echo "\t\t".'<totalresults>-1</totalresults>'.CRLF;
	echo "\t".'</results>'.CRLF;
	
	foreach ($data->item as $item) {
		$size = (int)$item->report_size;
		if ((string)$item->report_size['type'] == 'bytes') {
			$sizemb = $size/1024.0/1024.0;
			$mbcalc = "true";
		} elseif ((string)$item->report_size['type'] == 'kilobytes') {
			$sizemb = $size/1024.0;
			$mbcalc = "true";
		} else if ((string)$item->report_size['type'] == 'mega') {
			$sizemb = $size;
			$mbcalc = "false";
		}
	
		// Check requested sizes.
		if ((isset($_REQUEST['maxmb']) && $sizemb > $_REQUEST['maxmb']) || (isset($_REQUEST['minmb']) && $sizemb < $_REQUEST['minmb'])) {
			continue;
		}
		
		$nzbid = (int)$item->report_id;
		
		$attrs = array();
		foreach ($item->report_attributes->report_attribute as $attr) {
			$type = (string)$attr['type'];
			$attrs[str_replace(' ', '_', strtolower($type))][] = (string)$attr;
		}
		
		echo "\t".'<item>'.CRLF;
		echo "\t\t".'<nzbid>'.$nzbid.'</nzbid>'.CRLF;
		echo "\t\t".'<name>'.htmlspecialchars((string)$item->title).'</name>'.CRLF;
		echo "\t\t".'<category>'.htmlspecialchars((string)$item->report_category).'</category>'.CRLF;
		
		foreach ($item->report_groups->report_group as $group) {
			echo "\t\t".'<group>'.htmlspecialchars($group).'</group>'.CRLF;
		}
		
		if (isset($attrs['langauge'])) {
			foreach ($attrs['langauge'] as $lang) {
				echo "\t\t".'<language>'.htmlspecialchars($lang).'</language>'.CRLF;
			}
		}
		
		echo "\t\t".'<sizemb calculated="'.$mbcalc.'">'.$sizemb.'</sizemb>'.CRLF;
		echo "\t\t".'<url>http://v3.newzbin.com/browse/post/'.$nzbid.'/</url>'.CRLF;
		echo "\t\t".'<posted exact="'.htmlspecialchars((string)$item->report_postdate).'" />'.CRLF;
		echo "\t\t".'<reported exact="'.htmlspecialchars((string)$item->pubDate).'" />'.CRLF;
		echo "\t\t".'<status description="'.htmlspecialchars((string)$item->report_progress).'">'.getStaus((int)$item->report_progress['value']).'</status>'.CRLF;
		
		echo "\t\t".'<comments count="'.(int)$item->report_stats->report_comments.'">http://v3.newzbin.com/browse/post/'.$nzbid.'/comments/</comments>'.CRLF;
		
		if ($report->report_moreinfo) {
			echo "\t\t".'<infolink>'.htmlspecialchars((string)$report->report_moreinfo).'</infolink>'.CRLF;
		}
		if ($report_nfo->report_link) {
			echo "\t\t".'<nfo>'.htmlspecialchars((string)$report_nfo->report_link).'</nfo>'.CRLF;
		}

		echo "\t\t".'<reportinfo url="http://v3.newzbin.com/backend/reportinfo/'.$nzbid.'">'.CRLF;
		foreach ($attrs as $type => $values) {
			foreach ($values as $value) {
				echo "\t\t\t".'<'.$type.' id="-1">'.htmlspecialchars($value).'</'.$type.'>'.CRLF;
			}
		}
		echo "\t\t".'</reportinfo>'.CRLF;
		echo "\t".'</item>'.CRLF;
	}

	die('</nzb>'.CRLF);
?>