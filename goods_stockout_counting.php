<?php

require_once("utils_func.php");

function main($argv){

    if(count($argv)!=2){
        return ;
    }

    $dealer=array();

    $st=getNextDay($argv[1],0,"Ymd");

    $glog=getSql("log");

    $sql_command = "SELECT log_msg  FROM apibq_demand WHERE log_date='$st';";
    echo "$sql_command\n";

    if ($result = $glog->query($sql_command)) {
        while($row =$result->fetch_assoc() ){ 
            $data=json_decode($row['log_msg'],true);
            $goods=json_decode($data['goods'],true);
            if(!is_array($goods)){
                continue;
            }

            if(!isset($dealer[$data['dealerid']])){
                $dealer[$data['dealerid']]=array();
            }
            foreach($goods as $g => $v){
                if(!isset($dealer[$data['dealerid']][$g])){
                    $dealer[$data['dealerid']][$g]=array(0,0);
                }
                $dealer[$data['dealerid']][$g][0]++;
                if($v==0){
                    $dealer[$data['dealerid']][$g][1]++;
                }
            }
        }
        $result->close();
    }
    else {
        printf("Sql:%s Error: %s\n",$sql_command, $glog->error);
    }

    $st=getNextDay($argv[1],0);

    $ceshi=getSql("offline");

    foreach($dealer as $d => $goods){
        foreach($goods as $g => $v){
            $sql_command = "INSERT INTO goods_stockout_info (dealer,goodsid,date,occur,stockout) VALUES ('$d','$g','$st','".$v[0]."','".$v[1]."')  ON DUPLICATE KEY UPDATE occur='".$v[0]."',stockout='".$v[1]."';";
            echo "$sql_command\n";

            if (false == $ceshi->query($sql_command)) {
                printf("sql:%s Error: %s\n",$sql_command, $ceshi->error);
            }
        }

    }


}

main($argv);

?>
