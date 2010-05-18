<?php
	//----------------------------------------------------------------------------
	// Daemon Tools
	// Author: Shane Mc Cormack
	//----------------------------------------------------------------------------
	// This script handles daemonising and forking of an application.
	//
	// It also handles processing of command line arguments.
	//  - Arguments can be passed as either long (--long) or short (-l).
	//  - Short arguments can be passed either separately (-a -b -c) or as a
	//    group (-abc)
	//  - Params for arguments that require a param can be specified by being
	//    after the params (-a foo -bc foo bar) or in the case of long parameters,
	//    they can be passed using = or : (--long=value --long:value)
	//  - All values passed are retained, not just the last one.
	//  - Params can be specified multiple times and a count is maintained (-vv)
	//  - Unregognised parameters/values are retained (in order)
	//  - "help text" can be generated using showCLIParams()
	//----------------------------------------------------------------------------

	/** Default forked state. */
	$__daemontools['forked'] = false;

	/** Default force_echo state. */
	$__daemontools['force_echo'] = false;
	
	/**
	 * Used to let the application know that this is a call from inside the loop.
	 * This has no arguments.
	 */
	define('DAEMONTOOLS_LOOP', 0);
	$__daemontools['callbacks'][0] = array('description' => 'Loop',
	                                       'args' => array()
	                                      );
	/**
	 * Used to let the application know  that forking failed when it was
	 * requested.
	 * The application will continue to run un-forked.
	 * This has no arguments.
	 */
	define('DAEMONTOOLS_FORK_FAILED', 1);
	$__daemontools['callbacks'][1] = array('description' => 'Fork Failed',
	                                       'args' => array()
	                                      );
	
	/**
	 * Used to let the application know  that forking was successful when it was
	 * requested.
	 * This has 2 arguments:
	 *  - 'parent' which will be true for the parent, and false for the child
	 *  - 'pid' which will be the pid of the child.
	 */
	define('DAEMONTOOLS_FORK_SUCCESS', 2);
	$__daemontools['callbacks'][2] = array('description' => 'Fork Success',
	                                       'args' => array('parent' => 'Is this the parent?',
	                                                       'pid' => 'Pid of the child'
	                                                      )
	                                      );
	
	/**
	 * Used to let the application know that a sigal was recieved.
	 * This will have a 'signal' parameter in the args array.
	 */
	define('DAEMONTOOLS_SIGNAL', 3);
	$__daemontools['callbacks'][3] = array('description' => 'Signal Recieved',
	                                      'args' => array('signal' => 'Signal that was recieved')
	                                      );
	/**
	 * Used to let the application know that the loop is about to begin
	 * This has no arguments.
	 */
	define('DAEMONTOOLS_STARTED', 4);
	$__daemontools['callbacks'][4] = array('description' => 'Daemon Started',
	                                       'args' => array()
	                                      );
	/**
	 * Used to let the application know that stopDaemon() was called.
	 * DAEMONTOOLS_EXITING will still be called.
	 * This has no arguments.
	 */
	define('DAEMONTOOLS_STOPPED', 5);
	$__daemontools['callbacks'][5] = array('description' => 'stopDaemon() Called',
	                                       'args' => array()
	                                      );
	/**
	 * Used to let the application know that the loop has ended
	 * This has no arguments.
	 */
	define('DAEMONTOOLS_EXITING', 6);
	$__daemontools['callbacks'][6] = array('description' => 'Daemon Exiting',
	                                       'args' => array()
	                                      );
	
	/**
	 * Used to let the application know that daemonising failed due to the
	 * application already running.
	 * This has 1 arguments, the pid of the existing application.
	 */
	define('DAEMONTOOLS_ALREADY_RUNNING', 7);
	$__daemontools['callbacks'][7] = array('description' => 'Already Running',
	                                       'args' => array('pid' => 'PID of existing process')
	                                      );
	
	/**
	 * Echo function that only echos when not forked.
	 *
	 * @param $text Text to echo. (Multiple parameters can be passed, which will
	 *              all be passed to echo);
	 */
	function doEcho($text) {
		global $__daemontools;
		
		if (!$__daemontools['forked'] || $__daemontools['force_echo']) {
			foreach (func_get_args() as $arg) {
				echo $arg;
			}
		}
	}
	
	/**
	 * print_r function that only works when not forked.
	 *
	 * @param $text Text to print.
	 */
	function doPrintR($text) {
		global $__daemontools;
		
		if (!$__daemontools['forked']) {
			print_r($text);
		}
	}
	
	/**
	 * Handle daemonising of the current script.
	 * This function will not exit as long as the loop is running.
	 * As well as having return codes, the callback function will also be called
	 * back for each of the return codes prior to returning.
	 *
	 * @param $pid Pid file to use
	 * @param $fork Should we fork?
	 * @param $function Function to callback to.
	 *                  This function will be called at various times, with 2
	 *                  parameters. The first will be one of the DAEMONTOOLS_
	 *                  constants defined above, and the second will be an array
	 *                  containing any parameters associated with the call.
	 * @param $looptime Approximate time in microseconds that the loop should
	 *                  sleep for. ($function is called every time the loop loops)
	 *                  This shouldn't be too long as signals are only handled when
	 *                  the loop loops.
	 * @param $file Filename of main application (__FILE__ should be passed here.)
	 * @return -1 If application is already running (DAEMONTOOLS_ALREADY_RUNNING)
	 *          0 When application exits. (DAEMONTOOLS_EXITING)
	 *          If the process forked successfully, then the PID of the child will
	 *          be returned. (DAEMONTOOLS_FORK_SUCCESS)
	 */
	function startDaemon($pid, $fork, $function, $looptime, $file) {
		global $__daemontools;
		
		// Variables passed to this function that others may need.
		$__daemontools['pid'] = $pid;
		$__daemontools['fork'] = $fork;
		$__daemontools['function'] = $function;
		$__daemontools['looptime'] = $looptime;
		$__daemontools['file'] = $file;
		
		// State Variables
		$__daemontools['forked'] = false;
		$__daemontools['loop'] = true;
		// usleep takes millionth of a second, not thousanth like expected.
		$__daemontools['usleep_time'] = $looptime * 1000;
		
		if (daemontools_checkPID()) {
			$pid = file_get_contents($__daemontools['pid']);
			@call_user_func($__daemontools['function'], DAEMONTOOLS_ALREADY_RUNNING, array('pid' => $pid));
			return -1;
		} else {
			if ($__daemontools['fork']) {
				$pid = pcntl_fork();
				if ($pid == -1) {
					@call_user_func($__daemontools['function'], DAEMONTOOLS_FORK_FAILED, array());
				} else if ($pid != 0) {
					// pcntl_wait($status);
					@call_user_func($__daemontools['function'], DAEMONTOOLS_FORK_SUCCESS, array('parent' => true, 'pid' => $pid));
					return $pid;
				} else {
					$__daemontools['forked'] = true;
					@call_user_func($__daemontools['function'], DAEMONTOOLS_FORK_SUCCESS, array('parent' => false, 'pid' => getmypid()));
				}
			}
			
			// Store the PID.
			file_put_contents($__daemontools['pid'], getmypid());
		}
		
		// Add handler for the SIGTERM and SIGHUP signals
		pcntl_signal(SIGTERM, "daemontools_handleSignal");
		pcntl_signal(SIGHUP, "daemontools_handleSignal");
	
		// If pcntl_signal_dispatch() does not exist, then Set ticks to 1 to allow
		// the signal handler to work.
		if (!function_exists('pcntl_signal_dispatch')) {
			declare(ticks = 1);
		}
		
		// Prevent the script from timing out.
		set_time_limit(0);
		
		// Let the application know we have started
		@call_user_func($__daemontools['function'], DAEMONTOOLS_STARTED, array());
		
		// And begin the execution loop
		while (daemontools_handleSignal(-1) && $__daemontools['loop']) {
			@call_user_func($__daemontools['function'], DAEMONTOOLS_LOOP, array());
			usleep($__daemontools['usleep_time']);
		}
		
		// Try to delete the pid if we exited cleanly.
		if (file_exists($__daemontools['pid'])) {
			unlink($__daemontools['pid']);
		}
		
		// Let the application know we are exiting
		@call_user_func($__daemontools['function'], DAEMONTOOLS_EXITING, array());
		return 0;
	}
	
	/**
	 * Stop the daemon.
	 * This will stop any further loops from occuring.
	 */
	function stopDaemon() {
		global $__daemontools;
	
		// Let the application know we are stopping
		@call_user_func($__daemontools['function'], DAEMONTOOLS_STOPPED, array());
		
		// Prevent any further loops
		$__daemontools['loop'] = false;
	}
	
	/**
	 * Check if the given PID file represents this daemon to prevent multiple
	 * instances.
	 *
	 * @param $file (Default = '') File to check. If blank, the file given to the
	 *              startDaemon() function will be used.
	 * @return True if the pid is for an instance of this daemon
	 */
	function daemontools_checkPID($file = '') {
		global $__daemontools;
		$file = (empty($file)) ? $__daemontools['pid'] : $file;
		// Check to see if the PID file exists.
		if (!file_exists($file)) { return false; }
		
		$pid = file_get_contents($file);
		
		// Check that the PID exists
		// This will return false on non-linux based systems.
		if (!file_exists('/proc/'.$pid.'/')) { return false; }
		
		// Now check to see if the PID is actaully us (incase the PID was reused by
		// another app before we could remove the PID file.)
		// First get the cmdline that was used to run this app
		$cmdline = explode("\0", file_get_contents('/proc/'.$pid.'/cmdline'));
		// Now check what directory it was run from
		$cwd = readlink('/proc/'.$pid.'/cwd');
		// And the executeable that is running it (should be php)
		$exe = readlink('/proc/'.$pid.'/exe');
		// Now find the real path to the file that is being run
		$file = ($cmdline[1]{0} == '/') ? realpath($cmdline[1]) : realpath($cwd.'/'.$cmdline[1]);
		
		// Check that the file we found in the PID, is us, and that our exe is
		// the same as the one that is running the file in the PID (so that
		// editing the file doesn't cause us to think that we are running)
		return ($__daemontools['file'] == $file && $exe == readlink('/proc/'.getmypid().'/exe'));
	}
	
	/**
	 * Handler for signals sent to the daemon.
	 *
	 * @param $signal Signal that was sent. If $signal is -1, then 
	 *                pcntl_signal_dispatch() will be called where supported.
	 * @return returns true if signal is -1 or if signal was handled.
	 */
	function daemontools_handleSignal($signal) {
		global $loop, $__daemontools;
		
		// Call pcntl_signal_dispatch where supported.
		if ($signal == -1) {
			if (function_exists('pcntl_signal_dispatch')) { pcntl_signal_dispatch(); }
			return true;
		}
		
		@call_user_func($__daemontools['function'], DAEMONTOOLS_SIGNAL, array('signal' => $signal));
		
		switch ($signal) {
			case SIGTERM:
				$__daemontools['loop'] = false;
				return true;
			default:
				break;
		}
		
		return false;
	}
	
	/**
	 * Add a potential CLI Param.
	 *
	 * @param $short Short code for this param. ('' for no short param, must be a
	 *               single characater, 'h' is not permitted)
	 * @param $long Long code for this param. (required, 'help' is
	 *              not permitted)
	 * @param $description Description for this param
	 * @param $takesValue (Default = false) Does this param require a value?
	 */
	function addCLIParam($short, $long, $description, $takesValue = false) {
		global $__daemontools;
		
		if ($short == null) { $short = ''; }
		if (strlen($short) > 1) { $short = ''; }
		if ($long == null) { return; }
		if ($short == 'h' || $long == 'help') { return; }
		if (empty($short) && empty($long)) { return; }
		if (isset($__daemontools['cli']['params'][$long])) { return; }
		if ($short != '' && isset($__daemontools['cli']['shortmap'][$short])) { return; }
		
		$long = strtolower($long);
		$short = strtolower($short);
		$__daemontools['cli']['params'][$long] = array('short' => $short,
		                                          'long' => $long,
		                                          'description' => $description,
		                                          'takesValue' => $takesValue
		                                         );
		if ($short != '') { $__daemontools['cli']['shortmap'][$short] = $long; }
		
		if (isset($__daemontools['cli']['longest'])) {
			if (strlen($long) > $__daemontools['cli']['longest']) {
				$__daemontools['cli']['longest'] = strlen($long);
			}
		} else {
			$__daemontools['cli']['longest'] = strlen($long);
		}
	}
	
	/**
	 * Return a string that can be printed as help text.
	 *
	 * @return String to use as help text.
	 */
	function showCLIParams() {
		global $__daemontools;
		
		$result = '';
		
		$length = $__daemontools['cli']['longest'] + 5;
		
		foreach ($__daemontools['cli']['params'] as $param) {
			$short = (empty($param['short'])) ? '' : '-'.$param['short'].',';
			$long = '--'.$param['long'];
			$result .= sprintf('%-4s %-'.$length.'s  %s', $short, $long, $param['description']);
			if ($param['takesValue']) {
				$result .= ' [Requires value]';
			}
			$result .= "\n";
		}
		
		$result .= sprintf('%-4s %-'.$length.'s  %s', '-h,', '--help', 'Show help');
		$result .= "\n";
		
		return $result;
	}
	
	/**
	 * Parse the given string as CLI Params.
	 *
	 * @param $bits (Default = empty array) Args to parse.
	 * @param $ignorefirst (Default = true) Should the first value passwd in the
	 *                     array be ignored? (usually the app name)
	 * @return Array representing what was passed to the application.
	 */
	function parseCLIParams($bits = array(), $ignorefirst = true) {
		global $__daemontools;
		
		// Temporarily add help entries
		$__daemontools['cli']['params']['help'] = array('short' => 'h', 'long' => 'help', 'description' => 'help', 'takesValue' => false);
		$__daemontools['cli']['shortmap']['h'] = 'help';
		
		$start = ($ignorefirst) ? 1 : 0;
		$parsed = array();
		// Loop through each of the bits
		for ($i = $start; $i < count($bits); $i++) {
			// Get the current bit.
			$bit = $bits[$i];
			
			// This list will contain all params found in this bit.
			$params = array();
			if (strpos($bit, '--') !== false && strpos($bit, '--') == 0) {
				// Long parameter.
				// Get the parameter
				$full_param_bit = substr($bit, 2);
				// Only get the bit before the = if there is one
				$param_bits = preg_split('@[=:]@', $full_param_bit);
				$param_bit = strtolower($param_bits[0]);
				// Check that it is valid,
				if (isset($__daemontools['cli']['params'][$param_bit])) {
					// If so, add it to the list of params we found in this "bit"
					// For long params this will be the only one.
					$params[] = $full_param_bit;
				} else {
					// If not, add it to the rejected list.
					$parsed['rejected params'][] = '--'.$param_bit;
				}
			} elseif (strpos($bit, '-') !== false && strpos($bit, '-') == 0) {
				// Short parameter(s)
				// Get the parameter(s)
				$param_bits = str_split(substr(strtolower($bit), 1));
				// Loop through each parameter passed here
				foreach ($param_bits as $param_bit) {
					// Check if we have the required short->long mapping.
					if (isset($__daemontools['cli']['shortmap'][$param_bit])) {
						// If so, add it to the list of params we found in this "bit"
						$params[] = $__daemontools['cli']['shortmap'][$param_bit];
					} else {
						// If not, add it to the rejected list.
						$parsed['rejected params'][] = '-'.$param_bit;
					}
				}
			} else {
				// Not a long parameter, or accpted as a value, add as rejected.
				$parsed['rejected params'][] = $bit;
			}
			
			// Loop though each param we found in this bit
			foreach ($params as $param) {
				// Split on = incase a value was given aswell.
				$param_bits = preg_split('@[=:]@', $param, 2);
				$value = (count($param_bits) > 1) ? $param_bits[1] : null;
				$param = strtolower($param_bits[0]);
			
				// Make sure an array in the $parsed array exists for this param
				if (!isset($parsed[$param])) { $parsed[$param] = array('count' => 0, 'values' => array()); }
				// Increase the count
				$parsed[$param]['count']++;
				// Check if the param takes a value.
				if ($__daemontools['cli']['params'][$param]['takesValue']) {
					// If it does, get the value. 
					// First check if it was passed with an =
					if ($value != null) {
						$parsed[$param]['values'][] = $value;
					} else {
						// else get it from the bits passed to us, and increment the counter
						// so that the outer loop doesn't try to parse the value as a param
						$i++;
						$parsed[$param]['values'][] = $bits[$i];
					}
				} else {
					// Remove the value entry from the array if this param doesn't take
					// any parameters
					unset($parsed[$param]['values']);
				}
			}
		}
		
		// Remove help entries
		unset($__daemontools['cli']['params']['help']);
		unset($__daemontools['cli']['shortmap']['h']);
		
		// Store the parsed array so that it can be accessed by getParsedCLIParams()
		$__daemontools['cli']['parsed'] = $parsed;
		return $parsed;
	}
	
	/**
	 * Get the Parseed CLI Params.
	 *
	 * @return Array representing previously-parsed CLI Params.
	 */
	function getParsedCLIParams() {
		global $__daemontools;
		
		return (isset($__daemontools['cli']['parsed'])) ? $__daemontools['cli']['parsed'] : array();
	}
?>