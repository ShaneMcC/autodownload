<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');
	set_time_limit(0);
	
	if (!isset($_REQUEST['showid']) && !isset($_REQUEST['download']) && (!isset($_REQUEST['search']) || empty($_REQUEST['search']))) {
		head('Series Downloader :: Search', '', getCSS(false, true));
		echo '		<form method="GET" action="">', CRLF;
		echo '			<div class="center">', CRLF;
		echo '				<div class="formrow">', CRLF;
		echo '					<span class="labelmed">Show Name (May not be blank):</span>', CRLF;
		echo '					<span class="inputreg"><input name="search" size=25></span>', CRLF;
		echo '				</div>', CRLF;
		echo '				<br><br>', CRLF;
		echo '				<div class="formrow">', CRLF;
		echo '					<span class="labelmed">&nbsp;</span>', CRLF;
		echo '					<span class="inputreg"><input class="submit" type="Submit" value="Search"></span>', CRLF;
		echo '				</div>', CRLF;
		echo '			</div>', CRLF;
		echo '		</form>', CRLF;
	} else if (isset($_REQUEST['search'])) {
		head('Series Downloader :: Search :: '.htmlentities($_REQUEST['search']), '', getCSS(true, false));
		$shows = searchSeriesInfo($_REQUEST['search']);
		echo 'Found <strong>', count($shows), '</strong> matches.', CRLF;
		
		foreach ($shows as $show) {
			$class = 'downloadList' . (strtolower($series['name']) == strtolower($_REQUEST['search']) ? ' best' : '') ;
			$actions = '[<a href="series.php?showid='.urlencode($show['id']).'&showname='.urlencode($show['name']).'">Get show info</a>]';
			
			displaySeriesInfo($show, $class, $actions);
		}
	} else if (isset($_REQUEST['showid']) && !isset($_REQUEST['download'])) {
		$css = getCSS(true, false);
		$css .= '
			div.seasonHeader {
				font-size: 24px;
				color: #000000;
				border-bottom: solid 2px #000000;
				width: 50%;
				margin-top: 10px;
				margin-bottom: 5px;
				padding-left: 10px;
			}
			
			table.episode {
				border-collapse: collapse;
				/* width: 699px; */
			}
			
			table.episode td, table.episode th {
				border: 1px solid black;
				padding: 1px 5px;
			}
			
			table.episode th {
				background-color: #AAA;
				text-align: right;
				width: 80px;
			}
			
			table.episode td {
				text-align: left;
				width: 139px;
			}
			
			table.episode td.info {
				height: 20px;
			}
			
			table.episode td.image {
				width: 250px;
				text-align: center;
				font-size: 32px;
				height: 138px;
			}
			
			table.episode td.image img {
				display: block;
				width: 250px;
				border: 1px solid black;
			}
		';
		
		$showid = (int)$_REQUEST['showid'];
		$showname = (isset($_REQUEST['showname'])) ? $_REQUEST['showname'] : 'Show ID: '.$showid;
		head('Series Downloader :: Download :: '.htmlentities($showname), '', $css);
		$showinfo = getSeriesInfo($showid);
		
		displaySeriesInfo($showinfo, 'downloadList', '');
		
		echo 'Found <strong>', $showinfo['episodecount'], '</strong> episodes.', EOL;
		echo '<ul>', CRLF;
		$link = 'series.php?showid='.urlencode($showid).'&showname='.urlencode($showname);
		echo '    <li> <a href="'.$link.'&download=undownloaded">Download undownloaded</a>', CRLF;
		echo '    <li> <a href="'.$link.'&download=all">Download all</a>', CRLF;
		echo '</ul>', CRLF;
		
		$info = getShowInfo($showinfo['name']);
		$info['size'] = (($showinfo['runtime'] * (2/3)) * 100);
		$cleanName = cleanName($info['name'], true);
		
		foreach ($showinfo['episodes'] as $season => $episodes) {
			echo '<div class="seasonHeader"> Season ', $season, '</div>', CRLF;
			foreach ($episodes as $episode) {
				$number = sprintf('%dx%02d [%d]', $season, $episode['seasonepnum'], $episode['totalepnum']);
				
				$show = array();
				$show['name'] = $cleanName;
				$show['time'] = strtotime($showinfo['airdate']);
				$show['season'] = $season;
				$show['episode'] = $episode['seasonepnum'];
				$show['title'] = $episode['title'];
				$show['sources'] = 'tvrage_search';
				$show['info'] = $info;
				
				echo '<table class="episode">', CRLF;
				echo '  <tr>', CRLF;
				echo '    <td rowspan=4 class="image">';
				if ($episode['screencap']) {
					echo '        <img src="', $episode['screencap'], '">';
				} else {
					echo '        No Image<br>Found';
				}
				echo '    </td>', CRLF;
				echo '    <th>Title</th>', CRLF;
				echo '    <td colspan=3 class="info">';
				echo $episode['title'];
				if (hasDownloaded($show['name'], $show['season'], $show['episode']) > 0) {
					echo ' [<strong>Got</strong>]';
				}
				echo '    </td>', CRLF;
				echo '  </tr>', CRLF;
				
				echo '  <tr>', CRLF;
				echo '    <th>Number</th>', CRLF;
				echo '    <td class="info">', $number, '</td>', CRLF;
				echo '    <th>ProdNum</th>', CRLF;
				echo '    <td>', $episode['prodnum'],'</td>', CRLF;
				echo '  </tr>', CRLF;
				
				echo '  <tr>', CRLF;
				echo '    <th>Aired</th>', CRLF;
				echo '    <td class="info">', $episode['airdate'],'</td>', CRLF;
				echo '    <th>Link</th>', CRLF;
				echo '    <td><a href="', $showinfo['link'],'">More Information</a></td>', CRLF;
				echo '  </tr>', CRLF;
				
				echo '  <tr>', CRLF;
				echo '    <td colspan=4 class="spacer">&nbsp</td>', CRLF;
				echo '  </tr>', CRLF;
				
				echo '  <tr>', CRLF;
				echo '    <th>Actions</th>', CRLF;
				echo '    <td colspan=4 class="info">', CRLF;
				echo '        [<a href="GetTV.php?info=', urlencode(serialize($show)), '">Download this Episode</a>]';
				echo '    </td>', CRLF;
				echo '  </tr>', CRLF;

				echo '</table>', CRLF;
				echo '<br>';
			}
		}
	} else if (isset($_REQUEST['showid']) && isset($_REQUEST['download'])) {
		$showid = (int)$_REQUEST['showid'];
		$showname = (isset($_REQUEST['showname'])) ? $_REQUEST['showname'] : 'Show ID: '.$showid;
		head('Series Downloader :: Downloading :: '.htmlentities($showname).' ('.$_REQUEST['download'].')');
		$showinfo = getSeriesInfo($showid);
		
		$info = getShowInfo($showinfo['name']);
		$info['size'] = (($showinfo['runtime'] * (2/3)) * 100);
		$cleanName = cleanName($info['name'], true);
		
		$count = 0;
		echo '<pre>', CRLF;
		foreach ($showinfo['episodes'] as $season => $episodes) {
			foreach ($episodes as $episode) {
				$show = array();
				$show['name'] = $cleanName;
				$show['time'] = strtotime($showinfo['airdate']);
				$show['season'] = $season;
				$show['episode'] = $episode['seasonepnum'];
				$show['title'] = $episode['title'];
				$show['sources'] = 'tvrage_search';
				$show['info'] = $info;
		
				if (hasDownloaded($show['name'], $show['season'], $show['episode'], $show['title']) && $_REQUEST['download'] != "all") {
					echo sprintf('Skipping: %s %dx%02d -> %s', $cleanName, $season, $episode['seasonepnum'], $show['title']), CRLF;
					flush();
					continue;
				}
				$count++;
				if ($count > $config['seriesdownload']['sleepcount']) {
					$count = 0;
					sleepProgress($config['seriesdownload']['sleeptime'], 1, 'to reduce load on newzbin server');
				}
				
				echo sprintf('Trying: %s %dx%02d -> %s', $cleanName, $season, $episode['seasonepnum'], $show['title']), CRLF;
				flush();
		
				// Search for this show, and get the optimal match.
				ob_start();
				$search = searchFor(getSearchString($show), false);
				$buffer = ob_get_contents();
				ob_end_clean();
				
				// Check for errors
				if (preg_match('@function.file-get-contents</a>]: (.*)  in@U', $buffer, $matches)) {
					echo 'An error occured loading the search provider: ', $matches[1], CRLF;
				} else if ($search === false) {
					echo 'An error occured getting the search results: The search provider could not be loaded', CRLF;
				} else  if ($search->error['message'] && $search->error['message'] != '') {
					echo 'An error occured getting the search results: ', (string)$search->error['message'], CRLF;
				} else {
					// No errors, get the best item
					$items = $search->item;
					$optimal = GetBestOptimal($search->xpath('item'), $info['size'], false, true, $show);
					// If a best item was found
					if (count(items) > 0 && $optimal != -1) {
						$best = $items[$optimal];
						$bestid = (int)$best->nzbid;
						echo "\t\t", 'Found Newzbin ID: ', $bestid, '...';
						flush();
						// Try to download.
						$result = downloadNZB($bestid);
						if ($result['status']) {
							echo ' Success!', CRLF;
							// Hellanzb tells us that the nzb was added ok, so mark the show as downloaded
							setDownloaded($show['name'], $show['season'], $show['episode'], $show['title']);
							doReport(array('source' => 'daemon::handleCheckAuto', 'message' => sprintf('Beginning series download of: %s %dx%02d [%s] (NZB: %d)', $show['name'], $show['season'], $show['episode'], $show['title'], $bestid)));
						} else {
							echo ' Failed.', CRLF;
						}
					} else {
						echo "\t\t", '<strong><em>No search results found.</em></strong>', CRLF;
					}
				}
				flush();
			}
		}
		echo '</pre>';
	}
	
	foot();
?>