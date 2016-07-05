<?php
/*
This is the landing page. It reads my GitHub account via the public API, gets
and then displays the content in a single page. It uses caching to keep the
request count low and not spam GitHub.
*/
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
require_once("{$includepath}/functions.php");
//User to pull the data for
$githubuser = 'jaredballou';


$cache_file = 'website/content.html';
$payload_file = 'website/payload.json';
/*
if (isset($_POST['payload'])) {
	file_put_contents($payload_file,$_POST['payload']);
//	$payload = json_decode($_POST['payload']);
//	$branch = substr($payload->ref, strrpos($payload->ref, '/') + 1);
//	$repository = $payload->repository->name;
}
*/
//var_dump($_POST);
function GetGithibReadmes($githubuser) {
	$data = GetGithubURL("users/{$githubuser}/repos");
	$list = json_decode($data,true);
	$data = array();
	foreach ($list as $repo)
	{
		if (startsWith($repo['name'],'insurgency-'))
			$data[] = GetReadme($repo['name']);
	}
	PutCacheFile($cache_file,implode("\r\n",$data));
//"theaters/{$mod}/{$version}/{$filename}.json",prettyPrint(json_encode($cache)));

//	file_put_contents($cache_file
}

function GetReadme($repo)
{
	$url = "repos/{$GLOBALS['githubuser']}/{$repo}";
	$data = GetGithubURL("{$url}/readme",'application/vnd.github.v3.html+json');
	$data = preg_replace('/href=[\'"]([^#][^\'":]*)[\'"]/',"href='https://github.com/{$GLOBALS['githubuser']}/{$repo}/blob/master/\\1'",$data);
	$data = preg_replace('/<\/a>(.*)<\/h1>/',"</a><a href='https://github.com/{$GLOBALS['githubuser']}/{$repo}'>\\1</a></h1>",$data);
	return $data;
}
function GetGithubURL($url,$accept='application/vnd.github.v3+json')
{
	$header = "Accept: {$accept}\r\n";
	if (isset($GLOBALS['apiauth']))
		$header.="Authorization: Basic {$GLOBALS['apiauth']}\r\n"; 
	$opts = array('http' => array('method' => 'GET', 'user_agent'=> 'jballou-website', 'header' => $header));
	$context = stream_context_create($opts);
	$url = "https://api.github.com/{$url}";
	return (file_get_contents($url, false, $context));
}
function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
?>
