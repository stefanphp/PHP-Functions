<?php
define('szMax', 1024*1000*50);
Class Database
{    
    public $conn = null;
    public $isConnected = 0;
    public $hasError = 0;
    public $res = null;
    public $err = null;
        
    public function open($dbname)
    {
        $db = new PDO("sqlite:{$dbname}");
        if(strtolower(gettype($db)) === 'object')
        {
            $stm = <<<EOF
                CREATE TABLE IF NOT EXISTS "files" (
                    "name"	TEXT NOT NULL,
                    "size"	INTEGER NOT NULL,
                    "data"	BLOB NOT NULL,
                    "thumb"	BLOB,
                    "type"	TEXT NOT NULL DEFAULT '0x0000a',
                    "hash"	TEXT NOT NULL UNIQUE
                )        
            EOF;
            $db->exec($stm);
            $stm = <<<EOF
                CREATE index "id" on "files"(
                    "name" ASC
                )
            EOF;
            $db->exec($stm);
            $this->conn = $db;
            $this->isConnected = 1;
        }
        else $this->hasError = 1;
    }
    public function commit()
    {
        $this->conn->commit();
        return 1;
    }
    public function query($stm)
    {
        if($stm = $this->conn->prepare($stm))
        {
            $stm->execute();
            $this->res = $stm->fetchAll(PDO::FETCH_ASSOC);
            
            return;
        }
        $this->err = $this->conn->errorInfo();
        $this->hasError = 1;
    }
    public function close()
    {
        $this->conn = null;
        $this->isConnected = 0;
        $this->res = null;
    }
        
    public function getThumb($img, $w = 0, $h = -1, $q = 75)
    {
        if((is_file($img) && is_readable($img)))
            if(mime_content_type($img) === 'image/jpeg'){
                $gd = imagecreatefromjpeg($img);
                $gd = imagescale($gd, $w, $h);
                ob_start();
                imagejpeg($gd, null, $q);
                $data = ob_get_clean();                
                return $data;
            }
            return 0xaa;
        return 1;
    }
    public function index($path, $depth = -1)
    {
        $dir = new RecursiveDirectoryIterator($path, 4096);
        $it = new RecursiveIteratorIterator($dir, 1, 16);
        $it->setMaxDepth($depth);
        return $it;
    }
    public function insertA(&$data, &$stm, $commit = 0)
    {
        if(!is_array($data))
        {
            $this->err = "Data parameter invalid.";
            $this->hasError = 1;
            return 0;
        }
        $sz = sizeof($data);
        $this->conn->beginTransaction();
        for($i=0;$i<$sz;$i++)
            $stm->bindParam($i, $data[$i]);
        $stm->execute();
        if($commit)
            $this->conn->commit();
    }
                    # WIP 
    public function custom_aio($path = '', $verbose = 0) 
    {
        //$filesDB, $files = [];        
        $w = 250;
        $h = -1;
        $data = $this->index($path);
        $stm = "insert into files(name, size, data, thumb, type, hash) values(?, ?, ?, ?, ?, ?)";
        $stm = $this->conn->prepare($stm);
        $this->conn->beginTransaction();
        foreach($data as $f)
        {
            if($f->isFile() && $f->isReadable() && $f->getSize() <= szMax && $f->getExtension() === 'jpg')
            {
                $fd = fopen($f, 'rb');
                $file = [
                    $f->getBasename(),
                    $f->getSize(),
                    $fd,
                    $this->getThumb($f->getPathname(), $w, $h),
                    $f->getExtension(), md5_file($f)
                ];
                $stm->bindParam(1, $file[0], PDO::PARAM_STR);
                $stm->bindParam(2, $file[1], PDO::PARAM_INT);
                $stm->bindParam(3, $file[2], PDO::PARAM_LOB);
                $stm->bindParam(4, $file[3], PDO::PARAM_LOB);
                $stm->bindParam(5, $file[4], PDO::PARAM_STR);
                $stm->bindParam(6, $file[5], PDO::PARAM_STR);
                $stm->execute();
                fclose($fd);
                if($verbose) echo "{$file[0]}\n";
            }
        }
        $this->commit();
    }
}