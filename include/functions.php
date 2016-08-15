<?php
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
// These variables must be set before anything else

// includepath is the include directory
$includepath = realpath(dirname(__FILE__));
// rootpath is the insurgency-tools root
$rootpath=dirname($includepath);

$command = isset($_REQUEST['command']) ? $_REQUEST['command'] : "";
// Pull in configuration settings
include "{$includepath}/config.php";

/*
	BEGIN COMMON EXECUTION CODE
	This section is run by every script, so it shouldn't do too much.
*/

// Load custom library paths for include
//parseLibPath();

use phpFastCache\CacheManager;
//require_once("phpfastcache/phpfastcache.php");
//phpFastCache::setup



CacheManager::setup(array(
	"path"		=> $cachepath,
	"allow_search"	=> true,
));
CacheManager::CachingMethod("phpfastcache");


$cache = CacheManager::Files();

//new phpFastCache("files");
//$cache->driver_set('path',$cachepath);
//$cache->driver_set('securitykey','cache.folder');

// Create cache dir if needed
if (!file_exists($cachepath)) {
	mkdir($cachepath,0755,true);
}

// Connect to HLStatsX database if requested
if (isset($use_hlstatsx_db)) {
	// If HLStatsX config exists, try that first
	if (file_exists($hlstatsx_config)) {
		require $hlstatsx_config;
		mysql_connect(DB_HOST,DB_USER,DB_PASS);
		$mysql_connection = mysql_select_db(DB_NAME);
	}
	// If no database connected (either config missing or failed to connect) use fallback
	if (@!$mysql_connection) {
		mysql_connect($mysql_server,$mysql_username,$mysql_password);
		$mysql_connection = mysql_select_db($mysql_database);
	}
}

// Get the command passed to the script
$command = @$_REQUEST['command'];

/**
 * Return a relative path to a file or directory using base directory. 
 * When you set $base to /website and $path to /website/store/library.php
 * this function will return /store/library.php
 * 
 * Remember: All paths have to start from "/" or "\" this is not Windows compatible.
 * 
 * @param   String   $base   A base path used to construct relative path. For example /website
 * @param   String   $path   A full path to file or directory used to construct relative path. For example /website/store/library.php
 * 
 * @return  String
 */
function getRelativePath($base, $path) {
	// Detect directory separator
	$separator = substr($base, 0, 1);
	$base = array_slice(explode($separator, rtrim($base,$separator)),1);
	$path = array_slice(explode($separator, rtrim($path,$separator)),1);

	return $separator.implode($separator, array_slice($path, count($base)));
}

// BEGIN range
// Units of measurement
$range_units = array(
	'U' => 'Game Units',
	'M' => 'Meters',
	'FT' => 'Feet',
	'YD' => 'Yards',
	'IN' => 'Inches'
);
// Set range unit, override if valid unit is requested.
$range_unit = 'M';
if (isset($_REQUEST['range_unit'])) {
	if (array_key_exists($_REQUEST['range_unit'],$range_units)) {
		$range_unit = $_REQUEST['range_unit'];
	}
}

// Set range
$range = 10;

if (isset($_REQUEST['range'])) {
	$_REQUEST['range'] = dist($_REQUEST['range'],$range_unit,'IN',0);
	if (($_REQUEST['range'] >= 0) && ($_REQUEST['range'] <= 20000)) {
		$range = $_REQUEST['range'];
	}
}

// END range


// Load maplist and gametypes
//$mldata = json_decode(file_get_contents("{$datapath}/thirdparty/maplist.json"),true);
$gtlist = json_decode(file_get_contents("{$datapath}/thirdparty/gamemodes.json"),true);
$gametypelist = array();
foreach ($gtlist as $type=>$modes) {
	foreach ($modes as $mode) {
		$gametypelist[$mode] = "{$type}: {$mode}";
	}
}
// explode(":",implode(array_values($gtlist['pvp'] + $gtlist['coop']),":"));



/*
================================================================================
===                                                                          ===
===                                                                          ===
===                             BEGIN FUNCTIONS                              ===
===                                                                          ===
===                                                                          ===
================================================================================
*/
// TODO: Break these out into separate classes and better define them.

function GetDataFiles($filename,$mod=null,$version=null,$which=-1) {
	global $langcode, $lang,$datapath,$latest_version;
	if (is_null($mod)) $mod = $GLOBALS['mod'];
	if (is_null($version)) $version = $GLOBALS['version'];
	$paths = array(
		"{$datapath}/mods/{$mod}/{$version}",
		"{$datapath}/mods/{$mod}/*",
		"{$datapath}/mods/insurgency/{$version}",
		"{$datapath}/mods/insurgency/{$latest_version}",
		"{$datapath}/mods/insurgency/*",
		$datapath
	);
	$files = array();
	foreach ($paths as $path) {
		$files = array_merge($files,glob("{$path}/{$filename}"));
	}
	$files = array_unique($files);

	// -2 is special case that defaults to the value given. Useful for checking if a file exists, and using the base path as a default if none are found.
	if ($which == -2) {
		if (!count($files)) {
			return "{$datapath}/{$filename}";
		}
		$which = 0;
	}
	if (($which > -1) && (isset($files[$which]))) {
		return $files[$which];
	}
	if (count($files)) {
		return $files;
	}
}
function GetDataFile($filename,$mod=null,$version=null,$which=0) {
	return GetDataFiles($filename,$mod,$version,$which);
}

function GetURL($file) {
	return str_replace($GLOBALS['datapath'],"{$GLOBALS['urlbase']}data",$file);
}

function GetDataURLs($filename,$mod=null,$version=null,$which=-1) {
	$files = GetDataFiles($filename,$mod,$version,$which);
	if (is_array($files)) {
		if ($which < 0) {
			foreach ($files as $idx => $file) {
				$files[$idx] = GetURL($file);
			}
			return $files;
		} else {
			return GetURL($files[$which]);
		}
	} else {
		return GetURL($files);
	}
}
function GetDataURL($filename,$mod=null,$version=null) {
	return GetDataURLs($filename,$mod,$version,0);
}


// rglob - recursively locate all files in a directory according to a pattern
function rglob($pattern, $getfiles=1,$getdirs=0,$flags=0) {
	$dirname = dirname($pattern);
	$basename = basename($pattern);
	$glob = glob($pattern, $flags);
	$files = array();
	$dirlist = array();
	foreach ($glob as $path) {
		if (is_file($path) && (!$getfiles)) {
			continue;
		}
		if (is_dir($path)) {
			$dirlist[] = $path;
			if (!$getdirs) {
				continue;
			}
		}
		$files[] = $path;
	}
	foreach (glob("{$dirname}/*", GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
		$dirfiles = rglob($dir.'/'.$basename, $getfiles,$getdirs,$flags);
		$files = array_merge($files, $dirfiles);
	}
	return $files;
}

// delTree - recursively DELETE AN ENTIRE DIRECTORY STRUCTURE!!!!
function delTree($dir='') {
	if (strlen($dir) < 2)
		return false;
	$files = array_diff(scandir($dir), array('.','..'));
	foreach ($files as $file) {
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
	}
	return rmdir($dir);
}

// is_numeric_array - test if all values in an array are numeric
function is_numeric_array($array) {
	foreach ($array as $key => $value) {
		if (!is_numeric($value)) return false;
	}
	return true;
}

// formatBytes - Display human-friendly file sizes
function formatBytes($bytes, $precision = 2) { 
	$units = array('B', 'KB', 'MB', 'GB', 'TB'); 

	$bytes = max($bytes, 0); 
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
	$pow = min($pow, count($units) - 1); 

	// Uncomment one of the following alternatives
	$bytes /= pow(1024, $pow);
	// $bytes /= (1 << (10 * $pow)); 

	return round($bytes, $precision) . ' ' . $units[$pow];
}

function FormatCacheFileName($filename,$format='json') {
	$path = "{$GLOBALS['cachepath']}/{$filename}";
	$path = dirname($path)."/".basename($path,".{$format}").".{$format}";
	return $path;
}

function PutCacheFile($filename,$data,$format='json') {
	global $cache;
	$cache->set($filename,$data,0);
/*
	$path = FormatCacheFileName($filename,$format);
	switch ($format) {
		case 'json':
			if (is_array($data) || is_object($data)) {
				$data = prettyPrint(json_encode($data));
			}
			break;
	}
	if (!file_exists(dirname($path))) {
		mkdir(dirname($path),0755,true);
	}
	file_put_contents($path,$data);
*/
}

function GetCacheFile($filename,$format='json') {
	global $cache;
	$data = $cache->get($filename);
	return $data;
	if (is_null($data)) {
	}
	$path = FormatCacheFileName($filename,$format);
	if (!file_exists($path)) {
		return;
	}
	switch ($format) {
		case 'json':
			$data = json_decode(file_get_contents($path),true);
			break;
	}
	if (isset($data)) {
		return $data;
	}
}

/*
GetMaterial
Get the material path
*/
function GetMaterial($name,$type='img',$path='') {
	// This is shit path munging, fix it
	$pathparts = array_values(array_filter(array_merge(explode("/",preg_replace('/\.(vmt|vtf|png)$/','',"{$path}/{$name}")))));
	if ($pathparts[0] != 'materials') {
		array_unshift($pathparts,'materials');
	}
	$filepath = implode("/",$pathparts);
	// If we have a PNG, just send it
	if (file_exists(GetDataFile("{$filepath}.png"))) {
		return GetDataURL("{$filepath}.png");
	}

	// Try to use VMT to get image
	if (file_exists(GetDataFile("{$filepath}.vmt"))) {
		$vmt = file_get_contents(GetDataFile("{$filepath}.vmt"));
		preg_match_all('/basetexture[" ]+([^"\s]*)/',$vmt,$matches);
		return GetMaterial($matches[1][0],$type);
/*
		if (file_exists(GetDataFile("{$matches[1][0]}.png"))) {
			return GetDataURL("{$$matches[1][0]}.png");
		}
*/
	}
	// No hope
	return '';
}

/* getvgui
Display the icon for an object
*/
function getvgui($name, $type='img', $path='vgui/inventory', $width=256, $height=128) {
	$img = GetMaterial($name,$type,$path);
	$unit_height = (is_numeric($height)) ? "px" : "";
	$unit_width = (is_numeric($width)) ? "px" : "";

	$css = array();

	$top_offset = (substr($name, 0, 9 ) == 'template_') ? 0 : 16;
	$css['background-size'] = "{$width}{$unit_width} {$height}{$unit_height}";
	$css['background-position'] = 'top {$top_offset}px center';
	$css['min-height'] = ($height + $top_offset).$unit_height;
	$css['height'] = ($height + $top_offset).$unit_height;
	$css['width'] = $width.$unit_width;

	if ($img) {
		if ($type == 'img')
			return "<img src='{$img}' alt='{$name}' height='{$height}' width='{$width}'/><br>";
		if ($type == 'bare')
			return $img;
		if ($type == 'css') {
			$css['background-image'] = "url('{$img}')";
			$css_str = generate_css_properties($css);
			return " style=\"{$css_str}\" class='vgui'";
		}
	}
}

function generate_css_properties($rules, $indent = 0) {
  $css = '';
  $prefix = str_repeat('  ', $indent);
  foreach ($rules as $key => $value) {
    if (is_array($value)) {
      $selector = $key;
      $properties = $value;

      $css .= $prefix . "$selector {\n";
      $css .= $prefix .grasmash_generate_css_properties($properties, $indent + 1);
      $css .= $prefix . "}\n";
    }
    else {
      $property = $key;
      $css .= $prefix . "$property: $value;\n";
    }
  }
  return $css;
}

// parseLibPath - Load custom library paths, this should only get called after config is loaded but before any other includes are called
function parseLibPath() {
	global $custom_libpaths;
	if (!is_array($custom_libpaths)) {
		$custom_libpaths = array($custom_libpaths);
	}
	foreach ($custom_libpaths as $path) {
		addLibPath($path);
	}
}

// addLibPath - Add path to include path, this is how we should add new libraries
function addLibPath($path) {	
	global $libpaths;
	if (!in_array($path,$libpaths)) {
		$libpaths[] = $path;
		set_include_path(implode(PATH_SEPARATOR,$libpaths));
	}
}

function getSteamVersion($appid=0) {
	if (!$appid) $appid = $GLOBALS['appid'];
	$url = "http://api.steampowered.com/ISteamApps/UpToDateCheck/v0001?appid={$appid}&version=0";
	$raw = json_decode(file_get_contents($url),true);
	return implode('.',str_split($raw['response']['required_version']));
}

// Is this array associative?
function isAssoc($arr)
{
	return array_keys($arr) !== range(0, count($arr) - 1);
}

// Return the string representing data type
function vartype($data) {
	$words = explode(" ",$data);
	if (is_array($data)) {
		return "array";
	}
	if (count($words) == 3) {
		foreach ($words as $idx=>$word) {
			if (is_numeric($word)){
				unset($words[$idx]);;
			}
		}
		if (!count($words))
			return "vector";
	}
	if (is_numeric($data)) {
		if (strpos($data,'.') !== false)
			return "float";
		return "integer";
	}
	if (is_string($data)) {
		if (substr($data,0,1) == "#")
			return "translate";
		return "string";
	}
	return "UNKNOWN";
}

function var_dump_ret($mixed = null) {
	ob_start();
	var_dump($mixed);
	$content = ob_get_contents();
	ob_end_clean();
	return $content;
}

function GetSmarty() {
	require_once("{$rootpath}/thirdparty/smarty/libs/Autoloader.php");
	Smarty_Autoloader::register();
	$smarty = new Smarty;
	return $smarty;
}
/*
// Handle keywords
$keywords = (isset ($_REQUEST['keywords'])) ? explode (" ", $_REQUEST['keywords']) : array();
$smarty->assign('keywords_active',$keywords);

//Load resume data
$smarty->assign('format', $_REQUEST['format']);
$smarty->assign('data', $data);

// Display content
$smarty->display('templates/resume.tpl');
*/

// Include classes
$files = glob("{$includepath}/classes/*");
foreach ($files as $file) {
	require_once($file);
}


// Post Class include global code - all of this is shit and needs to be fixed!

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

if (isset($_REQUEST['language'])) {
	if (in_array($_REQUEST['language'],$lang)) {
		$language = $_REQUEST['language'];
	}
}
// Loading languages here because we are only loading the core language at this time
LoadLanguages($language);
$gamemodes = array();
$raw = preg_grep('/^[\#]*game_gm_(.*)$/', array_keys($lang[$language]));
foreach ($raw as $key) {
	$bits = explode("_",$key,3);
	$gm = $bits[2];
	$gamemodes[$gm]['name'] = @$lang[$language][$key];
	$gamemodes[$gm]['desc'] = @$lang[$language]["#game_description_{$gm}"];
	$gamemodes[$gm]['desc_short'] = @$lang[$language]["#game_description_short_{$gm}"];
}

// Theater
$snippets = array();
$sections = array();
$snippet_path = "{$rootpath}/theaters/snippets";
$base_theaters = array();

// BEGIN theater
// Populate $theaters array with all the theater files in the selected version
$files = glob("{$datapath}/mods/{$mod}/{$version}/scripts/theaters/*.theater");
foreach ($files as $file) {
	if ((substr(basename($file),0,5) == "base_") || (substr(basename($file),-5,5) == "_base")) {
		continue;
	}
	$theaters[] = basename($file,".theater");
}
// Add all custom theaters to the list, these do NOT depend on version, they will always be added
foreach ($custom_theater_paths as $name => $path) {
	if (file_exists($path)) {
		$ctfiles = glob("{$path}/*.theater");
		foreach ($ctfiles as $ctfile) {
			$label = basename($ctfile,".theater");
			$theaters[] = "{$name} {$label}";
		}
	}
}

// Default theater file to load if nothing is selected
$theaterfile = "default";

// If a theater is specified, find out if it's custom or stock, and set the path accordingly
if (isset($_REQUEST['theater'])) {
	if (strpos($_REQUEST['theater']," ")) {
		$bits = explode(" ",$_REQUEST['theater'],2);
		if (in_array($bits[0],array_keys($custom_theater_paths))) {
			$theaterpath = $custom_theater_paths[$bits[0]];
			$theaterfile = $bits[1];
		}
	} elseif (in_array($_REQUEST['theater'],$theaters)) {
		$theaterfile = $_REQUEST['theater'];
	}
}
// Comparison stuff
$theaterfile_compare = $theaterfile;
$theaterpath_compare = $theaterpath;
if (isset($_REQUEST['theater_compare'])) {
	if (strpos($_REQUEST['theater_compare']," ")) {
		$bits = explode(" ",$_REQUEST['theater_compare'],2);
		if (in_array($bits[0],array_keys($custom_theater_paths))) {
			$theaterpath_compare = $custom_theater_paths[$bits[0]];
			$theaterfile_compare = $bits[1];
		}
	} elseif (in_array($_REQUEST['theater_compare'],$theaters)) {
		$theaterfile_compare = $_REQUEST['theater_compare'];
	}
}
// END theater
