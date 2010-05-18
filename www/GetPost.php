<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');

	if (isset($_REQUEST['@show']) && is_array($_REQUEST[$_REQUEST['@show']])) {
		$_REQUEST['show'] = implode($_REQUEST[$_REQUEST['@show']], '');
	}
	
	$title = 'Downloading';
	$posts = array();
	$directfilename = "";

	// Discover what we are downloading and set a nice title.
	if (isset($_REQUEST['show'])) {
		$show = unserialize($_REQUEST['show']);
		$directfilename = sprintf('%s - %dx%02d - %s', $show['name'], $show['season'], $show['episode'], $show['title']);
		$title .= ' - '.$directfilename;
		
		if (!hasDownloaded($show['name'], $show['season'], $show['episode'])) {
			setDownloaded($show['name'], $show['season'], $show['episode'], $show['title']);
			$title .= ' (New Download)';
		} else {
			$title .= ' (Repeat Download)';
		}
		
		$extra = '';
		if ($config['daemon']['autotv']['showmanage']) {
			$extra .= ' (Manage: '.$config['daemon']['autotv']['manageurl'].'?show='.urlencode($show['name']).')';
		}
		doReport(array('source' => 'GetPost', 'message' => sprintf('Beginning manual download of: %s %dx%02d [%s] (NZB: %d)%s', $show['name'], $show['season'], $show['episode'], $show['title'], $_REQUEST['nzbid'], $extra)));
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

	// A raw NZB.
	} else if (isset($_REQUEST['rawid'])) {
		$id = implode(',', $_REQUEST['rawid']);
		$subtitle = 'Raw NZB: '.$id;
		$raws[] = $id;

	// A URL.
	} else if (isset($_REQUEST['dlurl'])) {
		$id = $_REQUEST['dlurl'];
		$subtitle = 'Downloaded NZB: '.$id;
		$dlurl[] = $id;
		
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

	if (isset($_REQUEST['nzbtype'])) {
		$subtitle .= ' ['.$_REQUEST['nzbtype'].']';
		if ($nzbtype != 'newzbin') {
			$nzbtype = '_' . $_REQUEST['nzbtype'];
		}
	} else {
		$nzbtype = '';
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
	$querynumber = 0;
	for ($a = 0; $a != count($posts); $a++) {
		$post = $posts[$a];
		if ($post == "") { continue; }

		if ($querynumber >= $querysleepcount) {
			echo CRLF.'---------------------------------------------------------'.CRLF;
			sleepProgress($config['seriesdownload']['sleeptime'], 1, 'to reduce load on newzbin server');
			$querynumber = 0;
			echo '---------------------------------------------------------'.CRLF.CRLF;
		}
		flush();
		$querynumber++;

		echo 'Asking hellahella to download: ', $post, CRLF;
		$result = call_user_func('downloadNZB'.$nzbtype, $post, $directfilename);
		var_dump($result['output']);
	}

	for ($a = 0; $a != count($dlurl); $a++) {
		$post = $dlurl[$a];
		if ($post == "") { continue; }

		if ($querynumber >= $querysleepcount) {
			echo CRLF.'---------------------------------------------------------'.CRLF;
			sleepProgress($config['seriesdownload']['sleeptime'], 1, 'to reduce load on newzbin server');
			$querynumber = 0;
			echo '---------------------------------------------------------'.CRLF.CRLF;
		}
		flush();
		$querynumber++;

		echo 'Asking hellahella to download: ', $post, CRLF;
		$result = call_user_func('downloadURL'.$nzbtype, $post, $directfilename);
		var_dump($result['output']);
	}

	for ($a = 0; $a != count($raws); $a++) {
		$post = $raws[$a];
		if ($post == "") { continue; }

		if ($querynumber >= $querysleepcount) {
			echo CRLF.'---------------------------------------------------------'.CRLF;
			sleepProgress($config['seriesdownload']['sleeptime'], 1, 'to reduce load on newzbin server');
			$querynumber = 0;
			echo '---------------------------------------------------------'.CRLF.CRLF;
		}
		flush();
		$querynumber++;

		echo 'Asking hellahella to download: ', $post, CRLF;
		$result = call_user_func('downloadDirect'.$nzbtype, $post, $directfilename);
		var_dump($result['output']);
	}
	
	echo "</pre>";
	
	foot();
?>
