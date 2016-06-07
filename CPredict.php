<?php

require_once('utils_func.php');

function cmp($a,$b){

    $v1=isset($a['plan'])?$a['plan']:0;
    $v2=isset($b['plan'])?$b['plan']:0;

    $p1=isset($a['h_stockout'])?$a['h_stockout']:0;
    $p2=isset($b['h_stockout'])?$b['h_stockout']:0;

    if($v1===$v2){
        return ($p2 < $p1)? -1:1;
    }
    return ($v2 < $v1)? -1:1;

}

function cmpr($a,$b){

    $v1=isset($a['fplan'])?$a['fplan']:0;
    $v2=isset($b['fplan'])?$b['fplan']:0;

    $p1=isset($a['plan'])?$a['plan']:0;
    $p2=isset($b['plan'])?$b['plan']:0;

    $v1=round(($p1-$v1)/($p1+1));
    $v2=round(($p2-$v2)/($p2+1));

    $s1=isset($a['h_sales_w'])?$a['h_sales_w']:0;
    $s2=isset($b['h_sales_w'])?$b['h_sales_w']:0;

    if($v1==$v2){
        return ($s2 < $s1)? -1:1;
    }

    return ($v2 < $v1)? -1:1;

}

function outputTable($user_info,$n){
    $res='';
    if($n===0){
        $res.="<tr>";
        foreach($user_info as $k => $v){
            $res.="<th>".$k."</th>";
        }
        $res.="</tr>";
    }

    $res.="<tr>";
    foreach($user_info as $v){
        if(!is_array($v)){
            $res.="<td>".$v."</td>";
            continue;
        }
        $res.="<td>";
        foreach($v as $k => $vv){
            $res.=$k."&nbsp;:&nbsp;<font style='color:red;font-weight:bold;'>".$vv."</font><br>";
        }
        $res.="</td>";
    }
    $res.="</tr>";

    return $res;
}

class GoodsArrange {
    
    public $dealer;
    public $goods;
    public $cluster;
    public $gid;
    public $dealer2cluster;
    public $cluster2dealer;

    private $order_accept;

    private $dealer_liner;
    private $dealer_ann;
    private $cluster_liner;
    private $scale;

    public $st;
    public $ed;
    public $pt;
    public $pd;
    public $yt;
    public $hd;
    public $ld;
    public $holiday;

    public $weather;

    function init($date,$goods_id,$cluster_file){

        $this->setDate($date);
        $this->getWeather();
        $this->getHoliday();
        $this->setGoodsId($goods_id);
        $this->loadCluster($cluster_file);
        $this->loadModel();
        $this->loadOrderInfo();

        return true;
    }

    function setGoodsId($gid){
        $this->gid=$gid;
        return true;
    }

    function setDate($date){
        $this->st=getNextDay($date,0,"Y-m-d");
        $this->ed=getNextDay($this->st,1,"Y-m-d");
        $this->pt=getNextDay($this->st,-2,"Y-m-d");
        $this->pd=getNextDay($this->pt,1,"Y-m-d");
        $this->yt=getNextDay($this->pt,-1,"Y-m-d");
        $this->hd=getNextDay($this->pt,-7,"Y-m-d");
        $this->ld=getNextDay($this->pt,-30,"Y-m-d");

        return true;
    }

    function loadCluster($fn){
        if(!file_exists($fn)){
            printf("$fn doesn`t exist!\n");
            return false;
        }
        $dealer2cluster;
        $cluster2dealer;

        $fp=fopen($fn,"r");
        $context=fgets($fp);
        $context=json_decode($context,true);
        foreach($context as $v){
            $dealer2cluster[$v[0]]=$v[1];
            if(!isset($cluster2dealer[$v[1]])){
                $cluster2dealer[$v[1]]=array();
            }
            $cluster2dealer[$v[1]][]=$v[0];
        }
        fclose($fp);

        $this->dealer2cluster=$dealer2cluster;
        $this->cluster2dealer=$cluster2dealer;

        return true;
    }

    function readLineModel($fn){

        $model=array();
        $fp=fopen($fn,"r");

        $started=0;
        while (($buffer = fgets($fp, 4096)) !== false) {
            $buffer=trim($buffer,"\n\r\t ");
            if(strcasecmp($buffer,"Linear Regression Model")==0){
                $started=1;
                continue;
            }
            if($started!=1){
                continue;
            }
            if(strncasecmp($buffer,"Time taken to build model",strlen("Time taken to build model"))==0){
                break;
            }
            $line=explode("*",$buffer);
            $line[0]=trim($line[0]," ");
            if(!is_numeric($line[0])){
                continue;
            }
            if(count($line)==1){
                $model['bias']=$line[0];
                continue;
            }
            $line[1]=trim($line[1]," +");
            $model[$line[1]]=doubleval($line[0]);
        }

        return $model;
    }

    function loadModel(){
        $fn=$this->gid.".model";
        if(file_exists($fn)){
            $this->dealer_liner=$this->readLineModel($fn);
        }
        else {
            printf("$fn doesn`t exist!\n");
            $this->dealer_liner=NULL;
        }
        $fn=$this->gid.".c.model";
        if(file_exists($fn)){
            $this->cluster_liner=$this->readLineModel($fn);
        }
        else {
            printf("$fn doesn`t exist!\n");
            $this->cluster_liner=NULL;
        }
        $fn=$this->gid.".net";
        if(file_exists($fn)){
            $this->dealer_ann=fann_create_from_file($fn);
        }
        else {
            printf("$fn doesn`t exist!\n");
            $this->dealer_ann=NULL;
        }
        $fn=$this->gid.".scale";
        if(file_exists($fn)){
            $fp=fopen($fn,"r");
            $cont=fgets($fp,"10240");
            $this->scale=json_decode($cont,true);
        }
        else {
            printf("$fn doesn`t exist!\n");
            $this->scale=NULL;
        }
        return true;
    }

    function loadOrderInfo(){
        $fn="order_info.".$this->st.".csv";
        if(!file_exists($fn)){
            printf("$fn doesn`t exist!\n");
            $this->order_accept=NULL;
        }
        else {

            $fp=fopen($fn,"r");
            while (($buffer = fgetcsv($fp, 1000, ",")) !== false) {
                $this->order_accept[$buffer[0]][$buffer[1]]=1;
            }
        }

        return true;
    }

    function getHoliday(){

        $k=getNextDay($this->st,0,"Ymd");
        
        $url="www.easybots.cn/api/holiday.php?d=".$this->st;

        $data=requestPost($url);

        $data=json_decode($data,true);

        $this->holiday=intval($data[$k]);

        return true;
    }

    function getWeather(){

        $weather=array();
        $ceshi=getSql("offline");

        $sql_command = "SELECT * from weather where date='".$this->st."';";
        echo "$sql_command\n";

        if ($result = $ceshi->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $wea=json_decode($row['weather'],true);
                $weather[$row['city']]['tem']=intval($wea['tem_h']);
                $weather[$row['city']]['wind']=$wea['wind_h']%10;

                switch(array_iconv($wea['wea_d'])){
                    case '晴':
                    case '多云':
                    case '阴':
                    case '雾':
                        $weather[$row['city']]['wea']=0;
                        break;
                    case '霾':
                    case '小雪':
                    case '小雨':
                    case '扬沙':
                    case '浮尘':
                    case '阵雨':
                    case '雷阵雨':
                        $weather[$row['city']]['wea']=1;
                        break;
                    default :
                        $weather[$row['city']]['wea']=2;
                        break;

                }
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $ceshi->error);
        }

        $sql_command = "SELECT * from weather where date='".$this->pd."';";
        echo "$sql_command\n";

        if ($result = $ceshi->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $wea=json_decode($row['weather'],true);
                $weather[$row['city']]['tem_d']=$weather[$row['city']]['tem']-$wea['tem_h'];
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $ceshi->error);
        }

        $this->weather=$weather;

        return true;
    }

    function getDealerInfo(){

        $dealer=array();

        $soil=getSql("online");

        $sql_command = "SELECT id,city_short AS city,number FROM dealer WHERE layer=3 AND IsGold=1 AND IsTest=0 AND is_del=0";
        echo "$sql_command\n";

        if ($result = $soil->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $dealer[$row['id']]=$row;
                $dealer[$row['id']]['h_orders']=0.0;
                $dealer[$row['id']]['h_orders_w']=0.0;
                $dealer[$row['id']]['h_sales']=0.0;
                $dealer[$row['id']]['h_sales_w']=0.0;
                $dealer[$row['id']]['h_sales_stdv_w']=0.0;
                $dealer[$row['id']]['m_sales']=0.0;
                $dealer[$row['id']]['m_recives']=0.0;
                $dealer[$row['id']]['m_refund']=0.0;
                $dealer[$row['id']]['h_stockout']=0.0;
                $dealer[$row['id']]['h_occur']=0.0;
                $dealer[$row['id']]['h_stockout_rate_w']=0.0;
                $dealer[$row['id']]['convey']=0.0;
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $soil->error);
        }

        $gdata=getSql("gdata");

        $sql_command = "SELECT atdate,dealer_id,finish_order FROM t_rpt_dealer_order WHERE atdate>='".$this->hd."' AND atdate<'".$this->pt."' ;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                if($row['atdate']==$this->yt){
                    $dealer[$row['dealer_id']]['h_orders']=doubleval($row['finish_order']);
                }
                $dealer[$row['dealer_id']]['h_orders_w']+=doubleval($row['finish_order']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT atdate,dealer_id,sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='".$this->gid."' AND atdate>='".$this->ld."' AND atdate <'".$this->pt."';";
        echo "$sql_command\n";

        $sales_week=array();
        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                if($row['atdate']==$this->yt){
                    $dealer[$row['dealer_id']]['h_sales']=doubleval($row['sale_qty']);
                }
                if($row['atdate']>=$this->hd){
                    $sales_week[$row['dealer_id']][]=doubleval($row['sale_qty']);
                }
                $dealer[$row['dealer_id']]['m_sales']+=doubleval($row['sale_qty']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }
        foreach($sales_week as $d => $v){
            while(count($v)<7){
                $v[]=0;
            }
            $dealer[$d]['h_sales_w']=getAverge($v);
            $dealer[$d]['h_sales_stdv_w']=getStdv($v,$dealer[$d]['h_sales_w']);
        }

        $sql_command = "SELECT dealer_id,SUM(com_cnt) AS recive FROM t_raw_sell_dealer WHERE goods_id='".$this->gid."' AND complete_date>='".$this->ld."' AND complete_date<'".$this->pt."' AND cargo_status=9 GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['m_recives']+=doubleval($row['recive']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT dealer_id,SUM(dh_cnt) AS recive FROM t_raw_sell_dealer WHERE goods_id='".$this->gid."' AND create_date>='".$this->yt."' AND create_date<'".$this->pt."' AND cargo_status!=10 AND cargo_status!=5 GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['convey']=doubleval($row['recive']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT dealer_id,SUM(applay_cnt) AS acnt FROM `t_raw_return_dealer`  WHERE goods_id='".$this->gid."' AND create_date>='".$this->ld."' AND create_date<'".$this->pt."' GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['m_refund']=doubleval($row['acnt']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $current=date("Y-m-d");
        if($current>$this->pt){
            //$sql_command = "SELECT dealer_id,goods_cnt FROM `t_snap_dealer_goods` WHERE goods_id='".$this->gid."' AND athour='15' AND state='1' AND atdate='".$this->pt."';";
            $sql_command = "SELECT atdate,dealer_id,stock_cnt AS goods_cnt FROM t_dealer_goods_sale WHERE goods_id='".$this->gid."' AND atdate='".$this->yt."';";
            echo "$sql_command\n";

            if ($result = $gdata->query($sql_command)) {
                while($row =$result->fetch_assoc() ){ 
                    if(!isset($dealer[$row['dealer_id']])){
                        continue;
                    }
                    $dealer[$row['dealer_id']]['stock']=doubleval($row['goods_cnt']);
                }
                $result->close();
            }
            else {
                printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
            }

        }
        else {
            $sql_command = "SELECT dealer_id,number FROM oak_product_dealer WHERE product_id='".$this->gid."' ;";
            echo "$sql_command\n";

            if ($result = $soil->query($sql_command)) {
                while($row =$result->fetch_assoc() ){ 
                    if(!isset($dealer[$row['dealer_id']])){
                        continue;
                    }
                    $dealer[$row['dealer_id']]['stock']=doubleval($row['number']);
                }
                $result->close();
            }
            else {
                printf("Sql:%s Error: %s\n",$sql_command, $soil->error);
            }
        }

        $ceshi=getSql("offline");
        $sql_command = "SELECT dealer,date,occur,stockout FROM goods_stockout_info WHERE DATE>='".$this->hd."' AND DATE<'".$this->pt."' AND goodsid='".$this->gid."';";
        echo "$sql_command\n";

        $stockout_week=array();
        if ($result = $ceshi->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer']])){
                    continue;
                }
                if($row['date']>=$this->yt){
                    $dealer[$row['dealer']]['h_stockout']=doubleval($row['stockout']);
                    $dealer[$row['dealer']]['h_occur']=doubleval($row['occur']);
                }
                $stockout_week[$row['dealer']][]=doubleval($row['stockout'])/doubleval($row['occur']+1);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $ceshi->error);
        }
        foreach($stockout_week as $d => $v){
            $dealer[$d]['h_stockout_rate_w']=getAverge($v);
        }

        $this->dealer=$dealer;
        return true;

    }

    function getGoodsInfo(){

        $goods=array();
        $promotion=array();
        $goods_str=$this->gid;

        $soil=getSql("online");
        $sql_command = "SELECT promo_cond,promo_value,area_option FROM `hs_promotion` WHERE time_start<='".$this->st."' AND time_end>'".$this->st."';";
        echo "$sql_command\n";

        if ($result = $soil->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $cond=json_decode($row['promo_cond'],true);
                $value=json_decode($row['promo_value'],true);
                if($cond[0]['id']!=$this->gid){
                    continue;
                }
                $goods_str.=",".$value[0]['id'];
                $promotion[]=$row;
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $soil->error);
        }

        $gdata=getSql("gdata");
        $sql_command = "SELECT goods_id,city_id,sell_price FROM t_snap_city_goods WHERE atdate='".$this->pt."' AND athour='1' AND goods_id in ($goods_str);";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $goods[$row['goods_id']][$row['city_id']]['price']=doubleval($row['sell_price']);
                $goods[$row['goods_id']][$row['city_id']]['unit']=1;
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        foreach($promotion as $row){
            $cond=json_decode($row['promo_cond'],true);
            $value=json_decode($row['promo_value'],true);

            $city=explode(",",$row['area_option']);

            $id1=$cond[0]['id'];
            $id2=$value[0]['id'];

            foreach($city as $c){
                if(!isset($goods[$id1][$c])||!isset($goods[$id2][$c])){
                    continue;
                }
                $goods[$id1][$c]['promotion']=$goods[$id2][$c]['price']*$value[0]['num']/($goods[$id1][$c]['price']*$cond[0]['num']);
                if($id1==$id2){
                    $goods[$id1][$c]['unit']++;
                }
            }
        }

        $this->goods=$goods;
        return true;
    }

    function getClusterInfo(){

        $cluster=array();

        foreach($this->cluster2dealer as $c => $ds){

            $cluster[$c]['id']=$c;
            $cluster[$c]['cnum']=count($ds);
            $cluster[$c]['h_orders']=0.0;
            $cluster[$c]['h_orders_w']=0.0;
            $cluster[$c]['h_sales']=0.0;
            $cluster[$c]['h_sales_w']=0.0;
            $cluster[$c]['m_sales']=0.0;
            $cluster[$c]['m_recives']=0.0;
            $cluster[$c]['m_refund']=0.0;
            $cluster[$c]['h_stockout']=0.0;
            $cluster[$c]['h_occur']=0.0;

            foreach($ds as $d){
                if(!isset($this->dealer[$d]['stock'])){
                    continue;
                }
                $cluster[$c]['city']=$this->dealer[$d]['city'];
                $cluster[$c]['h_orders']+=$this->dealer[$d]['h_orders'];
                $cluster[$c]['h_orders_w']+=$this->dealer[$d]['h_orders_w'];
                $cluster[$c]['h_sales']+=$this->dealer[$d]['h_sales'];
                $cluster[$c]['h_sales_w']+=$this->dealer[$d]['h_sales_w'];
                $cluster[$c]['m_sales']+=$this->dealer[$d]['m_sales'];
                $cluster[$c]['m_recives']+=$this->dealer[$d]['m_recives'];
                $cluster[$c]['m_refund']+=$this->dealer[$d]['m_refund'];
                $cluster[$c]['h_stockout']+=$this->dealer[$d]['h_stockout'];
                $cluster[$c]['h_occur']+=$this->dealer[$d]['h_occur'];
            }
        }

        $this->cluster=$cluster;
        return true;
    }


    function predict(){

        $feature=array();

        $num=0;

        foreach($this->dealer as $d => $v){
            if(!isset($v['stock'])){
                continue;
            }
            if(!isset($this->goods[$this->gid][$v['city']])){
                continue;
            }

            $num++;

            $fea=array();

            $fea['date']=$this->st;
            $fea['id']=$d;

            $fea['price']=$this->goods[$this->gid][$v['city']]['price'];
            $fea['promotion']=0;
            if(isset($this->goods[$this->gid][$v['city']]['promotion'])){
                $fea['promotion']=$this->goods[$this->gid][$v['city']]['promotion'];
            }

            $fea['h_orders']=$v['h_orders'];
            $fea['h_orders_w']=$v['h_orders_w'];
            $fea['h_sales']=$v['h_sales'];
            $fea['h_sales_w']=$v['h_sales_w'];
            $fea['h_sales_stdv_w']=$v['h_sales_stdv_w'];
            $fea['m_sales']=$v['m_sales'];

            if(isset($this->dealer2cluster[$d])){

                $cid=$this->dealer2cluster[$d];

                $fea['c_h_orders']=$this->cluster[$cid]['h_orders'];
                $fea['c_h_orders_w']=$this->cluster[$cid]['h_orders_w'];
                $fea['c_h_sales']=$this->cluster[$cid]['h_sales'];
                $fea['c_h_sales_w']=$this->cluster[$cid]['h_sales_w'];
                $fea['c_m_sales']=$this->cluster[$cid]['m_sales'];
            }
            else {
                $fea['c_h_orders']=$v['h_orders'];
                $fea['c_h_orders_w']=$v['h_orders_w'];
                $fea['c_h_sales']=$v['h_sales'];
                $fea['c_h_sales_w']=$v['h_sales_w'];
                $fea['c_m_sales']=$v['m_sales'];
            }

            $fea['m_recives']=$v['m_recives'];
            $fea['m_refund']=$v['m_refund'];
            $fea['h_stockout']=$v['h_stockout'];
            $fea['h_occur']=$v['h_occur'];
            $fea['h_stockout_rate_w']=$v['h_stockout_rate_w'];

            $fea['stock']=$v['stock'];

            $fea['holiday']=$this->holiday;

            $fea['wea']=$this->weather[$v['city']]['wea'];
            $fea['tem']=$this->weather[$v['city']]['tem'];
            $fea['tem_d']=$this->weather[$v['city']]['tem_d'];
            $fea['wind']=$this->weather[$v['city']]['wind'];

            if($fea['h_sales_w']<1){
                $this->dealer[$d]['Lpredict']=$fea['h_sales_w'];
                $this->dealer[$d]['Apredict']=$fea['h_sales_w'];
                continue;
            }

            if($this->dealer_liner!==NULL){
                $Lpredict=0.0;
                foreach($this->dealer_liner as $k => $vv){
                    if(!isset($fea[$k])){
                        continue;
                    }
                    $Lpredict+=$fea[$k]*$vv;
                }
                $Lpredict+=$this->dealer_liner['bias'];
                $this->dealer[$d]['Lpredict']=$Lpredict;
            }

            if($this->dealer_ann!=NULL){
                $ann_in=array();
                foreach($this->scale as $k => $vv){
                    $ann_in[]=($fea[$k]-$vv[0])/($vv[1]+1);
                }
                $ann_out=fann_run($this->dealer_ann,$ann_in);
                $this->dealer[$d]['Apredict']=$ann_out[0];
            }

            $feature[$d]=$fea;

        }

        printf("<br>num:%d before_filter:%d<br>\n",$num,count($feature));

        return $feature;

    }

    function cpredict(){

        $feature=array();

        foreach($this->cluster as $c => $v){
            if(!isset($v['city'])){
                continue;
            }
            if(!isset($this->goods[$this->gid][$v['city']])){
                continue;
            }

            $fea=array();

            $fea['date']=$this->st;
            $fea['id']=$c;
            $fea['cnum']=$v['cnum'];

            $fea['price']=$this->goods[$this->gid][$v['city']]['price'];
            $fea['promotion']=0;
            if(isset($this->goods[$this->gid][$v['city']]['promotion'])){
                $fea['promotion']=$this->goods[$this->gid][$v['city']]['promotion'];
            }

            $fea['h_orders']=$v['h_orders'];
            $fea['h_orders_w']=$v['h_orders_w'];
            $fea['h_sales']=$v['h_sales'];
            $fea['h_sales_w']=$v['h_sales_w'];
            $fea['m_sales']=$v['m_sales'];

            $fea['m_recives']=$v['m_recives'];
            $fea['m_refund']=$v['m_refund'];
            $fea['h_stockout']=$v['h_stockout'];
            $fea['h_occur']=$v['h_occur'];

            $fea['holiday']=$this->holiday;

            $fea['wea']=$this->weather[$v['city']]['wea'];
            $fea['tem']=$this->weather[$v['city']]['tem'];
            $fea['tem_d']=$this->weather[$v['city']]['tem_d'];
            $fea['wind']=$this->weather[$v['city']]['wind'];

            if($fea['h_sales_w']<$fea['cnum']){
                $this->cluster[$c]['Lpredict']=$fea['h_sales_w'];
                $this->cluster[$c]['Apredict']=$fea['h_sales_w'];
                continue;
            }

            if($this->cluster_liner!==NULL){
                $Lpredict=0.0;
                foreach($this->cluster_liner as $k => $vv){
                    if(!isset($fea[$k])){
                        continue;
                    }
                    $Lpredict+=$fea[$k]*$vv;
                }
                $Lpredict+=$this->cluster_liner['bias'];
                $this->cluster[$c]['Lpredict']=$Lpredict;
            }

            $feature[$c]=$fea;

        }

        return $feature;

    }

    function getHyperResult(){

        foreach($this->dealer as $d => $v){
            if(isset($v['Lpredict'])){
                $this->dealer[$d]['predict']=$v['Lpredict']>0?$v['Lpredict']:0;
            }
            if(isset($v['Apredict'])){
                $this->dealer[$d]['predict']=$v['Apredict']>0?$v['Apredict']:0;
            }
        }

        foreach($this->cluster2dealer as $c => $ds){
            if(!isset($this->cluster[$c]['Lpredict'])){
                continue;
            }
            $this->cluster[$c]['predict']=$this->cluster[$c]['Lpredict']>0?$this->cluster[$c]['Lpredict']:0;
            $sum=0.0;
            foreach($ds as $d){
                if(!isset($this->dealer[$d]['predict'])){
                    continue;
                }
                $sum+=$this->dealer[$d]['predict'];
            }
            foreach($ds as $d){
                if(!isset($this->dealer[$d]['predict'])){
                    continue;
                }
                $this->dealer[$d]['Hpredict']=$this->cluster[$c]['predict']*$this->dealer[$d]['predict']/($sum+1);
            }
        }

        return true;
    }

    function getPlan($total){

        if($total<=0){
            return false;
        }

        $res=array();
        $dealer=array();
        $offer=0;
        $plan_num=0;
        $predict_num=0;
        $deny=0;

        foreach($this->dealer as $d => $v){
            if(!isset($v['stock'])){
                continue;
            }

            $unit=$this->goods[$this->gid][$v['city']]['unit'];

            $plan=0;

            if(isset($this->dealer[$d]['Hpredict'])){
                //factor
                $refund_rate=1-$v['m_refund']/($v['m_recives']+1);
                $this->dealer[$d]['refund_fac']=$refund_rate>0?$refund_rate:0;

                $this->dealer[$d]['stockout_fac']=$v['h_stockout_rate_w'];

                $offline_rate=($v['m_recives']-$v['m_sales']-$v['m_refund']-$v['stock'])/($v['m_recives']+1);
                $this->dealer[$d]['offline_fac']=$offline_rate>0?$offline_rate:0;

                //formulate
                $mutil=3*$this->dealer[$d]['refund_fac']+$this->dealer[$d]['stockout_fac']+$this->dealer[$d]['offline_fac'];

                //result
                $plan=$mutil*$v['Hpredict'];

                $predict_num+=$v['Hpredict'];
            }

            if($v['h_sales_w']>0&&$plan<2*$unit){
                $plan=2*$unit;
            }
            if($v['m_recives']==0){
                $plan=$unit;
            }

            $plan-=$v['stock'];

            $this->dealer[$d]['plan']=$plan>0?$plan:0;

            $plan_num+=$this->dealer[$d]['plan'];

            if($this->order_accept!=NULL&&!isset($this->order_accept[intval($v['number'])][$this->gid])){
                $deny++;
                continue;
            };

            $dealer[$d]=$this->dealer[$d];

        }


        usort($dealer,"cmp");

        $sum_sales=0.0;
        $remain=$total;
        foreach($dealer as $d => $v){
            $unit=$this->goods[$this->gid][$v['city']]['unit'];

            $fplan=$total*$v['plan']/($plan_num+1);

            if($fplan>2*$unit && $fplan+$v['stock']+$v['convey']>6*$v['h_sales_w']){
                $fplan=6*$v['h_sales_w']-$v['stock']-$v['convey'];
                $fplan=$fplan>0?$fplan:0;
            }

            $fplan=round($fplan/$unit)*$unit;
            if($fplan>$remain){
                $fplan=$remain;
            }

            $dealer[$d]['fplan']=$fplan;
            $remain-=$fplan;

            $sum_sales+=$v['h_sales_w'];
        }

        usort($dealer,"cmpr");

        $tremain=$remain;
        $last=0;

        while($remain<$total&&$remain!=$last){
            $last=$remain;
            foreach($dealer as $d => $v){
                $unit=$this->goods[$this->gid][$v['city']]['unit'];
                $give=ceil(($tremain*$v['h_sales_w']/$sum_sales)/$unit)*$unit;
                if($remain<$unit){
                    continue;
                }
                if($v['fplan']+$v['stock']+$v['convey']>6*$v['h_sales_w']){
                    continue;
                }
                if($remain<$give){
                    $give=$remain;
                }
                $dealer[$d]['fplan']+=$give;
                $remain-=$give;
            }
        }

        foreach($dealer as $d => $v){
            printf("id:%s gid:%d unit:%d sale:%f stock:%d convey:%d plan:%f fplan:%f\n",$v['number'],$this->gid,$this->goods[$this->gid][$v['city']]['unit'],$v['h_sales_w'],$v['stock'],$v['convey'],$v['plan'],$v['fplan']);
        }

        $fn="dealer_".$this->gid."_".$this->st."_".date("Ymd").".csv";
        $fp=fopen($fn,"w");

        $idx=0;
        foreach($dealer as $v){
            $unit=$this->goods[$this->gid][$v['city']]['unit'];
            $d=$v['id'];
            $this->dealer[$d]['fplan']=$v['fplan'];
            if($v['fplan']==0){
                continue;
            }
            $v['fplan']/=$unit;
            $arr=array($v['number'],$this->gid,$v['fplan']);
            $res[$v['number']][$this->gid]=$v['fplan'];
            fputcsv($fp,$arr);
            $offer+=$v['fplan'];
        }

        fclose($fp);

        printf("<br>predict :%f plan: %f remain: %d offer: %d deny:%d<br>\n",$predict_num,$plan_num,$tremain,$offer,$deny);

        return $res;
    }

    function work($total){

        if($total<=0){
            return false;
        }
    
        $this->getGoodsInfo();
        $this->getDealerInfo();
        $this->getClusterInfo();
        $this->predict();
        $this->cpredict();
        $this->getHyperResult();
        $this->getPlan($total);

        return true;
    }


    function simulate(){

        $total=0;

        $gdata=getSql("gdata");

        $sql_command = "SELECT dealer_id,SUM(com_cnt) AS recive FROM t_raw_sell_dealer WHERE goods_id='".$this->gid."' AND complete_date='".$this->pd."' AND cargo_status=9 GROUP BY complete_date;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $total=doubleval($row['recive']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $this->work($total);

        $dealer=$this->dealer;

        $sql_command = "SELECT dealer_id,SUM(sale_qty) as sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='".$this->gid."' AND atdate>='".$this->pd."' AND atdate <'".$this->ed."'  group by dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['sales']=doubleval($row['sale_qty']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT dealer_id,SUM(com_cnt) AS recive FROM t_raw_sell_dealer WHERE goods_id='".$this->gid."' AND complete_date='".$this->pd."' AND cargo_status=9 GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['recive']=doubleval($row['recive']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT atdate,dealer_id,stock_cnt FROM t_dealer_goods_sale WHERE goods_id=".$this->gid." AND atdate='".$this->pt."' GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['base']=doubleval($row['stock_cnt']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }


        $dealer_sum=0;
        $sales_sum=0;
        $plan_sum=$total;
        $stockout1=0;
        $stockout2=0;
        $sales1=0;
        $sales2=0;
        $remain1=0;
        $remain2=0;

        foreach($dealer as $d => $v){
            if(!isset($v['base'])){
                continue;
            }
            if(!isset($v['fplan'])){
                $v['fplan']=0;
            }
            if(!isset($v['sales'])){
                $v['sales']=0;
            }
            if(!isset($v['recive'])){
                $v['recive']=0;
            }
            $dealer_sum++;
            $sales_sum+=$v['sales'];
            if($v['sales']>=$v['base']+$v['recive']){
                $stockout1++;
                $sales1+=$v['base']+$v['recive'];
            }
            else {
                $sales1+=$v['sales'];
                $remain1+=$v['base']+$v['recive']-$v['sales'];
            }
            if($v['sales']>=$v['base']+$v['fplan']){
                $stockout2++;
                $sales2+=$v['base']+$v['fplan'];
            }
            else {
                $sales2+=$v['sales'];
                $remain2+=$v['base']+$v['fplan']-$v['sales'];
            }
        }

        printf("date %s dealer_sum:%d sales_sum:%f plan_sum:%d\n",$this->st,$dealer_sum,$sales_sum,$plan_sum);
        printf("stockout:%d sales:%f remain:%d\n",$stockout1,$sales1,$remain1);
        printf("stockout:%d sales:%f remain:%d\n",$stockout2,$sales2,$remain2);

        return true;
    }

    function analyse(){

        $dealer=array();

        $ceshi=getSql("offline");

        $sql_command = "SELECT dealer,date,occur,stockout FROM goods_stockout_info WHERE DATE='".$this->st."' AND goodsid='".$this->gid."'  AND dealer NOT IN (997,3248);";
        echo "$sql_command\n";

        if ($result = $ceshi->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $dealer[$row['dealer']]['id']=$row['dealer'];
                $dealer[$row['dealer']]['stockout']=doubleval($row['stockout']);
                $dealer[$row['dealer']]['occur']=doubleval($row['occur']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $ceshi->error);
        }

        $sql_command = "SELECT dealer,date,occur,stockout FROM goods_stockout_info WHERE DATE>='".$this->pd."' AND DATE<'".$this->ed."' AND goodsid='".$this->gid."';";
        echo "$sql_command\n";

        if ($result = $ceshi->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer']])){
                    continue;
                }
                if(strncmp($row['date'],$this->pd,strlen($this->pd))==0){
                    $dealer[$row['dealer']]['y_st_rate']=doubleval($row['stockout'])/($row['occur']+1);
                }
                if(strncmp($row['date'],$this->st,strlen($this->st))==0){
                    $dealer[$row['dealer']]['c_st_rate']=doubleval($row['stockout'])/($row['occur']+1);
                }
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $ceshi->error);
        }

        $soil=getSql("online");

        $sql_command = "SELECT id,city_short AS city,number FROM dealer WHERE layer=3 AND IsGold=1 AND IsTest=0 AND is_del=0";
        echo "$sql_command\n";

        if ($result = $soil->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['id']])){
                    continue;
                }
                $dealer[$row['id']]['number']=$row['number'];
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $soil->error);
        }

        $gdata=getSql("gdata");

        $sql_command = "SELECT dealer_id,SUM(sale_qty) as sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='".$this->gid."' AND atdate>='".$this->pd."' AND atdate <'".$this->ed."'  group by dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['sales']=doubleval($row['sale_qty']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT atdate,dealer_id,sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='".$this->gid."' AND atdate>='".$this->pd."' AND atdate <'".$this->ed."';";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                if($row['atdate']==$this->pd){
                    $dealer[$row['dealer_id']]['y_sales']=doubleval($row['sale_qty']);
                }
                if($row['atdate']==$this->st){
                    $dealer[$row['dealer_id']]['c_sales']=doubleval($row['sale_qty']);
                }
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT dealer_id,SUM(com_cnt) AS recive FROM t_raw_sell_dealer WHERE goods_id='".$this->gid."' AND complete_date='".$this->pd."' AND cargo_status=9 GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['recive']=doubleval($row['recive']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT atdate,dealer_id,stock_cnt FROM t_dealer_goods_sale WHERE goods_id=".$this->gid." AND atdate='".$this->pt."' GROUP BY dealer_id;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $dealer[$row['dealer_id']]['base']=doubleval($row['stock_cnt']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $stockout=0;
        $sales=0;
        $recive=0;
        $stock=0;
        $sales_dealer=0;

        $sql_command = "SELECT dealer_id,sale_qty FROM t_rpt_dealer_goods_4 WHERE quota=3 AND goods_id='".$this->gid."' AND atdate='".$this->st."';";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                if(!isset($dealer[$row['dealer_id']])){
                    continue;
                }
                $sales+=doubleval($row['sale_qty']);
                $sales_dealer++;
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT SUM(com_cnt) AS recive FROM t_raw_sell_dealer WHERE goods_id='".$this->gid."' AND complete_date='".$this->pd."' AND cargo_status=9;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $recive=doubleval($row['recive']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $sql_command = "SELECT atdate,SUM(stock_cnt) as stock_cnt FROM t_dealer_goods_sale WHERE goods_id=".$this->gid." AND atdate='".$this->st."' GROUP BY atdate;";
        echo "$sql_command\n";

        if ($result = $gdata->query($sql_command)) {
            while($row =$result->fetch_assoc() ){ 
                $stock=doubleval($row['stock_cnt']);
            }
            $result->close();
        }
        else {
            printf("Sql:%s Error: %s\n",$sql_command, $gdata->error);
        }

        $stockout_dealer=0;

        $res=array(
            "unexpect" => array(0,0),
            "deny" => array(0,0),
            "stockout" => array(0,0),
            "other" => array(0,0),
            "deteriorate" => array(0,0)
        );


        foreach($dealer as $d => $v){
            if($v['stockout']==0){
                continue;
            }
            if(!isset($v['sales'])){
                $v['sales']=0;
            }
            if(!isset($v['recive'])){
                $v['recive']=0;
            }
            if(!isset($v['y_sales'])){
                $v['y_sales']=0;
            }
            if(!isset($v['c_sales'])){
                $v['c_sales']=0;
            }
            if(!isset($v['y_st_rate'])){
                $v['y_st_rate']=0;
            }
            if(!isset($v['c_st_rate'])){
                $v['c_st_rate']=0;
            }
            $stockout+=$v['stockout'];
            $number=intval($v['number']);
            if(!isset($v['base'])){
                $res['unexpect'][0]+=$v['stockout'];
                $res['unexpect'][1]++;
            }
            else if($this->order_accept!=NULL&&!isset($this->order_accept[intval($number)])){
                $res['deny'][0]+=$v['stockout'];
                $res['deny'][1]++;
            }
            else if($v['sales']>=$v['base']+$v['recive']){
                $res['stockout'][0]+=$v['stockout'];
                $res['stockout'][1]++;
            }
            else {
                $res['other'][0]+=$v['stockout'];
                $res['other'][1]++;
            }
            if($v['y_sales']>$v['c_sales']&&$v['y_st_rate']<$v['c_st_rate']){
                $res['deteriorate'][0]+=$v['stockout'];
                $res['deteriorate'][1]++;
                //var_dump($v);
            }

            $stockout_dealer++;
        }

        printf(chr(27)."[1m".chr(27)."[32m"."[%s][stockout:%d sotckout_dealer:%d stock:%d recive:%d sales:%d sale_dealers:%d]\n".chr(27)."[0m",
            $this->st,$stockout,$stockout_dealer,$stock,$recive,$sales,$sales_dealer);

        foreach($res as $k => $v){
            printf(chr(27)."[1m".chr(27)."[32m"."[%s]%d %f %d\n".chr(27)."[0m",$k,$v[0],$v[0]/($stockout+1),$v[1]);
        }


        return true;
    }

}


?>
