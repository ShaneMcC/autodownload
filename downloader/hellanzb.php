<?php
	//----------------------------------------------------------------------------
	// Downloader: hellanzb
	// Author: Shane McCormack
	//----------------------------------------------------------------------------
	// This downloader works by doing the RPCXML calls required to talk to
	// hellanzb itself.
	//----------------------------------------------------------------------------

	function getFromFile($file, $var) {
		$regex = '#^\s*\Q'.$var.'\E = \'?(.*?)\'?\s*$#m';
		preg_match_all($regex, $file, $matches);
		return $matches[1][count($matches[1]) - 1];
	}

	function getPage($server, $port, $username, $password, $page, $contents) {
		$data = '';
		$data .= '<?xml version="1.0"?>';
		$data .= '<methodCall>';
		$data .= '<methodName>'.$contents['method'].'</methodName>';
		if (isset($contents['params'])) {
			$data .= '<params>';
			foreach ($contents['params'] as $key => $param) {
				$data .= '<param>';
				$data .= '<value><'.$param[0].'>'.$param[1].'</'.$param[0].'></value>';
				$data .= '</param>';
			}
			$data .= '</params>';
		}
		$data .= '</methodCall>';

		$headers[] = 'POST /'.$page.' HTTP/1.0';
		$headers[] = 'Host: http://'.$server.':'.$port.'/';
		$headers[] = 'Authorization: Basic '.base64_encode($username.':'.$password);
		$headers[] = 'Content-Type: text/xml';
		$headers[] = 'Content-length: '.strlen($data);
		
		$fp = fsockopen($server, $port, $errno, $errstr, 5);
		if ($fp) {
			fputs($fp, implode("\r\n", $headers));
			fputs($fp, "\r\n\r\n");
			fputs($fp, $data);
			$result = '';
			while(!feof($fp)) {
				$line = fgets($fp, 128);
				$result .= $line;
			}
			fclose($fp);
			return preg_replace("/^.*\r\n\r\n/s", '', $result);
		}
	
		return "<?xml version=\"1.0\"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>-1</int></value></member><member><name>faultString</name><value><string>No response from server</string></value></member></struct></value></fault></methodResponse>";
	}


	/**
	 * Download the given NZB.
	 *
	 * @param $nzbid
	 * @param $name Optional name to pass
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadNZB($nzbid, $name = '') {
		global $config;

		$file = file_get_contents($config['downloader']['hellanzb']['config']);
		
		$server = getFromFile($file, 'Hellanzb.XMLRPC_SERVER');
		$port = getFromFile($file, 'Hellanzb.XMLRPC_PORT');
		$password = getFromFile($file, 'Hellanzb.XMLRPC_PASSWORD');
		
		$enqueue = array('method' => 'enqueuenewzbin', 'params' => array(array('int', $nzbid)));
		$output = getPage($server, $port, 'hellanzb', $password, 'status', $enqueue);
		$result = simplexml_load_string($output);
		
		// print_r($output);
		// print_r($result);
		if ($result->fault) {
			return array('output' => $output, 'status' => false);
		} else {
			return array('output' => $output, 'status' => true);
		}
	}


	/**
	 * Download the given NZB local file.
	 *
	 * @param $file
	 * @param $name
	 * @return Array containing the output from the downloader, and the status code.
	 */
	function downloadFromFile($file, $name = '') {
		$result['output'] = array();
		$result['status'] = false;
		return $result;
	}
?>