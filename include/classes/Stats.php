<?php
// DisplayStatsHeader - Show the page header with all the form fields to control theater display
function DisplayStatsHeader($startbody=1) {
	global 
		$mod, $mod_compare, $mods,
		$theaterfile, $theaterfile_compare, $theaters,
		$version, $version_compare, $versions,
		$theaterpath, $theaterpath_compare,
		$range, $range_unit, $range_units;
	// Are we comparing two different items?
	$compare = (($version != $version_compare) || ($mod != $mod_compare) || ($theaterfile != $theaterfile_compare));

	startbody();
	echo "
		<form action='{$_SERVER['PHP_SELF']}' method='get'>
		<div style='margin: 5px;'>
			<h1>Insurgency Theater Parser</h1>
			<h2>Viewing {$theaterfile} from {$mod} {$version}</h2>\n";
	if ($compare) {
		echo "<h2>Comparing to {$theaterfile_compare} from {$mod_compare} {$version_compare}</h2>\n";
	}

	// Mod/version/theater
	echo "<div>\n";
	DisplayModSelection();
	DisplayTheaterConditions();
	// Comparison Mod/version/theater
	echo "<div class='beta' style='float: right;'><b>Compare to another theater:</b> \n";
	DisplayModSelection(1);
	echo "</div>\n";
	echo "</div>\n";

	// Select range and units of measure
	echo "<div>\n";
	DisplayRangeSelection();
	echo "</div>\n";

	// Submit button
	echo "				<input type='submit' value='Parse'>\n			</div>\n";

}

function DisplayRangeSelection($inputname='range') {
	global $range_units;
	$range = $GLOBALS[$inputname];
	echo "				Range: <input type='text' value='".dist($range,'IN',null,0)."' name='{$inputname}'> <select name='{$inputname}_unit'>\n";
	foreach ($range_units as $ru => $runame) {
		$sel = ($GLOBALS["{$inputname}_unit"] == $ru) ? ' SELECTED' : '';
		echo "					<option{$sel} value='{$ru}'>{$runame}</option>\n";
	}
	echo "				</select>\n";
}

function closePage($bare=0) {
	if (isset($_REQUEST['dump'])) {
		global $theater,$stats_tables,$theater_compare;
		if (isset($theater_compare)) {
			echo "theater_compare<br>\n";
			var_dump($theater_compare);
		}
		echo "theater<br>\n";
		var_dump($theater);
		var_dump($stats_tables);
	}
	require_once "{$GLOBALS['includepath']}/footer.php";
	exit;
}

function DisplayStatTable() {
//$stats_tables,$startbody=1) {
	global $tableclasses,$stats_tables;
	DisplayStatsHeader();
	echo "<div style='text-align: center; width: 100%;'><h3>\n";
	$links =array();
	foreach (array_keys($stats_tables) as $sectionname) {
		$links[] = "<a href='#{$sectionname}'>{$sectionname}</a>";
	}
	echo implode(" .:. ",$links)."</h3></div>\n";
	foreach ($stats_tables as $sectionname => $sectiondata) {
		echo "		<a id='{$sectionname}'><h2>{$sectionname}</h2></a>
		<table class='display row-border' id='table-{$sectionname}' width='100%'>
			<thead>
				<tr>\n";
/*
		if ($sectionname != 'Teams')
			echo "					<th>Cmp</th>\n";
*/
		foreach ($sectiondata['fields'] as $fieldname => $show) {
			if (!$show)
				continue;
			echo "					<th>{$fieldname}</th>\n";
		}
		echo "			</tr>\n			 </thead>\n			 <tbody>\n";
		foreach ($sectiondata['items'] as $itemname => $itemdata) {
/*
			if ($sectionname != 'Teams')
			{
				$sel = '';
				if (isset($_REQUEST["compare-{$sectionname}"]))
					$_REQUEST["compare_{$sectionname}"] = $_REQUEST["compare-{$sectionname}"];
				if (isset($_REQUEST["compare_{$sectionname}"]))
				{
					if (!in_array($itemname, $_REQUEST["compare_{$sectionname}"]))
						continue;
					else
						$sel = ' CHECKED';
				}
				echo "			<tr><td><input type='checkbox' name='compare_{$sectionname}[]' value='{$itemname}'{$sel}></td>\n";
			}
*/
			foreach ($sectiondata['fields'] as $fieldname => $show) {
				if (!$show)
					continue;
				echo "				<td valign='top'";
				$fd = @$itemdata[$fieldname];
				if ($fieldname == 'Name') {
					if (isset($itemdata['Img'])) {
						echo $itemdata['Img'];
					} else {
						echo " class='vgui'";
					}
					$fd = "<a href='stats.php?showitem={$itemname}&amp;showtype={$sectionname}' id='{$itemname}' target='_blank'>{$itemname}<br>{$fd}</a>";
				}
				echo ">{$fd}</td>\n";
			}
			echo "			</tr>\n";
		}
		echo "</tbody></table>\n";
	}
}

function DisplayTheaterCompare() {
	global
		$theater,
		$mod,
		$theaterfile,
		$theaterpath,
		$version,
		$mod_compare,
		$theaterfile_compare,
		$theaterpath_compare,
		$version_compare;
	$theater_compare = ParseTheaterFile("{$theaterfile_compare}.theater", $mod_compare, $version_compare, $theaterpath_compare);
	$index = "{$version}/{$theaterfile}";
	$index_compare = "{$version_compare}/{$theaterfile_compare}";
	$changes = multi_diff($index, $theater, $index_compare, $theater_compare);
	DisplayStatsHeader();
	echo "<table border='1' cellpadding='2' cellspacing='0'><tr><th>Setting</th><th>{$index}</th><th>{$index_compare}</th></tr>\n";
	$sections = array();
	foreach ($changes as $name => $data) {
		$sections[] = $name;
		if (isset($data[$index]) || isset($data[$index_compare])) {
			echo "<tr><td>".implode("/", $sections)."</td>";
			echo "<td>".printval($data, $index,0,'-')."</td>\n";;
			echo "<td>".printval($data, $index_compare,0,'-')."</td>\n";;
			echo "</tr>\n";
		} else {
			DisplayCompare($data, $sections, $index, $index_compare);
		}
		array_pop($sections);
	}
	closePage(1);
}

// GenerateStatTable - 
function GenerateStatTable() {
	global $theater, $upgrades, $armors, $range, $stats_tables;
//	$stats_tables = array();
	$armors = array('No Armor' => ($theater['player_settings']['damage']['DamageHitgroups']));
	foreach ($theater["player_gear"] as $gearname => $data) {
		$gear = getobject("player_gear", $gearname);
		$img = getvgui($gearname,'css');
		$thisitem = array();
		$thisitem['Img'] = $img;
		$thisitem['Name'] = getlookup(getval($gear,'print_name'));
		$thisitem['Team'] = printval($gear,"team_access");
		$thisitem['Slot'] = printval($gear,"gear_slot");
		$thisitem['Cost'] = printval($gear,"gear_cost");
		$thisitem['Weight'] = printval($gear,"gear_weight");
		$thisitem['Ammo'] = printval($gear,"extra_ammo");
		if (isset($gear["DamageHitgroups"])) {
			$thisitem['DamageHitgroups'] = getbodygraph($gear, $gear["DamageHitgroups"],'',0,2);
		}
		$stats_tables['Gear']['items'][$gearname] = $thisitem;
		if ($data["gear_slot"] == 'armor') {
			$armors[$thisitem['Name']] = $gear["DamageHitgroups"];
		}
	}
	foreach ($theater["weapons"] as $wpnname => $data) {
		if (isset($data["IsBase"])) {
			continue;
		}
		$thisitem = array();
		$item = getobject("weapons", $wpnname);
		$pn = getlookup(getval($item,"print_name"));
		$img = getvgui($wpnname,'css');
		$thisitem['Img'] = $img;
		$thisitem['Name'] = getlookup(getval($item,"print_name"));
		$thisitem['Class'] = printval($item,"weapon_class");
		$thisitem['CR'] = (printval($item,"class_restricted")) ? printval($item,"class_restricted") : 0;
		$thisitem['Length'] = dist($item["barrel_length"],'IN');
		$thisitem['Cost'] = printval($item,"weapon_cost");
		$thisitem['Slot'] = printval($item,"weapon_slot");
		$thisitem['Weight'] = printval($item,"weapon_weight");
		$thisitem['RPM'] = printval($item,"rounds_per_minute");
		$thisitem['Sway'] = printval($item,"sway");
		$thisitem['Damage'] = 0;
		$thisitem['Ammo'] = '';
		if (isset($item["ballistics"])) {
			$thisitem['Fire Modes'] = printval($item["ballistics"],"FireModes");
		} else {
			$thisitem['Fire Modes'] = 'single';
		}
		if (isset($item["explosives"])) {
			$thisitem['Ammo'] = printval($item["explosives"],"entity",1);
			$expammo = getobject("ammo", $item["ammo_clip"]["ammo_type"]);
			$thisitem['Carry'] = printval($expammo,"carry");
			$thisitem['Carry Max'] = printval($expammo,"carry");
		} elseif (isset($item["ammo_clip"])) {
//			$thisitem['Ammo'] = printval($item["explosives"],"entity",1);
			$ammo = getobject("ammo", $item["ammo_clip"]["ammo_type"]);
			$dmg = damageatrange(getval($ammo,'Damage',0), $range);
			$thisitem['Damage'] = $dmg;
			if (isset($ammo['bulletcount']) && $ammo['bulletcount'] > 1) {
				$thisitem['Damage']=($dmg*$ammo['bulletcount'])." max ({$ammo['bulletcount']} pellets)";
			}
			$thisitem['DamageChart'] = printval($ammo,"Damage");
			$thisitem['Spread'] = isset($item["ballistics"]['spread']) ? getspreadgraph($item["ballistics"]['spread']) : '';
//."<br>{$item["ballistics"]['spread']}";
//			$thisitem['Spread'] = $item["ballistics"]['spread'];
			$thisitem['Recoil'] = getrecoilgraph($item['recoil']);
			$thisitem['Ammo'] = printval($item["ammo_clip"],"ammo_type",1);
			if (isset($item["ammo_clip"]["clip_max_rounds"]) && (!isset($item["ballistics"]["singleReload"]) || !$item["ballistics"]["singleReload"])) {
				$thisitem['Magazine'] = printval($item["ammo_clip"],"clip_max_rounds");
				$thisitem['Carry'] = printval($item["ammo_clip"],"clip_default")." (".($item["ammo_clip"]["clip_max_rounds"]*$item["ammo_clip"]["clip_default"]).")\n";
				$thisitem['Carry Max'] = isset($item["ammo_clip"]["clip_max"]) ? printval($item["ammo_clip"],"clip_max")." (".($item["ammo_clip"]["clip_max_rounds"]*$item["ammo_clip"]["clip_max"]).")\n" : '';
			} else {
				$thisitem['Magazine'] = printval($item["ammo_clip"],"clip_max_rounds");
				$thisitem['Carry'] = printval($item["ammo_clip"],"clip_default");
				$thisitem['Carry Max'] = printval($item["ammo_clip"],"clip_max");
			}
		} elseif (isset($item["melee"])) {
			$thisitem['Damage'] = printval($item["melee"],"MeleeDamage");
		}
		$thisitem['Upgrades'] = '';
		$stats_tables['Weapons']['items'][$wpnname] = $thisitem;
	}
	foreach ($theater["weapon_upgrades"] as $upname => $data) {
		if (isset($data["IsBase"])) {
			continue;
		}

		if ((substr($upname,0,5) == "base_") || (substr($upname,-5,5) == "_base")) {
			continue;
		}
		$upgrade = getobject("weapon_upgrades", $upname,1);
		$img = getvgui($upname,'css');
		$thisitem = array();
		if (isset($upgrade['attach_weapon'])) {
			if (isset($stats_tables['Weapons']['items'][$upgrade['attach_weapon']])) {
				$stats_tables['Weapons']['items'][$upgrade['attach_weapon']]['Img'] = $img;
			}
		}
		if (!isset($upgrade['upgrade_cost'])) {
			$upgrade['upgrade_cost'] = 0;
		}
		if (substr($upname,0,5) == "ammo_") {
			$link = "<br><a href='#{$upgrade['ammo_type_override']}'>{$upgrade['ammo_type_override']} [{$upgrade['upgrade_cost']}]</a>";
			$fn = 'Ammo';
		} else {
			$link = "<a href='#{$upname}'>".getlookup(getval($upgrade,'print_name'))." [{$upgrade['upgrade_cost']}]</a><br>";
			$fn = 'Upgrades';
		}
		// Add ammo and upgrade links to weapon items
		$aw = array();
		if (isset($upgrade['allowed_weapons'])) {
			foreach ($upgrade['allowed_weapons'] as $order => $witem) {
				$aw[] = "<a href='#{$witem['weapon']}'>{$stats_tables['Weapons']['items'][$witem['weapon']]['Name']}</a>";
				$stats_tables['Weapons']['items'][$witem['weapon']][$fn].=$link;
			}
		}
		$thisitem['Img'] = $img;
		$thisitem['Name'] = getlookup(getval($upgrade,'print_name'));
		$thisitem['Slot'] = printval($upgrade,"upgrade_slot");
		$thisitem['CR'] = printval($upgrade,"class_restricted");
		$thisitem['Cost'] = printval($upgrade,"upgrade_cost");
		$thisitem['Ammo Type'] = printval($upgrade,"ammo_type_override",1);
		$thisitem['Abilities'] = printval($upgrade,"weapon_abilities");
		
		$thisitem['Weapons'] = implode("<br>", $aw);
		$stats_tables['Upgrades']['items'][$upname] = $thisitem;
	}
	foreach ($theater["ammo"] as $ammoname => $data) {
		// Hide rockets and grenades (so we can link to them in #explosives), and other items we don't want to see at all
		if ((substr($ammoname,0,7) == "rocket_") || (substr($ammoname,0,8) == "grenade_") || ($ammoname == 'default') || ($ammoname == 'no_carry')) {
			continue;
		}
		$ammo = getobject("ammo", $ammoname);
		if (!isset($ammo['carry']))
			continue;
		$thisitem = array();
		$thisitem['Name'] = "<a id='{$ammoname}'>{$ammoname}</a>";
		$thisitem['Carry'] = printval($ammo,"carry");
		$thisitem['Mag'] = printval($ammo,"magsize");
		$dmg = damageatrange(getval($ammo,'Damage',0), $range);
		if (isset($ammo['bulletcount']) && $ammo['bulletcount'] > 1) {
			$dmg=($dmg*$ammo['bulletcount'])." max ({$ammo['bulletcount']} pellets)";
		}
		$thisitem['Damage'] = $dmg;
		$thisitem['DamageGraph'] = printval($ammo,"Damage");
		$thisitem['PenetrationPower'] = damageatrange(getval($ammo,"PenetrationPower",0), $range);
		$thisitem['PenetrationGraph'] = printval($ammo,"PenetrationPower");
		$thisitem['Tracer'] = isset($ammo["tracer_frequency"]) ? "Type: {$ammo["tracer_type"]}<br>Frequency: {$ammo["tracer_frequency"]}<br>Low Ammo: {$ammo["tracer_lowammo"]}" : '';
		$thisitem['Suppression'] = printval($ammo,"SuppressionIncrement");
		$thisitem['DamageHitgroups'] = isset($ammo["DamageHitgroups"]) ? getbodygraph($ammo, $ammo["DamageHitgroups"],array_keys($armors)) : '';
		$stats_tables['Ammo']['items'][$ammoname] = $thisitem;
	}
	foreach ($theater["explosives"] as $explosivename => $data) {
		if (isset($data["IsBase"])) {
			continue;
		}
		$explosive = getobject("explosives", $explosivename);
		$thisitem = array();
		$thisitem['Name'] = "<a id='{$explosivename}'>{$explosivename}</a>";
		$thisitem['Class'] = printval($explosive,"entity_class");
		$thisitem['FuseTime'] = printval($explosive,"FuseTime");
		if (isset($explosive["Cookable"])) {
			$thisitem['Cookable'] = printval($explosive,"Cookable");
		} else {
			$thisitem['Cookable'] = 1;
		}
		if (isset($explosive["RocketStartSpeed"])) {
			$speeds = array($explosive["RocketStartSpeed"]);
			if (isset($explosive["RocketTopSpeed"])) {
				if (isset($explosive["RocketAcceleration"])) {
					for ($i=(($explosive["RocketStartSpeed"])+($explosive["RocketAcceleration"]));$i<$explosive["RocketTopSpeed"];$i+=($explosive["RocketAcceleration"])) {
						$speeds[] = $i;
					}
				}
				$speeds[] = $explosive["RocketTopSpeed"];
			}
		}
		if (count($speeds) > 1) {
			$thisitem['Speed'] = getgraph($speeds,'Speed','Time');
		} else {
			$thisitem['Speed'] = isset($explosive["RocketStartSpeed"]) ? dist($explosive["RocketStartSpeed"],'IN')."/s" : '';
		}
		if (isset($explosive["DetonateDamageRadius"])) {
			if ($range < $explosive["DetonateDamageRadius"]) {
//				var_dump("DetonateDamage",$explosive["DetonateDamage"],"DetonateDamageRadius",$explosive["DetonateDamageRadius"],"DetonateDamageRadius",$explosive["DetonateDamageRadius"]);
				$dmg = safe_round(safe_round($explosive["DetonateDamage"]) * (safe_round($explosive["DetonateDamageRadius"]) / (safe_round($explosive["DetonateDamageRadius"]) - $range)),2);
			} else {
				$dmg = 0;
			}
		} else {
			$dmg = 0;
		}
		$thisitem['Damage'] = $dmg;
		if (isset($explosive["AreaDamageAmount"])) {
			$thisitem['DamageGraph'] = "AreaDamageTime: {$explosive["AreaDamageTime"]}<br>AreaDamageFrequency: {$explosive["AreaDamageFrequency"]}<br>AreaDamageMinRadius: ".dist($explosive["AreaDamageMinRadius"],'IN')."<br>AreaDamageMaxRadius: ".dist($explosive["AreaDamageMaxRadius"],'IN')."<br>AreaDamageGrowSpeed: {$explosive["AreaDamageGrowSpeed"]}<br>AreaDamageAmount: {$explosive["AreaDamageAmount"]}<br>DamageType: {$explosive["DamageType"]}";
		} else {
			$thisitem['DamageGraph'] = isset($explosive["DetonateDamageRadius"]) ? (getcirclegraph($explosive)."<br>DetonateDamage: {$explosive["DetonateDamage"]}<br>DetonatePenetrationRadius: ".dist($explosive["DetonatePenetrationRadius"],'IN')."<br>DetonateDamageRadius: ".dist($explosive["DetonateDamageRadius"],'IN')) : '';
			if (isset($explosive["DetonateFlashDuration"])) {
				$thisitem['DamageGraph'].= "<br>DetonateFlashDuration: {$explosive["DetonateFlashDuration"]}";
			}
		}
		$stats_tables['Explosives']['items'][$explosivename] = $thisitem;
	}
	foreach ($theater['player_templates'] as $classname => $classdata) {
		$img = getvgui($classname,'css','vgui/inventory',256,512);
		$thisitem = array();
		$thisitem['Img'] = $img;
		$thisitem['Name'] = getlookup(getval($classdata,'print_name'));
		$thisitem['Team'] = printval($classdata,"team");
		$thisitem['Models'] = printval($classdata,"models");
		$thisitem['Buy order'] = '';
		$thisitem['Allowed Items'] = '';
		if (isset($classdata["buy_order"])) {
			foreach ($classdata["buy_order"] as $order => $buyitem) {
				foreach ($buyitem as $type => $item) {
					$thisitem['Buy order'].= "<a href='#{$item}'>{$item}</a><br>";
				}
			}
		}
		if (isset($classdata["allowed_items"])) {
			foreach ($classdata["allowed_items"] as $order => $aitem) {
				foreach ($aitem as $type => $item) {
					if (is_array($item)) {
						foreach ($item as $it => $in) {
							$thisitem['Allowed Items'].= "<a href='#{$in}'>[{$type}] {$in}</a><br>";
						}
					} else {
						$thisitem['Allowed Items'].= "<a href='#{$item}'>{$item}</a><br>";
					}
				}
			}
		}
		$stats_tables['Classes']['items'][$classname] = $thisitem;
	}
	// Teams are goofy because of display method
	foreach ($theater['teams'] as $teamname=>$teamdata) {
		$thisitem = '<table>';
		$tn = getlookup(getval($teamdata,'name'));
		$stats_tables['Teams']['fields'][$tn] = 1;
		if (isset($teamdata['logo'])) {
			$img = getvgui($teamdata['logo'],'bare','vgui');
			$thisitem.="<div style='text-align: center;'><img src='{$img}' style='width: 64px; height: 64px;'></div>\n";

		}
		if (isset($teamdata['squads'])) {
			foreach ($teamdata['squads'] as $squad => $squaddata) {
				$sn = getlookup($squad);
				$thisitem.="<tr><td><h3>{$sn}</h3></td></tr><tr><td>\n";
				foreach ($squaddata as $order => $slot) {
					foreach ($slot as $title => $role) {
						$label = getlookup($title);
						$thisitem.="<a href='#{$role}'>{$label}<br>\n";
					}
				}
				$thisitem.="</td></tr>\n";
			}
		}
		$thisitem.="</table>\n";
		$stats_tables['Teams']['items'][0][$tn] = $thisitem;
	}
	// Do cleanup and make links between items here
	foreach (array_keys($stats_tables) as $sectionname) {
		ksort($stats_tables[$sectionname]['items']);
	}
	return($stats_tables);
}
/*
function ProcessListLinks($list) {
	$keys = implode('',array_keys($list));
	$items = array();
	if (is_numeric($keys)) {
		foreach ($list as $order => $aitem) {
			if (is_array($aitem)) {
				$items[$order] = ProcessListLinks($aitem);
			} else {
				$items[$order] = $aitem;
			}
		foreach ($aitem as $type => $item) {
			if (is_array($item)) {
				foreach ($item as $it => $in) {
					$thisitem['Allowed Items'].= "<a href='#{$in}'>[{$type}] {$in}</a><br>";
				}
			} else {
				$thisitem['Allowed Items'].= "<a href='#{$item}'>{$item}</a><br>";
			}
		}
	}
}
*/
// FUNCTIONS
function safe_round($val,$digits=0,$elements=0) {
	if (is_array($val)) {
		$val = array_shift(array_values($val));
	}
	if (is_numeric($val)) {
		$val = round($val,$digits);
	} else {
		$val = array_shift(explode(".",$val));
	}
	return $val;
}

function DisplayLoggerConfig() {
	echo "<pre>";
	echo "\"weapons\"\n{\n";
	foreach ($weapons as $weapon => $item) {
		printf("\t%-20s\t%s\n", "\"{$weapon}\"",'"'.getlookup(getval($item,"print_name")).'"');
	}
	echo "}\n";
}
function DisplaySMTranslation() {
	echo "<pre>";
	global $lang, $langcode;
	$phrases = array();
	foreach ($lang as $language => $tokens) {
		$lc = strtolower($language);
		$lc = $langcode[$lc];
		if ($lc) {
			foreach ($tokens as $key => $val) {
				$val = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $val);

				$phrases[$key][$lc] = $val;
			}
		}
	}
// jballou - commented out until I decide if we even need this
/*
	$reader = new KVReader();
	ksort($phrases);
	echo $reader->write(array('Phrases' => $phrases));
*/
}
function DisplayWikiView() {
	$itemtype = $_REQUEST['itemtype'];
	$itemname = $_REQUEST['itemname'];
	$item = getobject($itemtype, $itemname);
	
}
function DisplayHLStatsX() {
	global $theater;
	echo "<pre>";
	$values = array();
	$tables = array(
		'Awards' => array(
			'allfields' => array('game', 'code', 'name', 'verb'),
			'fields'	=> array('name', 'verb')
		),
		'Ribbons' => array(
			'allfields' => array('game', 'awardCode', 'awardCount', 'special', 'image', 'ribbonName'),
			'fields'	=> array('image', 'ribbonName')
		),
		'Weapons' => array(
			'allfields' => array('game', 'code', 'name', 'modifier'),
			'fields'	=> array('name')
		),
		'Teams' => array(
			'allfields' => array('game', 'code', 'name'),
			'fields'	=> array('name')
		),
		'Roles' => array(
			'allfields' => array('game', 'code', 'name'),
			'fields'	=> array('name')
		)
	);
	foreach ($theater['weapons'] as $weapon => $item) {
		if (isset($item["IsBase"])) {
			continue;
		}
		$wpnname = getlookup(getval($item,"print_name"));
		$wn = str_replace('weapon_','', $weapon);
		$values['Awards'][] = "('insurgency', '{$weapon}', '{$wpnname}', 'Kills with {$wpnname}')";
		$values['Ribbons'][] = "('insurgency', '{$weapon}', '10', '0', 'rg_{$wn}.png', 'Gold {$wpnname}')";
		$values['Ribbons'][] = "('insurgency', '{$weapon}', '5', '0', 'rs_{$wn}.png', 'Silver {$wpnname}')";
		$values['Weapons'][] = "('insurgency', '{$weapon}', '{$wpnname}', '1.00')";
	}

	foreach ($theater['player_templates'] as $class => $classdata) {
		if (isset($classdata["IsBase"])) {
			continue;
		}
		$classname = getlookup(getval($classdata,'print_name'));
		$shortclass = str_replace(array('template_','_insurgent','_security','_training','_elimination','_coop','coop_'),'', $class);
		$values['Roles'][$shortclass] = "('insurgency','{$shortclass}','{$classname}')";
	}
	foreach ($theater['teams'] as $team=>$teamdata) {
		$teamname = getlookup(getval($teamdata,'name'));
		$values['Teams'][] = "('insurgency','{$teamdata['name']}','{$teamname}')";
	}
	$dbprefix = $_REQUEST['dbprefix'] ? $_REQUEST['dbprefix'] : 'hlstats';
	foreach ($tables as $table => $tdata) {
		echo "--\n-- Update {$dbprefix}_{$table}\n--\n\n";
		$fields = array();
		foreach ($tdata['fields'] as $field) {
			$fields[] = "{$field} = VALUES({$field})";
		}
		asort($values[$table]);
		echo "INSERT INTO `{$dbprefix}_{$table}`\n	(`".implode('`, `', $tdata['allfields'])."`)\n	 VALUES\n		 ".implode(",\n		 ", $values[$table])."\n	 ON DUPLICATE KEY UPDATE ".implode(', ', $fields).";\n";
	}
}
function damageatrange($dmg, $range, $dec=2) {
	if (!is_array($dmg))
		return;
	ksort($dmg);
	foreach ($dmg as $dist => $dmg) {
		if ($dist >= $range) {
			if (!isset($mindist)) {
				return $dmg;
			}
			$diffdist = ($dist - $mindist);
			$diffdmg = ($mindmg - $dmg);
			$diffrange = ($range - $mindist);
			$dmgpct = $diffrange/$diffdist;
			return safe_round(($mindmg - ($dmgpct * $diffdmg)), $dec);
		}
		else {
			$mindmg = $dmg;
			$mindist = $dist;
		}
	}
}
/*
getgraph
*/
function getgraph($points, $xname='Damage', $yname='Distance', $type='line', $height=100, $width=200) {
	ksort($points);
	if (count($points) > 2) {
		$midpoints = array_slice($points,1,-1,true);
	}
	$maxdmg = max(array_values($points));
	$maxdist = max(array_keys($points));
	$mindmg = min(array_values($points));
	$mindist = min(array_keys($points));

	if ($xname == 'Damage') {
		$gmaxdmg = 300;
		$gmaxdist = 20000;
		$gmindmg = 0;
		$gmindist = 10;
	} elseif ($xname == 'PenetrationPower') {
		$gmaxdmg = 1500;
		$gmaxdist = 20000;
		$gmindmg = 0;
		$gmindist = 10;
	} else {
		$gmaxdmg = $maxdmg;
		$gmaxdist = $maxdist;
		$gmindmg = $mindmg;
		$gmindist = $mindist;
	}
	$margin = 64;
	$half = ($margin / 2);
	$hm = ($height / ($gmaxdmg - $gmindmg));
	$wm = ($width / ($gmaxdist - $gmindist));
	$pts = array();
	if ($maxdist < $gmaxdist)
		$points[$gmaxdist] = $points[$maxdist];
	foreach ($points as $dist => $dmg) {
		$pts[] = safe_round((($dist - $gmindist) * $wm)+$margin).",".safe_round($height - (($dmg - $gmindmg) * $hm));
	}
	$dispdistmin = ($xname == 'Speed') ? $gmindist : dist($gmindist,'IN');
	$dispdistmax = ($xname == 'Speed') ? $gmaxdist : dist($gmaxdist,'IN');
	$dispdmgmin = ($xname == 'Speed') ? dist($gmindmg,'IN')."/s" : $gmindmg;
	$dispdmgmax = ($xname == 'Speed') ? dist($gmaxdmg,'IN')."/s" : $gmaxdmg;
	$pts = "M".implode(' ', $pts);
	$svg = "							<svg xmlns='http://www.w3.org/2000/svg' height='".($height+$half)."' width='".($width+$margin+2)."' style='fill: red;'>
								<defs>
									<marker id='circle' markerWidth='7' markerHeight='7' refx='4' refy='4'><rect x='1' y='1' width='5' height='5' style='stroke: none; fill:#000000;' /></marker>
								</defs>
								<rect x='{$margin}' height='{$height}' width='{$width}' y='2' style='fill:rgb(255,255,200);stroke-width:2;stroke:rgb(0,0,0)' />
								<path d='{$pts}' style='fill:none; stroke:blue; stroke-width:1; marker-start: url(#circle); marker-mid: url(#circle); marker-end: url(#circle);'/>
								<g style='font-size: 12; font-face: sans-serif; fill: #000000; stroke: none;'>
									<text font-size='16' x='16' y='50%' text-anchor='middle' style='font-weight: bold; writing-mode: tb; glyph-orientation-vertical: 90;'>{$xname}</text>
									<text font-size='16' x='50%' y='{$height}' dy='{$half}' dx='{$half}' text-anchor='middle' style='font-weight: bold;'>{$yname}</text>
									<text x='{$margin}' dx='-2' y='12' text-anchor='end'>{$dispdmgmax}</text>
									<text x='{$margin}' dx='-2' y='{$height}' dy='-2' text-anchor='end'>{$dispdmgmin}</text>
									<text x='{$margin}' y='{$height}' dy='16' text-anchor='start'>{$dispdistmin}</text>
									<text x='".($width+$margin)."' y='116' text-anchor='end'>{$dispdistmax}</text>
								</g>
								<g font-size='12' font='sans-serif' fill='black' stroke='none'	text-anchor='left'>\n";
/*
	foreach ($midpoints as $dist => $dmg) {
		$x=safe_round((($dist - $gmindist) * $wm)+$margin) + 4;
		$y=safe_round($height - (($dmg - $gmindmg) * $hm)) - 4;
		$ta = ($x < $width) ? 'start' : 'end';
		if ($xname == 'Speed') {
			$cap = "{$dist}:".dist($dmg,'IN')."/s";
		} else {
			$cap = dist($dist,'IN',null,0).":{$dmg}";
		}
		$svg.= "									<text x='{$x}' y='{$y}' text-anchor='{$ta}'>{$cap}</text>\n";
	}
*/
	$svg.= "								</g>
							</svg>\n";
	return $svg;
}
/* getcirclegraph
Show a graph for DamageRadius stuff
*/
function getcirclegraph($object, $size=100, $maxdamage=1200, $margin = 0) {
	$dmg = getval($object,"DetonateDamage",0);
	$dmgrad = getval($object,"DetonateDamageRadius",0);
	$dmgpen = getval($object,"DetonatePenetrationRadius",0);
	if (!$dmg || !$dmgrad) {
		return;
	}
	$half = $margin / 2;
	$dm = ($maxdamage / $size);
	$c = ($size+$margin)/2;
	$step = ($dmg / $dmgrad);
	$dmg_size = (($dmg / $maxdamage) * $size)/2;
	$pen_size = (($dmgpen / $maxdamage) * $size)/2;
	$svg = "							<svg xmlns='http://www.w3.org/2000/svg' height='".($size+$margin)."' width='".($size+$margin)."'>
								<rect x='{$half}' y='{$half}' height='{$size}' width='{$size}' style='fill:rgb(255,255,200);stroke-width:3;stroke:rgb(0,0,0)' />
								<circle cx='{$c}' cy='{$c}' r='{$dmg_size}' stroke='black' stroke-width='3' fill='yellow' />
								<circle cx='{$c}' cy='{$c}' r='{$pen_size}' stroke='black' stroke-width='3' fill='red' />
							</svg>\n";
	return $svg;
}
/* getspreadgraph
Show a graph for spread
*/
function getspreadgraph($vector, $size=100, $grid=6, $maxspread=1, $margin = 0) {
	$step = ($size/$grid);
	$coords = explode(' ', $vector);
	$half = $margin / 2;
	$dm = ($maxspread / $size);
	$c = ($size+$margin)/2;
	$radr = (($coords[0] / $maxspread) * $size)/2;
	$svg = "							<svg xmlns='http://www.w3.org/2000/svg' height='".($size+$margin)."' width='".($size+$margin)."'>
								<defs>
									<pattern id='grid' width='{$step}' height='{$step}' patternUnits='userSpaceOnUse'>
										<path d='M {$step} 0 L 0 0 0 {$step}' fill='none' stroke='gray' stroke-width='1'/>
									</pattern>
								</defs>
								<rect x='{$half}' y='{$half}' height='{$size}' width='{$size}' fill='url(#grid)' stroke='gray' stroke-width='1'/>
								<circle cx='{$c}' cy='{$c}' r='{$radr}' stroke='black' stroke-width='3' fill='red' />
								<circle cx='{$c}' cy='{$c}' r='1' stroke='black' stroke-width='1' fill='black' />
							</svg>\n";
	$svg.="<div style='text-align: center;'>{$coords[0]}</div>\n";
	return $svg;
}
/* getrecoilgraph
Show a graph for recoil
*/
function getrecoilgraph($recoil, $size=100, $maxrecoil=9, $margin = 0) {
	if (!isset($recoil['recoil_lateral_range']) || !isset($recoil['recoil_vertical_range'])) {
		return;
	}
	$vector = "{$recoil['recoil_lateral_range']} {$recoil['recoil_vertical_range']}";
	$coords = explode(" ", $vector);

	$c = ($size+$margin)/2;
	$step = ($size / $maxrecoil);
	$half = $maxrecoil / 2;

	$rx = ($half - abs($coords[0])) * $step;
	$ry = ($maxrecoil - $coords[3]) * $step;
	$rwidth = ((abs($coords[0]) + abs($coords[1])) * $step);
	$rheight = (($coords[3] - $coords[2]) * $step);
	$svg = "
<svg xmlns='http://www.w3.org/2000/svg' height='".($size+$margin)."' width='".($size+$margin)."'>
<defs>
<pattern id='grid' width='{$step}' height='{$step}' patternUnits='userSpaceOnUse'>
<path d='M {$step} 0 L 0 0 0 {$step}' fill='none' stroke='gray' stroke-width='1'/>
</pattern>
</defs>
<rect width='100%' height='100%' fill='url(#grid)' stroke='gray' stroke-width='1'/>
<circle cx='{$c}' cy='{$size}' r='4' stroke='black' stroke-width='3' fill='red' />
<rect x='{$rx}' y='{$ry}' height='{$rheight}' width='{$rwidth}' style='fill:rgb(255,0,0);stroke-width:1;stroke:rgb(0,0,0)' />
</svg>\n";

	$svg.="<div style='text-align: center;'>{$coords[3]}<br>{$recoil['recoil_lateral_range']}<br>{$coords[2]}</div>\n";
	return $svg;
	$svg.="<table>
<tr>
<td>&nbsp;</td>
<td>{$vertical[1]}</td>
<td>&nbsp;</td>
</tr>
<tr>
<td>{$lateral[0]}</td>
<td>&nbsp;</td>
<td>{$lateral[1]}</td>
</tr>
<tr>
<td>&nbsp;</td>
<td>{$vertical[0]}</td>
<td>&nbsp;</td>
</tr></table>\n";

/*

	$rwidth = (abs($lateral[1] - $lateral[0]) * $step);
	$rheight = (abs($vertical[1] - $vertical[0]) * $step);
	$rx = ($half - $lateral[0]) * $step;
	$ry = ($half - $vertical[0]) * $step;
	$svg = "							<svg xmlns='http://www.w3.org/2000/svg' height='".($size+$margin)."' width='".($size+$margin)."'>
<defs>
		<pattern id='grid' width='9' height='9' patternUnits='userSpaceOnUse'>
			<path d='M 9 0 L 0 0 0 9' fill='none' stroke='gray' stroke-width='1'/>
		</pattern>

	</defs>
	<rect width='100%' height='100%' fill='url(#grid)' />
								<rect x='{$rx}' y='{$ry}' height='{$rheight}' width='{$rwidth}' style='fill:rgb(255,0,0);stroke-width:1;stroke:rgb(0,0,0)' />
								<rect x='{$px}' y='{$py}' height='{$pheight}' width='{$pwidth}' style='fill:rgb(255,0,255);stroke-width:1;stroke:rgb(0,0,0)' />\n";
	$svg.="								 <line </svg>\n";
	$svg.="							 </svg>\n";

*/
}
function getbodygraph($object, $hitgroups, $disparmor='', $headers=1, $dec=0) {

	$positions = array('HITGROUP_HEAD' => '78,22','HITGROUP_CHEST' => '78,86','HITGROUP_STOMACH' => '78,136','HITGROUP_LEFTARM' => '122,122','HITGROUP_RIGHTARM' => '32,122','HITGROUP_LEFTLEG' => '56,222','HITGROUP_RIGHTLEG' => '94,222');
	global $armors, $range;
	$graph = '';
	$header = '';
	if (!is_array($disparmor)) {
		$disparmor = array($disparmor);
	}
	foreach ($disparmor as $armor) {
		$graph.="<td><div class='bodygraph'>\n";
// style='background: url(images/stats/body.png);'>\n";
//<img src='images/stats/body.png' style='position: absolute; left: 0; top: 0;>";
		$armordata = array();
		foreach ($positions as $key => $val) {
			$armordata[$key] = isset($armors[$armor][$key]) ? safe_round($armors[$armor][$key],2) : 1;
		}
		$dist = dist($GLOBALS['range'],'IN');
		$header.="<th>{$armor} @ {$dist}</th>\n";
		foreach ($armordata as $key => $val) {
			$eq='';
			if (isset($object["Damage"])) {
				$basedmg = damageatrange($object['Damage'], $range);
				$result = ($basedmg * $armors['No Armor'][$key]);
				$eq.= "base damage: {$basedmg}\nbase hitgroup: * {$armors['No Armor'][$key]}\n";
				if ($hitgroups[$key]) {
					$result*=$hitgroups[$key];
					$eq.= "ammo hitgroup: * {$hitgroups[$key]}\n";
				}
				if ($armor != 'No Armor') {
					$eq.= "armor hitgroup: * {$val}\n";
					$result*=$val;
				}
				if (isset($object['bulletcount']) && $object['bulletcount'] > 1) {
					$eq.= "bullet count: * {$object['bulletcount']}\n";
					$result*=$object['bulletcount'];
				}
				$eq.="total: {$result}\n";
			} else {
				$result = ($val * $armordata[$key] * $hitgroups[$key]);
			}
			$result = safe_round($result, $dec);
			$coords = explode(',', $positions[$key]);
			$graph.="<div title='{$eq}' style='position: absolute; left: {$coords[0]}px; top: {$coords[1]}px; width: 100px; text-align: center; transform: translate(-50%, -50%);'>{$result}</div>\n";
		}
		$graph.="</div></td>\n";
	}
	if ($headers) {
		$retval = "<table><tr>{$header}</tr><tr>{$graph}</tr></table>";
	} else {
		$retval = "<table><tr>{$graph}</tr></table>";
	}
	return $retval;
}


/* getobject
Take a type (weapon, ammo, explosive, etc), key (name of item), and boolean for recursing
*/
function getobject($type, $key, $recurse=0) {
	global $theater;
	// Get object from theater. This has a case insensitive failsafe, since theater keys sometimes aren't the same case.
	if (@isset($theater[$type][$key])) {
		$object = $theater[$type][$key];
	} else {
		return;
		foreach ($theater[$type] as $ikey=>$item) {
			if (strtolower($key) == strtolower($ikey)) {
				$object = $item;
				break;
			}
		}
	}
	// Merge in imports
	if (!isset($object))
		return;
	if (isset($object["import"])) {
		// Merge using replacement of like items, which will not merge sub-array values like damagehitgroups or ammo_clip if the object also defines the section. This appears to be the way the game processes these sections.
		$import = getobject($type, $object["import"], $recurse);
		if ($import) {
			unset($import['IsBase']);
			$object = theater_array_replace($import, $object);
		}
	}
	return $object;
}
/* printarray
Display the damage of bullets, this is used to show damage at distances
*/
function printarray($object, $index, $link=0, $nulldisp='&nbsp;', $prefix='') {
	$data = '';
	if ($prefix != '') {
		$prefix.='->';
	}
	global $range;
	$graph = (in_array($index,array('Damage','PenetrationPower','PhysicsImpulse','PenetrationDamage'))) ? 1 : 0;
	if ($graph) {
		if (!isset($object[$index][$range])) {
			$object[$index][$range] = damageatrange($object[$index], $range);
		}
		ksort($object[$index]);
	}
	$arr = array();
	$dmg = array();
	foreach ($object[$index] as $rangedist => $rangedmg) {
		if (is_array($rangedmg)) {
			$arr[] = printarray($object[$index], $rangedist, $link, $nulldisp, $prefix.$rangedist);
		} else {
			$disprange = ($graph) ? dist($rangedist,'IN') : $rangedist;
			$dmg[$rangedist] = $rangedmg;
			if ($index == 'Damage' && (isset($object['bulletcount']) && $object['bulletcount'] > 1)) {
				$totaldmg = $rangedmg * $object['bulletcount'];
				$dmg[$rangedist] = $totaldmg;
				$arr[] = "{$prefix}{$disprange}: {$totaldmg}";
			} else {
				if (($index == 'DamageHitgroups') && ($object['Damage'])) {
					$basedmg = array_shift(array_values($object['Damage']));
					if ($object['bulletcount'] > 1) {
						$basedmg = $basedmg * $object['bulletcount'];
					}
					$arr[] = "{$prefix}{$disprange}: {$rangedmg} (".($rangedmg * $basedmg)." dmg)";
				} else {
					$arr[] = "{$prefix}{$disprange}: {$rangedmg}";
				}
			}
		}
	}
	if ($graph) {
		$data = getgraph($dmg, $index)."<br>\n";
	}
	return $data.implode('<br>', $arr);
}
/*
getlookup
Returns a string localized
*/
function getlookup($key) {
	global $language, $lang;
	if (is_array($key))
		$key = end($key);
	if (substr($key,0,1) == "#") {
		$key = strtolower($key);
		return $lang[$language][$key];
	}
	return $key;
}

function getval($object, $index, $default='') {
	if (isset($object[$index])) {
		return $object[$index];
	} else {
		return $default;
	}
}
/*
printval
Displays a <td> item of one cell of information
$object - (array of theater section, like $ammo, $weapons),
$index - Name of the item
$open - Open the td
$close - Close the td
$link - Create a link to an anchor of the name $index
*/
function printval($object, $index, $link=0, $nulldisp='&nbsp;') {
	$data = '';
	if (isset($object[$index])) {
		if (is_array($object[$index])) {
			$data.=printarray($object, $index, $link=0, $nulldisp='&nbsp;');
		} else {
			$data.=getlookup($object[$index]);
			if ($link) {
				$data = "<a href='#{$object[$index]}'>{$data}</a>";
			}
		}
	} else {
		$data.=$nulldisp;
	}
	return $data;
}
/*
dist
Returns the value sent in the correct units
$dist - The distance
$fromunits - Units the distance provided is in, defaults to IN (Game Units)
$tounits - Units to convert to, defaults to global $range_units
$suffix - Append space and tounits at the end
*/
function dist($dist, $fromunits='U', $tounits=null, $suffix=1) {
	if (is_array($dist)) {
		$dist = array_shift(array_values($dist));
	}
	if ((!array_key_exists($tounits, $GLOBALS['range_units'])) || (is_null($tounits))) {
		$tounits = $GLOBALS['range_unit'];
	}
	// If no conversion just return
	if ($fromunits != $tounits) {
		$conv = array(
			'U' => 1,
			'IN' => 0.75,
			'M' => 0.01905,
			'FT' => (1/16),
			'YD' => (1/48)
		);
		// Convert to units first
		if ($fromunits != 'U') {
			//var_dump($dist,$conv,$fromunits);
			$dist = safe_round($dist/$conv[$fromunits]);
		}
		$dist = safe_round($dist*$conv[$tounits],2);
	}
	if ($suffix) {
		$dist.=strtolower($tounits);
	}
	return $dist;
}
