<?php
$max=999999999;
class Citac
{
    public function citajL($file="",$lines=1,&$out,$sz=64)
    {
        if(!is_file($file)||!is_array($out)) die("Not a valid file!");
        try
        {
            $f = fopen($file,'r');
            for($i=0;$i<$lines;$i++)
            {
                $line = fgets($f,$sz);
                array_push($out,$line);
            }
            $line = null;
            fclose($f);
        }catch(Exception $e){return $e;}
    }
    public function citajB($file="",$bytes=64)
    {
        if(!is_file($file)) die("Not a valid file!");
        try
        {
            $f = fopen($file,'r');
            $buffer = fread($f,$bytes);
            fclose($f);
            return $buffer;
            $buffer = null;
        }catch(Exception $e){return $e;}
    }
    public function pisiB($file,$data)
    {
        if(is_array($data)) die("Use *pisiArr* when dealing with arrays.");
        try
        {
            $f = fopen($file,'w+');
            fwrite($f,$data);
            fclose($f);
            return 1;
        }catch(Exception $e){return $e;} 
    }
    public function pisiArr($file,$data=[])
    {
        if(sizeof($data)==0&&$data==null) die("Empty and/or not valid array!");
        try
        {
            $f = fopen($file,'w+');
            $len = sizeof($data);
            for($i=0;$i<$len;$i++)
            {
                $line = $data[$i];
                fwrite($f,$line,strlen($line));
            }
            fclose($f);
            return 1;
        }catch(Exception $e){return $e;}
    }
}