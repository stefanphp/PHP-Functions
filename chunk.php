<?php

function find($file,$key,$depth)
{
	$start = microtime(1);
	$chunk = 128;
	$f = fopen($file,'r');
	$i = 0;
	$n = 0;
	$temp='';
	$pos = 0;

	while(!feof($f) && $i<=$depth){
		$buff = fread($f,$chunk);
		$len = strlen($buff);
		for($l=0;$l<$len;$l++){
			if($buff[$l] !== "\n"){
				$temp .= $buff[$l];
			}
			else {
				if($temp === $key) $pos = $n;
				$temp = '';
				$n++;
			}
		}
		$i++;
	}

	$mem = round(memory_get_peak_usage()/(1024*1024),2);
	fclose($f);
	
	echo "\n\n\n[INFO] Buffer has $n lines\n";
	echo "Your word '$key' is at line $pos of this huge ass file\n";
	echo "Memmory used: $mem MB\n$n";
	echo "\n\n[INFO] Time: ";
	echo microtime(1) - $start;
}
