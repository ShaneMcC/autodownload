#!/usr/bin/php
<?php
	include_once(dirname(__FILE__).'/config.php');
	include_once(dirname(__FILE__).'/functions.php');
	include_once(dirname(__FILE__).'/daemon.tools.php');

	addCLIParam('f', 'foreground', 'Don\'t fork.');
	addCLIParam('b', 'background', 'Do Fork. (Takes priority over --foreground)');
	addCLIParam('r', 'reindex', 'Force a manual reindex, don\'t start daemon.');
	addCLIParam('a', 'autotv', 'Run a manual AutoTV check, don\'t start daemon.');
	addCLIParam('', 'noreindex', 'Don\'t periodically reindex.');
	addCLIParam('', 'noautotv', 'Don\'t periodically run AutoTV checks.');
	addCLIParam('', 'pid', 'Specify an alternative PID file.', true);
	
	$daemon['cli'] = parseCLIParams($_SERVER['argv']);
	if (isset($daemon['cli']['help'])) {
		echo 'Usage: ', $_SERVER['argv'][0], ' [options]', CRLF, CRLF;
		echo 'Options:', CRLF, CRLF;
		echo showCLIParams(), CRLF;
		die();
	}
	
	/**
	 * Handle the Daemon Loop
	 *
	 * @param $type Type of callback
	 * @param $args Array of arguments for this callback
	 */
	function doDaemonLoop($type, $args) {
		// global $__daemontools;
		// echo 'doDaemonLoop: ', $__daemontools['callbacks'][$type]['description'], CRLF;
		
		switch ($type) {
			case DAEMONTOOLS_LOOP:
				handleLoop();
				break;
			case DAEMONTOOLS_FORK_FAILED:
				doEcho('Failed to fork, continuing in foreground...'.CRLF);
				break;
			case DAEMONTOOLS_FORK_SUCCESS:
				if ($args['parent']) {
					doEcho('Forked.'.CRLF);
				}
				break;
			case DAEMONTOOLS_ALREADY_RUNNING:
				doEcho('Already running, terminating.'.CRLF);
				break;
			// Currently unhandled callbacks
			case DAEMONTOOLS_SIGNAL:
			case DAEMONTOOLS_STARTED:
			case DAEMONTOOLS_STOPPED:
			case DAEMONTOOLS_EXITING:
			default:
				break;
		}
	}
	
	/**
	 * Handle the loop from startDaemon.
	 */
	function handleLoop() {
		global $config, $daemon;
		echo 'Loop', CRLF;

		foreach ($config['times'] as $time => $format) {
			// Get the time from the previous loop
			$last[$time] = (isset($daemon['time'][$time])) ? $daemon['time'][$time] : 0;
			// Get the current time.
			$this[$time] = date($format);
			
			// Store if the time changed
			$changed[$time] = ($this[$time] != $last[$time]);
			// Store the time for the next loop
			$daemon['time'][$time] = $this[$time];
		}
		
		foreach ($config['times'] as $time => $format) {
			if ($changed[$time] && function_exists('handle'.ucfirst($time).'Changed')) {
				@call_user_func('handle'.ucfirst($time).'Changed', $last, $this);
			}
		}
		
		echo 'End Loop', CRLF;
	}
	
	/**
	 * Handle the Minute changing.
	 *
	 * @param $last The last time the loop ran.
	 * @param $this The time now.
	 */
	function handleMinuteChanged($last, $this) {
		global $config, $daemon;
		
		// Check if its time to run autotv
		if (!isset($daemon['cli']['noautotv']) && checkTimeArray($config['daemon']['autotv']['times'], $this)) {
			handleCheckAuto();
		}
		
		// Check if its time to run reindex
		if (!isset($daemon['cli']['noreindex']) && checkTimeArray($config['daemon']['reindex']['times'], $this)) {
			echo 'Reindex time!';
			handleReindex();
		}
	}
	
	/**
	 * Handle moving files from the download directory to the sorted directory
	 */
	function handleReindex() {
		global $config;
	}
	
	/**
	 * Handle checking for automatic downloads
	 */
	function handleCheckAuto() {
		global $config;
		
		// Posts we need to download.
		$posts = array();
		
		foreach (getShows(true, -1, -1, false, $config['autodownload']['source']) as $show) {
			$info = $show['info'];
			// Check if this show is marked as automatic, (and is marked as important if onlyimportant is set true)
			// Also check that the show hasn't already been downloaded.
			$important = (($info['important'] && $config['download']['onlyimportant']) || !$config['download']['onlyimportant']);
			if ($info['automatic'] && $important && !hasDownloaded($show['name'], $show['season'], $show['episode'])) {
				// Search for this show, and get the optimal match.
				ob_start();
				$search = searchFor(getSearchString($show), false);
				$buffer = ob_get_contents();
				ob_end_clean();
				
				if (preg_match('@function.file-get-contents</a>]: (.*)  in@U', $buffer, $matches)) {
					// echo 'An error occured loading the search provider: ', $matches[1], EOL;
				} else if ($search === false) {
					// echo 'An error occured getting the search results: The search provider could not be loaded', EOL;
				} else  if ($search->error['message'] && $search->error['message'] != '') {
					// echo 'An error occured getting the search results: ', (string)$search->error['message'], EOL;
				} else {
					$items = $search->item;
					$optimal = GetBestOptimal($items, $show['size'], false, true);
					if ($optimal != -1) {
						$best = $items[$optimal];
						// Try to download.
						$result = downloadNZB((int)$best->nzbid);
						if ($result['status']) {
							// Hellanzb tells us that the nzb was added ok, so mark the show as downloaded
							setDownloaded($show['name'], $show['season'], $show['episode'], $show['title']);
						}
					}
				}
			}
		}
	}
	
	// Should we fork?
	$fork = $config['daemon']['fork'];
	if (isset($daemon['cli']['foreground'])) { $fork = false; }
	if (isset($daemon['cli']['background'])) { $fork = true; }
	
	// Where should we store the pid?
	$pid = (isset($daemon['cli']['pid'])) ? $daemon['cli']['pid']['values'][count($daemon['cli']['pid']['values'])-1] : $config['daemon']['pid'];
	
	// Should the daemon loop be started?
	$daemonise = true;
	if (isset($daemon['cli']['reindex'])) { $daemonise = false; handleReindex(); }
	if (isset($daemon['cli']['autotv'])) { $daemonise = false; handleCheckAuto(); }
	
	// Start the daemon so that it loops every 5 seconds.
	if ($daemonise) {
		startDaemon($pid, $fork, doDaemonLoop, (5 * 1000), __FILE__);
	}
?>