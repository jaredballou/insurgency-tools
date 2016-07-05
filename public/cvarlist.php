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
		$('#cvarlist').dataTable({
			saveState: true,
			columnDefs: [
				{
					targets: [ 2,3,4,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20 ],
					visible: false,
					searchable: false
				}
			],
		});
		$('#cvarlist').floatThead();
} );";

if (isset($_REQUEST['fetch'])) {
	require_once("{$includepath}/functions.php");
} else {
	require_once("{$includepath}/header.php");
}

// Get versions that have CVAR lists in data
$dirs = glob("${datapath}/cvarlist/*");
foreach ($dirs as $dir) {
	if (!is_dir($dir)) {
		continue;
	}
	$ver = basename($dir);
	$files = glob("{$dir}/*.csv");
	foreach ($files as $file) {
		$fn = basename($file,".csv");
		$lists[$ver][$fn] = $fn;
	}
}
//asort($lists);

// Select version
$version = end(array_keys($lists));
if ($_REQUEST['version']) {
	if (in_array($_REQUEST['version'],array_keys($lists))) {
		$version = $_REQUEST['version'];
	}
}

// Select list type
$listtype = end($lists[$version]);
if ($_REQUEST['listtype']) {
	if (in_array($_REQUEST['listtype'],array_keys($lists[$version]))) {
		$listtype = $_REQUEST['listtype'];
	}
}
//var_dump($lists,$version,$listtype);

// If we don't have sane values, abort
if ((!$version) || (!$listtype)) {
	echo "Data not found";
	include "{$includepath}/footer.php";
	exit;
}

$data = GetCVARList($version,$listtype);

if ($_REQUEST['fetch'] == 'list') {
	header('Content-Type: application/json');
	echo prettyPrint(json_encode($data));
	exit;
}

//Start display
startbody();
DisplayCVARList($data);
require_once("{$includepath}/footer.php");
exit;

function DisplayCVARList($data) {
	global $version,$listtype,$lists;
	//Collect Headers
	$header="";
	foreach (array_keys(current($data)) as $field) {
		$header.="<th>{$field}</th>";
	}
	echo "<h2>Version {$version} - {$listtype}</h2>\n";
	echo "<form><select name='version'>";
	foreach (array_keys($lists) as $ver) {
		$sel = ($ver == $version) ? ' SELECTED' : '';
		echo "				<option{$sel}>{$ver}</option>\n";
	}
	echo "</select><select name='listtype'>";
	foreach ($lists[$version] as $list) {
		$sel = ($list == $listtype) ? ' SELECTED' : '';
		echo "				<option{$sel}>{$list}</option>\n";
	}
	echo "</select><input type='submit' name='command' value='Load'><br><input type='submit' name='command' value='Dump Config'></form>\n";
	echo "CVAR lists are created from the game, by running <b>cvarlist log {$listtype}.csv</b> in console<br>\n";
	if ($_REQUEST['command'] == 'Dump Config') {
		echo "<textarea cols='80' rows='40'>\n";
		echo CreateConfigFromCVARList($data);
		echo "</textarea>\n";
	} else {
		echo "<table class='display' id='cvarlist'>\n";
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



function GetCVARList($version,$listtype) {
	global $datapath;

	// Load settings if available
	$settings = array();
	$settingfile = "${datapath}/cvarlist/{$version}/{$listtype}.txt";
	$cachefile = "cvarlist/{$version}/{$listtype}.json";
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
	$listfile = "${datapath}/cvarlist/{$version}/{$listtype}.csv";
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
				//$row['Value'].=" ({$settings[$row['Name']]})";
				//var_dump($row['Name'].": ".$row['Value']);
			}
		}
		$data[] = $row;
	}
	fclose($f);
	PutCacheFile($cachefile,$data);
	return $data;
}

function CreateConfigFromCVARList($data) {
	$str='';
	foreach ($data as $row) {
		$prefix = '';
		if ($row['Value'] == 'cmd')
			continue;
		if ($row['Help Text'] || $row['CHEAT']) {
			$help = " //{$row['Help Text']}";
			if ($row['CHEAT']) {
				$prefix = 'sm_cvar ';
				$help.=" CHEAT";
			}
		}
		$str.="{$prefix}{$row['Name']} \"{$row['Value']}\"{$help}\n";
	}
	return $str;
}

?>
