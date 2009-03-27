<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');

	head("Dataforce's Automatic TV Downloader");
	
	echo '<a href="TV.php">List TV Found today</a>', EOL;
	echo '<a href="TV.php?week">List TV Found this week</a>', EOL;
	echo '<a href="TV.php?month">List TV Found this month</a>', EOL;
	echo EOL;
	echo '<a href="series.php">Download an entire series</a>', EOL;
	// echo '<a href="AutoTV.php">Start the Automatic TV Downloading Process</a>', EOL;
	// echo '<a href="Reindex.php">Move downloaded episodes out of download location</a>', EOL;
	
	foot();
?>