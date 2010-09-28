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
	// If using LSOF, this should be higher due to the extra time taken to
	// run lsof for every event that causes an access.
	$config['daemon']['looptime'] = 10;
	// Daemon log file/
	$config['daemon']['logfile'] = dirname(__FILE__).'/daemon.log';

	// Automatically add new shows discovered to the database?
	$config['daemon']['autotv']['autodb'] = true;
	
	// Add new shows as automatic?
	// 0 == never, 1 == always, 2 == only if 1x01
	$config['daemon']['autotv']['autoautomatic'] = 2;
	
	// Announce only automatic shows that are added?
	$config['daemon']['autotv']['onlyannounceautomatic'] = true;
	
	// URL to manage.php
	$config['daemon']['autotv']['manageurl'] = 'http://localhost/new/manage.php';
	
	// Link to manage.php when starting a new download.
	$config['daemon']['autotv']['showmanage'] = true;

	// What search providers to use for searching?
	// '' for default, this can be a single provider, or an array.
	$config['daemon']['autotv']['providers'] = '';
	
	// Should all 1x01's be downloaded ?
	// This is ignored if only-important is true.
	$config['daemon']['autotv']['allfirst'] = true;

	// Which extra days should we try to download a show?
	// example: array('1', '6'); would try find the show on the day it is released
	// 1 day after, and 6 days afterwards.
	// Only integer values inside an array will be counted.
	// This can also allow for checking for things in advance also.
	$config['daemon']['autotv']['tryagain'] = array('1');
	
	// Times that the daemon should check for new TV.
	// This is an array containing "time arrays".
	// "time arrays" are arrays with keys corresponding to the values of
	// $config['times'] (see "Misc Defines" section).
	// The values for each of the timenames should be a string of numbers
	// separated by any non-numeric value.
	// Example: array(array('hour' => '03,04,05,06,09', 'minute' => '00,30'));
	// This is checked every minute
	$config['daemon']['autotv']['times'] = array(array('hour' => '03,04,05,06,09', 'minute' => '00,30'),
	                                             array('hour' => '02,23,20', 'minute' => '30'),
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
	// Base directory to reindex from.
	// This can also be an array of directories.
	$config['daemon']['reindex']['downloaddir'] = '/media/data/nzb/Automatic';
	
	// When reindexing, ignore episodes that end up as 0x00.
	// 0x00 Usually means that the parsing of the title went wrong.
	$config['daemon']['reindex']['ignore0x00'] = true;
	
	// Patterns used to match directory names.
	// Array containing key/value pairs. Keys are the patterns to match, values
	// are an array which shows where each key-part of the match is.
	$config['daemon']['reindex']['dirpatterns'] = array('/^(?:\s?UNPACK\s)?(.*)[_ ]-[_ ]([0-9S]+)[Xx]([0-9\-]+)[_ ]-[_ ]([^_]+)(?:_.*)?$/U' => array('name' => 1, 'season' => 2, 'episode' => 3, 'title' => 4),
	                                                    '/^(?:\s?UNPACK\s)?(.*)[_ ]-[_ ]([0-9S]+)[Xx]([0-9\-]+)[^0-9\-]*.*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                    '/^(?:\s?UNPACK\s)?(.*)[_ ]S([0-9]+)E([0-9\-]+)[^0-9\-]+.*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                    '/^(?:\s?UNPACK\s)?(.*)[_ ]-[_ ]([0-9]+)(?:[^0-9]+.*)?$/U' => array('name' => 1, 'episode' => 2, 'force_season' => '1'),
	                                                    '/^selected_files_[0-9]+-[0-9]+$/U' => array('usefilepattern' => true),
	                                                    '/^force_reindex.*$/U' => array('usefilepattern' => true),
	                                                   );
	// Patterns used to match file names when using inotify
	$config['daemon']['reindex']['filepatterns'] = array('/^(?:\s?UNPACK\s)?(.*)\.[Ss]([0-9]+)[Ee]([0-9][0-9]).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                     '/^(?:\s?UNPACK\s)?(.*)\.([0-9]+)[Xx]?([0-9][0-9]).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                     '/^(?:\s?UNPACK\s)?(.*) ([0-9]+)[Xx]?([0-9][0-9]).*$/U' => array('name' => 1, 'season' => 2, 'episode' => 3),
	                                                    );
	
	// Should filepatterns above be used when comparing files inside a directory
	// during reindexing? (If true and a pattern matches, then the info from the
	// file is used instead of the info from the folder).
	// This is primarily useful for all-in-one seasons downloads.
	// This can be explicitly set/unset for a given dir pattern by adding a
	// 'usefilepattern' => true/false key to the array.
	$config['daemon']['reindex']['usefilepatterns'] = false;
	
	// File extentions that we care about when reindexing.
	$config['daemon']['reindex']['extentions'] = array('avi', 'mkv', 'mpg', 'mpeg', 'flv', 'ts');
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
	// If using LSOF, this needs to be lower due to the extra time taken to
	// run lsof every access.
	$config['daemon']['reindex']['inotify_count'] = 50; // 100
	
	// Should the 'Unwatched' folder actually only contain symlinks rather than
	// actual files? (Actual files will be put into the 'Watched' folder, inotify
	// will delete symlinks when watched according to settings.)
	//
	// This is useful if you want to keep it obvious which files are unwatched,
	// but want to allow other machines to access everything without marking them
	// as watched.
	$config['daemon']['reindex']['symlinkwatched'] = true;
	
	// Delete symlinks when watched
	$config['daemon']['reindex']['deletesymlink'] = true;
	
	// should 'fuser' be used to decide if a watch should be counted?
	//
	// If 'fuser' output is empty then the watch will NOT be counted. (thus
	// accesses by other users won't count, unless daemon is running as root)
	//
	// Otherwise access will be counted unless "user-process" is in the blacklist.
	// (user or process can be *)
	$config['daemon']['reindex']['fuser'] = true;
	
	// Fuser blacklist.
	$config['daemon']['reindex']['fuser_blacklist'] = array('*-ccxstream');
	
	//----------------------------------------------------------------------------
	// Automatic Downloading settings.
	//----------------------------------------------------------------------------
	// Commands to execute at certain times.
	// $config['commands']['<type>'] = array('command1', 'command2');
	// valid types are: post_reindex
	//                  pre_reindex
	//                  pre_checkauto
	//                  post_checkauto
	//
	// Type must be lowercase.
	
	//----------------------------------------------------------------------------
	// Automatic Downloading settings.
	//----------------------------------------------------------------------------
	// Source to use for automatic downloads. ('' for default);
	$config['autodownload']['source'] = '';
	
	//----------------------------------------------------------------------------
	// Series Downloading settings.
	//----------------------------------------------------------------------------
	// Sleep after how many searches/downloads?
	$config['seriesdownload']['sleepcount'] = 5;
	// Sleep for how long?
	$config['seriesdownload']['sleeptime'] = 60;

	//----------------------------------------------------------------------------
	// How should we search newzbin?
	//----------------------------------------------------------------------------
	// Search provider
	// This provider should give search results in XML like so:
	// <nzb>
	//     <item>
	//         <nzbid>12345</nzbid>             <!-- ID for this item. -->
	//         <name>Example Entry</name>       <!-- Name for this item. -->
	//         <sizemb>400</sizemb>             <!-- Size of this item in MB. -->
	//         <category>TV</category>          <!-- Optional Category. -->
	//         <group>alt.bin.example1</group>  <!-- Optional Groups. -->
	//         <group>alt.bin.example2</group>
	//         <raw />                          <!-- Does this actually represent a raw view from newzbin? -->
	//         <files>                          <!-- List of file IDs that are part of this item -->
	//             <file>123456780</file>       <!-- If these are provided, then we will download -->
	//             <file>123456781</file>       <!-- an NZB made up of these files, rather than -->
	//             <file>123456782</file>       <!-- using the nzbid field. -->
	//             ...
	//             <file>123456788</file>
	//             <file>123456789</file>
	//         </files>
	//         <status>Completed<status>        <!-- Optional status information. -->
	//         <comments count="0">http://v3.newzbin.com/browse/post/12345/comments/</comments>      <!-- Optional comment information. -->
	//     </item>
	//     <item>
	//         ...
	//     </item>
	// </nzb>
	//
	// If files are included, they will be given priority over the nzbid.
	// Name and Size will be used to see if this is a good match and are required.
	// nzbid is required, even if it is not actually what will be downloaded.
	// <raw /> is not required, but will allow GetTV.php to make it obvious that
	// the search is from RAW view.
	//
	// It also needs to accept some or all of the parameters below:
	//  * search - The string to search for, this is required.
	//  * username/password - The username/password to login with. Not required
	//                        if provider already has a built in username/password
	//  * limit - How many results to return. Not required, just return all.
	//  * sizesort - Specifies sort order of results. Preferably small to large
	//               to make viewing on GetTV.php less random-looking. Optional
	$config['search']['provider'] = 'http://localhost/new/nzb/nzbmatrix_search.php';
	// Username for searching (if needed)
	$config['search']['username'] = 'unknown';
	// Password for searching (if needed)
	$config['search']['password'] = 'unknown';

	// Username for downloading from nzbmatrix;
	$config['search']['nzbmatrix_username'] = 'unknown';
	// API Key for downloading from nzbmatrix;
	$config['search']['nzbmatrix_apikey'] = 'unknown';

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
	// What should be considered the default sources if none are specified?
	$config['default']['sources'] = '!tvrage_britain';
	// Default dirname
	$config['default']['dirname'] = 'Watched';
	
	//----------------------------------------------------------------------------
	// Download Modifiers
	//----------------------------------------------------------------------------
	// Only download shows marked as "important" ?
	$config['download']['onlyimportant'] = false;
	// Try to download shows in high def? (This doubles the optimal download size)
	$config['download']['highdef'] = false;
	
	// How many times larger than the optimal is considered "too high"?
	// (If the value is higher than this, then a potential smaller result will be
	// looked for instead).
	$config['download']['optimal']['toohigh'] = 2;
	// How many times smaller is too low?
	$config['download']['optimal']['toolow'] = 0.8;
	
	//----------------------------------------------------------------------------
	// Hellanzb details
	//----------------------------------------------------------------------------
	$config['downloader']['type'] = 'sabnzbd';
	
	// Configuration Settings for hellanzb
	$config['downloader']['hellanzb']['config'] = '/usr/etc/hellanzb.conf';
	
	// Configuration Settings for hellanzb_shell
	// Location of python
	$config['downloader']['hellanzb_shell']['python'] = '/usr/bin/python';
	// Location of hellanzb
	$config['downloader']['hellanzb_shell']['location'] = '/usr/bin/hellanzb.py';
	// Location of hellanzb config (libnotify must be disabled)
	$config['downloader']['hellanzb_shell']['config'] = '/usr/etc/hellanzb.conf.web';
	
	// Configuration Settings for sabnzbd
	// Username for api access
	$config['downloader']['sabnzbd']['username'] = '';
	// Password for api access
	$config['downloader']['sabnzbd']['password'] = '';
	// Category to add downloads to
	$config['downloader']['sabnzbd']['category'] = 'Automatic';
	// Host of sabnzbd
	$config['downloader']['sabnzbd']['server'] = '127.0.0.1';
	// Port of sabnzbd
	$config['downloader']['sabnzbd']['port'] = '8080';
	// apikey of sabnzbd
	$config['downloader']['sabnzbd']['apikey'] = '';
	
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
	$config['tv']['default_source'] = 'database';
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
