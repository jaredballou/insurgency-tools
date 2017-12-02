<?php
/*
This script creates an HLStatsX-compatable MySQL dump. It uses the game files
in data to get the information and then dumps it in an idempotent query to
create or update the items in HLStatsX.
*/
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
$use_hlstatsx_db = 1;
require_once("{$includepath}/header.php");

$games = array(
	"doi" => "Day of Infamy",
	"insurgency" => "Insurgency"
);

$values = array();

if (in_array($mysql_safe['game'], array_keys($games))) {
	$game=$mysql_safe['game'];
} else {
	$game = "insurgency";
}
if (isset($mysql_safe['gamecode']) && $mysql_safe['gamecode'] != "") {
	$gamecode = $mysql_safe['gamecode'];
} else {
	$gamecode = $game;
}
if (isset($mysql_safe['dbprefix']) && $mysql_safe['dbprefix'] != "") {
	$dbprefix=$mysql_safe['dbprefix'];
} else {
	$dbprefix="hlstats";
}

// Begin HTML
startbody();
// Begin form
echo "<form>Game:<select name='game'>";
foreach ($games as $code => $name) {
	if ($code == $game) {
		$sel = " SELECTED";
	} else {
		$sel = "";
	}
	echo "<option value='{$code}'{$sel}>{$name}</option>";
}
echo "</select> DB Prefix:<input type='text' name='dbprefix' value='{$dbprefix}'> Custom DB Game code:<input type='text' name='gamecode' value='{$mysql_safe['gamecode']}'><input type='submit'></form>\n";
// end form
// Begin SQL dump
echo "<textarea style='width: 100%; height: calc(100% - 50px); box-sizing: border-box;'>";
echo "--\n-- Add {$games[$game]} to games\n--\n\n";
echo "INSERT IGNORE INTO `{$dbprefix}_Games` (`code`, `name`, `hidden`, `realgame`) VALUES ('{$gamecode}', '{$games[$game]}', '0', '{$gamecode}');\n";
echo "INSERT IGNORE INTO `{$dbprefix}_Games_Supported` (`code`, `name`) VALUES ('{$gamecode}', '{$games[$game]}');\n";
foreach ($tables as $table => $tdata) {
	$mf = current(array_values($tdata['allfields']));
//var_dump($mf);
	$query = "select * from {$dbprefix}_{$table} where {$mf}='{$gamecode}' ORDER BY {$tdata['fields'][0]}";
//	echo($query);
	$result = $mysqli->query($query);
	while ($row = $result->fetch_assoc()) {
		$val = array();
		$row[$mf] = $gamecode;
		foreach ($tdata['allfields'] as $field) {
			$val[] = "'{$row[$field]}'";
		}
		$val = '('.implode(', ',$val).')';
		$values[$table][$val] = $val;
	}
}
foreach ($tables as $table => $tdata) {
	if (count($values[$table])) {
		echo "--\n-- Update {$dbprefix}_{$table}\n--\n\n";
		$fields = array();
		foreach ($tdata['fields'] as $field) {
			$fields[] = "{$field} = VALUES({$field})";
		}
		asort($values[$table]);

		echo "INSERT INTO `{$dbprefix}_{$table}`\n  (`".implode('`, `',$tdata['allfields'])."`)\n  VALUES\n    ".implode(",\n    ",$values[$table])."\n  ON DUPLICATE KEY UPDATE\n    ".implode(",\n    ",$fields).";\n";
	}
}
//var_dump($tables);
//var_dump($values);
echo "</textarea>\n";
require_once("{$includepath}/footer.php");

exit;
