<?php
	//----------------------------------------------------------------------------
	// Misc defines
	//----------------------------------------------------------------------------
	define('CRLF', "\r\n");
	define('EOL', '<br>'.CRLF);

	//----------------------------------------------------------------------------
	// Indexing daemon config settings
	//----------------------------------------------------------------------------
	// Pid File
	$config['daemon']['pid'] = dirname(__FILE__).'/daemon.pid';
	// Should the daemon fork?
	$config['daemon']['fork'] = false;

	//----------------------------------------------------------------------------
	// Automatic Downloading settings.
	//----------------------------------------------------------------------------
	// Source to use for automatic downloads. ('' for default);
	$config['autodownload']['source'] = 'pogdesign';

	//----------------------------------------------------------------------------
	// How should we search newzbin?
	//----------------------------------------------------------------------------
	// Search provider
	$config['search']['provider'] = 'http://localhost/new/nzb/';
	// Username for searching
	$config['search']['username'] = 'unknown';
	// Password for searching
	$config['search']['password'] = 'unknown';
	
	//----------------------------------------------------------------------------
	// Data Storage confiuration
	//----------------------------------------------------------------------------
	// What storage to use
	$config['storage']['type'] = 'mysql';
	
	// Configuration Settings for mysql
	// Database host
	$config['storage']['mysql']['host'] = '127.0.0.1';
	// Database port
	$config['storage']['mysql']['port'] = '3306';
	// Database username
	$config['storage']['mysql']['user'] = 'autodownload';
	// Database password
	$config['storage']['mysql']['pass'] = 'wf345fr5yrt';
	// Database name
	$config['storage']['mysql']['database'] = 'autodownload';
	
	// Configuration Settings for text-based storage
	// Where is Show information stored (Equivilent of "shows" table)
	$config['storage']['text']['settings'] = 'DataDB.php';
	// Where is downloaded information stored (Equivilent of "downloads" table)
	$config['storage']['text']['got'] = 'GotDB.php';
	
	//----------------------------------------------------------------------------
	// Misc Defaults
	//----------------------------------------------------------------------------
	// What pattern should we use for searching?
	$config['default']['searchstring'] = '{series} {season}x{episode}';
	// What attributes should we use when searching?
	$config['default']['attributes'] = 'Attr:Language=English';
	// If dirsort is enabled, what should be the default if unspecified
	$config['default']['dirname'] = 'Misc';
	
	//----------------------------------------------------------------------------
	// Download Modifiers
	//----------------------------------------------------------------------------
	// Only download shows marked as "important" ?
	$config['download']['onlyimportant'] = false;
	// Try to download shows in high def? (This doubles the optimal download size)
	$config['download']['highdef'] = false;
	
	//----------------------------------------------------------------------------
	// Hellanzb details
	//----------------------------------------------------------------------------
	$config['downloader']['type'] = 'hellanzb_shell';
	
	// Configuration Settings for hellanzb
	$config['downloader']['hellanzb']['config'] = '/usr/etc/hellanzb.conf';
	
	// Configuration Settings for hellanzb_shell
	// Location of python
	$config['downloader']['hellanzb_shell']['python'] = '/usr/bin/python';
	// Location of hellanzb
	$config['downloader']['hellanzb_shell']['location'] = '/usr/bin/hellanzb.py';
	// Location of hellanzb config (libnotify must be disabled)
	$config['downloader']['hellanzb_shell']['config'] = '/usr/etc/hellanzb.conf.web';
	
	//----------------------------------------------------------------------------
	// Multi-Download sleep times.
	// Slow mode will always use 5 and 60, these values are for normal mode
	//----------------------------------------------------------------------------
	// Sleep after this many queries to newzbin
	$config['download']['sleepcount'] = 5;
	// Sleep for this numer of seconds
	$config['download']['sleeptime'] = 10;
	
	//----------------------------------------------------------------------------
	// Sources for TV listing
	//----------------------------------------------------------------------------
	// Default source?
	$config['tv']['default_source'] = 'on-my.tv';
	// What is the web-accessible path to folder for the sources?
	$config['tv']['urlbase'] = 'http://localhost/new/sources/';
	// What is the path to folder for the sources?
	$config['tv']['filebase'] = dirname(__FILE__).'/www/sources/';
	// How long should the sources cache their data?
	$config['tv']['cachetime'] = 82800; // Cache for 23 hours
	
	
	//----------------------------------------------------------------------------
	// Look for config.user.php and load it if found to overwrite the defaults
	// here.
	// config.user.php will be ignored by git
	//----------------------------------------------------------------------------
	if (file_exists(dirname(__FILE__).'/config.user.php')) {
		include_once(dirname(__FILE__).'/config.user.php');
	}
?>