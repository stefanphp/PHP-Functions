<?php

function DBDeploy(&$db, $type){
	if($type === 'sqlite')
		$cmd = <<<EOF
		CREATE TABLE "files" (
		"name"	TEXT NOT NULL,
		"size"	INTEGER NOT NULL,
		"mime"	TEXT NOT NULL,
		"thumb"	BLOB,
		"data"	BLOB NOT NULL,
		"hash"	TEXT NOT NULL UNIQUE,
		PRIMARY KEY("hash")
		);
EOF;
	if($type === 'mysql')
		$cmd = <<<EOF
		CREATE TABLE IF NOT EXISTS `files` (
		`name` varchar(50) NOT NULL,
		`size` int(10) unsigned NOT NULL,
		`mime` int(2) unsigned NOT NULL,
		`thumb` mediumblob DEFAULT '',
		`data` longblob DEFAULT '',
		`hash` binary(32) NOT NULL,
		PRIMARY KEY (`hash`),
		UNIQUE KEY `hash` (`hash`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF;
	return $db->exec($cmd);
}

function indexer(string $path, array $ext, array &$out)
{
    $i=$sz=$t0=0;
    $cmd = 'dir ';
    $files = [];
    foreach($ext as $f) $cmd .= "$path\*.$f ";
    $cmd .= '/a-d /a-h /b /s';
    $t0 = time();
    exec($cmd, $files);
    $t0 = time()-$t0;
	echo "[INFO] Indexing done. [{$t0}s]\r\n";
	$t0 = time();
    foreach($files as $f)
    {
		$sz = filesize($f);
        if($sz <= 6410241024 && $sz >= 51200)
        {
			$name = basename($f);
			$type = mime_content_type($f);
            $out[fHash($f)] = [$name,$sz,$type,$f];
        }
    }
	$t0 = time()-$t0;
	echo "[INFO] HashMap done. [{$t0}s]\r\n";
}

function getThumb(string $file, $w=250, $h=250, $q = 90)
{
    if(mime_content_type($file) != 'image/jpeg') return 0x0;
    $im = imagecreatefromjpeg($file);
    $im = imagescale($im, $w ,$h);
    ob_start();
    imagejpeg($im, null, $q);
    
    return ob_get_clean();
}

function rDpl(array &$a, array &$b)
{
    $tmp = [];
	foreach($a as $k=>$v)
		foreach($b as $kk=>$vv)
			if($k === $vv['hash'])
				unset($a[$k]);
}

function fHash(string $file): string
{
    $f = fopen($file, 'rb');
    $h = hash_init('md5');
    hash_update_stream($h, $f);
    fclose($f);
	return hash_final($h);
}

function DBwrite(&$db, array &$files)
{	
    $result = [];
    $done = $sz = 0;
	$sz = sizeof($files);
	if($sz === 0) return 'no files';    
    $cmd = "insert into files(name,size,mime,thumb,data,hash) values(?,?,?,?,?,?)";
    $stm = $db->prepare($cmd);
	if($stm === false) die("[ERR] Failed to prepare a statement.\r\n");
	echo "[INFO] Writing to database, please wait.\r\n\r\n";
	$t0 = time();
	$i = 0;
    foreach($files as $k=>$v)
    {
		$file = $files[$k];
        $f = fopen($file[3], 'rb');
		$hash = hex2bin($k);
        $thumb = getThumb($file[3]);
        $stm->bindParam(1, $file[0], PDO::PARAM_STR); // Name
        $stm->bindParam(2, $file[1], PDO::PARAM_INT); // Size
        $stm->bindParam(3, $file[2], PDO::PARAM_STR); // Mime type
		$stm->bindParam(4, $thumb, PDO::PARAM_LOB);   // Thumbnail
        $stm->bindParam(5, $f, PDO::PARAM_LOB);		  // File data [BLOB]
        $stm->bindParam(6, $hash, PDO::PARAM_STR);    // File hash [BINARY]
		
		$mem = memory_get_peak_usage(true)/1024/1024;
        if($stm->execute() === true){
            if($stm->rowCount() === 1) {$done++;echo"{$file[0]}\r\n";}
		}
        else echo "[$mem/128]\r\n";
		#die();
		#fclose($f); ???
    }
	$t0 = time()-$t0;
	if($done === 0) echo "[INFO] No new files found. [{$t0}s]\r\n";
	else echo "\r\n[INFO] Files added: $done/$sz. [{$t0}s]\r\n";    
}

function DBread(&$db, string $cmd)
{
    $stm = $db->query($cmd);
    $stm = $stm->fetchAll(PDO::FETCH_ASSOC);    
    return $stm;
}

function aio(&$db, $ext, $dbtype, $path){
	$files = [];
	DBDeploy($db, $dbtype);
	Indexer($path, $ext, $files);
	$hashes = DBread($db, 'select hash from files');
	rDpl($files, $hashes);
	DBwrite($db, $files);
}


$db = null;
$dbtype = '';

if($argc === 4){
	$db = new PDO("sqlite:{$argv[3]}");
	$dbtype = 'sqlite';
}
elseif($argc === 6){
	$pass = $argv[5];
	if($pass === '@') $pass = '';
	$db = new PDO("mysql:dbname={$argv[3]}", $argv[4], $pass);
	$dbtype = 'mysql';
}
else{
	echo "\r\nUsage for sqlite: php indexer.php C:/images/ jpg:png files.db.\r\n";
	echo "\r\nUsage for mysql: php indexer.php C:/images/ jpg:png dbname user pass.\r\n";
	exit(1);
}

print_r($argv);

$ext = explode(':', $argv[2]);
aio($db, $ext, $dbtype, $argv[1]);

$db = null;
