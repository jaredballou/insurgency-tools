<?php
/*
This tool reads the game's theater files and produces a table of information
to represent the stats of in-game items as well as possible. It is slow, prone
to breaking when the theater format and sections are changes and renamed, and
should probably be rewritten from scratch at some point.
*/
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
$title = "Insurgency Theater Parser";
$tableclasses = "table table-striped table-bordered table-condensed table-responsive";
if (isset($_REQUEST['fetch'])) {
	require_once("{$includepath}/functions.php");
} else {
	require_once("{$includepath}/header.php");
}
// Load theater now so we can create other arrays and validate
$theater = ParseTheaterFile("{$theaterfile}.theater",$mod,$version,$theaterpath);
//var_dump($theater);

if (($version != $version_compare) || ($theaterfile != $theaterfile_compare)) {
	DisplayTheaterCompare();
}

if (isset($_REQUEST['command'])) {
	switch ($_REQUEST['command']) {
		case 'tc':
			echo "<pre>\n";
			exit;
			break;
		case 'weaponlog':
			DisplayLoggerConfig();
			closePage(1);
			break;
		case 'wiki':
			DisplayWikiView();
			closePage(1);
			break;
		case 'hlstats':
		case 'hlstatsx':
			DisplayHLStatsX();
			closePage(1);
			break;
		case 'smtrans':
			DisplaySMTranslation();
			closePage(1);
			break;
	}
}

// Load weapon items
$weapons = array();
foreach($theater["weapons"] as $wpnname => $data) {
	if (isset($data["IsBase"])) {
		continue;
	}
	$object = getobject("weapons", $wpnname,1);
	ksort($object);
	$weapons[$wpnname] = $object;
}
ksort($weapons);
$weapon = current($weapons);
if (isset($_REQUEST['weapon'])) {
	if (array_key_exists($_REQUEST['weapon'], $weapons)) {
		$weapon = $_REQUEST['weapon'];
	}
}
// Load weapon_upgrade items
$weapon_upgrades = array();
$weapon_upgrade_slots = array();
foreach($theater["weapon_upgrades"] as $wpnname => $data) {
	if (isset($data["IsBase"])) {
		continue;
	}
	$object = getobject("weapon_upgrades", $wpnname,1);
	$weapon_upgrades[$wpnname] = $object;
	if ($object['upgrade_slot'])
		$weapon_upgrade_slots[$object['upgrade_slot']] = $object['upgrade_slot'];
}
ksort($weapon_upgrades);
ksort($weapon_upgrade_slots);
$weapon_upgrade = current($weapon_upgrades);
if (isset($_REQUEST['weapon_upgrade'])) {
	if (array_key_exists($_REQUEST['weapon_upgrade'], $weapon_upgrades)) {
		$weapon_upgrade = $_REQUEST['weapon_upgrade'];
	}
}
// Begin main program
// Process weapon upgrades first so we can connect them to the weapons
foreach ($theater["weapon_upgrades"] as $upname => $data) {
	if (isset($data["IsBase"])) {
		continue;
	}
//	if ((substr($upname,0,5) == "base_") || (substr($upname,-5,5) == "_base")) {
//		continue;
//	}
	$item = getobject("weapon_upgrades", $upname,1);
	if (isset($item["allowed_weapons"])) {
		$arr = (is_array(current($item["allowed_weapons"]))) ? current($item["allowed_weapons"]) : $item["allowed_weapons"];
		foreach ($arr as $wpn) {
			$upgrades[$wpn][$upname] = $item;
		}
	}
}
if (isset($_REQUEST['fetch'])) {
	switch ($_REQUEST['fetch']) {
		case 'theater':
			$data = $theater;
			break;
		case 'mods':
			$data = $mods;
			break;
	}
	if (isset($data)) {
		header('Content-Type: application/json');
		echo prettyPrint(json_encode($data));
		exit;
	}
}

?>

<script type="text/javascript" class="init">
$(document).ready(function() {
		$('table.display').dataTable({ saveState: true });
		$('table.display').floatThead();
} );
</script>
<?php
GenerateStatTable();
DisplayStatTable();
echo "		</form>";
closePage();
exit;
