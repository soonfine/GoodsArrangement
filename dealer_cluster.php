<?php
require_once('utils_func.php');

function getDistance($lng1, $lat1, $lng2, $lat2)
{
    //approximate radius of earth in meters
    
    $earthRadius = 6367000; 

    /*
     *      Convert these degrees to radians
     *      to work with the formula
     */

    $lat1 = ($lat1 * pi() ) / 180;
    $lng1 = ($lng1 * pi() ) / 180;

    $lat2 = ($lat2 * pi() ) / 180;
    $lng2 = ($lng2 * pi() ) / 180;

    /*
     *        Using the Haversine formula
     *        http://en.wikipedia.org/wiki/Haversine_formula
     *        calculate the distance
     */

    $calcLongitude = $lng2 - $lng1;
    $calcLatitude = $lat2 - $lat1;
    $stepOne = pow(sin($calcLatitude / 2), 2) 
        + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);  
    $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
    $calculatedDistance = $earthRadius * $stepTwo;

    return round($calculatedDistance);
}

function getCentrePoint($a,&$dealer){

    $coordinate=array(0.0,0.0);
    $num=0.0;

    foreach($a as $id => $v){
        $coordinate[0]+=$dealer[$id]['coordinate1'];
        $coordinate[1]+=$dealer[$id]['coordinate2'];
        $num++;
    }

    $coordinate[0]/=$num;
    $coordinate[1]/=$num;

    return $coordinate;
}

function isMerge($cid,$did,&$cluster,&$dealer){

    $a=$cluster[$cid];
    $b=$cluster[$did];

    foreach($a as $id1 => $v){
        foreach($b as $id2 => $vv){
            if($dealer[$id1]['city_short']!=$dealer[$id2]['city_short']){
                return false;
            }
        }
    }

    $centre1=getCentrePoint($a,$dealer);
    $centre2=getCentrePoint($b,$dealer);

    $centredis=getDistance(
            $centre1[0],
            $centre1[1],
            $centre2[0],
            $centre2[1]
        );

    if($centredis>3000){
        return false;
    }

    $c=array_keys($a+$b);

    $aver=0.0;
    $vari=0.0;
    $sum=0.0;
    $num=0;
    $max_d=0.0;
    $dis=array();


    foreach($c as $cid){
        foreach($c as $nid){
            if($cid===$nid){
                continue;
            }
            $dis[$num]=getDistance(
                $dealer[$cid]['coordinate1'],
                $dealer[$cid]['coordinate2'],
                $dealer[$nid]['coordinate1'],
                $dealer[$nid]['coordinate2']
            );
            $max_d=$max_d<$dis[$num] ? $dis[$num] : $max_d;
            $sum+=$dis[$num];
            $num++;
        }
    }

    if($num==0){
        var_dump($a,$b);
        exit();
    }

    $num=doubleval($num);

    $aver=$sum/$num;

    foreach($dis as $n => $dd){
        $vari+=pow(($dd-$aver),2);
    }
    $std=sqrt($vari/$num);

    if($max_d>3000||$std>2000){
        return false;
    }

    return array($aver,$std,$centredis);
}


function main(){

    $soil=getSql("online");
    $ceshi=getSql("offline");

    $dealer=array();
    $cluser=array();
    $averstd=array();
    $bestsolution=array(0,0,0);
    $clusterid=0;

    $sql_command = "SELECT id,city_short,coordinate1,coordinate2,delivery_area FROM dealer where layer=3 AND IsGold=1 AND IsTest=0 AND is_del=0";
    if ($result = $soil->query($sql_command)) {
        while($row =$result->fetch_assoc() ){ 
            $dealer[$row['id']]=$row;
            $averstd[$clusterid]=0;
            $cluster[$clusterid++]=array(
                $row['id'] => 1
            );
        }
        $result->close();
    }
    else {
        printf("Error: %s\n", $soil->error);
    }

    $bestsolution[1]=$clusterid;

    $bestsolution[2]=$bestsolution[0]/$bestsolution[1] + pow($bestsolution[1],1);

    $pq = new SplPriorityQueue();

    foreach($cluster as $cid => $c){
        if(empty($cluster[$cid])){
            continue;
        }
        foreach($cluster as $did => $d){
            if($cid>=$did){
                continue;
            }
            if(empty($cluster[$did])){
                continue;
            }
            $res=isMerge($cid,$did,$cluster,$dealer);
            if($res===false){
                continue;
            }
            $pq->insert(array($cid,$did,$res),-1*$res[2]);
        }
    }

    $pq->setExtractFlags(SplPriorityQueue::EXTR_DATA);

    $iterations=0;
    while($pq->valid()){

        $edge=$pq->extract();

        $cid=$edge[0];
        $did=$edge[1];
        $res=$edge[2];

        if(empty($cluster[$cid])||empty($cluster[$did])){
            continue;
        }

        $tmpsolution[0]=$bestsolution[0]-$averstd[$cid] - $averstd[$did] + $res[1];
        $tmpsolution[1]=$bestsolution[1]-1;
        $tmpsolution[2]=$tmpsolution[0]/($tmpsolution[1]) + pow($tmpsolution[1],1);

        $cluster[$clusterid]=$cluster[$cid]+$cluster[$did];
        $averstd[$clusterid]=$res[1];
        $cluster[$cid]=array();
        $averstd[$cid]=0;
        $cluster[$did]=array();
        $averstd[$did]=0;

        $bestsolution=$tmpsolution;

        foreach($cluster as $did => $d){
            if($clusterid===$did||empty($cluster[$did])){
                    continue;
                }
            $res=isMerge($clusterid,$did,$cluster,$dealer);
            if($res===false){
                continue;
            }
            $pq->insert(array($clusterid,$did,$res),-1*$res[2]);
        }
        $clusterid++;

        $iterations++;
    }

    $result=array();

    foreach($cluster as $cid => $c){
        foreach($c as $did => $dd){
            $result[]=array(
                $did,
                $cid,
                $dealer[$did]['coordinate1'],
                $dealer[$did]['coordinate2'],
                $dealer[$did]['delivery_area']
            );

        }
    }

    echo json_encode($result);

}

main();


?>
