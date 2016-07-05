<?php
require_once "include/functions.php";
require_once "kvreader2.php";

$reader = new KVReader();
$files = glob("data/appinfo/*.txt");
foreach ($files as $file) {
	$fn = basename($file,".txt");
	$data[$fn] = $reader->readFile($file);
//parseKeyValues($file);
}
var_dump($data);
