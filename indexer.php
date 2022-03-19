<?php

class Indexer{

    public function __construct(string $path = '', string $dbname = '')
    {
        $this->db = new PDO("sqlite:$dbname");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->files_arr = [];
        $this->f_ok = 0;
        $this->f_err = 0;
        $this->f_cnt = 0;
        $this->db_stm = $this->db->prepare("insert into files(name, hash, size, ext, timestamp, path, ctime, atime, mtime) values(:a,:b,:c,:d,:e,:f,:g,:h,:i)");
        $this->indexFiles($path);
    }

    private function deployDB(){
        $table = <<<EOF
                CREATE TABLE "files" (
                "name"	TEXT NOT NULL,
                "hash"	TEXT NOT NULL UNIQUE,
                "size"	INTEGER NOT NULL,
                "ext"	TEXT NOT NULL,
                "timestamp"	INTEGER NOT NULL,
                "path"	TEXT NOT NULL,
                "ctime"	INTEGER NOT NULL,
                "atime"	INTEGER NOT NULL,
                "mtime"	INTEGER NOT NULL,
                PRIMARY KEY("hash"))
        EOF;
        $this->db->exec($table);
    }

    private function indexFiles($dir)
    {
        $this->deployDB();
        $x = 0;
        $dir = new RecursiveDirectoryIterator($dir, 4096 | 0);
        $it = new RecursiveIteratorIterator($dir, 1, 16);        

        foreach($it as $file)
        {
            if(!$file->isFile()) continue;
            if($x === 1000){
                $this->removeDoubles();
                $this->insertToDB();
                $x = 0;
                echo "\n--1000--(ok $this->f_ok:$this->f_err err)--$this->f_cnt\n";
            }
            $name  =  $file->getBasename();
            $hash  =  md5_file($file);
            $size  =  $file->getSize();
            $ext   =  $file->getExtension();
            $time  =  time();
            $path  = $file->getPath();
            $ctime = $file->getCtime();
            $atime = $file->getAtime();
            $mtime = $file->getMtime();
            $tmp   = [$name,$hash,$size,$ext,$time,$path,$ctime,$atime,$mtime];
            array_push($this->files_arr, $tmp);
            $this->f_cnt++;
            $x++;
        }
        $this->removeDoubles();
        $this->insertToDB();        
        echo "\n\n| Found $this->f_cnt files.\n";
        echo "| Added $this->f_ok files.\n";
        echo "| Error adding $this->f_err files.\n";
        echo '| Mem used: ',round(memory_get_peak_usage()/1024),' kb.';
    }

    private function removeDoubles()
    {        
        $hash_cnt = $this->db->query('select count(_rowid_) from files')->fetchAll(PDO::FETCH_NUM)[0][0];
        if($hash_cnt === 0) return;
        $hash_rng = range(0, $hash_cnt+1000, 1000);
        foreach($hash_rng as $xkey=>$x)
        {
            $hash_arr = $this->db->query("select hash from files limit 1000 offset $xkey")->fetchAll(PDO::FETCH_NUM);            
            $this->files_arr = new ArrayObject($this->files_arr);
            $this->files_arr = $this->files_arr->getIterator();
            $hash_arr = new ArrayObject($hash_arr);
            $hash_arr = $hash_arr->getIterator();
            while($this->files_arr->valid()){
                while($hash_arr->valid()){
                    if($this->files_arr->current()[1] === $hash_arr->current()[0])
                        unset($this->files_arr[$this->files_arr->key()]);
                    $hash_arr->next();
                }
                $this->files_arr->next();
            }
        }        
    }

    private function insertToDB()
    {
        foreach($this->files_arr as $file)
        {            
            $this->db_stm->bindValue(':a', $file[0], PDO::PARAM_STR);
            $this->db_stm->bindValue(':b', $file[1], PDO::PARAM_STR);
            $this->db_stm->bindValue(':c', $file[2], PDO::PARAM_INT);
            $this->db_stm->bindValue(':d', $file[3], PDO::PARAM_STR);
            $this->db_stm->bindValue(':e', $file[4], PDO::PARAM_INT);
            $this->db_stm->bindValue(':f', $file[5], PDO::PARAM_STR);
            $this->db_stm->bindValue(':g', $file[6], PDO::PARAM_INT);
            $this->db_stm->bindValue(':h', $file[7], PDO::PARAM_INT);
            $this->db_stm->bindValue(':i', $file[8], PDO::PARAM_INT);
            $res = $this->db_stm->execute();
            if($res === true) $this->f_ok++;
            else if($res === false) $this->f_err++;
        }
        $this->files_arr = [];
    }
}

if(sizeof($argv) !== 3) die("Usage: php $argv[0] path_to_dir path_to_db.\n");
$dir = $argv[1];
$db  = $argv[2];
if(!is_dir($dir))  die("Dir err: '$dir'\n");
if(!is_file($db)) die("DB err: '$db'.\n");
$index = new Indexer($dir, $db);
