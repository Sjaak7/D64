<?php

set_time_limit(0);
ob_implicit_flush();

define('HOST_NAME','172.18.0.52');
define('PORT','8080');
$null = NULL;

class d64{
	private $socketResource;
	private array $clientSocketArray;
	private array $clientInfoArray;

	private int $pingTime = 15;
	private array $consoleData;

	private array $module;
	public float $moduleTimerLag = 0;

	public int $loop = 1;

	function __construct()
	{
		$this->socketResource = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
		socket_set_option($this->socketResource,SOL_SOCKET,SO_REUSEADDR,1);
		socket_bind($this->socketResource,0,PORT);
		socket_listen($this->socketResource);

		$this->consoleData['server'] = [
			'activeConnections'	=> 0,
			'connectionsIndex'	=> 0,
			'massSendTime'		=> 0,
			'lastReceivedCommand'	=> '',
			'lastSendCommand'	=> '',
			'lastOpcode'		=> ''
			];

		pcntl_async_signals(true);

		pcntl_signal(SIGTERM,array(&$this,"END"));
	}

	function __destruct()
	{
		$this->loop = 0;
		socket_close($this->socketResource);
		echo "DOES THIS WORK?\n IT actually does in some cases..";
	}

	public function registerModule(array $module)
	{
		$this->module[$module['name']]['mod'] = new $module['name']($this);
		if(isset($module['hook']))
			$this->module[$module['name']]['hook']=$module['hook'];
		if(isset($module['timer']))
			$this->module[$module['name']]['timer']=$module['timer'];
		if(isset($module['end']))
			$this->module[$module['name']]['end']=$module['end'];
	}

	private function checkModuleHook(string $hook)
	{
		foreach($this->module as $module => $val)
			if(isset($val['hook']))
				foreach($val['hook'] as $key => $func)
					if($func===$hook)
						$modules[] = $module;
		return $modules;
	}

	private function moduleTimers()
	{
		foreach($this->module as $module => $val)
			if(isset($val['timer'])){
				$key = array_key_first($val['timer']);
				$this->module[$module]['mod']->$key($val['timer'][$key]);
			}
	}

	public function send(string $message,bool $everyone,int $index = 0,string $type = 'text',int $except = 0) : bool
	{
		$message = $this->encode($message,$type);
		if($everyone){
			$this->consoleData['server']['massSendTime'] = microtime(true);
			foreach($this->clientSocketArray as $key => $clientSocket){
				if($clientSocket===$this->socketResource || $except!==0 && $key===$except)
                        		continue;
				else{
					foreach($message as $fragVal)
						$sent = @socket_write($this->clientSocketArray[$key],$fragVal['frame'],$fragVal['length']);
					if($type==='close')
						$this->closeConnection($key);
					// delay ping..
					if(isset($this->clientInfoArray[$key]))
						$this->clientInfoArray[$key][0] = $this->clientInfoArray[$key][0]+$this->pingTime;
				}
			}
			$this->consoleData['server']['massSendTime'] = microtime(true)-$this->consoleData['server']['massSendTime'];
		}else{
			foreach($message as $fragVal)
				@socket_write($this->clientSocketArray[$index],$fragVal['frame'],$fragVal['length']);
		}
		$this->sendCommand($type);
		return true;
	}

	private function decode(string $data) : array
	{
		$bytes = $data;
		$d = [
			'dataLength'	=>'',
			'mask'		=>'',
			'codedData'	=>'',
			'decodedData'	=>'',
			'opcode'	=>ord($data[0]) & 0xf,
			'secondByte'	=>decbin(ord($bytes[1]))
		];
		$masked = ($d['secondByte'][0]==='1') ? true : false;
		$d['dataLength'] = ($masked===true) ? ord($bytes[1]) & 127 : ord($bytes[1]);
		if($masked){
			if($d['dataLength']===126){
				$d['mask'] = substr($bytes,4,4);
				$d['codedData'] = substr($bytes,8);
			}elseif($d['dataLength']===127){
				$d['mask'] = substr($bytes,10,4);
				$d['codedData'] = substr($bytes,14);
			}else{
				$d['mask'] = substr($bytes,2,4);
				$d['codedData'] = substr($bytes,6);
			}
			for($i=0;$i<strlen($d['codedData']);$i++)
				$d['decodedData'] .= $d['codedData'][$i] ^ $d['mask'][$i % 4];
		}else{
			if($d['dataLength']===126)
				$d['decodedData'] = substr($bytes,4);
			elseif($d['dataLength']===127)
				$d['decodedData'] = substr($bytes,10);
			else $d['decodedData'] = substr($bytes,2);
		}
		return ['opcode'=>$d['opcode'],'data'=>$d['decodedData']];
	}

	// A server MUST NOT mask any frames that it sends to the client

	private function encode(string $payload, string $type = 'text') : array
	{
		$payloadMax = 128;

		$fragmentedPayload = str_split($payload,$payloadMax);

		$frameHead = [];
		$frame = [];

		switch($type){
			case 'close' :
				// first byte indicates FIN, Close Frame(10001000) dec: 136:
				$binHead = bindec('10001000');
				break;
			case 'ping' :
				// first byte indicates FIN, Ping frame (10001001): dec: 137:
				$binHead = bindec('10001001');
				break;
			case 'pong' :
				// first byte indicates FIN, Pong frame (10001010): dec: 138:
				$binHead = bindec('10001010');
				break;
		}

		$frames = count($fragmentedPayload);

		for($i=0;$i<$frames;$i++){
			$payloadLength = strlen($fragmentedPayload[$i]);
			// set mask and payload length (using 1, 3 or 9 bytes)
			if($type==='text'){
				if($frames>1){
					if($i===0)
						$frameHead[$i][0] = bindec('00000001');
					elseif($i===$frames-1)
						$frameHead[$i][0] = bindec('10000000');
					else
						$frameHead[$i][0] = bindec('00000000');
				}else
					$frameHead[$i][0] = bindec('10000001');
			}else $frameHead[$i][0] = $binHead;

			if($payloadLength>65535){
				$payloadLengthBin = str_split(sprintf('%064b',$payloadLength),8);
				$frameHead[$i][1] = 127;
				for($j=0;$j<8;$j++)
					$frameHead[$i][$j+2] = bindec($payloadLengthBin[$j]);
			}elseif($payloadLength>125){
				$payloadLengthBin = str_split(sprintf('%016b',$payloadLength),8);
				$frameHead[$i][1] = 126;
				$frameHead[$i][2] = bindec($payloadLengthBin[0]);
				$frameHead[$i][3] = bindec($payloadLengthBin[1]);
			}else
				$frameHead[$i][1] = $payloadLength;
			// convert frame-head to string:
			foreach(array_keys($frameHead[$i]) as $j)
				$frameHead[$i][$j] = chr($frameHead[$i][$j]);
			$frame[$i]['frame'] = implode('',$frameHead[$i]);
			// append payload to frame:
			for($j=0;$j<$payloadLength;$j++)
				$frame[$i]['frame'] .= $fragmentedPayload[$i][$j];
			$frame[$i]['length'] = strlen($frame[$i]['frame']);
		}
		return $frame;
	}

	private function handshake(string $received_header, $client_socket_resource, int $key) : array
	{
		$headers = [];
		$lines = preg_split('/\r\n/',$received_header);
		foreach($lines as $line){
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/',$line,$matches))
				$headers[$matches[1]] = $matches[2];
		}
		$secKey = $headers['Sec-WebSocket-Key'];
		// See RFC6455 Page 7
		$secAccept = base64_encode(pack('H*',sha1($secKey.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$buffer  =
			"HTTP/1.1 101 Switching Protocols\r\n".
			"Upgrade: websocket\r\n".
			"Connection: Upgrade\r\n".
			"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
		socket_write($client_socket_resource,$buffer,strlen($buffer));
		return $headers;
	}

	private function receivedCommand(string $command) : void
	{
		$this->consoleData['server']['lastReceivedCommand'] = $command;
		$this->console('New received');
	}

	private function sendCommand(string $command) : void
	{
		$this->consoleData['server']['lastSendCommand'] = $command;
		$this->console('New send');
	}

	private function countConnections(bool $plusminus) : void
	{
		if($plusminus){
			$this->consoleData['server']['activeConnections']++;
			$this->consoleData['server']['connectionsIndex']++;
			echo "\x07";
		}else $this->consoleData['server']['activeConnections']--;
	}

	private function console(string $msg) : void
	{
		system('clear');
		echo
			"================================================================================\n".
			' connections      : '.$this->consoleData['server']['activeConnections']."\n".
			' index            : '.$this->consoleData['server']['connectionsIndex']."\n".
			' last opcode      : '.$this->consoleData['server']['lastOpcode']."\n".
			' clientsocketarr  : '.count($this->clientSocketArray)."\n".
			' clientinfoarr    : '.count($this->clientInfoArray)."\n".
			' last RX command  : '.$this->consoleData['server']['lastReceivedCommand']."\n".
			' last TX command  : '.$this->consoleData['server']['lastSendCommand']."\n".
			' system command   : '.$msg."\n".
			' mass send time   : '.round(($this->consoleData['server']['massSendTime']/1000),5)." s\n".
			' memory           : '.round(memory_get_usage()/1048576,5)." MiB\n".
			' memory (real)    : '.round(memory_get_usage(true)/1048576,2)." MiB\n".
			' memory (peak)    : '.round(memory_get_peak_usage()/1048576,5)." MiB \n".
			' module timer lag : '.$this->moduleTimerLag."\n".
			' Directory        : '.__DIR__."\n";

		if(isset($this->consoleData['module']))
			foreach($this->consoleData['module'] as $key => $val)
				echo ' '.$this->consoleData['module'][$key][0].':'.$this->consoleData['module'][$key][1]."\n";
	}

	public function addConsoleData(string $key, array $val) : void
	{
		$this->consoleData['module'][$key]=$val;
	}

	private function jsonValidator(string $msg)
	{
		if(!empty($msg))
			if($data = @json_decode($msg,true));
				return $data;
	}

	/*
	* RFC6455: 5.1: A server MUST close the connection upon
	* receiving a frame that is not masked.
	*/

	private function handleIncomingData(int $key, string $data) : void
	{
		$received = $this->decode($data);
		$this->consoleData['server']['lastOpcode'] = $received['opcode'];
		// received pong
		if($received['opcode']===10)
			$this->clientInfoArray[$key][2] = true;
		// received disconnect
		if($received['opcode']===8)
			$this->closeConnection($key);
		elseif(isset($received['data']))
			$this->handleMessages($received['data'],$key);
	}

	private function handleMessages(string $msg, int $key) : void
	{
		if($data=$this->jsonValidator($msg)){
			if(isset($data['mod'])&&$data['mod']==='chat'){
				if($toCall=$this->checkModuleHook('incoming'))
					foreach($toCall as $mod)
						$this->module[$mod]['mod']->incomingData($data,$key);
			}
		}else $this->receivedCommand($msg);
	}

	private function reIndexClientArrays() : void
	{
		if($this->consoleData['server']['connectionsIndex']>=100){
			$this->clientSocketArray = [...$this->clientSocketArray];
			$this->clientInfoArray = [...$this->clientInfoArray];
			$this->consoleData['server']['connectionsIndex']=0;
		}
	}

	private function handleNewConnection() : bool
	{
		if(in_array($this->socketResource,$this->newSocketArray)){
			$newSocket = socket_accept($this->socketResource);
			// big test..
			$this->reIndexClientArrays();
			$this->clientSocketArray[] = $newSocket;
			$lastKey = array_key_last($this->clientSocketArray);

			$header = socket_read($newSocket,1024);
			if($headers = $this->handshake($header,$newSocket,$lastKey)){
				$this->clientInfoArray[$lastKey] = [time(),$headers['X-Real-IP'],true];
				$this->countConnections(true);

				if($toCall=$this->checkModuleHook('handshake'))
					foreach($toCall as $mod)
						$this->module[$mod]['mod']->handshake($headers,$lastKey);

				$this->console("CONNECT");
				$this->newSocketArray = [];
				return true;
			}else return false;
		}else return false;
	}

	private function closeConnection(int $key) : void
	{
		$this->countConnections(false);
		unset($this->clientSocketArray[$key]);
		unset($this->clientInfoArray[$key]);

		if($toCall=$this->checkModuleHook('close'))
			foreach($toCall as $mod)
				$this->module[$mod]['mod']->closeConnection($key);

		$this->console("DISCONNECT");
	}

	private function checkForClosedConnections(int $key) : void
	{
		$socketData = @socket_read($this->clientSocketArray[$key],1024,PHP_NORMAL_READ);
		if($socketData===false)
			$this->closeConnection($key);
	}

	private function pingClients() : void
	{
		foreach($this->clientInfoArray as $key => $socket)
			if($key!==0 && (time()-$this->clientInfoArray[$key][0])>=$this->pingTime){
				if($this->clientInfoArray[$key][2]){
					$this->send('Hey',false,$key,'ping');
					$this->clientInfoArray[$key][0] = time();
					$this->clientInfoArray[$key][2] = false;
				}else $this->closeConnection($key);
			}
	}

	public function server() : void
	{
		$this->clientSocketArray = [$this->socketResource];
		$this->clientInfoArray = ['system'];

		while($this->loop===1){
			$this->moduleTimers();

			$this->newSocketArray = $this->clientSocketArray;
			@socket_select($this->newSocketArray,$null,$null,0,null);

			// handleNewConnection will return a empty newSocketArray..
			// so the foreach is skipped until there are NO new connections..
			// oopsy... maybe build in a rate-limit..
			$this->handleNewConnection();

			foreach($this->newSocketArray as $key => $newSocketArrayResource){
				while(socket_recv($newSocketArrayResource,$data,1024,0) >= 1){
					$this->handleIncomingData($key,$data);
					break 2;
				}
				$this->checkForClosedConnections($key);
			}

			$this->pingClients();
		}
	}

	private function END() : void
	{
		$this->loop=0;
		sleep(5);
		foreach($this->module as $key => $module){
			if(isset($module['end'])){
				$call = $module['end'];
				$this->module[$key]['mod']->$call();
			}
		}
		$this->send('Development',true,0,'close');
	}

	public function addClientInfo(int $index, string $info, int $lastKey) : void
	{
		$this->clientInfoArray[$lastKey][$index]=$info;
	}

	public function getClientInfoArray() : array
	{
		return $this->clientInfoArray;
	}
}

require('./socket/d64btc.php');
require('./socket/d64chat.php');

$d64 = new d64();
$d64->registerModule([
	'name'	=>	'chat64',
	'hook'	=>	[
			'handshake',
			'incoming',
			'close'
			],
	'end'	=>	'END'
	]);
$d64->registerModule([
	'name'	=>	'bit64',
	'timer'	=>	[
			'BTCtimer'	=>	30
			]
	]);

$d64->server();

?>
