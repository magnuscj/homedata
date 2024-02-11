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

sleep(60);

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

$resources = array("Fry_ko", "Kyl_ko", "Kyl_gr", "kylFrys", "Fry_ga", "Garage", "Skorst", "vaxthus");

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
    $frameColor     = 'gray:0.43';
    $backGroundClr  = 'gray:0.43';
   	$sensors 		= getSensorNames($username,$password,$database, $serverHostName); //From sensor configuration
	
    $sensorNameToShow = "Fry_ko";
    $length     = 57.5;
    $canLength  = $length*3.8/2.0*1.0;
    $canHight   = $length*1.64*1.2;
    
	$graph = new CanvasGraph($canLength,$canLength-5,'auto');
	$graph->SetMargin(7,7,7,7);
	$graph->SetMarginColor($frameColor);
	$graph->SetColor($backGroundClr);
    $graph->InitFrame();
   
    $Y=20;
    foreach ($resources as $resource)
    {
        $sensorId = getSensorId($resource, $username,$password,$database,$serverHostName);
        $temp = number_format(getCurr($sensorId,$username,$password,$serverHostName,$database),1);
        $tempValue = floatval($temp);
        $skip=0;
        $skift=0;

        if ((explode('_', $resource)[0] == "Fry") || $resource=="kylFrys")
        {
            $min=-25.0;
            $max=-10.0;
        }
        elseif (explode('_', $resource)[0] == "Kyl")
        {
            $min=4.0;
            $max=7.0;
        }
        elseif ($resource == "XSovrum")
        {
            $min=17.0;
            $max=21.0;
        }
        elseif ($resource == "Garage")
        {
            $min=0.5;
            $max=3.0;
            $skift=1;
        }
        elseif ($resource == "Skorst")
        {
            $min=18.0;
            $max=40.0;
        }
        elseif ($resource == "vaxthus")
        {
            $min=30.0;
            $max=38.0;
        }

        else
        {
            $skip=1;
        }

        if($skip==0)
        {
            $tempValue = $tempValue < $min ? $min : $tempValue;
            $tempValue = $tempValue > $max ? $max : $tempValue;

            $f = ($tempValue -$min)/($max-$min);
            $R = !$skift ? 255*$f : 255*(1-$f);
            $G = $skift ? 255*$f : 255*(1-$f);
            $B=0;

            $color = array($R, $G, $B);
            $resource = $resource=="kylFrys" ? "Fry_gr" : $resource;
            $t = new Text($resource);       
            $t->SetPos(10,$Y,'left');
            $t->SetFont(FF_ARIAL,FS_BOLD,$length*0.155);
            $t->SetColor($textColor);
            $t->Align('left','bottom');
            $graph->AddText($t);

            $te = new Text($temp);       
            $te->SetPos(80,$Y,'right');
            $te->SetFont(FF_ARIAL,FS_BOLD,$length*0.155);
            $te->SetColor($textColor);
            $te->Align('right','bottom');
            $graph->AddText($te);

            $graph->img->SetColor($color);
            $graph->img->FilledCircle($canLength - 15, -5 + $Y,4);

            $Y = $Y + 12;
        }

    }
    
   	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
        $graph->img->Stream($path2);		
		$utr = time()-$time;
		print ", "."$utr"."s sleep "."$sleepTime"."s\n";
        print $path2;
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
