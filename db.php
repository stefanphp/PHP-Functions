<?php

class DB{
    public function __construct(string $dbname){
        try{
            $this->db = new PDO("sqlite:$dbname");
            $this->connected = true;
        }
        catch(PDOException $err){
            $this->connected = false;
            $this->errStr = $err->getMessage();
        }
    }

    public function get(string $key, int $limit, int $offset){
        $cmd = "select name from meta where name like :k'%' order by 1 asc limit :l offset :o";
        $query = $this->db->prepare($cmd);
        $query->bindValue('k', $key,    PDO::PARAM_STR);
        $query->bindValue('l', $limit,  PDO::PARAM_STR);
        $query->bindValue('o', $offset, PDO::PARAM_STR);
        return $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    }
}

#if((isset($_GET['key']) && isset($_GET['limit']) && isset($_GET['offset'])) === false) return 2;
$db = new DB('baza.db');
#if(!$db->connected) return 1;

@$key = 'K';#$_GET['key'];
@$l = 4;#$_GET['limit'];
@$o = 0;#$_GET['offset'];

$res = $db->get($key, $l, $o);
debug_zval_dump($res);