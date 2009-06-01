#!/usr/bin/php
<?php
	include_once(dirname(__FILE__).'/config.php');
	include_once(dirname(__FILE__).'/functions.php');
	include_once(dirname(__FILE__).'/daemon.tools.php');

	addCLIParam('f', 'foreground', 'Don\'t fork.');
	addCLIParam('b', 'background', 'Do Fork. (Takes priority over --foreground)');
	addCLIParam('r', 'reindex', 'Force a manual reindex, don\'t start daemon.');
	addCLIParam('a', 'autotv', 'Run a manual AutoTV check, don\'t start daemon.');
	addCLIParam('', 'autotv-force', 'Run a manual AutoTV check and redownload shows marked as "got", don\'t start daemon.');
	addCLIParam('', 'noreindex', 'Don\'t periodically reindex.');
	addCLIParam('', 'noautotv', 'Don\'t periodically run AutoTV checks.');
	addCLIParam('', 'pid', 'Specify an alternative PID file.', true);
	
	$daemon['cli'] = parseCLIParams($_SERVER['argv']);
	if (isset($daemon['cli']['help'])) {
		doEcho('Usage: ', $_SERVER['argv'][0], ' [options]', CRLF, CRLF);
		doEcho('Options:', CRLF, CRLF);
		doEcho(showCLIParams(), CRLF);
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
		// doEcho('doDaemonLoop: ', $__daemontools['callbacks'][$type]['description'], CRLF);
		
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
				handleSignal($args['signal']);
				break;
			case DAEMONTOOLS_STARTED:
			case DAEMONTOOLS_STOPPED:
			case DAEMONTOOLS_EXITING:
			default:
				break;
		}
	}
	
	/**
	 * Handler for signals sent to the daemon.
	 *
	 * @param $signal Signal that was sent.
	 * @return returns true if signal was handled.
	 */
	function handleSignal($signal) {
		global $config;
		switch ($signal) {
			case SIGHUP:
				include(dirname(__FILE__).'/config.php');
				return true;
			default:
				break;
		}
		
		return false;
	}
	
	/**
	 * Handle the loop from startDaemon.
	 */
	function handleLoop() {
		global $config, $daemon;

		// Check to see what time has changed
		foreach ($config['times'] as $time => $format) {
			// Get the time from the previous loop
			$lastTime[$time] = (isset($daemon['time'][$time])) ? $daemon['time'][$time] : 0;
			// Get the current time.
			$thisTime[$time] = date($format);
			
			// Store if the time changed
			$changed[$time] = ($thisTime[$time] != $lastTime[$time]);
			// Store the time for the next loop
			$daemon['time'][$time] = $thisTime[$time];
		}
		
		// Call appropriate functions
		foreach ($config['times'] as $time => $format) {
			if ($changed[$time] && function_exists('handle'.ucfirst($time).'Changed')) {
				@call_user_func('handle'.ucfirst($time).'Changed', $lastTime, $thisTime);
			}
		}
		
		// Now, check if we are supposed to be doing inotify-y things.
		if (!$config['daemon']['reindex']['usedirnames'] && function_exists('inotify_init')) {
			handleINotify();
		}
		
		
		// Remember that we have run the loop before.
		$daemon['looped'] = true;
	}
	
	/**
	 * Handle the Minute changing.
	 *
 	 * @param $lastTime The last time the loop ran.
	 * @param $thisTime The time now.
	 */
	function handleMinuteChanged($lastTime, $thisTime) {
		global $config, $daemon;
		
		// Check if its time to run autotv
		if (!isset($daemon['cli']['noautotv']) && checkTimeArray($config['daemon']['autotv']['times'], $thisTime)) {
			handleCheckAuto();
		}
		
		// Check if its time to run reindex
		if (!isset($daemon['cli']['noreindex']) && checkTimeArray($config['daemon']['reindex']['times'], $thisTime)) {
			handleReindex();
		}
	}
	
	/**
	 * Add an INotify watch for the given file.
	 *
	 * @param $file File to watch.
	 */
	function addINotifyWatch($file) {
		global $config, $daemon;
		
		$basedir = $config['daemon']['reindex']['basedir'];
		$watchdir = preg_replace('@//@si', '/', $basedir.'/'.$config['daemon']['reindex']['dirs'][0]);
		
		$item['file'] = $file;
		$item['access_count'] = 0;
		$id = inotify_add_watch($daemon['inotify']['fd'], $watchdir.'/'.$file, IN_ACCESS | IN_CLOSE);
		// Store this watch so we can perform associated actions later
		$daemon['inotify']['files'][$id] = $item;
		// Store a reverse mapping of filenames -> ids so we can get the id
		// from a filename.
		$daemon['inotify']['file_names'][$file] = $id;
		
		doEcho("\t", 'Added watch for: ', $file, ' [', $id,']', CRLF);
	}
	
	/**
	 * Delete an INotify watch for the given file.
	 *
	 * @param $file File to watch.
	 */
	function delINotifyWatch($file) {
		global $config, $daemon;
		
		$id = $daemon['inotify']['file_names'][$file];
		
		unset($daemon['inotify']['file_names'][$file]);
		unset($daemon['inotify']['files'][$id]);
		
		inotify_rm_watch($daemon['inotify']['fd'], $id);
		doEcho("\t", 'Removed watch for: ', $file, CRLF);
	}
	
	/**
	 * Take the given destination name, and make sure its not currently in use.
	 * If it is, keep trying to find a valid one by adding (1) (2) (3) etc untill
	 * a name that isn't taken is found.
	 *
	 * @param $dest File name to work from.
	 * @return Filename based on $dest that isn't already in use.
	 */
	function getDestFile($dest) {
		$filename = explode('.', $dest);
		$fileext = strtolower(array_pop($filename));
		
		$tempname = $dest;
		$i = 0;
		while (file_exists($tempname)) {
			$newtempname = preg_replace('@\.'.$fileext.'$@', ' ('.$i++.').'.$fileext, $dest);
			doEcho("\t\t\t", 'File "'.$tempname.'" already exists, trying: "'.$newtempname.'"', CRLF);
			$tempname = $newtempname;
		}
		return $tempname;
	}
	
	
	/**
	 * Handle INotify events.
	 */
	function handleINotify() {
		global $config, $daemon;
		
		$basedir = $config['daemon']['reindex']['basedir'];
		$watchdir = preg_replace('@//@si', '/', $basedir.'/'.$config['daemon']['reindex']['dirs'][0]);
		$watcheddir = preg_replace('@//@si', '/', $basedir.'/'.$config['daemon']['reindex']['dirs'][1]);
		if (!isset($daemon['looped'])) {
			doEcho('Watching: ', $watchdir, CRLF);
			doEcho(' Watched: ', $watcheddir, CRLF);
			
			// We havn't looped before, so start the inotify stuff
			$daemon['inotify']['fd'] = inotify_init();
			$daemon['inotify']['root'] = inotify_add_watch($daemon['inotify']['fd'], $watchdir, IN_CREATE | IN_MOVED_FROM | IN_MOVED_TO | IN_DELETE);
			$files = directoryToArray($watchdir, false, false, false);
			foreach ($files as $file) {
				addINotifyWatch($file['name']);
			}
			stream_set_blocking($daemon['inotify']['fd'], 0);
		}
		
		// Record which nodes hvae been accessed this loop.
		// Sometimes the loop will only get 1 event for a particular file, however
		// sometimes it gets upwards of 500, this really breaks things, so this
		// is used to make sure we only increment the counter for IN_ACCESS once
		// per file, per loop, regardless of how many events we get.
		$has_accessed = array();
		
		while (($events = inotify_read($daemon['inotify']['fd'])) !== false) {
			doEcho("\t\t\t\t\t\t\t\t\t\t", 'Got ', count($events), ' events!', CRLF);
			foreach ($events as $event) {
				// doPrintR($event);
			
				// Have we gained a file from being created or moved in?
				if ((($event['mask'] & IN_CREATE) > 0 || ($event['mask'] & IN_MOVED_TO) > 0) && ($event['mask'] & IN_ISDIR) == 0) {
					// Add watch for new file
					doEcho('Discovered new file: ', $event['name'], CRLF);
					
					addINotifyWatch($event['name']);
				}
				
				// Have we lost a file from being deleted or moved away?
				if ((($event['mask'] & IN_DELETE) > 0 || ($event['mask'] & IN_MOVED_FROM) > 0) && ($event['mask'] & IN_ISDIR) == 0) {
					doEcho('Lost file: ', $event['name'], CRLF);
					
					delINotifyWatch($event['name']);
				}

				if (!isset($daemon['inotify']['files'][$event['wd']])) { continue; }

				// Has a watched file been accessed
				if (($event['mask'] & IN_ACCESS) > 0 && isset($daemon['inotify']['files'][$event['wd']]['access_count'])) {
					if (!in_array($event['wd'], $has_accessed)) {
						$has_accessed[] = $event['wd'];
						$blacklist = false;
						if ($config['daemon']['reindex']['fuser']) {
							$source = preg_replace('@//@si', '/', $watchdir.'/'.$daemon['inotify']['files'][$event['wd']]['file']);
							exec('lsof "'.$source.'" 2>/dev/null | tail -n +2', $output);
							
							$blacklist_reason = 'Unknown';
							if (count($output) == 0) {
								$blacklist = true;
								$blacklist_reason = 'No LSOF output';
							} else {
								foreach ($output as $access) {
									$bits = preg_split("/\s+/", $access);
									$process = $bits[0];
									$user = $bits[2];
									
									$array = $config['daemon']['reindex']['fuser_blacklist'];
									if (in_array($user.'-'.$process, $array) || in_array('*-'.$process, $array) || in_array($user.'-*', $array)) {
										$blacklist = true;
										$blacklist_reason = $user.'-'.$process.' matches a blacklisted pair';
										break;
									}
								}
							}
						}
						doEcho($daemon['inotify']['files'][$event['wd']]['file'], ' has been accessed. ');
						if (!$blacklist) {
							// Record how many IN_ACCESS events we get for a file.
							$daemon['inotify']['files'][$event['wd']]['access_count']++;
							doEcho('[', $daemon['inotify']['files'][$event['wd']]['access_count'],']', CRLF);
						} else {
							doEcho('Ignored due to blacklisting. ('.$blacklist_reason.')', CRLF);
						}
					}
				}
				
				// Has a watched file been closed?
				if (($event['mask'] & IN_CLOSE_NOWRITE) > 0 && isset($daemon['inotify']['files'][$event['wd']]['access_count'])) {
					// File was closed, check if it was accessed enough to warrant being
					// considered as watched.
					doEcho($daemon['inotify']['files'][$event['wd']]['file'], ' has been accessed ', $daemon['inotify']['files'][$event['wd']]['access_count'], ' times.', CRLF);
					if ($daemon['inotify']['files'][$event['wd']]['access_count'] > $config['daemon']['reindex']['inotify_count']) {
						doEcho($daemon['inotify']['files'][$event['wd']]['file'], ' is being marked as watched.', CRLF);
						// Get the source of this file.
						$source = preg_replace('@//@si', '/', $watchdir.'/'.$daemon['inotify']['files'][$event['wd']]['file']);
						
						if (is_link($source)) {
							doEcho($daemon['inotify']['files'][$event['wd']]['file'], ' is actually a symlink, ');
							if ($config['daemon']['reindex']['deletesymlink']) {
								doEcho('removing.', CRLF);
								unlink($source);
							} else {
								doEcho('ignoring.', CRLF);
							}
						} else {
							doEcho($daemon['inotify']['files'][$event['wd']]['file'], ' is not a symlink. Moving', CRLF);
							
							// Check if it matches any of the patterns.
							$patterns = $config['daemon']['reindex']['filepatterns'];
							
							$info = getEpisodeInfo($config['daemon']['reindex']['filepatterns'], $daemon['inotify']['files'][$event['wd']]['file']);
							if ($info != null) {
								$filename = explode('.', $daemon['inotify']['files'][$event['wd']]['file']);
								$fileext = strtolower(array_pop($filename));
								
								$targetdir = sprintf('%s/%s/Season %d', $watcheddir, $info['name'], $info['season']);
								$targetname = sprintf('%s %dx%02d.%s', $info['name'], $info['season'], $info['episode'], $fileext);
	
								$dest = getDestFile(preg_replace('@//@si', '/', $targetdir.'/'.$targetname));
								
								doEcho("\t\t", 'Moving from: ', $source, CRLF);
								doEcho("\t\t", 'Moving to: ', $dest, CRLF);
								
								doEcho($source, ' => ', $dest, CRLF);
								doReport(array('source' => 'daemon::handleINotify', 'message' => $daemon['inotify']['files'][$event['wd']]['file'].' has been archived as watched.'));
								
								// Make sure the target directory exists
								if (!file_exists($targetdir)) { mkdir($targetdir, 0777, true); }
								if (!rename($source, $dest)) {
									// Rename failed.
									// If the file still exists (its possible for a file to get lost
									// in a failed rename) then readd the watch.
									
									addINotifyWatch($daemon['inotify']['files'][$event['wd']]['file']);
								}
							}
						}
					}
					
					// If the watch wasn't removed, then reset the access count.
					if (isset($daemon['inotify']['files'][$event['wd']])) {
						$daemon['inotify']['files'][$event['wd']]['access_count'] = 0;
					}
				} else if (($event['mask'] & IN_CLOSE) > 0 && isset($daemon['inotify']['files'][$event['wd']]['access_count'])) {
					// Reset the counter on all close events.
					$daemon['inotify']['files'][$event['wd']]['access_count'] = 0;
				}
			}
		}
	}
	
	/**
	 * Handle moving files from the download directory to the sorted directory
	 */
	function handleReindex() {
		global $config;
		
		// The var names in the config are huge, make them nicer for use here.
		$basedir = $config['daemon']['reindex']['basedir'];
		$dirs = $config['daemon']['reindex']['dirs'];
		$downloaddir = $config['daemon']['reindex']['downloaddir'];
		$folderpatterns = $config['daemon']['reindex']['dirpatterns'];
		$filepatterns = $config['daemon']['reindex']['filepatterns'];
		$extentions = $config['daemon']['reindex']['extentions'];
		$badfiles = $config['daemon']['reindex']['badfile'];
		$usedirnames = $config['daemon']['reindex']['usedirnames'];
		$symlinkwatched = $config['daemon']['reindex']['symlinkwatched'];
		
		// Get a listing of the directory
		$dirlist = directoryToArray($downloaddir, false, false);
		foreach ($dirlist as $dir) {
			// Check if this is actually a directory.
			if (isset($dir['contents'])) {
				$deleteDir = false;
				// Check if it matches any of the patterns.
				$folderinfo = getEpisodeInfo($folderpatterns, $dir['name']);
				if ($folderinfo != null) {
					// Print them.
					doEcho('-------------------------------------------------------',CRLF);
					doEcho('Found Downloaded show that matches pattern: ', $folderinfo['pattern'], CRLF, CRLF);
					doEcho('Name: ', $folderinfo['name'], CRLF);
					doEcho('Season: ', $folderinfo['season'], CRLF);
					doEcho('Episode: ', $folderinfo['episode'], CRLF);
					doEcho('Title: ', $folderinfo['title'], CRLF);
					doEcho('-------------------------------------------------------',CRLF);
					
					// Look at all the files inside this directory.
					$files = directoryToArray($downloaddir.'/'.$dir['name'], false, false);
					foreach ($files as $file) {
						if (isset($file['contents'])) { continue; }
						
						doEcho("\t", $file['name'], CRLF);
						$filename = explode('.', $file['name']);
						$fileext = strtolower(array_pop($filename));
						$filename = implode('.', $filename);
						
						// Check if this file has a good file extention
						if (in_array($fileext, $extentions)) {
							// Check that its not a "bad" file
							foreach ($badfiles as $badfile) {
								if (stristr($file['name'], $badfile)) { continue 2; }
							}
							
							doEcho("\t\t", 'Found good file: ', $file['name'], CRLF);
							$info = null;
							if ($folderinfo['usefilepattern']) {
								$info = getEpisodeInfo($filepatterns, $file['name']);
							}
							if ($info == null) { $info = $folderinfo; }
							if ($usedirnames || $symlinkwatched) {
								if ($usedirnames) {
									$dirname = (isset($showinfo['dirname']) && in_array($showinfo['dirname'], $dirs)) ? $showinfo['dirname'] : $dirs[0];
								} elseif ($symlinkwatched) {
									$dirname = $dirs[1];
								}
								$targetdir = sprintf('%s/%s/%s/Season %d', $basedir, $dirname, $info['name'], $info['season']);
								$linkdir = sprintf('%s/%s', $basedir, $dirs[0]);
							} else {
								$targetdir = sprintf('%s/%s', $basedir, $dirs[0]);
							}
							$targetname = sprintf('%s %dx%02d.%s', $info['name'], $info['season'], $info['episode'], $fileext);
							
							if ($config['daemon']['reindex']['ignore0x00'] && (int)$info['season'] == 0 && (int)$info['episode'] == 0) {
								doEcho('Epsiode appears to be 0x00, ignoring.', CRLF);
								continue;
							}
							
							// Make sure the target directory exists
							if (!file_exists($targetdir)) { mkdir($targetdir, 0777, true); }
							
							// Get the full target/source names
							$source = preg_replace('@//@si', '/', $downloaddir.'/'.$dir['name'].'/'.$file['name']);
							$dest = getDestFile(preg_replace('@//@si', '/', $targetdir.'/'.$targetname));
							
							doEcho("\t\t", 'Moving from: ', $source, CRLF);
							doEcho("\t\t", 'Moving to: ', $dest, CRLF);
							
							doReport(array('source' => 'daemon::handleReindex', 'message' => sprintf('Download Completed: %s %dx%02d', $info['name'], $info['season'], $info['episode'])));
							
							// Move it. If the move is successful then the directory will
							// be deleted.
							// If multiple files in this directory get moved, then the
							// status of the last one will be used.
							$deleteDir = rename($source, $dest);
							if ($symlinkwatched) {
								$link = getDestFile(preg_replace('@//@si', '/', $linkdir.'/'.$targetname));
								doEcho("\t\t", 'Linking to: ', $link, CRLF);
								symlink($dest, $link);
							}
						}
					}
				}
				
				if ($deleteDir) {
					$dirname = preg_replace('@//@si', '/', $downloaddir.'/'.$dir['name']);
					doEcho(CRLF, 'Removing: ', $dirname, CRLF);
					rmdirr($dirname);
				}
			}
		}
	}
	
	/**
	 * Handle checking for automatic downloads
	 */
	function handleCheckAuto() {
		global $config, $daemon;
		
		// Posts we need to download.
		$posts = array();
		
		doReport(array('source' => 'daemon::handleCheckAuto', 'message' => 'Beginning Auto Download Check.'));
		doEcho('Trying Auto Download..', CRLF);
		// Look at all the shows that aired yesterday
		foreach (getShows(true, -1, -1, false, $config['autodownload']['source']) as $show) {
			$info = $show['info'];
			$first = (((int)$show['season'] == 1 && (int)$show['episode'] == 1) && $config['daemon']['autotv']['allfirst']);
			// Check if this show is marked as automatic, (and is marked as important if onlyimportant is set true)
			// Also check that the show hasn't already been downloaded.
			$important = (($info['important'] && $config['download']['onlyimportant']) || !$config['download']['onlyimportant']);
			if (goodSource($show) && ($info['automatic'] || $first) && $important && (isset($daemon['cli']['autotv-force']) || !hasDownloaded($show['name'], $show['season'], $show['episode']))) {
				doEcho('Show: ', $show['name'], CRLF);
				// Search for this show, and get the optimal match.
				ob_start();
				$search = searchFor(getSearchString($show), false);
				$buffer = ob_get_contents();
				ob_end_clean();
				
				// Check for errors
				if (preg_match('@function.file-get-contents</a>]: (.*)  in@U', $buffer, $matches)) {
					doEcho('An error occured loading the search provider: ', $matches[1], CRLF);
				} else if ($search === false) {
					doEcho('An error occured getting the search results: The search provider could not be loaded', CRLF);
				} else  if ($search->error['message'] && $search->error['message'] != '') {
					doEcho('An error occured getting the search results: ', (string)$search->error['message'], CRLF);
				} else {
					// No errors, get the best item
					$items = $search->item;
					doEcho('Items: ');
					doPrintR($items);
					$optimal = GetBestOptimal($search->xpath('item'), $info['size'], false, true, $show);
					// If a best item was found
					if (count(items) > 0 && $optimal != -1) {
						$best = $items[$optimal];
						$bestid = (int)$best->nzbid;
						doEcho('Optimal: ', $optimal, CRLF);
						doEcho('Best: ', $bestid, CRLF);
						// Try to download.
						$result = downloadNZB($bestid);
						doEcho('Result: ');
						doPrintR($result);
						if ($result['status']) {
							// Hellanzb tells us that the nzb was added ok, so mark the show as downloaded
							setDownloaded($show['name'], $show['season'], $show['episode'], $show['title']);
							doReport(array('source' => 'daemon::handleCheckAuto', 'message' => sprintf('Beginning automatic download of: %s %dx%02d [%s] (NZB: %d)', $show['name'], $show['season'], $show['episode'], $show['title'], $bestid)));
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
	if (isset($daemon['cli']['autotv']) || isset($daemon['cli']['autotv-force'])) { $daemonise = false; handleCheckAuto(); }
	
	// Start the daemon so that it loops every few seconds seconds.
	if ($daemonise) {
		startDaemon($pid, $fork, doDaemonLoop, ($config['daemon']['looptime'] * 1000), __FILE__);
	}
?>