#!/opt/rh/php55/root/usr/bin/php
<?php
/*
#/usr/bin/env php
VPK reader
*/
$scriptpath = realpath(dirname(__FILE__));
$rootpath=dirname(dirname($scriptpath));
include "${rootpath}/thirdparty/php-vpk-reader/VPKReader.php";
$vpk_file = isset($argv[1]) ? $argv[1] : '';
$src_path = isset($argv[2]) ? $argv[2] : '';
$dst_path = isset($argv[3]) ? $argv[3] : dirname($vpk_file)."/".basename($vpk_file,'.vpk');

//var_dump($vpk_file,$src_path,$dst_path);
//exit;

//'/home/insserver/serverfiles/insurgency/insurgency_misc_dir.vpk';
if (!file_exists($vpk_file)) {
	echo "ERROR: \"{$vpk_file}\" does not exist!\n";
	show_usage();
	exit(1);
}

$vpk = new \VPKReader\VPK($vpk_file);

$ent_tree = $vpk->vpk_entries;

Export($src_path,$dst_path);

// ExportFiles - this recursively goes through the objects, when it hits an array it recurses and when it hits an object it extracts.
function ExportFiles($data,$src_path,$dst_path) {
	global $vpk;
	if(!is_null($data) && count($data) > 0) {
		if (is_array($data)) {
			foreach($data as $name=>$children) {
				ExportFiles($children,"{$src_path}/{$name}","{$dst_path}/{$name}");
			}
		} else {
			// In case we are exporting to a directory but did not specify the file name (i.e. export maps/buhriz.txt to data/maps)
			if (is_dir($dst_path)) {
				$dst_path.="/{$src_file}";
			}
			$src_dir = dirname($src_path);
			$dst_dir = dirname($dst_path);
			$src_file = basename($src_path);
			$dst_file = basename($dst_path);
			if (!file_exists($dst_dir)) {
				echo "Creating {$dst_dir}\n";
				mkdir($dst_dir,0755,true);
			}
			$content = ($data->size) ? ($vpk->read_file($src_path, $data->size)) : '';
			$src_md5 = md5($content);
			$dst_md5 = md5_file($dst_path);
			if ($src_md5 == $dst_md5) {
				echo "Skipping {$src_path}, identical to {$dst_path}\n";
			} else {
				echo "Saving {$src_path} to {$dst_path}\n";
				file_put_contents($dst_path, $content);
			}
		}
	}

}
// Export - This points the file extractor at the right location in the VPK tree, and begins the process
function Export($src_path,$dst_path) {
	global $ent_tree;
	$temp = &$ent_tree;
	$bits = array_filter(explode("/",$src_path));
	foreach($bits as $key) {
		$temp = &$temp[$key];
	}
	ExportFiles($temp,$src_path,$dst_path);
}

// show_usage - Shows usage. Yep.
function show_usage() {
	echo "VPK Extractor by Jared Ballou <insurgency@jballou.com>\n";
	echo "https://github.com/jaredballou/insurgency-tools\n";
	echo "Built off Aphexx's VPK Reader\n";
	echo "Usage:\n";
	echo "vpk.php path/to/file.vpk [vpk/path/to/export] [where/to/export]\n";
	echo "Notes:\n";
	echo "The extractor is recursive, and it will create all directories needed for exporting\n";
	echo "If you specify a filename for path to export but a directory for the destination, the file will be exported with the source filename in that directory.\n";
	echo "If you omit the source (second parameter), it will extract all files in the VPK\n";
	echo "If you omit the destination (third parameter), it will extract to the same directory as the VPK, in a new directory with the same name as the VPK.\n";
}
