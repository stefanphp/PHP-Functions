<?php 

function index($path,$dept=1,$log=0){
	if ($log === 1) $f = fopen('files.txt','w');
	$size=$dir=$file=0;
	echo("Scaning dir $dir, depth -> $dept");
	
	$fl = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD
	);
	$fl->setMaxDepth($dept);

	echo "\n[RUNNING] Indexing files";

	foreach ($fl as $name) {
		$tmp = $name->getRealPath();
		if(substr($tmp,3,4) !== 'Boot'
		&& substr($tmp,3,7) !== 'Windows'
		&& substr($tmp,3,1) !== '$'
		&& (substr(mime_content_type($tmp),0,5) === 'image'
		||	substr(mime_content_type($tmp),0,5) === 'video'
		||	substr(mime_content_type($tmp),0,5) === 'audio'
		||	substr(mime_content_type($tmp),0,4) === 'text')){
			if(is_dir($tmp)) $dir++;
			if(is_file($tmp)){
				$size += filesize($tmp);
				if ($log === 1) fwrite($f,$tmp.PHP_EOL);
				$file++;
			}
		}
	}
	if ($log === 1) fclose($f);
	
	$size = round($size/1048576,2);

	echo "\n[INFO]Folders: $dir\n";
	echo "[INFO]Files: $file\n";
	echo "[INFO]Size: $size MB\n";
	
	$fl=$arr=$size=$name=0;
}

echo mime_content_type('script.js');

#index("D:\\",1);

?>