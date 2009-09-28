<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');
	set_time_limit(0);
	
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
			echo '[<a href="?show='.urlencode($_GET['show']).'&toggleAutomatic">Set automatic to ', ($show['automatic'] ? 'false' : 'true' ) ,'</a>] - ';
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
		if ($show['known']) {
			echo '<h2>', $_GET['show'], ' is now ',($show['automatic'] ? 'not ' : '' ),'automatic.</h2>';
			addShow($_GET['show'], $show, !$show['automatic']);
		} else {
			echo '<h2>', $_GET['show'], ' is not known.</h2>';
		}
		echo '<br>';
		echo '    <a href="?show='.urlencode($_GET['show']).'">Back to manage page</a>', CRLF;
	} else if (!isset($_GET['show'])) {
		head('Series Downloader :: Manage', '', getCSS(false, true));
		echo 'Sometime there might be a show listing here.';
	}
	
	foot();
?>