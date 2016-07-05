<?php
//================================================================================
// Server Update Checker
// (c) 2016 Jared Ballou <insurgency@jballou.com>
// 
// This tool attempts to take an array of servers, find their game version, and
// use the Steam API to verify that it is the latest version. This is just a POC
// for me to poke at the API for now. The eventual idea is to have it handle
// automatic updates of game servers when they are out of date.
//================================================================================

//$appid = 237410;

//error_reporting(E_ALL);
error_reporting(E_ERROR);
//error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));

include "include/functions.php";
$steamdata = "${cachedir}/steam";
// In a pinch, comment the above and uncomment the lines below and just put the
// variables here if you don't want to use the rest of my library.
/*
$servers = array(
	'insurgency.7thcavalry.us:27015' => array(
	),
	'insurgency.7thcavalry.us:27016' => array(
	),
	'insurgency.7thcavalry.us:27017' => array(
	),
	'insurgency.7thcavalry.us:27018' => array(
	),
	'8.2.122.150:27015' => array(
	),
	'ins001.jballou.com:27015' => array(
	),
	'ins2.jballou.com:27015' => array(
	),
);
$apikey = 'YOUR_STEAM_API_KEY';
$appid = 222880;
*/

include "${rootpath}/thirdparty/steam-condenser-php/vendor/autoload.php";



// Get information about game before checking servers

// Get required version
$url = "http://api.steampowered.com/ISteamApps/UpToDateCheck/v0001?appid={$appid}&version=0";
$raw = json_decode(file_get_contents($url),true);
$required_version = $raw['response']['required_version'];

// Paths for where to store the data
$version_path = "{$steamdata}/{$appid}/{$required_version}";
$version_schema = "{$version_path}/schema.json";

//Check if this app is up to date
if (!file_exists($version_path)) {
	mkdir($version_path,0755,true);
}
// Save schema if not present
if (!file_exists($version_schema)) {
	$schema = file_get_contents("http://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?key={$apikey}&appid={$appid}");
	file_put_contents($version_schema,$schema);
}

foreach ($servers as $address => $data) {
	echo "Checking {$address}...\n";

	// Load values from array if set
	$port = (isset($data['port'])) ? $data['port'] : '';
	$rcon_password = (isset($data['rcon_password'])) ? $data['rcon_password'] : '';

	// Connect to game server
	$server = new \SteamCondenser\Servers\SourceServer($address,$port);
	$server->initialize();

	// Collect data from Steam Condenser
	$getPing = $server->getPing();
	$getPlayers = $server->getPlayers($rcon_password);
	$getRules = $server->getRules();
	$getServerInfo = $server->getServerInfo();

	// Parse version information into different formats for different tools
	$version = $getServerInfo["gameVersion"];
	$version_num = preg_replace("/[^0-9]/", "",$version);

	// Some variables to use in the display
	$playercount = $getServerInfo["numberOfPlayers"];
	$maxplayers = $getServerInfo["maxPlayers"];

	// Check for version number
	$version_check = ($version_num == $required_version) ? "OK" : "FAIL - Should be {$required_version}";

	// Display the status of this server
	echo "{$address}: \"{$getServerInfo["serverName"]}\": ({$playercount}/{$maxplayers}): {$version}: {$version_check}\n";
	//var_dump($getServerInfo);
	//$getRules,$getPing,$getPlayers);
}

exit;
