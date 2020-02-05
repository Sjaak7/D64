<?php

declare(strict_types=1);

ini_set('display_errors', "1");
ini_set('display_startup_errors', "1");
error_reporting(E_ALL);

define('ROOTPATH',str_replace("/includes","",__DIR__));

define('URL','https://d64.nl');

require(ROOTPATH.'/includes/d64.php');

$d64 = new d64();

if(!defined('STDIN')){
	$d64->header = new d64header($d64);
	$d64->footer = new d64footer($d64);

	$d64->header->set_description("Test website van Gerda");

	$d64->footer->setFooter('<div class="w3-center">&copy; 2020 - <a href="https://github.com/Sjaak7/D64">Source</a></div>');
	// Check if we are on the frontpage without any jokes like querystrings.. holla hop
	if(!isset($d64->path[0]) && empty($_SERVER['QUERY_STRING'])){
		$bitpay = json_decode(file_get_contents(ROOTPATH.'/cache/btc.json'),true);
		if(is_array($bitpay))
			$bitprice = $bitpay[3]['rate'];
		else $bitprice = 'NaN';

		$d64->setContent(
			'<h1>Lobby:</h1>'.
			'<div id=cFrame>'.
				'<div id=cB></div>'.
				'<div class="w3-margin-top w3-small" id=cN></div>'.
			'</div>'
		);
		$d64->footer->setFooter(
			'<div id=cIForm>'.
				'<textarea id=cI maxlength=9></textarea>'.
				'<div>'.
					'<input type=checkbox class="w3-check" id=btc checked> <label for=btc>BTC updates</label>'.
					'<span class="w3-right">v<span id=version></span></span>'.
				'</div>'.
			'</div>'
		);
	}elseif($d64->path[0]==='offline'){
		$d64->setContent(
			'<h1>Geen verbinding</h1>'.
			'<p>Je hebt geen verbinding met internet. Ik probeer het opnieuw als de verbinding hersteld is.</p>'
		);
	}

	$d64->init();

	$d64->header->set_script('<script src="/js/d64.js"></script>');

	echo
		$d64->header->makeHeader().
		'<div class="swipePage visiblePage" id=p1>'.
			$d64->getContent().
		'</div>'.
		'<div class="swipePage hiddenPage" id=p2>'.
			'<h1>Swipe test</h1>'.
		'</div>'.
		$d64->footer->makeFooter();
}else require(ROOTPATH.'/includes/WebSocket/d64server.php');

?>
