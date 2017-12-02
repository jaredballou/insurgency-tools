<?php
if (!isset($lang))
	$lang = array();

// LoadLanguages - Load all the language files from the data directory
// Also loads the language codes from SourceMod (also in data directory)
function LoadLanguages($pattern='English') {
	global $langcode,$lang,$rootpath,$command,$datapath,$mod,$version;
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
	$subdir_files = GetDataFiles("resource/*/*_".strtolower($pattern).".txt");
	if (is_array($subdir_files)) {
		$langfiles = array_unique(array_merge($langfiles, $subdir_files), SORT_REGULAR);
	}
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
//var_dump($lang);
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
