<?php
/*
This tool takes a number of mutators (settings per section to change) and
snippets (small segments of a theater file) and combines them to generate one
complete theater. This is integrated with another SourceMod plugin which
includes most of this functionality as an in-game menu that admins can use to
generate custom theaters on the fly. It is still very much in-progress and help
would be welcomed on this one.
*/

//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }

$title="Theater Creator";

if (isset($_REQUEST['fetch'])) {
	require_once("{$includepath}/functions.php");
} else {
	require_once("{$includepath}/header.php");
}

LoadSnippets($snippets);
$theater = ParseTheaterFile("{$theaterfile}.theater",$mod,$version,$theaterpath);
if (isset($_REQUEST['fetch'])) {
	switch ($_REQUEST['fetch']) {
		case 'snippets':
			$fetch_data = $snippets;
			break;
		case 'Download Theater':
			$data = $_REQUEST['theaterdata'];//GenerateTheater();
			$filename = $_REQUEST['filename'];
			header('Content-type: text/plain');
			header("Content-Disposition: attachment; filename=\"{$filename}\"");
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: '.strlen($data));
			echo stripslashes($data);
			exit;
			break;
	}
	if (isset($fetch_data)) {
		header('Content-Type: application/json');
		echo prettyPrint(json_encode($fetch_data));
		exit;
	}
}

/*
<script>
	$(document).ready(function(){
		$(".toggle-section").click(function(){
			var target = "#" + $(this).attr('id').replace("header-","");
			$(target).toggle();
		});
	});
	$('#tbody').on('click', 'td.details-control', function () {
		var tr = $(this).closest('tr');
		var row = table.row( tr );
 
		if ( row.child.isShown() ) {
			// This row is already open - close it
			row.child.hide();
			tr.removeClass('shown');
		}
		else {
			// Open this row
			row.child( serverDetails(row.data()) ).show();
			tr.addClass('shown');
		}
	});

</script>
*/
//var_dump("{$theaterfile}.theater",$mod,$version,$theaterpath);

startbody();
echo "<div style='text-align: center'><span class='beta'>This tool is still new and may be buggy. Please report problems and let me know what theaters you want to see added.</span></div>\n";

if ($_REQUEST['go'] == "Generate Theater") {
	$data = GenerateTheater();
	$md5 = md5($data);
	echo "<form method='POST' action='theater.php'>\n";
	echo "<div><textarea rows='20' cols='120' name='theaterdata'>{$data}</textarea></div>\n";
	echo "<div><input type='text' name='filename' value='{$md5}.theater' size='50'><input type='submit' name='fetch' value='Download Theater'></div>\n";
	echo "</form>\n";
} else {
	DisplayTheaterCreationMenu();
}
include "${includepath}/footer.php";
exit;
