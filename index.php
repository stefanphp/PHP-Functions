<?php

function index($path,$depth=1,$log=0){
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	if ($log === 1) $f = fopen('files.txt','w');
	$size=$all=$dir=$file=0;
	$db = new PDO("mysql:host=localhost;dbname=data","root","root");
	$arr = array();

	echo("[RUNNING] Scaning dir $path, depth -> $depth");
	
	$dir = new RecursiveDirectoryIterator($path,
	RecursiveDirectoryIterator::SKIP_DOTS);

	$it = new RecursiveIteratorIterator($dir,
	RecursiveIteratorIterator::SELF_FIRST,
	RecursiveIteratorIterator::CATCH_GET_CHILD);
	$it->setMaxDepth($depth);

	echo "\n[RUNNING] Indexing files";
	
	foreach ($it as $name) {
		if($name->isDir()) $dir++;
		if($name->isFile()){
			$size += filesize($name);
			if ($log === 1) fwrite($f,$name.PHP_EOL);
				$file++;
				$hash = md5_file($name);
				$res = $db->prepare("select hash from data where hash='$hash'");
				$res->execute();
				$res = $res->fetch(PDO::FETCH_ASSOC);
			if(strlen($res['hash']) === 0){
				$size = filesize($name);
				$type = (finfo_file($finfo,$name));
				$clk = time();
				$stmt = $db->prepare("insert into data (id,name,clk,type,size,hash) 
				values(id,'$name','$clk','$type','$size','$hash')");
				$stmt->execute();
			}
		}
	}
	
	finfo_close($finfo);
	if ($log === 1) fclose($f);
	
	$size = round($size/1048576);
	echo "Files: $file -> $size MB\n";
	echo "Dir: $dir\n";
	echo "All: $all\n";
	$type=$hash=$stmt=$db=$size=$name=null;
}	

//index('D:\\',2,0);

?>
