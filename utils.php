<?php

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

    public function insert(&$data, &$stm)
    {
        $this->db->conn->beginTransaction();
        $stm->bindParam(1, $data[0], PDO::PARAM_STR);
        $stm->bindParam(2, $data[1], PDO::PARAM_INT);
        $stm->bindParam(3, $data[2], PDO::PARAM_LOB);
        $stm->bindParam(4, $data[3], PDO::PARAM_LOB);
        $stm->bindParam(5, $data[4], PDO::PARAM_STR);
        $stm->bindParam(6, $data[5], PDO::PARAM_STR);

        $stm->execute();
        $this->db->conn->commit();

        fclose($data[2]);
    }

}
