<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');
	if (isset($_REQUEST['is']) && is_array($_REQUEST[$_REQUEST['is']])) {
		$_REQUEST['info'] = implode($_REQUEST[$_REQUEST['is']], '');
	}
	$show = unserialize($_REQUEST['info']);

	head(sprintf('TV Downloader - %s %dx%02d', $show['name'], $show['season'], $show['episode']), '', getCSS(true, false));
	
	$info = (is_array($show['info'])) ? $show['info'] : getShowInfo($show['name']);
	
	$searchString = getSearchString($show, $info);
	
	echo 'Searching for: <strong>', $searchString,'</strong>', EOL;
	echo 'Optimal Size: <strong>', $info['size'],'mb</strong>', EOL;
	echo EOL;
	
	echo '<!--';
	ob_start();
	$search = searchFor($searchString, true);
	$buffer = ob_get_contents();
	ob_end_clean();
	echo '-->';
	if (isset($search->search->actual_search)) {
		echo 'Actually searched for: <strong>', $search->search->actual_search,'</strong>', EOL;
	}
	
	if (preg_match('@function.file-get-contents</a>]: (.*)  in@U', $buffer, $matches)) {
		echo 'An error occured loading the search provider: ', $matches[1], EOL;
	} else if ($search === false) {
		echo 'An error occured getting the search results: The search provider could not be loaded', EOL;
	} else  if ($search->error['message'] && $search->error['message'] != '') {
		echo 'An error occured getting the search results: ', (string)$search->error['message'], EOL;
	} else {
		$optimal = GetBestOptimal($search->xpath('item'), $info['size'], false, true, $show);
		
		/*echo '<pre>';
		print_r($items);
		print_r($search);
		echo '</pre>';*/
		
		$i = 0;
		foreach ($search->item as $item) {
			$best = ($optimal == $i);
			$i++;
			
			$groups = array();
			foreach ($item->group as $group) { $groups[] = (string)$group; }
			
			$id = (int)$item->nzbid;
			$name = (string)$item->name;
			$size = (string)$item->sizemb;
			$size .= ($best) ? ' [<strong>Optimal</strong>]' : '';
			$category = (string)$item->category;
			$groups = implode($groups, ', ');
			$status = (string)$item->status;
			if (isset($item->raw)) { $raw = true; $status .= ' (RAW)'; } else { $raw = false; }
			$comments = (int)$item->comments['count'];
			if ($comments > 0) { $comments .= ' (<a href="'.(string)$item->comments.'">View</a>)'; }
			
			$class = 'downloadList' . ($best ? ' best' : '') . (!isGoodMatch((string)$item->name, $show) ? ' bad' : '');
			
			echo '<table class="'.$class.'" id="nzb_', $id, '">'.CRLF;
			echo '  <tr>'.CRLF;
			echo '    <th>Name</th>'.CRLF;
			echo '    <td colspan=3>', $name,'</td>'.CRLF;
			echo '    <th>Size</th>'.CRLF;
			echo '    <td>', $size,'</td>'.CRLF;
			echo '  </tr>'.CRLF;
			
			echo '  <tr>'.CRLF;
			if ($raw) {
				echo '    <th>Raw Name</th>'.CRLF;
				echo '    <td colspan=5>', (string)$item->rawname,'</td>'.CRLF;
			} else {
				echo '    <th>Groups</th>'.CRLF;
				echo '    <td colspan=5>', $groups,'</td>'.CRLF;
			}
			echo '  </tr>'.CRLF;
			
			echo '  <tr>'.CRLF;
			echo '    <th>Status</th>'.CRLF;
			echo '    <td>', $status,'</td>'.CRLF;
			echo '    <th>Comments</th>'.CRLF;
			echo '    <td>', $comments,'</td>'.CRLF;
			echo '    <th>Category</th>'.CRLF;
			echo '    <td>', $category,'</td>'.CRLF;
			echo '  </tr>'.CRLF;
			
//			echo '  <tr>'.CRLF;
//			echo '    <td colspan=6 style="height: 4px; border: 0;"></td>'.CRLF;
//			echo '  </tr>'.CRLF;

			$nodownload = false;
			if ($raw) {
				$id = '';
				$nodownload = true;
				if (isset($item->files->file)) {
					foreach ($item->files->file as $file) {
						if (!empty($id)) { $id .= '&rawid[]='; }
						$id .= $file;
					}

					$nodownload = false;
				}
			}
			
			echo '  <tr>'.CRLF;
			echo '    <th>Actions</th>'.CRLF;
			echo '    <td colspan=5>';
			if (!$nodownload) {
				echo '[<a href="GetPost.php?'.($raw ? 'rawid[]' : 'nzbid').'=', $id, '&show=', urlencode(serialize($show)),'">Download</a>]';
			}
			echo '</td>'.CRLF;
			echo '  </tr>'.CRLF;
			echo '</table>'.CRLF;
			
			
			echo '<br>'.CRLF;
		}
		
		if ($i == 0) {
			echo '<strong>No search results were found.</strong>', EOL;
		}
	}
	
	foot();
?>
