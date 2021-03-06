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
	addCLIParam('', 'debug', 'Cause logging of actions by doDaemonLoop to be logged to '.$config['daemon']['logfile']);
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
		global $__daemontools, $config;

		if (DAEMON_DEBUG) {
			ob_start();
			$__daemontools['force_echo'] = true;
			doEcho('doDaemonLoop: ', $__daemontools['callbacks'][$type]['description'], CRLF);
		}
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
			case DAEMONTOOLS_SIGNAL:
				handleSignal($args['signal']);
				break;
			// Currently unhandled callbacks
			case DAEMONTOOLS_STARTED:
			case DAEMONTOOLS_STOPPED:
			case DAEMONTOOLS_EXITING:
			default:
				break;
		}
		if (DAEMON_DEBUG) { 
			$__daemontools['force_echo'] = false;
			$output = ob_get_contents();
			if ($__daemontools['forked']) {
				ob_end_clean();
			} else {
				ob_end_flush();
			}
			file_put_contents($config['daemon']['logfile'], $output, FILE_APPEND);
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
		global $config;
		$filename = explode('.', $dest);
		$fileext = strtolower(array_pop($filename));
		
		$tempname = $dest;
		$i = 0;
		while (file_exists($tempname)) {
			if ($i >= $config['daemon']['maxnames']) { return null; }
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
								doReport(array('source' => 'daemon::handleINotify', 'message' => $daemon['inotify']['files'][$event['wd']]['file'].' has been archived as watched.'));
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
								if ($dest == null || !rename($source, $dest)) {
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
		
		doCommands('pre_reindex');
		
		// The var names in the config are huge, make them nicer for use here.
		$basedir = $config['daemon']['reindex']['basedir'];
		$dirs = $config['daemon']['reindex']['dirs'];
		$downloaddirs = $config['daemon']['reindex']['downloaddir'];
		$folderpatterns = $config['daemon']['reindex']['dirpatterns'];
		$filepatterns = $config['daemon']['reindex']['filepatterns'];
		$extentions = $config['daemon']['reindex']['extentions'];
		$badfiles = $config['daemon']['reindex']['badfile'];
		$usedirnames = $config['daemon']['reindex']['usedirnames'];
		$symlinkwatched = $config['daemon']['reindex']['symlinkwatched'];

		if (!is_array($downloaddirs)) {
			$downloaddirs = array($downloaddirs);
		}

		foreach ($downloaddirs as $downloaddir) {
			// Get a listing of the directory
			$dirlist = directoryToArray($downloaddir, false, false);
			foreach ($dirlist as $dir) {
				// Check if this is actually a directory.
				if (isset($dir['contents'])) {
					$deleteDir = false;
					// Check if it matches any of the patterns.
					doEcho('Dir: ', $dir['name'], CRLF);
					$folderinfo = getEpisodeInfo($folderpatterns, $dir['name']);
					$hasGood = false;
					$goodcount = 0;
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
								$hasGood = true;
								$goodcount++;
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

								if ($dest == null) {
									doEcho("\t\t", 'Unable to move from: ', $source, ' - rename count exceeded.', CRLF);
								} else {
									doEcho("\t\t", 'Moving from: ', $source, CRLF);
									doEcho("\t\t", 'Moving to: ', $dest, CRLF);
	
									$extra = '';
									if ($config['daemon']['autotv']['showmanage']) {
										$extra .= ' (Manage: '.$config['daemon']['autotv']['manageurl'].'?show='.urlencode($info['name']).')';
									}
									doReport(array('source' => 'daemon::handleReindex', 'message' => sprintf('Download Completed: %s %dx%02d%s', $info['name'], $info['season'], $info['episode'], $extra)));
	
									// Move it.
									if (rename($source, $dest)) {
										$goodcount--;
									}
									if ($symlinkwatched) {
										$link = getDestFile(preg_replace('@//@si', '/', $linkdir.'/'.$targetname));
										if ($link != null) {
											doEcho("\t\t", 'Linking to: ', $link, CRLF);
											symlink($dest, $link);
										}
									}
								}
							}
						}
					}

					// If the dir had any good files in it, and all of them were moved, then
					// we can delete the dir.
					if ($hasGood && $goodcount == 0) {
						$dirname = preg_replace('@//@si', '/', $downloaddir.'/'.$dir['name']);
						doEcho(CRLF, 'Removing: ', $dirname, CRLF);
						rmdirr($dirname);
					}
				}
			}
		}
		
		doCommands('post_reindex');
	}
	
	/**
	 * Handle checking for automatic downloads
	 */
	function handleCheckAuto() {
		global $config, $daemon;
		
		doCommands('pre_checkauto');

		// Get days to check.
		$checkDays = array('0');
		if (isset($config['daemon']['autotv']['tryagain']) && is_array($config['daemon']['autotv']['tryagain'])) {
			foreach ($config['daemon']['autotv']['tryagain'] as $day) {
				if (preg_match('@^-?[0-9]+$@', $day)) {
					$checkDays[] = (0 - $day);
				}
			}
		}
		$checkDays = array_unique($checkDays);
		sort($checkDays, SORT_NUMERIC);

		$daemonProviders = $config['daemon']['autotv']['providers'];
		if ($daemonProviders == '' || (is_array($daemonProviders) && count($daemonProviders) == 0)) { $daemonProviders = null; }

		if (!is_array($daemonProviders)) {
			$daemonProviders = array($daemonProviders);
		}
		
		// Posts we need to download.
		$posts = array();
		
		doReport(array('source' => 'daemon::handleCheckAuto', 'message' => 'Beginning Auto Download Check.'));
		doEcho('Trying Auto Download..', CRLF);
		// Look at all the shows that aired the days we want.
		foreach ($checkDays as $day) {
			$startTime = strtotime('+'.($day - 1).' days 00:00');
			$endTime = strtotime('+'.($day - 1).' days 00:00');

			doEcho('Trying for day: ', date('r', $startTime), CRLF);

			foreach (getShows(true, $startTime, $endTime, false, $config['autodownload']['source']) as $show) {
//			foreach (getShows(true, strtotime("03/19/2010"), -1, false, $config['autodownload']['source']) as $show) {
				$info = $show['info'];
				$firstep = ((int)$show['season'] == 1 && (int)$show['episode'] == 1);
				$first = ($firstep && $config['daemon']['autotv']['allfirst']);
				// Make sure the show is known to the DB.
				if ($config['daemon']['autotv']['autodb'] && !$info['known']) {
					// Add show.
					$autoautomatic = ($config['daemon']['autotv']['autoautomatic'] == 1 || ($config['daemon']['autotv']['autoautomatic'] == 2 && $firstep));
					$result = addShow($show['name'], $show['info'], $autoautomatic, $show['sources']);
					if ($result == 0 && $autoautomatic || (!$autoautomatic && !$config['daemon']['autotv']['onlyannounceautomatic'])) {
						$url = $config['daemon']['autotv']['manageurl'].'?show='.urlencode($show['name']);
						doReport(array('source' => 'daemon::handleCheckAuto', 'message' => sprintf('Discovered new (%s) show: %s [Manage: %s]', ($autoautomatic ? 'automatic' : 'manual'), $show['name'], $url)));
					}
				}
				// Check if this show is marked as automatic, (and is marked as important if onlyimportant is set true)
				// Also check that the show hasn't already been downloaded.
				$important = (($info['important'] && $config['download']['onlyimportant']) || !$config['download']['onlyimportant']);
				if (($first || goodSource($show)) && ($info['automatic'] || $first) && $important && (isset($daemon['cli']['autotv-force']) || !hasDownloaded($show['name'], $show['season'], $show['episode']))) {
					doEcho('Show: ', $show['name'], CRLF);
					$items = array();
					
					foreach ($daemonProviders as $provider) {
						// Search for this show, and get the optimal match.
						ob_start();
						$search = searchFor(getSearchString($show), false, $provider);
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
							$i = $search->xpath('item');
 							foreach ($i as $item) {
								$items[] = $item;
							}
						}
					}

					if (count($items) > 0) {
						// No errors, get the best item
						doEcho('Items: ');
						doPrintR($items);
						$optimal = GetBestOptimal($items, $info['size'], false, true, $show);
						// If a best item was found
						$extra = '';
						if ($config['daemon']['autotv']['showmanage']) {
							$extra .= ' (Manage: '.$config['daemon']['autotv']['manageurl'].'?show='.urlencode($show['name']).')';
						}
						if (count($items) > 0 && $optimal != -1) {
							doEcho('Found something.');
							$best = $items[$optimal];
							$bestid = (int)$best->nzbid;

							$nzbtype = '';
							if (isset($best->nzbtype) && $best->nzbtype != 'newzbin') {
								$nzbtype = '_' . $best->nzbtype;
							}

							doEcho('Optimal: ', $optimal, CRLF);
							doEcho('Best: ', $bestid, CRLF);
							doEcho('NZB Type: ', (empty($nzbtype) ? 'newzbin' : substr($nzbtype, 1)), CRLF);

							// Try to download.
							$directfilename = sprintf('%s - %dx%02d - %s', $show['name'], $show['season'], $show['episode'], $show['title']);
							if (isset($best->raw)) {
								if (isset($best->files->file)) {
									$bestid = '';
									foreach ($best->files->file as $file) {
										if (!empty($bestid)) { $bestid .= ','; }
										$bestid .= $file;
									}
									$result = call_user_func('downloadDirect'.$nzbtype, $bestid, $directfilename);
								} else {
									$result = array('status' => false, 'output' => 'No files');
								}
							} else {
								$result = call_user_func('downloadNZB'.$nzbtype, $bestid, $directfilename);
							}

							doEcho('Result: ');
							doPrintR($result);
							if ($result['status']) {
								// Hellanzb tells us that the nzb was added ok, so mark the show as downloaded
								setDownloaded($show['name'], $show['season'], $show['episode'], $show['title']);
								doReport(array('source' => 'daemon::handleCheckAuto', 'message' => sprintf('Beginning automatic download of: %s %dx%02d [%s] (NZB: %d, Source: %s)%s', $show['name'], $show['season'], $show['episode'], $show['title'], $bestid, implode(', ', $show['sources']), $extra)));
							} else {
								doReport(array('source' => 'daemon::handleCheckAuto', 'message' => sprintf('Failed to start automatic download of: %s %dx%02d [%s] (NZB: %d, Source: %s)%s', $show['name'], $show['season'], $show['episode'], $show['title'], $bestid, implode(', ', $show['sources']), $extra)));
							}
						} else {
							doEcho('Not something.');
							doReport(array('source' => 'daemon::handleCheckAuto', 'message' => sprintf('No downloads found for: %s %dx%02d [%s] %s', $show['name'], $show['season'], $show['episode'], $show['title'], $extra)));
						}
					}
				}
			}
		}
		
		doCommands('post_checkauto');
	}
	
	// Debugging enabled?
	define('DAEMON_DEBUG', isset($daemon['cli']['debug']));
	if (DAEMON_DEBUG) {
		doEcho('Debugging Enabled. (', $config['daemon']['logfile'], ')', "\n");
		unlink('/tmp/daemon.log');
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
		startDaemon($pid, $fork, 'doDaemonLoop', ($config['daemon']['looptime'] * 1000), __FILE__);
	}
?>
