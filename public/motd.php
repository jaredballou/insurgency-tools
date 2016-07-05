<?php
/*
MOTD
*/
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
require_once("{$includepath}/classes/Spyc.php");
//functions.php");
function callbackhandler($matches) {
	return strtoupper(ltrim($matches[0], "_"));
}

$file = "data/mods/verynotfun/notes.yaml";
$yaml = Spyc::YAMLLoad($file);

$header = "<html>\n<head>\n<title>Very Not Fun Servers</title>\n</head>\n<body>\n<h1>Very Not Fun Servers</h1>\n";
$footer = "</body>\n</html>\n";

echo $header;
foreach ($yaml as $section => $items) {
	$title = ucwords(str_replace("_"," ",$section));
//preg_replace_callback("/_[a-z]?/","callbackhandler",$section);
	echo "<h2>{$title}</h2>\n<ul>\n";
	foreach ($items as $item) {
		echo "<li>{$item}</li>\n";
	}
	echo "</ul>\n";
}
echo $footer;
exit;
?>
