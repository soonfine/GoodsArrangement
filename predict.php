<?php
require_once('CPredict.php');

function main($argv){

    if(count($argv)<5){
        printf("Usage :\n%s date goods_id cluster_file mode total\nmode: [ 0 plan][ 1 simulate][ 2 analyse]\n",$argv[0]);
        return false;
    }

    $gids=explode(",",$argv[2]);

    foreach($gids as $gid){

        $g=new GoodsArrange();
        $g->init($argv[1],$gid,$argv[3]);

        if($argv[4]==0){

            ob_start();

            $g->work($argv[5]);

            $output0=ob_get_contents();

            var_dump($g);

            $output=ob_get_contents();
            ob_end_clean();

            echo $output0;

            $fp=fopen($argv[2].".".$argv[1].".res","w");
            fwrite($fp,$output);
            fclose($fp);

        }
        else if($argv[4]==1){
            $g->simulate();
        }
        else if($argv[4]==2){
            $g->analyse();
        }


    }



}

main($argv);

?>
