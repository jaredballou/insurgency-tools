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
$values = array();
echo "<form>DB Prefix:<input type='text' name='dbprefix' value='{$dbprefix}'> Game code:<input type='text' name='gamecode' value='{$gamecode}'><input type='submit'></form>\n";
echo "<textarea style='width: 100%; height: calc(100% - 50px); box-sizing: border-box;'>";
echo "--\n-- Add Insurgency to games\n--\n\n";
echo "INSERT IGNORE INTO `{$dbprefix}_Games` (`code`, `name`, `hidden`, `realgame`) VALUES ('{$gamecode}', 'Insurgency', '0', '{$gamecode}');\n";
echo "INSERT IGNORE INTO `{$dbprefix}_Games_Supported` (`code`, `name`) VALUES ('{$gamecode}', 'Insurgency');\n";
foreach ($tables as $table => $tdata) {
	$mf = current(array_values($tdata['allfields']));
//var_dump($mf);
	$result = mysql_query("select * from {$dbprefix}_{$table} where {$mf}='insurgency' ORDER BY {$tdata['fields'][0]}");
	while ($row = mysql_fetch_array($result)) {
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
