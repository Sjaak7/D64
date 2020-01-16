<?php

declare(strict_types=1);

ini_set('display_errors', "1");
ini_set('display_startup_errors', "1");
error_reporting(E_ALL);

define('URL','https://d64.nl');

include('./includes/d64.php');

$d64 = new d64();
$d64->header = new d64header($d64);
$d64->footer = new d64footer($d64);

$d64->header->set_description("Test website van Gerda");

// Check if we are on the frontpage without any jokes like querystrings.. holla hop
if(!isset($d64->path[0]) && empty($_SERVER['QUERY_STRING'])){

	$bitpay = json_decode(file_get_contents(ROOTPATH.'/cache/btc.json'),true);
	if(is_array($bitpay))
		$bitprice = $bitpay[3]['rate'];
	else $bitprice = 'NaN';

	$contentClass = 'w3-threequarter ';
	$leftPanel =
		'<div class="w3-quarter w3-green w3-padding">'.
			'<div>'.
				'<h3>Crypto</h3>'.
				'Bitcoin: &euro; <span id="btc_euro">'.$bitprice.'</span>'.
			'</div>'.
			'<h3>Live</h3>'.
			// ytp = youtube placeholder
			'<div id="ytp">'.
				// ytvi youtube image..
				'<img src="/img/live.png" class="w3-image" alt="Youtube live, play knop" id="ytvi">'.
			'</div>'.
		'</div>'.

	$d64->set_content(
		'<h2>Welkom</h2>'.
		'<p>Dit is een test project van Gerda. Gerda (m) is een eenzame sysop.. je kunt hem gerust een berichtje sturen maar houd het wel netjes ajb. P.S. ik ben niet zo goed met kleuren.</p>'.
		'<p>De laatste berichten:</p>'.
		'<div id="cB"></div>'.
		'<div class="w3-small w3-margin-top" id="cN"></div>'.
		'<div class="w3-bar w3-margin-top">'.
			'<input class="w3-input w3-border w3-bar-item" id="c" type="text" maxlength="9">'.
		'</div>'
	);
}elseif($d64->path[0]==='offline'){
	$contentClass = '';
	$leftPanel = '';
	$d64->set_content(
		'<h2>Geen verbinding</h2>'.
		'<p>Je hebt geen verbinding met internet. Ik probeer het opnieuw als de verbinding hersteld is.</p>'
	);
}else{
	$contentClass = '';
	$leftPanel = '';
}

$d64->init();

$d64->header->set_script('<script src="/js/d64.js"></script>');

echo $d64->header->make_header().
     '<div class="w3-row">'.
	$leftPanel.
       '<div class="'.$contentClass.'w3-dark-grey w3-padding">'.
         $d64->get_content().
       '</div>'.
     '</div>'.
     $d64->footer->make_footer();

?>
