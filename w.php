<?php

require_once('utils.php');
//$db->custom_aio('C:/users/stefk/desktop/hdd_back/pera/pera-slike', 1);

$start = @$_GET['start'];
$offset = @$_GET['offset'];
$imgid = @$_GET['imgid'];

$db = new Database();
$db->open('poy.db');

function get($db, $stm)
{
    $db->query($stm);
    return $db->res;
}

if(isset($imgid) && !(isset($start) && isset($offset)))
{    
    $stm = "select data from files where _rowid_ = $imgid";
    echo base64_encode(get($db, $stm)['thumb']);
}

if(isset($start) && isset($offset) && !isset($imgid))
{
    if($offset > 10 || $offset < 1) $offset = 10;
    $arr = [];
    $res = get($db, "select name,thumb from files limit $start,$offset");
    foreach($res as &$pair)
        array_push($arr, ['name' => $pair['name'],
                    'thumb'=>base64_encode($pair['thumb'])]);
    echo json_encode($arr);
    $arr = null;
    $res = null;
}



$db->close();