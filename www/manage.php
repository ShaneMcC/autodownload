<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');
	set_time_limit(0);
	
	if (isset($_GET['show'])) { $_GET['show'] = unslash($_GET['show']); }
	
	if (isset($_GET['show']) && !isset($_GET['toggleAutomatic']) ) {
		head('Series Downloader :: Manage :: '.$_GET['show'], '', getCSS(true, false));
		$show = getShowInfo($_GET['show']);

		if ($show['known']) {
			echo '<table class="downloadList">', CRLF;
			echo '  <tr>', CRLF;
			echo '    <th>Name</th>', CRLF;
			echo '    <td colspan=3>', $show['name'],'</td>', CRLF;
			echo '    <th>Automatic</th>', CRLF;
			echo '    <td>', ($show['automatic'] ? 'Yes' : 'No' ),'</td>', CRLF;
			echo '  </tr>', CRLF;
			
			echo '  <tr>', CRLF;
			echo '    <th>Search</th>', CRLF;
			echo '    <td colspan=3>', $show['searchstring'],'</td>', CRLF;
			echo '    <th>Dir Name</th>', CRLF;
			echo '    <td>', $show['dirname'],'</td>', CRLF;
			echo '  </tr>', CRLF;
			
			echo '  <tr>', CRLF;
			echo '    <th>Attributes</th>', CRLF;
			echo '    <td colspan=3>', $show['attributes'],'</td>', CRLF;
			echo '    <th>Size</th>', CRLF;
			echo '    <td>', $show['size'],'</td>', CRLF;
			echo '  </tr>', CRLF;
			
			echo '  <tr>', CRLF;
			echo '    <th>Sources</th>', CRLF;
			echo '    <td colspan=3>', $show['sources'],'</td>', CRLF;
			echo '    <th>Important</th>', CRLF;
			echo '    <td>', ($show['important'] ? 'Yes' : 'No' ),'</td>', CRLF;
			echo '  </tr>', CRLF;
			
			echo '  <tr>', CRLF;
			echo '    <th>Actions</th>', CRLF;
			echo '    <td colspan=5>';
			$autoType = ($show['automatic'] ? 'false' : 'true' );
			echo '[<a href="?show=', urlencode($_GET['show']), '&toggleAutomatic=', $autoType, '">Set automatic to ', $autoType ,'</a>] - ';
			echo '[<a href="series.php?search='.urlencode($_GET['show']).'">Search for Series</a>]';
			echo '</td>', CRLF;
			echo '  </tr>', CRLF;
			echo '</table>', CRLF;
			echo '<br>'.CRLF;
		} else {
			echo '<h2>', $_GET['show'], ' is not known.</h2>';
		}
	} else if (isset($_GET['show']) && isset($_GET['toggleAutomatic']) ) {
		head('Series Downloader :: Manage :: '.$_GET['show'], '', getCSS(true, false));
		$show = getShowInfo($_GET['show']);

		$newValue = !empty($_GET['toggleAutomatic']) ? (strtolower($_GET['toggleAutomatic']) == 'true') : !$show['automatic'];

		if ($show['known']) {
			$color = $newValue ? 'green' : 'red';
			echo '<h2 style="color: ', $color, '">', $_GET['show'], ' is now ',($newValue ? '' : 'not ' ),'automatic.</h2>';
			addShow($_GET['show'], $show, $newValue);
		} else {
			echo '<h2>', $_GET['show'], ' is not known.</h2>';
		}
		echo '<br>';
		echo '    <a href="?show='.urlencode($_GET['show']).'">Back to manage page</a>', CRLF;
	} else if (!isset($_GET['show'])) {
		head('Series Downloader :: Manage', '', getCSS(false, true));
		$show = getAllShows();
		$i = 0;
		$count = count($show);
		
		function printShow($show) {
			echo '<a href="manage.php?show='.urlencode($show['name']).'">'.htmlspecialchars($show['name']).'</a><br>';
		}
		
		echo '<H2>Automatic, Important</H2>';
		while ($count > $i && $show[$i]['automatic'] == true && $show[$i]['important'] == true) {
			printShow($show[$i++]);
		}
		
		echo '<br><hr>';
		echo '<H2>Automatic</H2>';
		while ($count > $i && $show[$i]['automatic'] == true) {
			printShow($show[$i++]);
		}
		
		echo '<br><hr>';
		echo '<H2>Other</H2>';
		while ($count > $i) {
			printShow($show[$i++]);
		}
	}
	
	foot();
?>