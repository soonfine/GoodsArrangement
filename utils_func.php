<?php

function requestPost($url, $data=NULL, $timeout=5){

    $ch = curl_init();

    curl_setopt ($ch, CURLOPT_URL, $url);

    curl_setopt ($ch, CURLOPT_POST, 1);

    if(isset($data)){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    curl_setopt ($ch, CURLOPT_AUTOREFERER, 1); 

    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 

    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

    curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);

    curl_setopt($ch, CURLOPT_HEADER, false);

    $file_contents = curl_exec($ch);

    curl_close($ch);

    return $file_contents;

}

function getNextDay($day,$nums=1,$str="Y-m-d 00:00:00"){

    $stamp=strtotime($day);

    $res=date($str,$stamp+$nums*60*60*24);

    return $res;
}

function getSql($line,$database='z2c'){

    if($line==='online'){
        $mysql_server_name = "rdsvzjrervzjrer.mysql.rds.aliyuncs.com"; //数据库服务器名称
        $mysql_username = "soil"; // 连接数据库用户名
        $mysql_password = "afd19267"; // 连接数据库密码
        $mysql_database = $database; // 数据库的名字
    }
    else if($line==='offline'){
        $mysql_server_name = "rdsemvaruemvaru.mysql.rds.aliyuncs.com"; //数据库服务器名称
        $mysql_username = "ceshi"; // 连接数据库用户名
        $mysql_password = "ceshi2015"; // 连接数据库密码
        $mysql_database = $database; // 数据库的名字
    }
    else if($line==='conan'){
        $mysql_server_name = "rds19ik613w3ow4o77vh.mysql.rds.aliyuncs.com"; //数据库服务器名称
        $mysql_username = "soil"; // 连接数据库用户名
        $mysql_password = "afd19267"; // 连接数据库密码
        $mysql_database = "conan"; // 数据库的名字
    }
    else if($line==='goods'){
        $mysql_server_name = "rds294f3kfsd4t0rj148.mysql.rds.aliyuncs.com"; //数据库服务器名称
        $mysql_username = "goods_read"; // 连接数据库用户名
        $mysql_password = "9a6b765d99c6801b2"; // 连接数据库密码
        $mysql_database = "db_base_goods"; // 数据库的名字
    }
    else if($line==='gdata'){
        $mysql_server_name = "rdso9fbkhl00n7m2cr14.mysql.rds.aliyuncs.com"; //数据库服务器名称
        $mysql_username = "dw_reader"; // 连接数据库用户名
        $mysql_password = "c6570e15fae6e7c4478"; // 连接数据库密码
        $mysql_database = "dw"; // 数据库的名字
    }
    else if($line==='log'){
        $mysql_server_name = "10.174.93.58"; //数据库服务器名称
        $mysql_username = "bdp_r"; // 连接数据库用户名
        $mysql_password = "bdp2015"; // 连接数据库密码
        $mysql_database = "olog"; // 数据库的名字
    }
    else if($line==='log2db'){
        $mysql_server_name = "rds6zrs2wp6cid9yae27.mysql.rds.aliyuncs.com"; //数据库服务器名称
        $mysql_username = "db_log2db_read"; // 连接数据库用户名
        $mysql_password = "6b0aa37bbddbf343"; // 连接数据库密码
        $mysql_database = "log2db"; // 数据库的名字
    }

    $sql=new mysqli($mysql_server_name,$mysql_username,$mysql_password,$mysql_database);

    if ($sql->connect_errno) {
        printf("Connect failed: %s\n", $sql->connect_error);
        exit();
    }

    return $sql;

}

function getTelphone($row){
    if(preg_match("/1[34578][0-9]{9}/",$row,$tel)===1){
        return $tel[0];
    }
    return "";
}

function array_iconv($data,  $output = 'utf-8') {  
    $encode_arr = array('UTF-8','ASCII','GBK','GB2312','BIG5','JIS','eucjp-win','sjis-win','EUC-JP');  
    $encoded = mb_detect_encoding($data, $encode_arr);  

    if (!is_array($data)) {  
        return mb_convert_encoding($data, $output, $encoded);  
    }  
    else {  
        foreach ($data as $key=>$val) {  
            $key = array_iconv($key, $output);  
            if(is_array($val)) {  
                $data[$key] = array_iconv($val, $output);  
            } else {  
                $data[$key] = mb_convert_encoding($data, $output, $encoded);  
            }  
        }  
        return $data;  
    }  
}  

function getAverge($sequence){
    if(!is_array($sequence)){
        return doubleval($sequence);
    }
    if(count($sequence)==0){
        return 0;
    }
    $num=0.0;
    $sum=0.0;
    foreach($sequence as $v){
        $num++;
        $sum+=$v;
    }
    return $sum/$num;
}

function getStdv($sequence,$aver){
    if(!is_array($sequence)){
        return 0;
    }
    if(count($sequence)==0){
        return 0;
    }
    $num=0.0;
    $sum=0.0;
    foreach($sequence as $v){
        $num++;
        $sum+=pow($v-$aver,2);
    }
    return sqrt($sum/$num);
}
?>
