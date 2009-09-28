<?php
	//----------------------------------------------------------------------------
	// Source: TVRage.com
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// The TVRage source provides alot more shows, but at the cost of only being
	// able to show this week, this makes it show shows from japan.
	//----------------------------------------------------------------------------
	$country = 'JP';
	$special = '?country='.$country;
	$cache_special='.'.$country;
	$sourcename = str_replace('.php', '', basename(__FILE__));
	include_once(dirname(__FILE__).'/tvrage.php');
?>