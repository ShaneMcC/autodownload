#!/usr/bin/php
<?php
	include_once(dirname(__FILE__).'/config.php');
	include_once(dirname(__FILE__).'/functions.php');
	include_once(dirname(__FILE__).'/daemon.tools.php');

	addCLIParam('f', 'foreground', 'Don\'t fork');
	addCLIParam('b', 'background', 'Fork');
	addCLIParam('r', 'reindex', 'Force a manual reindex, then exit');
	addCLIParam('a', 'autotv', 'Run a manual AutoTV check, then exit');
	
	$cli = parseCLIParams($_SERVER['argv']);
	if (isset($cli['help'])) {
		echo 'Usage: ', $_SERVER['argv'][0], ' [options]', CRLF, CRLF;
		echo 'Options:', CRLF, CRLF;
		echo showCLIParams(), CRLF;
		die();
	}
	
	// Start the daemon so that it loops every 5 seconds.
	//startDaemon($config['daemon']['pid'], $config['daemon']['fork'], doDaemonLoop, (5 * 1000), __FILE__);
	
	/**
	 * Handle the Daemon Loop
	 *
	 * @param $type Type of callback
	 * @param $args Array of arguments for this callback
	 */
	function doDaemonLoop($type, $args) {
		global $__daemontools;
		
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
		doEcho('Loop;'.CRLF);
	}
	
	/**
	 * Handle moving files from the download directory to the sorted directory
	 */
	function handleReindex() { }
	
	/**
	 * Handle checking for automatic downloads
	 */
	function handleCheckAuto() { }
?>