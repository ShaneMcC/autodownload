<?php
	//----------------------------------------------------------------------------
	// Downloader: hellanzb
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// This downloader works by doing the RPCXML calls required to talk to
	// hellanzb itself.
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
		
		
		$result['output'] = array();
		$result['status'] = true;
		return $result;
	}
?>