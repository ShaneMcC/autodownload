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
		$cmd = $config['downloader']['hellanzb_shell']['python'].' '.$config['downloader']['hellanzb_shell']['location'].' -c '.$config['downloader']['hellanzb_shell']['config'].' -l '.$log.' enqueuenewzbin '.$nzbid;
		
		exec($cmd, $output, $return);
		if (file_exists($log)) { unlink($log); }
		$result = array();
		$result['output'] = $output;
		$result['status'] = ($return == 0);
		// Hellanzb returns 0 even if the connection failed.
		// stupid poc.
		if ($result['status']) {
			if (stristr(implode(" ", $output), "Unable to connect to XMLRPC server") !== FALSE) {
				$result['status'] = false;
			}
		}
		return $result;
	}
?>