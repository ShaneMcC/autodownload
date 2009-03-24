<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');
	
	$title = 'Downloading';
	$posts = array();

	// Discover what we are downloading and set a nice title.
	if (isset($_REQUEST['show'])) {
		$show = unserialize($_REQUEST['show']);
		$title .= sprintf(' - %s %dx%02d', $show['name'], $show['season'], $show['episode']);
		
		if (!hasDownloaded($show['name'], $show['season'], $show['episode'])) {
			setDownloaded($show['name'], $show['season'], $show['episode'], $show['title']);
			$title .= ' (New Download)';
		} else {
			$title .= ' (Repeat Download)';
		}
	}
	
	// URL to newzbin page.
	if (isset($_REQUEST['nzb'])) {
		$pattern = '@(.*)/browse/post/([0-9]+).*@i'; preg_match($pattern, $_REQUEST['nzb'], $matches);
		
		$title .= ' - '.$_REQUEST['nzb'];
		if (isset($matches[2])) { $posts[] = $matches[2]; }
		
	// A single NZB.
	} else if (isset($_REQUEST['nzbid']) || isset($_REQUEST['post'])) {
		$id = (isset($_REQUEST['nzbid'])) ? $_REQUEST['nzbid'] : $_REQUEST['post'];
		$subtitle = 'NZB: '.$id;
		$posts[] = $id;
		
	// Multiple NZBs
	} else if (isset($_REQUEST['post_list'])) {
		$post_list = unserialize($_REQUEST['post_list']);
		if ($post_list === false) {
			$post_list = explode(',', $post_list);
		}
		
		// Add any of the nzbs in the list to the list of nzbs we are downloading,
		// and make a nice string for title...
		$list = '';
		$overflowed = false;
		foreach ($post_list as $post) {
			$posts[] = trim($post);
			if (strlen($list) < 20) {
				if (!empty($list)) { $list .= ', '; }
				$list .= trim($post);
			} else if (!$overflowed) {
				$overflowed = true;
				$list .= ', ...';
			}
		}
		
		$subtitle = 'NZBs: '.$list;
	}
	
	head($title, $subtitle);
	
	// Slow down the requests (useful for large amounts or to keep in order)
	$querysleepcount = $config['download']['sleepcount'];
	$querysleeptime = $config['download']['sleeptime'];
	
	if (isset($_REQUEST['slow'])) {
		$querysleepcount = 5; // After this many downloads
		$querysleeptime = 60; // Sleep for this many seconds
		echo '<pre>';
		echo '@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@'.CRLF;
		echo '@                   Slow mode enabled                   @'.CRLF;
		echo '@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@'.CRLF.CRLF;
		echo '</pre>';
	}
	
	echo "<pre>";
	$log = '/tmp/hella_'.time().'.log';
	$querynumber = 0;
	for ($a = 0; $a != count($posts); $a++) {
		$post = $posts[$a];
		if ($post == "") { continue; }

		if ($querynumber >= $querysleepcount) {
			echo CRLF.'---------------------------------------------------------'.CRLF;
			echo 'Sleeping (for '.$querysleeptime.' seconds) to reduce load on newzbin server.. '.CRLF;
			// Show the size of the progress bar
			echo '|';
			for ($sleepcount = 0; $sleepcount < $querysleeptime; $sleepcount++) { echo '-'; }
			echo '|'.CRLF;
			
			// Draw the progress bar
			echo ' ';
			for ($sleepcount = 0; $sleepcount < $querysleeptime; $sleepcount++) {
				echo '#';
				flush();
				sleep(1);
			}
			echo CRLF;
			
			$querynumber = 0;
			echo 'done!'.CRLF;
			echo '---------------------------------------------------------'.CRLF.CRLF;
		}
		flush();
		$querynumber++;

		$cmd = $config['hellanzb']['python'].' '.$config['hellanzb']['location'].' -c '.$config['hellanzb']['config'].' -l '.$log.' enqueuenewzbin '.$post;
		echo 'Exec: '.$cmd."\r\n";
		flush();
		
		if (isset($output)) { unset($output); }
		exec($cmd, $output);
		flush();
		print_r($output);
	}
	
	if (file_exists($log)) { unlink($log); }
	echo "</pre>";
	
	foot();
?>