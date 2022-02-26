<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
require_once ('jpgraph_plotline.php');
require_once ("jpgraph_date.php");
require_once ("jpgraph_regstat.php");
require_once ("jpgraph_bar.php");
require_once ('jpgraph_canvas.php');
include ("homeFunctions.php");


$file = explode(".php", __FILE__);
$file = explode("/", $file[0]);
$fileName = $file[sizeof($file)-1].".png";

if(isCli())
{
    $path = "pictures\\".$fileName;
    $path2 = "/var/www/html/picture/".$fileName;
	 $sleepTime = getConfig("SLEEP")+20;
}
else
{
    $path = "..\\pictures\\".$fileName;
    $path2 = "J:\\www\\pictures\\".$fileName;
    $sleepTime = 60;
}

do 
{
	if(isCli())
	{
		$time = time();
		print date('H:i:s',$time).", ".$fileName;
	}
	$debug          = false;
	$username		= getConfig("DBUSN");
	$password		= getConfig('DBPSW');
	$database		= getConfig('DBNAME');
	$serverHostName	= getConfig('DBIP');
    waitDbAlive($serverHostName,$username,$password,$database);
    $textColor      = 'gray:2.7';
    $frameColor     = 'black:1.1';
    $backGroundClr  = 'gray:0.43';
    $needleColor    = 'cornflowerblue';
    $tickColor      = 'white';
	$sensors 		= getSensorNames($username,$password,$database, $serverHostName); //From sensor configuration
	
	
    $sensorNameToShow = "WiSDir";
    $length     = 57.5;
    $ticLength  = 3;
    $length_M3  = ($length * 0.61);
    $ticScaleL  = 1.09;	
    $baseLength = $length*0.1;

    $canLength  = $length*3.8/2.0*1.0;
    $canHight   = $length*1.64*1.2;
    
	$graph = new CanvasGraph($canLength,$canLength,'auto');
	$graph->SetMargin(7,7,7,7);
	$graph->SetMarginColor($frameColor);
	$graph->SetColor($backGroundClr);
	
	$sensorId = getSensorId($sensorNameToShow, $username,$password,$database,$serverHostName);
    $deg = 90+-1*getCurr($sensorId, $username, $password, $serverHostName, $database);
    
    $graph->InitFrame();
    $tmp_O = -0.043*$length;

    for($deg_M=360;$deg_M>=0;$deg_M = $deg_M-18)
    { 
        $X_coord= (($length_M3)*cos(deg2rad($deg_M)));
        $Y_coord= (($length_M3)*sin(deg2rad($deg_M)));
        $X_stop_deg  = $length + $X_coord + $tmp_O;
        $Y_stop_deg  = $length - $Y_coord + $tmp_O;
        
        $X_coord= ($ticScaleL*$length*0.7*cos(deg2rad($deg_M)));
        $Y_coord= (($ticScaleL*$length*0.7)*sin(deg2rad($deg_M)));
        $X_stop_deg_M  = $length + $X_coord + $tmp_O;
        $Y_stop_deg_M  = $length - $Y_coord + $tmp_O;
        
        $graph->img->SetColor($tickColor);

        $t = new Text("",$X_stop_deg ,$Y_stop_deg);

        if($deg_M == 0)
        {
            $t = new Text("O",$X_stop_deg ,$Y_stop_deg+4);
        }
        elseif($deg_M == 90)
        {
            $t = new Text("N",$X_stop_deg-4 ,$Y_stop_deg);
        }
        elseif($deg_M == 180)
        {
            $t = new Text("V",$X_stop_deg-9 ,$Y_stop_deg+4);
        }
        elseif($deg_M == 270)
        {
            $t = new Text("S",$X_stop_deg-4 ,$Y_stop_deg+9);
        }
        elseif($deg_M == 360)
        {

        }
        else
        {
            $graph->img->Line($X_stop_deg_M,$Y_stop_deg_M,$X_stop_deg,$Y_stop_deg);
        }

        $t->SetFont(FF_ARIAL,FS_BOLD,8);
        $t->SetColor($textColor);
        $t->Align('left','bottom');
        $t->ParagraphAlign('left');
        $graph->AddText($t);        
    }

    $X_B_coord= (($baseLength*0.7)*cos(deg2rad(90-$deg)));
    $Y_B_coord= (($baseLength*0.7)*sin(deg2rad(90-$deg)));   
    $X_coord  = (($ticScaleL*$length*0.7)*cos(deg2rad($deg)));
    $Y_coord  = (($ticScaleL*$length*0.7)*sin(deg2rad($deg)));
    
    $X_stop_deg= $length + $X_coord + $tmp_O ;
    $Y_stop_deg= $length - $Y_coord;
        
    $p = array( $length + $tmp_O - $X_B_coord, $length-$Y_B_coord , 
                $X_stop_deg, $Y_stop_deg , 
                $length + $tmp_O + $X_B_coord, $length+$Y_B_coord ,
                $length+ $tmp_O , $length ); 
    $graph->img->SetColor($needleColor);
    $graph->img->FilledPolygon($p);

	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
        $graph->img->Stream($path2);		
		$utr = time()-$time;
		print ", "."$utr"."s sleep "."$sleepTime"."s\n";
        exit(1);
		sleep($sleepTime);
	}
    else
    {
        $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
        $graph->img->Stream($path2);
		sleep($sleepTime);
    }
		
}while (true);
	
?>
