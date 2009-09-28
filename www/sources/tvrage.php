<?php
	//----------------------------------------------------------------------------
	// Source: TVRage.com
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// The TVRage source provides alot more shows, but at the cost of only being
	// able to show this week,
	//----------------------------------------------------------------------------
	include_once(dirname(__FILE__).'/../../config.php');
	include_once(dirname(__FILE__).'/../../functions.php');

	if (!isset($cache_special)) { $cache_special = ''; }
	if (!isset($special)) { $special = ''; }

	$savefile = __FILE__.'.cache'.$cache_special;
	$cachetime = $config['tv']['cachetime'];
	
	if (isset($_REQUEST['forcereload']) || !file_exists($savefile) || !is_file($savefile) || filemtime($savefile) < time()-$cachetime) {
		$cache = 'No';
		$contents = file_get_contents('http://www.tvrage.com/myweekrss.php'.$special);
		@file_put_contents($savefile, $contents);
	} else {
		$cache = 'Yes ('.time().'/'.date('r', time()).') ['.$savefile.']';
		$contents = file_get_contents($savefile);
	}
	$rss = simplexml_load_string($contents);
	
	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
	echo "<tvcal>\n";
	echo "<cache>".$cache."</cache>\n";
	$date = 'Jan/01/1970';
	$source = isset($sourcename) ? $sourcename : str_replace('.php', '', basename(__FILE__)).(empty($country) ? '' : '_'.$country);
	if (count($rss->channel->item) > 0) {
		foreach ($rss->channel->item as $item) {
			if (preg_match('@^([A-Za-z]+/[0-9]+/[0-9]+) @U', $item->title, $matches)) {
				// Date changed
				$date = $matches[1];
			} else {
				// Episode
				$time = strtotime(str_replace('/','-',$date));
				$description = (string)$item->description;
				
				preg_match('@^- (.*) \(([0-9]+)x([0-9]+)\)$@U', $item->title, $matches);
				preg_match('@^- (.*) \((.*)\)$@U', $item->title, $other_matches);
				$datestring = date('j-n-Y', $time);
				if (isset($_REQUEST['extra']) && !empty($_REQUEST['extra']) && $_REQUEST['extra'] != date('n-Y', $time)) {
					continue;
				}
				
				$name = (isset($matches[1]) ? $matches[1] : $other_matches[1]);
				$season = (isset($matches[2]) ? $matches[2] : 0);
				$episode = (isset($matches[3]) ? $matches[3] : $other_matches[1]);
				echo "\t<show>\n";
				echo "\t\t<date time=\"".$time."\">".$datestring."</date>\n";
				echo "\t\t<name>".htmlspecialchars($name, ENT_QUOTES, 'UTF-8')."</name>\n";
				echo "\t\t<title>".htmlspecialchars($description, ENT_QUOTES, 'UTF-8')."</title>\n";
				echo "\t\t<season>".$season."</season>\n";
				echo "\t\t<episode>".$episode."</episode>\n";
				echo "\t</show>\n";
				
				addAirTime($name, $season, $episode, $description, $time, $source);
			}
		}
	}
	echo "</tvcal>";

?>