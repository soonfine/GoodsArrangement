<?php

require_once("utils_func.php");

function main($argv){

    $city=array(
        '北京' => 2,
        '上海' => 21,
        '广州' => 424,
        '天津' => 43,
        '深圳' => 524,
        '武汉' => 1321,
        '南京' => 1644,
        '杭州' => 3134,
    );

    if(count($argv)!=2){
        return ;
    }

    $conn=getSql("offline");

    $days=intval($argv[1]);
    $st=getNextDay(date("Y-m-d"),$days);

    foreach($city as $c => $v){
        $url="http://php.weather.sina.com.cn/xml.php?password=DJOYnieT8234jlsK&day=$days&city=".urlencode(array_iconv($c,"GBK"));
        $wea=requestPost($url);
        $wea=simplexml_load_string($wea);
        $wea=json_decode(json_encode($wea),TRUE);
        $data=array();
        $data['date']=$st;
        $data['wea_d']=$wea['Weather']['status1'];
        $data['wea_n']=$wea['Weather']['status2'];
        $data['tem_h']=$wea['Weather']['temperature1'];
        $data['tem_l']=$wea['Weather']['temperature2'];
        $data['wind_h']=preg_replace("/[^0-9]/","",$wea['Weather']['power1']);
        $data['wind_l']=preg_replace("/[^0-9]/","",$wea['Weather']['power2']);
        $weather=addslashes(json_encode($data));

        $sql_command = "INSERT INTO weather (date,weather,city) VALUES ('$st','$weather','$v')  ON DUPLICATE KEY UPDATE weather='$weather';";
        echo "$sql_command\n";

        if (false == $conn->query($sql_command)) {
            printf("sql:%s Error: %s\n",$sql_command, $conn->error);
        }
    }


}

main($argv);

?>
