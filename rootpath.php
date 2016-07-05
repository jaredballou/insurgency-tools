<?php
/*
  * rootpath.php
  * (c) 2016, Jared Ballou <insurgency@jballou.com>
  *
  * This is the file that gets included by all PHP scripts to define the paths
  * used by many of the tools in a standardized way. The code which is included to load it is:
  *
*/
// rootpath is the insurgency-tools root
$rootpath = realpath(dirname(__FILE__));
require_once("{$rootpath}/vendor/autoload.php");

// includepath is the include directory
$includepath = "${rootpath}/include";

// publicpath is the publicly viewable path
$publicpath="${rootpath}/public";

// datapath is where the insurgency-data repo is checked out
$datapath="${publicpath}/data";

// Library include paths
$libpaths = explode(PATH_SEPARATOR,get_include_path());

// Custom libraries to load
$custom_libpaths = array(
        "{$rootpath}/thirdparty/php-binary",
        "{$rootpath}/thirdparty/php-binary/src",
        "{$rootpath}/thirdparty/php-binary/src/Exception",
        "{$rootpath}/thirdparty/php-binary/src/Field",
        "{$rootpath}/thirdparty/php-binary/src/Stream",
        "{$rootpath}/thirdparty/php-binary/src/Validator",
        "{$rootpath}/thirdparty/php-binary",
        "{$rootpath}/thirdparty/steam-condenser-php",
        "{$rootpath}/thirdparty/steam-condenser-php/vendor",
        "{$rootpath}/thirdparty/steam-condenser-php/lib",
        "{$rootpath}/thirdparty/steam-condenser-php/lib/SteamCondenser"
);

// Base

//theater path
$theaterpath='';

// Custom theater paths - include insurgency-theaters checkout
$custom_theater_paths = array('Custom' => "${rootpath}/theaters");

// Cache directory to stash temporary files. This should be inaccessible via your Web server!
$cachepath = "{$rootpath}/cache";

