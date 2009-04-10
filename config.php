<?php
	//----------------------------------------------------------------------------
	// Daemon config settings
	//----------------------------------------------------------------------------
	// Pid File
	$config['daemon']['pid'] = dirname(__FILE__).'/daemon.pid';
	// Should the daemon fork?
	$config['daemon']['fork'] = false;
	// How many seconds should the daemon sleep inbetween loops?
	// Default is 5
	$config['daemon']['looptime'] = 5;
	
	// Should all 1x01's be downloaded ?
	// This is ignored if only-important is true.
	$config['daemon']['autotv']['allfirst'] = true;
	
	// Times that the daemon should check for new TV.
	// This is an array containing "time arrays".
	// "time arrays" are arrays with keys corresponding to the values of
	// $config['times'] (see "Misc Defines" section).
	// The values for each of the timenames should be a string of numbers
	// separated by any non-numeric value.
	// Example: array(array('hour' => '03,04,05,06,09', 'minute' => '00,30'));
	// This is checked every minute
	$config['daemon']['autotv']['times'] = array(array('hour' => '03,04,05,06,09', 'minute' => '00,30'),
	                                             array('hour' => '02,23', 'minute' => '30'),
	                                            );
	// Same as above, but for running reindex.
	$config['daemon']['reindex']['times'] = array(array('minute' => '00,15,30,45'));
	
	// Valid dirs to reindex into. If a show has a dirname not in this list, then
	// the default (first) will be used.
	// $config['daemon']['reindex']['dirs'] = array('Misc', 'Watching', 'Unwatched', 'Watched');
	// $config['daemon']['reindex']['dirs'] = array('Unwatched', 'Watched');
	$config['daemon']['reindex']['dirs'] = array('Unwatched', 'Watched');
	// Base directory to reindex into
	$config['daemon']['reindex']['basedir'] = '/media/data/TV/';
	// Base directory to reindex from
	$config['daemon']['reindex']['downloaddir'] = '/media/data/nzb/';
	
	// Patterns used to match directory names.
	// Array containing key/value pairs. Keys are the patterns to match, values
	// are an array which shows where each key-part of the match is.
	$config['daemon']['reindex']['dirpatterns'] = array('/^(.*)[_ ]-[_ ]([0-9]+)[Xx]([0-9\-]+)[_ ]-[_ ]([^_]+)(?:_.*)?$/U' => array('name' => 1, 'season' => 2, 'episode' => 3, 'title' => 4),
	                                                    '/^(.*)[_ ]-[_ ]([0-9]+)[Xx]([0-9\-]+).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                    '/^selected_files_[0-9]+-[0-9]+$/U' => array('usefilepattern' => true),
	                                                   );
	// Patterns used to match file names when using inotify
	$config['daemon']['reindex']['filepatterns'] = array('/^(.*)\.s([0-9]+)e([0-9][0-9]).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                     '/^(.*)\.([0-9]+)[Xx]?([0-9][0-9]).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                     '/^(.*) ([0-9]+)[Xx]?([0-9][0-9]).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                    );
	
	// Should filepatterns above be used when comparing files inside a directory
	// during reindexing? (If true and a pattern matches, then the info from the
	// file is used instead of the info from the folder).
	// This is primarily useful for all-in-one seasons downloads.
	// This can be explicitly set/unset for a given dir pattern by adding a
	// 'usefilepattern' => true/false key to the array.
	$config['daemon']['reindex']['usefilepatterns'] = false;
	
	// File extentions that we care about when reindexing.
	$config['daemon']['reindex']['extentions'] = array('avi', 'mkv', 'mpg', 'mpeg', 'flv');
	// If any of the words in this array are in the file name we ignore it even if
	// its file extention is one of the above.
	$config['daemon']['reindex']['badfile'] = array('sample');
	// Use old dirnames system where shows specify what dir inside the basedir
	// they are reindexed into.
	// If this is false, shows will all be moved into the first dir in the dirs
	// setting, regardless of the "dirname" specified. inotify will then be used
	// to move the shows into subdirs inside the second specified directory after
	// the file has been watched.
	$config['daemon']['reindex']['usedirnames'] = false;
	// If inotify is watching a file, how many IN_ACCESS events need to be
	// generated to consider a file as watched?
	// This should be at least 10, preferably higher.
	// The count is increased by 1 every time the daemon loops and an inotify
	// event was recieved.
	$config['daemon']['reindex']['inotify_count'] = 100;
	

	//----------------------------------------------------------------------------
	// Automatic Downloading settings.
	//----------------------------------------------------------------------------
	// Source to use for automatic downloads. ('' for default);
	$config['autodownload']['source'] = '';

	//----------------------------------------------------------------------------
	// How should we search newzbin?
	//----------------------------------------------------------------------------
	// Search provider
	// This provider should give search results in XML like so:
	// <nzb>
	//     <item>
	//         <nzbid>12345</nzbid>
	//         <name>Example Entry</name>
	//         <sizemb>400</sizemb>
	//         <category>TV</category>
	//         <group>alt.bin.example1</group>
	//         <group>alt.bin.example2</group>
	//         <status>Completed<status>
	//         <comments count="0">http://v3.newzbin.com/browse/post/12345/comments/</comments>
	//     </item>
	//     <item>
	//         ...
	//     </item>
	// </nzb>
	//
	// It also needs to accept some or all of the parameters below:
	//  * search - The string to search for, this is required.
	//  * username/password - The username/password to login with. Not required
	//                        if provider already has a built in username/password
	//  * limit - How many results to return. Not required, just return all.
	//  * sizesort - Specifies sort order of results. Preferably small to large
	//               to make viewing on GetTV.php less random-looking. Optional
	//
	// A compatable search provider HAS been written, but due to newzbin terms
	// is unavailable for release at this time.
	$config['search']['provider'] = 'http://localhost/new/nzb/';
	// Username for searching
	$config['search']['username'] = 'unknown';
	// Password for searching
	$config['search']['password'] = 'unknown';
	
	//----------------------------------------------------------------------------
	// Data Storage configuration
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
	$config['storage']['mysql']['pass'] = 'password';
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
	$config['tv']['default_source'] = 'merge';
	// What is the web-accessible path to folder for the sources?
	$config['tv']['urlbase'] = 'http://localhost/new/sources/';
	// What is the path to folder for the sources?
	$config['tv']['filebase'] = dirname(__FILE__).'/www/sources/';
	// How long should the sources cache their data?
	$config['tv']['cachetime'] = 82800; // Cache for 23 hours
	
	//----------------------------------------------------------------------------
	// Misc defines - These shouldn't be changed.
	//----------------------------------------------------------------------------
	define('CRLF', "\r\n");
	define('EOL', '<br>'.CRLF);

	// The type of times available to match in time arrays.
	// 'timename' => 'format for date()'
	$config['times'] = array('second' => 's',
	                         'minute' => 'i',
	                         'hour' => 'H',
	                         'day' => 'd',
	                         'month' => 'm',
	                         'year' => 'Y'
	                        );
	
	
	//----------------------------------------------------------------------------
	// Look for config.user.php and load it if found to overwrite the defaults
	// here.
	// config.user.php will be ignored by git
	//----------------------------------------------------------------------------
	if (file_exists(dirname(__FILE__).'/config.user.php')) {
		include_once(dirname(__FILE__).'/config.user.php');
	}
?>
