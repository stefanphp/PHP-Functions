                        # WIP

<?php

function chunk($file,$key,$chunk=1024){
    $m = memory_get_peak_usage(true) / (1024 * 1024);
    $name = substr($file,3);
    $i = 0;
    $f = fopen($file,'r');
    $f2 = fopen($name,'w');
    #$arr = array();

    while(!feof($f) && $i<1){
        $buff = fread($f,$chunk);
        fwrite($f2,$buff);
        $arr = explode(PHP_EOL,$buff);
        $i++;
    }
    
    echo "\n[INFO] Memory used -> $m MB";
    echo "\n[INFO] Buffer ->  $chunk\n";
    
    fclose($f);
    fclose($f2);
   
}

chunk('D:\2bil.txt','bibba',50);

#echo file('2bil.txt')[5];

?>
