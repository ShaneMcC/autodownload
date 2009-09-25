<?php
	//----------------------------------------------------------------------------
	// Source: pogdesign.co.uk/cat
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// The pogdesign source is the original TV Calendar. on-my.tv source is
	// preferred, however sometimes this source has shows that on-my.tv does not.
	//
	// on-my.tv also sometimes experiences bugs due to being a live-site, these
	// bugs generally do not occur in the pogdesign source due to the original
	// coder for the calendar moving to on-my.tv leaving pogdesign as merely
	// hosting and importing on and older copy of the calendar source code.
	//----------------------------------------------------------------------------
	include_once(dirname(__FILE__).'/../../config.php');
	include_once(dirname(__FILE__).'/../../functions.php');

	$cat_savefile = __FILE__.'.cache';
	$cat_cachetime = $config['tv']['cachetime'];
	$cat_host = 'pogdesign.co.uk';
	$cat_page = 'http://'.$cat_host.'/cat/';
	
	if (isset($_REQUEST['extra'])) {
		$cat_page .= $_REQUEST['extra'];
	}
	
	function get_page() {
		global $cat_page, $cat_host;
		
		$postdata = "timezone=".urlencode("US/Central");
		$postdata .= "&style=0";
		$postdata .= "&s_sortbyname=0";
		$postdata .= "&s_numbers=1";
		$postdata .= "&s_sundayfirst=0";
		$postdata .= "&s_epnames=1";
		$postdata .= "&s_airtimes=0";
		$postdata .= "&s_networks=0";
		$postdata .= "&s_popups=0";
		$postdata .= "&s_wunwatched=0";
		$postdata .= "&settings=Save+Settings";
		
		$send  = "POST ".$cat_page." HTTP/1.0\r\n";
		$send .= "Host: ".$cat_host."\r\n";
		$send .= "User-Agent: evres-Replacement Generator (Dataforce@Dataforce.org.uk)\r\n";
		$send .= "Referer: ".$cat_page."\r\n";
		$send .= "Connection: Close\r\n";
		$send .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$send .= "Content-Length: ".strlen($postdata)."\r\n";
		$send .= "\r\n";
		$send .= $postdata;

		$fp = fsockopen($cat_host, 80);
		fputs ($fp, $send);
		while ($line = fread($fp,1024)) {
			$contents = $contents.$line;
		}
		fclose($fp);
		
		$pattern = "/(.*)Set-Cookie: CAT_UID=(.*); expires(.*)/U";
		if (preg_match($pattern, $contents, $matches)) {
			$send  = "GET ".$cat_page." HTTP/1.0\r\n";
			$send .= "Host: ".$cat_host."\r\n";
			$send .= "User-Agent: evres-Replacement Generator (Dataforce@Dataforce.org.uk)\r\n";
			$send .= "Referer: ".$cat_page."\r\n";
			$send .= "Cookie: CAT_UID=".$matches[2]."\r\n";
			$send .= "Connection: Close\r\n\r\n";
			
			$contents = '';

			$fp = fsockopen($cat_host, 80);
			fputs ($fp, $send);
			while ($line = fread($fp,1024)) {
				$contents = $contents.$line;
			}
			fclose($fp);
		}
	
		return $contents;
	}
	
	if (isset($_REQUEST['forcereload']) || !file_exists($cat_savefile) || !is_file($cat_savefile) || filemtime($cat_savefile) < time()-$cat_cachetime) {
		$cache = 'No';
		$contents = get_page();
		@file_put_contents($cat_savefile, $contents);
	} else {
		$cache = 'Yes ('.time().'/'.date('r', time()).') ['.$cat_savefile.']';
		$contents = file_get_contents($cat_savefile);
	}
	$lines = explode("\n", $contents);
	
	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
	echo "<tvcal>\n";
	echo "<cache>".$cache."</cache>\n";
	$source = str_replace('.php', '', basename(__FILE__));
	foreach ($lines as $line) {
		$pattern = '#href="/cat/day/([0-9]+-[0-9]+-[0-9]+).*?>(.*?)</a>.*?class=".*?">\'(.*?)\'</span>.*?class=".*?" >S: ([0-9]+) - Ep: ([0-9]+)#';
		
		if (preg_match($pattern, $line, $matches)) {
			$time = explode("-", $matches[1]);
			$time = mktime(0, 0, 0, intval($time[1]), intval($time[0]), intval($time[2]));
		
			$name = html_entity_decode($matches[2]);
			$season = $matches[4];
			$episode = $matches[5];
			$description = html_entity_decode($matches[3]);			
			echo "\t<show>\n";
			echo "\t\t<date time=\"".$time."\">".$matches[1]."</date>\n";
			echo "\t\t<name>".htmlspecialchars($name, ENT_QUOTES, 'UTF-8')."</name>\n";
			echo "\t\t<title>".htmlspecialchars($description, ENT_QUOTES, 'UTF-8')."</title>\n";
			echo "\t\t<season>".$season."</season>\n";
			echo "\t\t<episode>".$episode."</episode>\n";
			echo "\t</show>\n";
			
			addAirTime($name, $season, $episode, $description, $time, $source);
		}
	}
	echo "</tvcal>";

?>
