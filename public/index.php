<?php
/*
This is the landing page. It reads my GitHub account via the public API, gets
and then displays the content in a single page. It uses caching to keep the
request count low and not spam GitHub.
*/
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
$title = "Jared Ballou's Insurgency Tools";
require_once("{$includepath}/header.php");
//User to pull the data for
$githubuser = 'jaredballou';

startbody();
$content_file = "{$includepath}/content.yaml";
$content = Spyc::YAMLLoad($content_file);
echo "<div class='content-wrapper' style='margin: 10px;'>\n";
echo "<div class='content-heading'><h1>{$title}</h1>\n{$content['heading']}</div>\n";
foreach ($content['github']['repos'] as $name => $repo) {
	echo "<div class='content-section'>\n";
	echo "<div class='content-section-heading'><a name='{$name}' href='https://github.com/{$content['github']['user']}/{$name}' target='_blank'><h2>{$repo['title']}</h2></a></div>\n";
	echo "<div class='content-section-content'>{$repo['content']}</div>\n";
	echo "</div>\n";
}
echo "</div>\n";
require_once("{$includepath}/footer.php");
exit;


/*
exit;
	$data = GetGithubURL("users/{$githubuser}/repos");
	$list = json_decode($data,true);
	foreach ($list as $repo) {
		echo "{$repo['name']}\n";
	}
//var_dump($data);
exit;
	$data = array();
	foreach ($list as $repo) {
		if (startsWith($repo['name'],'insurgency-'))
			$data[] = GetReadme($repo['name']);
	}
	PutCacheFile($cache_file,implode("\r\n",$data));
}
echo GetCacheFile($cache_file);
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
//$_SERVER['HTTP_USER_AGENT']

	$context = stream_context_create($opts);
	$url = "https://api.github.com/{$url}";
	return (file_get_contents($url, false, $context));
}
function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
*/

?>
