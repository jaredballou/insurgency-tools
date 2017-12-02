<?php if (!isset($bare) || !$bare) { ?>
<div class="footbar"><a href="https://validator.w3.org/check?uri=referer"><img src="<?php echo $GLOBALS['urlbase']; ?>images/html5.png" alt="Valid HTML 5.0" height="31" width="88" /></a><a href="https://jigsaw.w3.org/css-validator/check/referer"><img style="border:0;width:88px;height:31px" src="https://jigsaw.w3.org/css-validator/images/vcss" alt="Valid CSS!" /></a></div>
<?php } ?>
</body>
</html>
<?php
if (isset($use_ob)) {
	$html = ob_get_clean();
	$config = array(
		'clean'			=> TRUE,
		'indent'		=> TRUE,
		'output-html'		=> TRUE,
		'wrap'			=> 0,
		'new-inline-tags'	=> 'li, option',
		'indent-spaces'		=> 4,
		'show-warnings'		=> TRUE,
		'indent-cdata'		=> TRUE,
	);
	$tidy = new tidy;
	$tidy->parseString($html, $config, 'utf8');
	$tidy->cleanRepair();
	echo $tidy;
	$clean = $tidy->repairString($html);
	echo $clean;
//print_r($tidy->getConfig());
} else {
	if (isset($html)) {
		echo $html;
	}
}
?>
