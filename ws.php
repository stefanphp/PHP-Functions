<?php

//Unmask incoming framed message
function ws_unmask($text) {
	$length = ord($text[1]) & 127;
	if($length === 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length === 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	$sz = strlen($data);
	for ($i = 0; $i < $sz; ++$i) 
		$text .= $data[$i] ^ $masks[$i%4];
	return $text;
}

//Encode message for transfer to client.
function ws_mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

//handshake new client.
function ws_handshake($header, $cSoc, $host, $port)
{
	$secKey = '';
    $header = preg_replace("/ /", '', $header);
    $header = explode("\r\n", $header);
    foreach($header as $v){
        $line = explode(':', $v);
        if(chop($line[0]) === 'Sec-WebSocket-Key'){
            $secKey = chop($line[1]);
            break;
        }
    }
    $secAccept = base64_encode(pack('H*', sha1($secKey.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));    
    
	//hand shaking header
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
	"Upgrade: websocket\r\n".
	"Connection: Upgrade\r\n".
	"WebSocket-Origin: $host\r\n".
	"WebSocket-Location: ws://$host:$port/\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($cSoc,$upgrade,strlen($upgrade));
}

function ws_write(&$cSoc, array &$msg){
    $t = ws_mask(json_encode($msg));
    return socket_write($cSoc, $t, strlen($t));
}

function ws_read(&$msg){
    $msg = json_decode(ws_unmask($msg), true);
}

function getPort(&$sock){
		$null = null;
		$port = 0;
		@socket_getpeername($sock, $null, $port);
		return $port;
}


$ip = '192.168.0.50';
$port = 8080;
$null = null;
$listening = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Err in create()\r\n");
socket_bind($listening, $ip, $port) or die("Err in bind()\r\n");
socket_listen($listening, 4) or die("Err in listen()\r\n");

$run = 1;
$msg = ['user'=>'root','msg'=>'Welcome :)'];
$master = [$listening];
$port = 0;
$ip = '';
socket_getsockname($listening, $null, $port); $null = null;
$user[$port] = 'x';

while($run) // Main loop
{
	$userCpy = $master;
	
	if(socket_select($userCpy, $null, $null, 0) === false) continue;
	
	foreach($userCpy as $k=>$sock)
	{
		if($sock === $listening) // New connection
		{
			$newSock = socket_accept($listening);
			socket_set_option ($newSock, SOL_SOCKET, SO_LINGER, ['l_onoff'=>1,'l_linger'=>0]);
            $header = socket_read($newSock, 1024); //Initial handshake header
            ws_handshake($header, $newSock, $ip, $port); //Handshake
            if(ws_write($newSock, $msg) > 0){
				$port = getPort($newSock);
				echo "Online: $port\r\n";
				$master[] = $newSock;
				$user[$port] = 'x';
			}
			continue;
		}
		
		$buff = null;
		$bytesIn = socket_recv($sock, $buff, 4096, 0);
		
		if($bytesIn <= 0) // Client offline
		{
			$port = getPort($sock);
			socket_close($sock);
			unset($master[$k]);
			unset($user[$port]);
			echo "Offline: $port\r\n";
			continue;
        }
		
		ws_read($buff);		
		
		if(isset($buff['cmd'])) // Client command
		{
			$buff = $buff['cmd'];

            if(isset($buff['name'])){
				//TODO set new nick
			}

			elseif(isset($buff['room'])){
				//TODO create chat room
			}

			elseif(isset($buff['call'])){
				//TODO create call session
			}
			
			continue;
		}
		
		if(isset($buff['data'])) // Process data message
		{
			foreach($master	as $outSock) // Broadcast data as json
				$port = getPort($outSock);
				if($user[$port] != 'x') $port = $user[$port];
				$msg = ['user'=>$port,'data'=>$buff['data']];
				if($outSock != $listening && $outSock === $sock){
					ws_write($outSock, $msg);
			}
			continue;
		}

		foreach($master	as $outSock) // Broadcast text message to all
		{
			if(!isset($buff['msg'])) continue;
			if(strlen(chop($buff['msg'])) === 0) continue;
			$port = getPort($outSock);
			if($user[$port] != 'x') $port = $user[$port];
			$msg = ['user'=>$port,'msg'=>$buff['msg']];
			if($outSock != $listening && $outSock != $sock){
				ws_write($outSock, $msg);
			}
		}
		#echo "[{$msg['user']}]: {$msg['data']}\r\n";
	}
	usleep(10000); // Sleep 10ms to avoid 100% core usage
}

socket_close($listening);
