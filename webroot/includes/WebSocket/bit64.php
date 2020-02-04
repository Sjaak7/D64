<?php

class WebSocket_bit64
{
	private object $d64;
	private int $timestamp;
	private string $btc_euro_rate = '0';

	function __construct(object &$parent)
	{
		$this->d64 = &$parent;

		$this->timestamp = time();
	}

	public function BTCtimer(int $seconds) : void
	{
		$diff = time()-$this->timestamp;
		if($diff>=$seconds){
			$this->d64->moduleTimerLag = ($diff*1000)-($seconds*1000);
			$this->timestamp = time();
			$this->getUpdate();
		}
	}

	private function getUpdate() : bool
	{
		$btc = json_decode(file_get_contents(ROOTPATH.'/cache/btc.json'),true);
		if(is_array($btc) && $btc[3]['rate']!=$this->btc_euro_rate){
			$this->d64->send('{"mod":"btc","btc_euro":"'.$btc[3]['rate'].'"}',true);
			$this->btc_euro_rate = $btc[3]['rate'];
			return true;
		}else return false;
	}
}

?>
