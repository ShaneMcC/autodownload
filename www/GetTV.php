<?php
	include_once(dirname(__FILE__).'/../config.php');
	include_once(dirname(__FILE__).'/../functions.php');
	
	$show = unserialize($_REQUEST['info']);
	
	$css = '
		table.downloadList {
			border-collapse: collapse;
			
			width: 699px;
		}
		
		table.downloadList td, table.downloadList th {
			border: 1px solid black;
			padding: 1px 5px;
		}
		
		table.downloadList th {
			background-color: #AAA;
			text-align: right;
			width: 75px;
		}
		
		table.downloadList td {
			text-align: left;
			width: 158px;
		}
		
		table.best td {
			background-color: #DAFFDA;
		}
	';
	
	head(sprintf('TV Downloader - %s %dx%02d', $show['name'], $show['season'], $show['episode']), '', $css);
	
	$info = (is_array($show['info'])) ? $show['info'] : getShowInfo($show['name']);
	
	$bit_search = array("{series}", "{season}", "{episode}", "{title}", "{time}");
	$bit_replace = array($show['name'], $show['season'], $show['episode'], $show['title'], $show['time']);
	
	$searchString = $info['searchstring'];
	$searchString = str_replace($bit_search, $bit_replace, $searchString);
	
	$searchString .= ' ';
	$searchString .= $info['attributes'];
	
	echo 'Searching for: <strong>', $searchString,'</strong>', EOL;
	echo 'Optimal Size: <strong>', $info['size'],'mb</strong>', EOL;
	echo EOL;
	
	echo '<!--';
	ob_start();
	$search = searchFor($searchString, true);
	$buffer = ob_get_contents();
	ob_end_clean();
	echo '-->';
	
	if (preg_match('@function.file-get-contents</a>]: (.*)  in@U', $buffer, $matches)) {
		echo 'An error occured loading the search provider: ', $matches[1], EOL;
	} else if ($search === false) {
		echo 'An error occured getting the search results: The search provider could not be loaded', EOL;
	} else  if ($search->error['message'] && $search->error['message'] != '') {
		echo 'An error occured getting the search results: ', (string)$search->error['message'], EOL;
	} else {
		$items = $search->item;
		$optimal = GetBestOptimal($items, $show['size'], false, true);
		
		echo '<pre>';
		print_r($items);
		print_r($search);
		echo '</pre>';
		
		$i = 0;
		foreach ($items as $item) {
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
			$comments = (int)$item->comments['count'];
			if ($comments > 0) { $comments .= ' (<a href="'.(string)$item->comments.'">View</a>)'; }
			
			$class = 'downloadList' . ($best ? ' best' : '');
			
			echo '<table class="'.$class.'" id="nzb_', $id, '">'.CRLF;
			echo '  <tr>'.CRLF;
			echo '    <th>Name</th>'.CRLF;
			echo '    <td colspan=3>', $name,'</td>'.CRLF;
			echo '    <th>Size</th>'.CRLF;
			echo '    <td>', $size,'</td>'.CRLF;
			echo '  </tr>'.CRLF;
			
			echo '  <tr>'.CRLF;
			echo '    <th>Groups</th>'.CRLF;
			echo '    <td colspan=5>', $groups,'</td>'.CRLF;
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
			
			echo '  <tr>'.CRLF;
			echo '    <th>Actions</th>'.CRLF;
			echo '    <td colspan=5>[<a href="GetPost.php?nzbid=', $id, '&show=', urlencode(serialize($show)),'">Download</a>]</td>'.CRLF;
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