<?php
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
if (isset($use_ob)) {
	ob_start();
}
require_once("{$GLOBALS['includepath']}/functions.php");
if (!isset($title)) {
	$title = 'Insurgency Tools';
}
?><!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo $title; ?></title>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <script src='https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js'></script>
    <script src="http://code.jquery.com/ui/1.11.2/jquery-ui.js"></script>

<!-- Latest compiled and minified CSS -->
<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css'>
<link rel='stylesheet' href='https://cdn.datatables.net/1.10.4/css/jquery.dataTables.min.css'>

<!-- Optional theme -->
<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap-theme.min.css'>

<!-- Latest compiled and minified JavaScript -->
<script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.0/js/bootstrap.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/floatthead/1.2.10/jquery.floatThead.min.js'></script>
    <script src='//cdn.datatables.net/1.10.4/js/jquery.dataTables.min.js'></script>

<!-- D3 -->
<script src="http://d3js.org/d3.v3.min.js" charset="utf-8"></script>

<link rel="stylesheet" href="<?php echo "{$GLOBALS['urlbase']}include/style.css"; ?>">

<script type="text/javascript" class="init">
	$('a.toggle-vis').on( 'click', function (e) {
		e.preventDefault();

		// Get the column API object
		var column = table.column( $(this).attr('data-column') );

		// Toggle the visibility
		column.visible( ! column.visible() );
	} );
<?php if (isset($js_content)) { echo $js_content; } ?>
</script>
<?php

$js_files = array(
	'fusioncharts.js',
	'fusioncharts.jqueryplugin.js',
);
foreach ($js_files as $js_file) {
	echo "<script type='text/javascript' src='{$GLOBALS['urlbase']}include/{$js_file}'></script>\n";
}

function startbody() {
	global $curpage,$pages,$includepath;
	echo "  </head>\n  <body>\n";
	include "{$includepath}/menu.php";
}
?>
