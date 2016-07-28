<?php
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
// These variables must be set before anything else

// includepath is the include directory
$includepath = realpath(dirname(__FILE__));
// rootpath is the insurgency-tools root
$rootpath=dirname($includepath);

$base_theaters = array();

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

// Create cache dir if needed
if (!file_exists($cachepath)) {
	mkdir($cachepath,0755,true);
}

if (isset($_REQUEST['language'])) {
	if (in_array($_REQUEST['language'],$lang)) {
		$language = $_REQUEST['language'];
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

//BEGIN mods
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
//exit;
// LoadLanguages - Load all the language files from the data directory
// Also loads the language codes from SourceMod (also in data directory)
function LoadLanguages($pattern='English') {
	global $langcode, $lang,$rootpath,$command,$datapath,$mod,$version;
	if (!isset($langcode))
		$langcode = array();
	if (!isset($lang))
		$lang = array();

	// Characters to strip. The files are binary, and the first few bytes break processing.
	$langfile_regex = '/[\x00-\x08\x0E-\x1F\x80-\xFF]/s';

	// Load languages into array with the key as the proper name and value as the code, ex: ['English'] => 'en'
	$data = trim(preg_replace($langfile_regex, '', file_get_contents("{$datapath}/sourcemod/configs/languages.cfg")));
	$data = parseKeyValues($data);
	foreach ($data['Languages'] as $code => $name) {
		$names = (is_array($name)) ? $name : array($name);
		foreach ($names as $name) {
			$name = strtolower($name);
			$langcode[$name] = $code;
		}
	}

	// Load all language files
	$langfiles = GetDataFiles("resource/*_".strtolower($pattern).".txt");
	foreach ($langfiles as $langfile) {
		$data = trim(preg_replace($langfile_regex, '', file_get_contents($langfile)));
		$data = parseKeyValues($data,false);
		if (!isset($data["lang"]["Tokens"])) continue;
		foreach ($data["lang"]["Tokens"] as $key => $val) {
			if ($command != 'smtrans') {
				$key = "#".strtolower($key);
			}
			$key = trim($key);
			if ($key) {
				// Sometimes NWI declares a string twice!
				if (is_array($val)) {
					$val = $val[0];
				}
				if (!isset($lang[$data["lang"]["Language"]][$key]))
					$lang[$data["lang"]["Language"]][$key] = $val;
			}
		}
	}
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

// kvwriteFile - Write KeyValues array to file
function kvwriteFile($file, $arr) {
	$contents = kvwrite($arr);
	$fh = fopen($file, 'w');
	fwrite($fh, $contents);
	fclose($fh);
}
// kvwrite - Turn an array into KeyValues
function kvwrite($arr,$tier=0,$tree=array()) {
	$str = "";
	kvwriteSegment($str, $arr,$tier,$tree);
	return $str;
}
// kvwriteSegment - Create a section of a KeyValues file from array
function kvwriteSegment(&$str, $arr, $tier = 0,$tree=array()) {
	global $ordered_fields;
	$indent = str_repeat(chr(9), $tier);
	// TODO check for a certain key to keep it in the same tier instead of going into the next?
	foreach ($arr as $key => $value) {
		if (is_array($value)) {
			$tree[$tier] = $key;
			$key = '"' . $key . '"';
			$str .= $indent . $key  . "\n" . $indent. "{\n";
			$path=implode("/",$tree);
			// If this item is an ordered array, deserialize it
			if ((matchTheaterPath($path,$ordered_fields)) && (is_numeric_array(array_keys($value)))) {
// 				echo "Ordered<br>\n";
				foreach ($value as $idx=>$item) {
					foreach ($item as $k => $v) {
						$str .= chr(9) . $indent . QuoteAndTabKeyValue($k,$v) . "\n";
					}
				}
			} else {
// 				echo "Array<br>\n";
				kvwriteSegment($str, $value, $tier+1,$tree);
			}
			$str .= $indent . "}\n";
			unset($tree[$tier]);
		} else {
// 			echo "String<br>\n";
			$str .= $indent . QuoteAndTabKeyValue($key,$value) . "\n";
		}
	}
	return $str;
}
// This function displays a spaced key value pair, quoted in aligned columns
function QuoteAndTabKeyValue($key,$val,$tabs=8) {

	$tabsize=4;
	$len = strlen($key)+2;
	$mod = ($len % $tabsize);
	$diff = floor($tabs - ($len / $tabsize))+($mod>0);
	$sep = str_repeat("\t",$diff);

	return "\"{$key}\"\t{$sep}\"{$val}\"";// {$len} {$mod} {$diff}";
	
}
/*

*/
function TypecastValue($val) {
	if (is_numeric($val)) {
		if (strpos($val,'.') !== false) {
			$val = (float)$val;
		} else {
			$val = (int)$val;
		}
	}
	return($val);
}
function filterTheaterPath ($val) {
	return ($val[0] != '?' && $val != '');
}

function matchTheaterPath($paths,$matches) {
	if (!is_array($paths)) {
		$paths=array($paths);
	}
	foreach ($paths as $path) {
		$path_parts = array_filter(explode("/",$path), 'filterTheaterPath');
		foreach ($matches as $match) {
			$match_parts = array_filter(explode("/",$match), 'filterTheaterPath');
			if (count($match_parts) != count($path_parts)) {
				continue;
			}
			foreach ($match_parts as $idx=>$part) {
				if (($part != $path_parts[$idx]) && ($part != '*')) {
					continue 2;
				}
				if (($idx+1) == count($match_parts)) {
					return $match;
				}
			}
		}
	}
	return false;
}

// parseKeyValues - Take a string of KeyValues data and parse it into an array
function parseKeyValues($KVString,$fixquotes=true,$debug=false)
{
	global $ordered_fields,$theater_conditions,$allow_duplicates_fields;
	// Escape all non-quoted values
	if ($fixquotes)
		$KVString = preg_replace('/^(\s*)([a-zA-Z]+)/m','${1}"${2}"',$KVString);
	$KVString = preg_replace('/^(\s+)/m','',$KVString);
	$KVLines = preg_split('/\n|\r\n?/', $KVString);
	$len = strlen($KVString);
	// if ($debug) $len = 2098;

	$stack = array();

	$isInQuote = false;
	$quoteKey = "";
	$quoteValue = "";
	$quoteWhat = "key";

	$lastKey = "";
	$lastPath = "";
	$lastValue = "";
	$lastLine = "";

	$keys = array();
	$comments = array();
	$commentLines=1;

	$ptr = &$stack;
	$c="";
	$line = 1;

	$parents = array(&$ptr);
	$tree = array();
	$path="";
	$sequential = '';
	$sequential_path = '';
	$conditional='';
	$conditional_path='';
	$allowdupes='';
	for ($i=0; $i<$len; $i++)
	{
		$l = $c;
		$c = $KVString[$i]; // current char
		switch ($c)
		{
			case "\"":
				$commentLines=1;
				if ($isInQuote) // so we are CLOSING key or value
				{
					// EDIT: Use quoteWhat as a qualifier rather than quoteValue in case we have a "" value
					if (strlen($quoteKey) && ($quoteWhat == "value"))
					{
						if ($sequential) {
							if (!$allowdupes) {
								// Check to make sure this value does not already exist
								if (is_array($ptr)) {
									foreach ($ptr as $item) {
										if (isset($item[$quoteKey])) {
											if ($item[$quoteKey] == $quoteValue) {
												$quoteValue = '';
											}
										}
									}
								}
							}

							if ($quoteValue) {
								$ptr[] = array($quoteKey => TypecastValue($quoteValue));
							}
						} else {
							// If this value is already set, make it an array
							if (isset($ptr[$quoteKey])) {
								// If the item is not already an array, make it one
								if (!is_array($ptr[$quoteKey])) {
									$ptr[$quoteKey] = array($ptr[$quoteKey]);
								}
								// Add this value to the end of the array
								$ptr[$quoteKey][] = TypecastValue($quoteValue);
							} else {
								// Set the value otherwise
								$ptr[$quoteKey] = TypecastValue($quoteValue);
							}
						}
						$lastLine = $line;
						$lastPath = "{$path}/${quoteKey}";
						$lastKey = $quoteKey;
						$quoteKey = "";
						$quoteValue = "";
					}
					
					if ($quoteWhat == "key")
						$quoteWhat = "value";
					else if ($quoteWhat == "value")
						$quoteWhat = "key";
				}
				$isInQuote = !$isInQuote;
				break;
			// Start new section
			case "{":
				$commentLines=1;
				if (strlen($quoteKey)) {
					if (substr($quoteKey,0,1) == '?') {
						$conditional=$quoteKey;
						$conditional_path=$path;
					} else {
						// Add key to tree
						$tree[] = $quoteKey;
						// Update path in tree
						$path = implode("/",$tree);
						if ($conditional) {
							$theater_conditions[$conditional][] = $path;
						}
						$sequential = matchTheaterPath($path,$ordered_fields);
						if ((!$sequential_path) && ($sequential)) {
							$sequential_path = $path;
//							echo "sequential {$sequential} path {$path} sequential_path {$sequential_path}\n";
						}
						if (!$allowdupes) {
//							echo "test allowdupes\n";
							$allowdupes = matchTheaterPath($path,$allow_duplicates_fields);
//							echo "allowdupes {$allowdupes}\n";
						}
						// Update parents array with current pointer in the new path location
						$parents[$path] = &$ptr;

						// If the object already exists, create an array of objects
						if ($quoteKey[0] == '?') {
							$ptr = &$ptr[][$quoteKey];
						} elseif (isset($ptr[$quoteKey])) {
							// Get all the keys, this assumes that the data will have non-numeric keys.
							$keys = implode('',array_keys($ptr[$quoteKey]));
							// So when we see non-numeric keys, we push the existing data into an array of itself before appending the next object.
							if (!is_numeric($keys)) {
								$ptr[$quoteKey] = array($ptr[$quoteKey]);
							}
							// Move the pointer to a new array under the key
							$ptr = &$ptr[$quoteKey][];
						} else {
							// Just put the object here if there is no existing object
							$ptr = &$ptr[$quoteKey];
						}
						$lastPath = "{$path}/${quoteKey}";
						$lastKey = $quoteKey;
					}
					$quoteKey = "";
					$quoteWhat = "key";
				}
				$lastLine = $line;
				break;
			// End of section
			case "}":
				$commentLines=1;
				// Move pointer back to the parent
				if ($conditional_path != $path) {
					$sequential='';
					if ($path == $allowdupes) {
						$allowdupes='';
//						echo "done allowdupes\n";
					}
					if ($sequential) {
//						echo "} sequential {$sequential} path {$path} sequential_path {$sequential_path}\n";
						if ($path == $sequential_path) {
//							echo "unset\n";
							$sequential_path='';
						}

					}
					$ptr = &$parents[$path];
					// Take last element off tree as we back out
					array_pop($tree);
					// Update path now that we have backed out
					$path = implode("/",$tree);
				} else {
					$conditional = '';
					$conditional_path = '';
				}
				$lastLine = $line;
				break;
				
			case "\t":
				break;
			case "/":
				// Comment "// " or "/*"
				if (($KVString[$i+1] == "/") || ($KVString[$i+1] == "*"))
				{
					$comment = "";
					// Get comment type
					$ctype = $KVString[$i+1];
					while($i < $len) {
						// If type is "// " stop processing at newline
						if (($ctype == '/') && ($KVString[$i+1] == "\n")) {
// 							$i+=2;
							break;
						}
						// If type is "/*" stop processing at "*/"
						if (($ctype == '*') && ($KVString[$i+1] == "*") && ($KVString[$i+2] == "/")) {
							$i+=2;
							$comment.="*/";
							break;
						}
						$comment.=$KVString[$i];
						$i++;
					}
					$comment = trim($comment);
					// Was this comment inline, or after the last item we processed?
					$where = ($lastLine == $line) ? 'inline' : 'newline';
					// If last line was also a comment, see if we can merge into a multi-line comment
					// Use the commentLines to see how far back this started
					$lcl = ($line-$commentLines);
					if (isset($comments[$lcl])) {
						$lc = $comments[$lcl];
						if ($lc['path'] == $lastPath) {
							$comments[$lcl]['line_text'].="\n{$KVLines[$line-1]}";
							$comments[$lcl]['comment'].="\n{$comment}";
							$comment='';
							$commentLines++;
						}
					}
					// If we have a comment, add it to the list
					if ($comment) {
						$comments[$line] = array('path' => $lastPath, 'where' => $where, 'line' => $line, 'line_text' => $KVLines[$line-1], 'comment' => $comment);
					}
					continue;
				}
			default:
				if ($isInQuote) {
					if ($quoteWhat == "key")
						$quoteKey .= $c;
					else
						$quoteValue .= $c;
				}
				if ($c == "\n")
					$line++;
		}
	}
	
	if ($debug) {
		echo "<hr><pre>";
		var_dump("stack: ",$stack);
	}
	return $stack;
}

// prettyPrint - Print JSON with proper indents and formatting
function prettyPrint( $json )
{
	$result = '';
	$level = 0;
	$in_quotes = false;
	$in_escape = false;
	$ends_line_level = NULL;
	$json_length = strlen( $json );

	for( $i = 0; $i < $json_length; $i++ ) {
		$char = $json[$i];
		$new_line_level = NULL;
		$post = "";
		if( $ends_line_level !== NULL ) {
			$new_line_level = $ends_line_level;
			$ends_line_level = NULL;
		}
		if ( $in_escape ) {
			$in_escape = false;
		} else if( $char === '"' ) {
			$in_quotes = !$in_quotes;
		} else if( ! $in_quotes ) {
			switch( $char ) {
				case '}':
				case ']':
					$level--;
					$ends_line_level = NULL;
					$new_line_level = $level;
					break;
				case '{':
				case '[':
					$level++;
					case ',':
					$ends_line_level = $level;
					break;

				case ':':
					$post = " ";
					break;

				case " ":
				case "\t":
				case "\n":
				case "\r":
					$char = "";
					$ends_line_level = $new_line_level;
					$new_line_level = NULL;
					break;
			}
		} else if ( $char === '\\' ) {
			$in_escape = true;
		}
		if( $new_line_level !== NULL ) {
			$result .= "\n".str_repeat( "\t", $new_line_level );
		}
		$result .= $char.$post;
	}
	return $result;
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

// stats functions
/*
multi_diff
Compare two arrays recursively, return an array of differences
Will be an array of differences (key structure identical to source arrays).
Each element is an array that has two values, key is the nameX variable and value is the value from that source array
Elements that are identical in both arrays are omitted
Example:
$array1 = array('object' => array('name' => 'object1', 'size' => 30, 'owner' => 'nobody'));
$array2 = array('object' => array('name' => 'object2', 'size' => 40, 'owner' => 'nobody'));
$result = multi_diff('array1',$array1,'array2',$array2);
$result will be:
array(
	'object' => array(
		'name' => array('array1' => 'object1','array2' => 'object2'),
		'size' => array('array1' => 30,'array2' => 40)
	)
);
*/
function multi_diff($name1,$arr1,$name2,$arr2) {
	$result = array();
	$merged = $arr1+$arr2;// array_merge($arr1,$arr2);
	foreach ($merged as $k=>$v){
		if(!isset($arr2[$k])) {
			$result[$k] = array($name1 => $arr1[$k], $name2 => NULL);
		} else if(!isset($arr1[$k])) {
			$result[$k] = array($name1 => NULL,$name2 => $arr2[$k]);
		} else {
			if(is_array($arr1[$k]) && is_array($arr2[$k])){
				$diff = multi_diff($name1, $arr1[$k], $name2, $arr2[$k]);
				if(!empty($diff)) {
					$result[$k] = $diff;
				}
			} else if ($arr1[$k] !== $arr2[$k]) {
				$result[$k] = array($name1 => $arr1[$k],$name2 => $arr2[$k]);
			}
		}
	}
	return $result;
}
/* ParseTheaterFile
Takes a KeyValues file and parses it. If #base directives are included, pull those and merge contents on top
*/
function ParseTheaterFile($filename,$mod='',$version='',$path='',&$base_theaters=array(),$depth=0) {
//var_dump("ParseTheaterFile",$filename,$mod,$version,$path,$base_theaters,$depth);
	global
		$custom_theater_paths,
		$newest_version,
		$latest_version,
		$theaterpath,
		$datapath,
		$steam_ver,
		$mods;
	if ($version == '')
		$version = $newest_version;
	$basename = basename($filename);


	if (file_exists($filename)) {
		$filepath = $filename;
	} else {
		if (file_exists("{$path}/{$filename}")) {
			$filepath = "{$path}/{$filename}";
		} else {
			$filepath = GetDataFile("scripts/theaters/{$basename}",$mod,$version);
		}
	}
	$base_theaters[$basename] = md5($filepath);

	$sniproot = "${GLOBALS['rootpath']}/theaters/snippets/";
	$snipfile = str_replace($sniproot,"",$filepath);
	if ($snipfile != $filepath) {
		$cachefile = "theaters/snippets/".str_replace("/","_","{$snipfile}");
//var_dump($sniproot,$snipfile,$snippath,$cachefile);
	} else {
		$cachefile = "theaters/{$mod}/{$version}/{$basename}";
	}
	// Attempt to load file from cache
	$cachedata = GetCacheFile($cachefile);
	if (isset($cachedata['base'])) {
		// Check all files for MD5
		foreach ($cachedata['base'] as $file => $md5) {
			if ($file == $basename) {
				$filemd5 = $base_theaters[$basename];
			} else {
				$bfpath = GetDataFile("scripts/theaters/{$file}",$mod,$version);
				$filemd5 = md5($bfpath);
			}
			// If a component file is modified, do not use the cache.
			if ($filemd5 != $md5) {
//var_dump("md5 no match for {$file} - {$filemd5} != {$md5}");
				$cachedata['theater'] = '';
				break;
			}
		}
	}
	if (!is_array($cachedata['theater'])) {
//var_dump("processing {$filename}");
		// Load raw theater file
		$data = file_get_contents($filepath);

		// Parse KeyValues data
		$thisfile = parseKeyValues($data);
//var_dump($thisfile);
		// Get theater array
		// If the theater sources another theater, process them in order using a merge which blends sub-array values from bottom to top, recursively replacing.
		// This appears to be the way the game processes these files it appears.
		if (isset($thisfile["#base"])) {
			$basedata = array();
			// Create an array of base files
			if (is_array($thisfile["#base"])) {
				$bases = $thisfile["#base"];
			} else {
				$bases = array($thisfile["#base"]);
			}
			// Merge all base files into basedata array
			foreach ($bases as $base) {
//var_dump("base {$base}");
				$base_file = GetDataURL("scripts/theaters/{$base}",$mod,$version);
				$cachedata['base'][$base] = md5($base_file);
				if (in_array($base,array_keys($base_theaters)) === true)
					continue;
				$base_theaters[$base] = $cachedata['base'][$base];
//var_dump("processing base {$base}");
				$basedata = array_merge_recursive(ParseTheaterFile($base,$mod,$version,$path,$base_theaters,$depth+1),$basedata);
			}
			// Merge this theater on top of combined base
			$cachedata['theater'] = theater_array_replace_recursive($basedata,$thisfile['theater']);
		} else {
			$cachedata['theater'] = $thisfile["theater"];
		}
/*
		// Include parts that might be conditional in their parents, basically put everything in flat arrays
		// This isn't congruent with how the game handles them, I believe this ougght to be a selector in the UI that can handle this better
		foreach ($cachedata['theater'] as $sec => $data) {
			foreach ($data as $key => $val) {
				if (($key[0] == '?') && (is_array($val))) {
					unset($cachedata['theater'][$sec][$key]);
					$cachedata['theater'][$sec] = $val;// theater_array_replace_recursive($cachedata['theater'][$sec],$val);
				}
			}
		}
*/
		// Save cache data
//var_dump($cachedata);
		PutCacheFile($cachefile,$cachedata);
	}
	// Send back theater object
	return $cachedata['theater'];
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
	$pathparts = array_filter(array_merge(explode("/",preg_replace('/\.(vmt|vtf|png)$/','',"{$path}/{$name}"))));
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
