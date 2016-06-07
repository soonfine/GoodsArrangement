<?php

require_once('CPredict.php');


function make_arff($feature,$scale,$fn){

    $fp=fopen($fn,"w");

    fprintf($fp,"@relation sales_forecasting\n\n");

    foreach($feature[0] as $fea){
        foreach($fea as $k => $v){
            if(is_numeric($v)){
                fprintf($fp,"@attribute %s numeric\n",$k);
            }
            else {
                fprintf($fp,"@attribute %s string\n",$k);
            }
        }
        break;
    }

    fprintf($fp,"\n\n@data\n\n");

    foreach($feature as $ff){
        foreach($ff as $fea){
            $str="";
            foreach($fea as $k => $v){
                $str.="$v,";
            }
            $str=trim($str,",");
            fprintf($fp,"$str\n");
        }
    }

    fclose($fp);
}

function make_ann($feature,$scale,$fn){

    $fp=fopen($fn,"w");

    $sum=0;
    foreach($feature as $fea){
        $sum+=count($fea);
    }

    fprintf($fp,"%d %d 1\n",$sum,count($scale)-1,1);

    foreach($feature as $ff){
        foreach($ff as $fea){
            $str="";
            foreach($scale as $k => $v){
                if(!isset($fea[$k])){
                    var_dump("error!",$fea);
                    exit();
                }
                if($k=="sales"){
                    continue;
                }
                $t=$fea[$k];
                $t=($t-$v[1])/($v[0]-$v[1]+1);
                $str.="$t ";
            }
            $sales=($fea['sales']-$scale['sales'][1])/($scale['sales'][0]-$scale['sales'][1]+1);
            $str=trim($str," ");
            fprintf($fp,"%s\n%f\n",$str,$sales);
        }

    }
    fclose($fp);
}

function main($argv){

    if(count($argv)!=6){
        printf("Usage :\n%s date goods_id cluster_file days mode\n\n",$argv[0]);
        return false;
    }

    $st=$argv[1];
    $gid=$argv[2];
    $days=$argv[4];

    $g=new GoodsArrange();
    $feature=array();

    for($i=0;$i<$days;$i++){

        $st=getNextDay($st,-1,"Y-m-d");

        $g->init($st,$gid,$argv[3]);

        $g->getGoodsInfo();
        $g->getDealerInfo();
        $g->getClusterInfo();

        if($argv[5]==0){
            $fea=$g->predict();

            $ceshi=getSql("offline");
            $sql_command = "SELECT dealer,date,occur,stockout FROM goods_stockout_info WHERE DATE='$st' AND goodsid='$gid';";
            echo "$sql_command\n";

            if ($result = $ceshi->query($sql_command)) {
                while($row =$result->fetch_assoc() ){ 
                    if(!isset($fea[$row['dealer']])){
                        continue;
                    }
                    $fea[$row['dealer']]['outrate']=doubleval($row['stockout'])/($row['occur']+1);
                }
                $result->close();
            }
            else {
                printf("Sql:%s Error: %s\n",$sql_command, $ceshi->error);
            }

            $gdata=getSql("gdata");

            $sql_command = "SELECT atdate,dealer_id,sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='$gid' AND atdate='$st';";
            echo "$sql_command\n";

            $sales_week=array();
            if ($result = $gdata->query($sql_command)) {
                while($row =$result->fetch_assoc() ){ 
                    if(!isset($fea[$row['dealer_id']])){
                        continue;
                    }
                    $fea[$row['dealer_id']]['sales']=doubleval($row['sale_qty']);
                }
                $result->close();
            }
            else {
                printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
            }


            foreach($fea as $k => $v){
                if(!isset($v['outrate'])||$v['outrate']>0.5){
                    unset($fea[$k]);
                    continue;
                }
                if(!isset($v['sales'])){
                    $fea[$k]['sales']=0;
                }
                unset($fea[$k]['outrate']);
            }

        }
        else {
            $fea=$g->cpredict();
            $dealer2Cluster=$g->dealer2cluster;

            $gdata=getSql("gdata");

            $sql_command = "SELECT atdate,dealer_id,sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='$gid' AND atdate='$st';";
            echo "$sql_command\n";

            $sales_week=array();
            if ($result = $gdata->query($sql_command)) {
                while($row =$result->fetch_assoc() ){ 
                    if(!isset($dealer2Cluster[$row['dealer_id']])){
                        continue;
                    }
                    $cid=$dealer2Cluster[$row['dealer_id']];
                    if(!isset($fea[$cid])){
                        continue;
                    }
                    if(!isset($fea[$cid]['sales'])){
                        $fea[$cid]['sales']=0;
                    }
                    $fea[$cid]['sales']+=doubleval($row['sale_qty']);
                }
                $result->close();
            }
            else {
                printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
            }

            foreach($fea as $k => $v){
                if(!isset($v['sales'])){
                    unset($fea[$k]);
                }
            }
        }
        
        printf("date:%s num:%d\n",$st,count($fea));

        $feature[]=$fea;
    }

    if(isset($feature[0])){
        $scale=array();
        if($argv[5]==0){
            $fn="$gid.scale";
        }
        else {
            $fn="$gid.c.scale";
        }
        if(file_exists($fn)){
            $fp=fopen($fn,"r");
            $context=fgets($fp);
            $scale=json_decode($context,true);
            fclose($fp);
        }
        else {
            foreach($feature as $ff){
                foreach($ff as $fea){
                    foreach($fea as $k => $v){
                        if($k=="date"||$k=="outrate"||$k=="id"){
                            continue;
                        }
                        if(!isset($scale[$k])){
                            $scale[$k]=array(0,99999999);
                        }
                        $scale[$k][0]=$scale[$k][0]>$v?$scale[$k][0]:$v;
                        $scale[$k][1]=$scale[$k][1]<$v?$scale[$k][1]:$v;
                    }

                }
            }
            $fp=fopen($fn,"w");
            fwrite($fp,json_encode($scale));
            fclose($fp);
        }

        $fn="feature_".$gid."_".$argv[5]."_".$argv[1]."_".$argv[4]."_".date("Ymd_hi");
        make_arff($feature,$scale,"$fn.arff");
        make_ann($feature,$scale,"$fn.ann");

    }


}

main($argv)

?>
