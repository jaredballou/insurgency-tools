<nav class="navbar navbar-inverse" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="index.php">Insurgency Tools</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
<?php
	foreach ($pages as $url => $page) {
		$act = (basename($url) == $curpage) ? ' class="active"' : '';
		$pre = (preg_match('%^((https?://)|(www\.))([a-z0-9-].?)+(:[0-9]+)?(/.*)?%i',$url)) ? '' : $GLOBALS['urlbase'];
		echo "            <li{$act}><a href='{$pre}{$url}'>{$page}</a></li>\n";
	}
?>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </nav>
<?php
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == 'ins.jballou.com') {
	echo "<div style='text-align: center; font-weight: bold;'>Development Site - These tools are being actively worked on and tested, latest stable versions at <a href='http://jballou.com/insurgency{$_SERVER['SCRIPT_URL']}'>http://jballou.com/insurgency</a></div>\n";
}
?>
