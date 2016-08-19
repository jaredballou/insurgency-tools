<?php
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
/*
This takes the CVAR list CSV files from data and displays them in a simple
tabular format.
*/
//Root Path Discovery
//$use_ob=1;

$title = "CVAR List";
$css_content = '
	table.floatThead-table {
		background-color: #FFFFFF;
	}
';
$js_content = "
$(document).ready(function() {
		$('#listing').dataTable({
			saveState: true,
			columnDefs: [
				{
					targets: [ 2,3,4,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20 ],
					visible: false,
					searchable: false
				}
			],
		});
		$('#listing').floatThead();
} );";

if (isset($_REQUEST['fetch'])) {
	require_once("{$includepath}/functions.php");
} else {
	require_once("{$includepath}/header.php");
}

$lists = GetModFiles('cvarlist');
//var_dump($lists);
// Default mod
$mod="insurgency";

// If mod in request is valid, use it
if (isset($_REQUEST['mod'])) {
        if (isset($lists[$_REQUEST['mod']])) {
                $mod = $_REQUEST['mod'];
        }
}

// Select version
$version = end(array_keys($lists[$mod]));
if (isset($_REQUEST['version'])) {
	if (in_array($_REQUEST['version'],array_keys($lists[$mod]))) {
		$version = $_REQUEST['version'];
	}
}

// Select list type
$cvarlist = end($lists[$mod][$version]);
if (isset($_REQUEST['cvarlist'])) {
	if (in_array($_REQUEST['cvarlist'],array_keys($lists[$mod][$version]))) {
		$cvarlist = $_REQUEST['cvarlist'];
	}
}

$data = GetCVARList($mod,$version,$cvarlist);

if (isset($_REQUEST['fetch']) && $_REQUEST['fetch'] == 'list') {
	header('Content-Type: application/json');
	echo prettyPrint(json_encode($data));
	exit;
}

//Start display
startbody();
echo "<div style='margin: 5px;'>\n";
echo "<h1>CVAR List</h1>\n";
echo "<h2>Viewing {$cvarlist} from {$mod} version {$version}</h2>\n";
echo "<form action='{$_SERVER['PHP_SELF']}' method='get'>\n";
DisplayModSelection(0,'cvarlist');
echo "<input type='submit' value='Parse'>\n";
echo "</form>\n";
echo "</div>\n";

DisplayCVARList($data);
require_once("{$includepath}/footer.php");
exit;

function DisplayCVARList($data) {
	//Collect Headers
	$header="";
	foreach (array_keys(current($data)) as $field) {
		$header.="<th>{$field}</th>";
	}
	if ($GLOBALS['command'] == 'Dump Config') {
		echo "<textarea cols='80' rows='40'>\n";
		echo CreateConfigFromCVARList($data);
		echo "</textarea>\n";
	} else {
		echo "<table class='display' id='listing'>\n";
		echo "<thead><tr>{$header}</tr>\n</thead>\n<tbody>\n";
		foreach ($data as $row) {
			echo "<tr>";
			foreach ($row as $field => $val) {
				if ($field == 'Name')
					$anchor = "<a name='".htmlspecialchars($val)."'>";
				else
					$anchor = "";
				echo "<td>{$anchor}".htmlspecialchars($val)."</td>";
			}
			echo "</tr>\n";
		}
		echo "</tbody></table>\n";
	}
}



function GetCVARList($mod,$version,$cvarlist) {
	global $datapath;

	// Load settings if available
	$settings = array();
	$settingfile = "${datapath}/mods/{$mod}/{$version}/cvarlist/{$cvarlist}.txt";
	$cachefile = "cvarlist/{$mod}/{$version}/{$cvarlist}.json";
	$data = GetCacheFile($cachefile);
	if ($data) {
		return $data;
	}
	if (file_exists($settingfile)) {
		$lines = file($settingfile);
		foreach ($lines as $line) {
			$bits = explode(" ",trim($line),2);
			$settings[$bits[0]] = $bits[1];
		}
	}

	// Load CVAR list
	$listfile = "${datapath}/mods/{$mod}/{$version}/cvarlist/{$cvarlist}.csv";
	$f = fopen($listfile, "r");
	// Load the file into fields
	while (($line = fgetcsv($f)) !== false) {
		// First line is field names
		if (!isset($fields)) {
			$fields = $line;
			continue;
		}
		//$row = array_filter(array_combine($fields,array_map('trim',$line)));
		$row = array_combine($fields,array_map('trim',$line));
		if (isset($settings[$row['Name']])) {
			if ($row['Value'] != $settings[$row['Name']]) {
				$row['Default'] = $row['Value'];
				$row['Value'] = $settings[$row['Name']];
			}
		}
		$data[] = $row;
	}
	fclose($f);
	PutCacheFile($cachefile,$data);
	return $data;
}

function CreateConfigFromCVARList($data) {
	$lines = array();
	foreach ($data as $row) {
		if ($row['Value'] == 'cmd')
			continue;
		$line="{$row['Name']} \"{$row['Value']}\"";
		if ($row['Help Text'] || $row['CHEAT']) {
			$line.= " //{$row['Help Text']}";
			if ($row['CHEAT']) {
				$line = "sm_cvar {$line} CHEAT";
			}
		}
		$lines[] = $line;
	}
	return implode("\n",$lines);
}

?>
