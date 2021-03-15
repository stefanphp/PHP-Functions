<?php

$t = time();

function indexFiles($dir, $depth = -1)
{
    $dir = new RecursiveDirectoryIterator($dir, 4096 | 0);
    $it = new RecursiveIteratorIterator($dir, 1, 16);
    $it->setMaxDepth($depth);
    
    return $it;
}

$t1 = time()-$t;
echo "[1/5] Indexing files. [{$t1}s]\n";

function insertToDB(&$it, $db_path){
    $t = time();
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $f_total = $f_ok = $f_err = 0;
    $files = [];
    
    $stm = $db->prepare("insert into meta(hash,name,size,ext,timestamp) values(:a,:b,:c,:d,:e)");
    $res = $db->query('select hash from meta')->fetchAll(PDO::FETCH_NUM);
    $res = array_column($res, 0);

    $t1 = time()-$t;
    echo "[2/5] Pulling hashes from DB. [{$t1}s]\n";

    foreach($it as $file){
        if($file->isFile() && $file->isReadable())
        {               
            $hash = md5_file($file);
            $name = $file->getBasename();
            $size = $file->getSize();
            $ext = $file->getExtension();
            $time = time();
            array_push($files, [$hash,$name,$size,$ext,$time]);
        }
    }

    $t1 = time()-$t;
    echo "[3/5] Preparing local files. [{$t1}s]\n";

    foreach($files as $k=>&$v)
        foreach($res as $kk=>&$vv)
                if($v[0] === $vv)
                    unset($files[$k]);

    $t1 = time()-$t;
    echo "[4/5] Removing duplicate files. [{$t1}s]\n";

    foreach($files as &$file)
    {
        $stm->bindParam(':a', $hash, PDO::PARAM_STR);
        $stm->bindParam(':b', $name, PDO::PARAM_STR);
        $stm->bindParam(':c', $size, PDO::PARAM_INT);
        $stm->bindParam(':d', $ext,  PDO::PARAM_STR);
        $stm->bindParam(':e', $time, PDO::PARAM_INT);
            
        if($stm->execute()){
            $f_ok++;
            echo '[OK] ', $name, ' [', $size, " bytes]\n";
        }
        else{
            $f_err++;
            echo '[ERR] ', $size, "\n";
        }
        $f_total++;
    }
    $t1 = time()-$t;
    echo "[5/5] Adding files to DB. [{$t1}s]\n";
    echo "\nFiles added: $f_ok.\nFiles error: $f_err.\nFiles total: $f_total.\n";
}

$f = indexFiles("X:\Path_to_folder", "X:\Path_to_DB\DB_file.db");
insertToDB($f);
