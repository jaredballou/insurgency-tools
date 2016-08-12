<?php
//BEGIN mods
$mods = LoadMods("{$datapath}/mods");
// Set version and newest_version to the latest one. Try to get the version from Steam, otherwise just choose the newest available.
ksort($mods);

// Default mod
$mod="insurgency";

// If mod in request is valid, use it
if (isset($_REQUEST['mod'])) {
	if (isset($mods[$_REQUEST['mod']])) {
		$mod = $_REQUEST['mod'];
	}
}

// Set mod_compare to our mod now that we have handled user input.
// These two need to be identical if we're not doing the mod compare dump command
$mod_compare = $mod;
if (isset($_REQUEST['mod_compare'])) {
	if (isset($mods[$mod][$_REQUEST['mod_compare']])) {
		$mod_compare = $_REQUEST['mod_compare'];
	}
}
// END mods



//BEGIN version

$steam_ver=getSteamVersion();
$newest_version = $version = isset($mods[$mod][$steam_ver]) ? $steam_ver : end(array_keys($mods[$mod]));

// If version sent by request, set it as the version if it's valid.
if (isset($_REQUEST['version'])) {
	if (isset($mods[$mod][$_REQUEST['version']])) {
		$version = $_REQUEST['version'];
	}
}

// Set version_compare to our version now that we have handled user input.
// These two need to be identical if we're not doing the version compare dump command
$version_compare = $version;
if (isset($_REQUEST['version_compare'])) {
	if (isset($mods[$mod][$_REQUEST['version_compare']])) {
		$version_compare = $_REQUEST['version_compare'];
	}
}
//END version


// Load mods
function LoadMods($path,$pattern='*',$level=0) {
	$result = array();
	$dirname=implode("/",array_slice(explode("/",realpath($path)),-$level));
	$items = glob("{$path}/{$pattern}");
	foreach ($items as $item) {
		// If it's a symlink, reference the target
		$file = (is_link($item)) ? readlink($item) : $item;
		$basename = basename($item);
		if (is_dir($file)) {
			$result[$basename] = LoadMods($item,$pattern,$level+1);
		} else {
			// Don't list files that are part of the mod metadata structure
			if ($level > 1) {
				$result[$basename] = "{$dirname}/{$basename}";
			}
		}
	}
	return $result;
}

function GetModFiles($type='theater') {
	switch ($type) {
		case 'theater':
			$path = array('scripts','theaters');
			break;
		case 'map':
			$path = array("${type}s");
			break;
		case 'cvarlist':
			$path = array($type);
			break;
	}
	// Populate data hash
	$data = array();
	foreach ($GLOBALS['mods'] as $mname => $mdata) {
		foreach ($mdata as $vname => $vdata) {
			foreach ($path as $key) {
				if (!isset($vdata[$key])) {
					continue 2;
				}
				$vdata = &$vdata[$key];
			}
			foreach ($vdata as $tname => $tpath) {
				$bn = preg_replace('/\.[^\.]+$/','',basename($tname));
				if ($type == 'map') {
					if (!(GetDataFile("maps/parsed/{$bn}.json"))) {
						continue;
					}
				}
				$data[$mname][$vname][$bn] = $bn;
			}
		}
	}
	return($data);
}

function DisplayModSelection($compare=0, $type='theater') {
	$fields = array('mod','version',$type);
	$fieldname = $ext = $type;
	$suffix = ($compare) ? '_compare' : '';
	$js = array();
	$vars = array('data' => GetModFiles($type));

	foreach ($fields as $field) {
		switch ($field) {
			case 'theater':
				$fieldname = 'theaterfile';
				break;
			case 'map':
			case 'cvarlist':
			default:
				$fieldname = $field;
				break;
		}
		$vars[$field] = 
			($suffix) ?
				(($GLOBALS["{$fieldname}{$suffix}"] == $GLOBALS[$fieldname]) ? '-' : $GLOBALS["{$fieldname}{$suffix}"]) :
				$GLOBALS[$fieldname];
		echo "{$field}: <select name='{$field}{$suffix}' id='{$field}{$suffix}'></select>\n";
		$jsf = ($field == $type) ? 'item' : $field;
		$js[] = "var select_{$jsf}{$suffix} = \$('#{$field}{$suffix}');";
		$js[] = "var cur_{$jsf}{$suffix} = '{$vars[$field]}';";
	}

	// If showing comparison options, put in blank as first entry to denote no comparison
	if ($compare)
		$vars['data']['-']['-']['-'] = '-';
?>
<script type="text/javascript">
jQuery(function($) {
	var data = <?php echo prettyPrint(json_encode($vars['data'])); ?>;
	<?php echo implode("\n\t",$js)."\n"; ?>

	$(select_mod).change(function () {
		var mod = $(this).val(), vers = data[mod] || [];
		var html =  $.map(Object.keys(vers).sort().reverse(), function(ver){
			return '<option value="' + ver + '">' + ver + '</option>'
		}).join('');
		select_version.html(html);
		select_version.change();
	});

	$(select_version).change(function () {
		var version = $(this).val(), mod = $(select_mod).val(), values = data[mod][version] || [];
		var html =  $.map(Object.keys(values), function(item){
			return '<option value="' + item + '">' + item + '</option>'
		}).join('');
		select_item.html(html);
		select_item.change();
	});
	var html =  $.map(Object.keys(data), function(mod){
		return '<option value="' + mod + '">' + mod + '</option>'
	}).join('');
	select_mod.html(html);
	select_mod.val(cur_mod);
	select_mod.change();
	select_version.val(cur_version);
	select_version.change();
	select_item.val(cur_item);
	select_item.change();
});
</script>
<?php
}
