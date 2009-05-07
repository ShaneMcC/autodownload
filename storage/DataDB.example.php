<?php
	//----------------------------------------------------------------------------
	// Automatic Downloads
	//----------------------------------------------------------------------------
	// $Auto['<Show Name>'] = 'dirname';
	
	//----------------------------------------------------------------------------
	// Show Aliases
	//----------------------------------------------------------------------------
	// $Alias['Show Alias'] = '<Show Name>';
	
	//----------------------------------------------------------------------------
	// "Important" Downloads
	//----------------------------------------------------------------------------
	// $important['showname'] = '';
	
	//----------------------------------------------------------------------------
	// Attributes for special shows
	//----------------------------------------------------------------------------
	// $Attributes['Show Name'] = '';
	
	//----------------------------------------------------------------------------
	// Sources for special shows
	//----------------------------------------------------------------------------
	// $Sources['Show Name'] = '';
	
	//----------------------------------------------------------------------------
	// Per-Show Search Strings here.
	//----------------------------------------------------------------------------
	// Variable Substitution:
	// {series} {season} {episode} {title}
	//----------------------------------------------------------------------------
	$Search['Knight Rider'] = "{series} (2008) {season}x{episode}";
	$Search['Merlin'] = "{series} (2008) {season}x{episode}";
	$Search['Greys Anatomy'] = "Grey's Anatomy {season}x{episode}";
	$Search['24'] = "{series} day {season}x{episode}";
	$Search['House'] = "{series} {season}x{episode} - {title}";
	$Search['Life'] = "{series} {season}x{episode} - {title}";
	$Search['Heroes'] = "-Hogan's {series} {season}x{episode}";

	//----------------------------------------------------------------------------
	// Optimal Sizes for downloads (~10mb per minute)
	//----------------------------------------------------------------------------
	$optimalsize['Scrubs'] = '200';
	$optimalsize['How I Met Your Mother'] = '200';
	$optimalsize['The Big Bang Theory'] = '200';
	$optimalsize['Top Gear'] = '600';
?>
