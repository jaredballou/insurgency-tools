<?php
function DisplayModSelection($compare=0, $type='theater') {
	$fields = array('mod','version',$type);
	$fieldname = $ext = $type;
	$suffix = ($compare) ? '_compare' : '';
	$js = $vars = $data = array();

	$path = array("{$type}s");
	foreach ($fields as $field) {
		switch ($field) {
			case 'theater':
				$fieldname = 'theaterfile';
				array_unshift($path,'scripts');
				break;
			case 'map':
				$path = array('resource','overviews');
			default:
				$fieldname = $field;
		}
		$data[$field] = 
			($suffix) ?
				(($GLOBALS["{$fieldname}{$suffix}"] == $GLOBALS[$fieldname]) ? '-' : $GLOBALS["{$fieldname}{$suffix}"]) :
				$GLOBALS[$fieldname];
		echo "{$field}: <select name='{$field}{$suffix}' id='{$field}{$suffix}'></select>\n";
		$vars[$field] = $data[$field];
		$jsf = ($field == $type) ? 'item' : $field;
		$js[] = "var select_{$jsf}{$suffix} = \$('#{$field}{$suffix}');";
		$js[] = "var cur_{$jsf}{$suffix} = '{$vars[$field]}';";
	}

	// If showing comparison options, put in blank as first entry to denote no comparison
	if ($compare)
		$vars['data']['-']['-']['-'] = '-';

	// Populate data hash
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
				$vars['data'][$mname][$vname][$bn] = $bn;
			}
		}
	}
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

