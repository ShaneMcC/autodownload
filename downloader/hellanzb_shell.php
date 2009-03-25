<?php
	//----------------------------------------------------------------------------
	// Downloader: hellanzb_shell
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// This downloader works by shelling out to the hellanzb binary and getting it
	// to do the RPCXML calls required.
	//----------------------------------------------------------------------------
	
	/**
	 * Download the given NZB.
	 *
	 * @param $nzbid
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadNZB($nzbid) {
		global $config;
		
		$log = '/tmp/hella_'.time().'.log';
		$cmd = $config['hellanzb']['python'].' '.$config['hellanzb']['location'].' -c '.$config['hellanzb']['config'].' -l '.$log.' enqueuenewzbin '.$nzbid;
		
		exec($cmd, $output, $return);
		if (file_exists($log)) { unlink($log); }
		$result = array();
		$result['output'] = $output;
		$result['status'] = ($return == 0);
		return $result;
	}
?>