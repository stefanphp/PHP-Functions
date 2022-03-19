<?php


$IP_G = $argv[1];
$PORT_G = $argv[2];

//Unmask incoming framed message
function ws_unmask(string $text) {
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
function ws_mask(string $text)
{
	$b1 = 129;//0x80 | (0x1 & 0x0f);
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
function ws_handshake(string $header, Socket $cSoc, string $host, int $port)
{
	$secKey = substr($header, strpos($header, 'Sec-WebSocket-Key: ')+19, 24);	
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

function ws_write(Socket &$cSoc, array &$msg){
    $t = ws_mask(json_encode($msg));	
    return socket_write($cSoc, $t, strlen($t));
}

function ws_read(&$msg){
    $msg = json_decode(ws_unmask($msg), true);	
}


$null = null;
$listening = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Err in create()\r\n");
socket_bind($listening, $IP_G, $PORT_G) or die("Err in bind()\r\n");
socket_setopt($listening, SOL_SOCKET, SO_REUSEADDR, true) or die("Err in config()\r\n");
socket_listen($listening, 4) or die("Err in listen()\r\n");

$run = true;
$master = [$listening];
$null = null;
$user[$PORT_G] = ['id'=>'root', 't_active'=>time(), 'room'=>'main'];
$room['main'] = ['clients'=>['root'], 'password'=>'0'];

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
            $header = socket_read($newSock, 512); // Handshake request header
            ws_handshake($header, $newSock, $IP_G, $PORT_G); // Handshake
			$port = spl_object_id($newSock);
			$msg = ['from'=>'root', 'type'=>'notify', 'id'=>$port];
			$notify = ['from'=>'root', 'type'=>'notify', 'online'=>$port];
			foreach($master as &$tmp)
				if($tmp != $sock && $tmp != $newSock && $tmp != $listening)
					ws_write($tmp, $notify);	
			if(ws_write($newSock, $msg) > 0){
				echo "Online: $port\r\n";
				$master[] = $newSock;
				$user[$port] = ['id'=>'guest', 't_active'=>time(), 'room'=>'main'];
			}
			continue;
		}
		
		$buff = null;
		$bytesIn = socket_recv($sock, $buff, 4096, 0);
		
		if($bytesIn <= 6) // Client offline
		{
			$port = spl_object_id($sock);
			socket_close($sock);
			unset($master[$k]);
			unset($user[$port]);
			echo "Offline: $port\r\n";
			$notify = ['from'=>'root', 'type'=>'notify', 'offline'=>$port];
			foreach($master as &$tmp)
				if($tmp != $sock && $tmp != $listening)
					ws_write($tmp, $notify);
			continue;
        }
		
		ws_read($buff);		
		//print_r($msg);
				
		foreach($master	as $outSock) // Broadcast text message to all
		{
			if(!isset($buff['type']))   continue;
			if($buff['type'] != 'text') continue;
			if(!isset($buff['data']))   continue;
			if(strlen(chop($buff['data'])) === 0) continue;
			$port = spl_object_id($outSock);
			$msg = ['from'=>"$port", 'type'=>'text', 'data'=>$buff['msg']];			
			if($outSock != $listening && $outSock != $sock){
				ws_write($outSock, $msg);
			}
		}		
	}
	usleep(10000); // Sleep 10ms to avoid 100% core usage
}

socket_close($listening);
