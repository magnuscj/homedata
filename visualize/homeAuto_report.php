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

$file = explode('.', __FILE__);
$file = explode('/', $file[0]);
$fileName = $file[sizeof($file)-1].".png";

if(isCli())
{
    $path2 = "/var/www/html/picture/".$fileName; 
    $sleepTime = getConfig("SLEEP")+20;
}
else
{
    $path2 = "/var/www/html/picture/".$fileName; 
    $sleepTime = 60;
}

do 
{
	if(isCli())
	{
		$time = time();
		print date('H:i:s',$time).", ".$fileName;
	}
	
	$username       = getConfig("DBUSN");
	$password       = getConfig('DBPSW');
	$database       = getConfig('DBNAME');
	$serverHostName = getConfig('DBIP');
	waitDbAlive($serverHostName,$username,$password,$database);
   	$textColor      = 'gray:2.7';
  	$frameColor     = 'black:1.1';
   	$backGroundClr  = 'gray:0.43';
	$sensors        = getSensorNames($username,$password,$database, $serverHostName); //From sensor configuration
	//Index names for the sensor configuration db table
	$colID          = 0;
	$colName        = 1;
	$colColor       = 2;
	$colVisible     = 3;
	$colType        = 4;
	$noOfFlowGraphs = 0;
	$txt            ="";
	$txt2           ="";
	$i              = 0;	//General counter/index variable
	$infoStart_Y    = 47;
	$senNo          = 0;
	$ttime = $ftime = date('H:i',time());
	$tdate          = date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
   	$fdate          = date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	$kwhPosDelta    = 0;
	$moiPosDelta    = 0;
	
	$graph = new CanvasGraph(405,425,'auto');
   	$graph->SetMarginColor($frameColor);
	$graph->SetMargin(5,6,6,6);
   	$graph->SetColor($backGroundClr);
   	$graph->initFrame();

	$t2 = new Text($tdate.", ".date("H:i"),356,409);
	$t2->SetFont(FF_ARIAL,FS_NORMAL,8);
	$t2->SetColor('gray:0.63');
	$t2->Align('center','top');
	$t2->ParagraphAlign('center');
	$graph->AddText($t2);
	$i=0;
	foreach($sensors[$colID] as $sensorId)
	{
		$name = $sensors[$colName][$senNo];
		if($sensors[$colType][$senNo] == "temp")
		{
         if((   $name == "kylFrys" && (getCurr($sensorId, $username, $password, $serverHostName, $database)> -15)) 
             || $name == "Inne" 
             || $name == "Ute" 
             || $name == "Sovrum" 
             ||($name == "Skorst" && (getCurr($sensorId, $username, $password, $serverHostName, $database)> 30)))
         {
            $sensorName= $sensors[$colName][$senNo];
            $t = new Text($sensorName,10,$infoStart_Y + $i*70-30);
            $t->SetFont(FF_ARIAL,FS_BOLD,15);
            $t->SetColor($textColor);
            $t->Align('left','top');	
            $t->ParagraphAlign('left');
            $graph->AddText($t);       
      
            $sensorValue= number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),1).'°';
            $t = new Text($sensorValue,240,$infoStart_Y-32 + $i*70);
            $t->SetFont(FF_ARIAL,FS_BOLD,50);
            $t->SetColor($textColor);
            $t->Align('right','top');	
            $t->ParagraphAlign('left');
            $graph->AddText($t);	      
             
            $max= "Max: ".number_format(getMax($fdate,$tdate,$sensorId,$username,$password,$serverHostName,$database),1).'°';
            $t = new Text($max,285,$infoStart_Y-32 + $i*70);
            $t->SetFont(FF_ARIAL,FS_BOLD,15);
            $t->SetColor('red:1.6');
            $t->Align('left','top');	
            $t->ParagraphAlign('left');	
            $graph->AddText($t);	
            
            $min= "Min: ".number_format(getMin($fdate,$tdate,$sensorId,$username,$password,$serverHostName,$database),1).'°';
            $t = new Text($min,285,$infoStart_Y+3 + $i*70);
            $t->SetFont(FF_ARIAL,FS_BOLD,15);
            $t->SetColor('blue:1.6');
            $t->Align('left','top');
            $t->ParagraphAlign('left');	
            $graph->AddText($t);
             
            $next = 68*$i;              
            $p =  array( 10,$infoStart_Y+28+$next, 
                         10,$infoStart_Y+30+$next, 
                        385,$infoStart_Y+30+$next,
                        385,$infoStart_Y+28+$next,
                         10,$infoStart_Y+28+$next); 
            $graph->img->SetColor('gray:0.47');
            $graph->img->FilledPolygon($p);
             
            $i++;
         }
      }
        
      if($sensors[$colType][$senNo] == "power")
		{	

			//Get all data with a give time range
			$ttimeP = $ftimeP = date('H:i',time());
			$fdateP = date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
			$tdateP = date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
			$retXY_P = addMissingTime(removeInvalidZeroes(deltaChange(getDataFromDb($username, $password, $database, $fdateP." ".$ftimeP, $tdateP." ".$ttimeP, $sensorId, $serverHostName))));
	
			//Get the max vale
			$maxP = max($retXY_P[0]);
			$maxPIndex = array_search($maxP,$retXY_P[0]);
			$toMaxTime = date('Y-m-d H:i:s',$retXY_P[1][$maxPIndex]+90);
			$frMaxTime = date('Y-m-d H:i:s',$retXY_P[1][$maxPIndex]-90);
			$avgMax = 60*60*getPowerAvg($frMaxTime,$toMaxTime,$sensorId,$username,$password,$serverHostName,$database)/1000;
			//Get the min value
			$minP = min($retXY_P[0]);
			$minPIndex = array_search($minP,$retXY_P[0]);
			$toMinTime = date('Y-m-d H:i:s',$retXY_P[1][$minPIndex]+90);
			$frMinTime = date('Y-m-d H:i:s',$retXY_P[1][$minPIndex]-90);
			$avgMin = 60*60*getPowerAvg($frMinTime,$toMinTime,$sensorId,$username,$password,$serverHostName,$database)/1000;

			$t = number_format($avgMin,1)."/".number_format($avgMax,1);
			$t = new Text($t,170,$infoStart_Y+223 + 90 + $kwhPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,9);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text

         	$sensorName= $sensors[$colName][$senNo];
			$t = new Text($sensorName,10,$infoStart_Y+195 + 90 + $kwhPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,15);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text

			$time = time();
		    $frdate = date('Y-m-d H:i:s',$time-180);
			$todate = date('Y-m-d H:i:s',$time);
			$avg = strval(60*60*getPowerAvg($frdate,$todate,$sensorId,$username,$password,$serverHostName,$database)/1000);			
			$txt= number_format($avg,2);
									
			$t = new Text($txt,170,$infoStart_Y+212 + 90 + $kwhPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,30);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
			
			$txt2= "kwh";
			$t = new Text($txt2,170,$infoStart_Y+212 + 90 + $kwhPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,12);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text

			$p =  array( 10,313, 
                         10,313, 
                        385,313,
                        385,312,
                         10,312); 
            $graph->img->SetColor('gray:0.47');
            $graph->img->FilledPolygon($p);
	
			$kwhPosDelta = $kwhPosDelta + 45;
		}

		if($sensors[$colType][$senNo] == "moisture" && $name == "Fukt")
		{	
         	$sensorName= $sensors[$colName][$senNo];
			$t = new Text($sensorName,240,$infoStart_Y+195 + 90 + $moiPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,15);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text

			$time = time();
		    $frdate = date('Y-m-d H:i:s',$time-180);
			$todate = date('Y-m-d H:i:s',$time);
			$avg = strval(getCurr($sensorId,$username,$password,$serverHostName,$database));			
			$txt= number_format($avg,1);
			$txt2= "%";
						
			$t = new Text($txt,370,$infoStart_Y+212 + 90 + $moiPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,30);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
			
			$t = new Text($txt2,370,$infoStart_Y+212 + 90 + $moiPosDelta);
			$t->SetFont(FF_ARIAL,FS_BOLD,12);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
		}

		if($sensors[$colType][$senNo] == "rain")
		{	
         	$sensorName= $sensors[$colName][$senNo];
			$t = new Text($sensorName,240,$infoStart_Y+210 + 90 + 33);
			$t->SetFont(FF_ARIAL,FS_BOLD,15);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
	    
			$retXY = deltaChange(addMissingTime(getDataFromDb($username,$password,$database, $fdate." ".$ftime,$tdate." ".$ttime,$sensorId, $serverHostName)));
			$avg = strval(number_format(sum($retXY[0], TRUE)*0.254,1));
			$day= number_format($avg,1);
			$txt2= "mm";

			$tdate = date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
			$wfdate = date("Y-m-d", mktime(0,0,0,date("m"),date("d")-7,date("Y")));
			$mfdate = date("Y-m-d", mktime(0,0,0,date("m"),date("d")-30,date("Y")));   

			$retXY = deltaChange(addMissingTime(getDataFromDb($username,$password,$database, $wfdate." ".$ftime,$tdate." ".$ttime,$sensorId, $serverHostName)));
			$avg  = strval(number_format(sum($retXY[0], TRUE)*0.254,1));
			$week  = number_format($avg,1);
									
			$retXY = deltaChange(addMissingTime(getDataFromDb($username,$password,$database, $mfdate." ".$ftime,$tdate." ".$ttime,$sensorId, $serverHostName)));
			$avg  = strval(number_format(sum($retXY[0], TRUE)*0.254,1));
			$month  = number_format($avg,1);

			$t = number_format($month,1)."/".number_format($week,1);
			$t = new Text($t,370,$infoStart_Y+223 + 90 + 48);
			$t->SetFont(FF_ARIAL,FS_BOLD,9);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text

			$t = new Text($day,370,$infoStart_Y+227 + 90 + 33);
			$t->SetFont(FF_ARIAL,FS_BOLD,30);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
			
			$t = new Text($txt2,370,$infoStart_Y+227 + 90 + 33);
			$t->SetFont(FF_ARIAL,FS_BOLD,12);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
		}

		if($sensors[$colType][$senNo] == "Wind")
		{
			$O=30;
			if($sensors[$colName][$senNo]=="WiSpeed")
			{
				$sensorValue= number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),1).'';
				$t = new Text($sensorValue,2400-$O,$infoStart_Y+140 + 90 + 33);
			}

			if($sensors[$colName][$senNo]=="WiSMax")
			{
				$sensorValue= '('.number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),1).')m/s';
				$t = new Text($sensorValue,268-$O,$infoStart_Y+140 + 90 + 33);
			}

			if($sensors[$colName][$senNo]=="WiSDir")
			{
				$DirStr = "";
				$sensorValue= number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),0);
				if($sensorValue >= 0 && $sensorValue <= 45) $DirStr = "N";
				if($sensorValue > 45 && $sensorValue <= 90) $DirStr = "NO";
				if($sensorValue > 90 && $sensorValue <= 135) $DirStr = "O";
				if($sensorValue > 135 && $sensorValue <= 180) $DirStr = "SO";
				if($sensorValue > 180 && $sensorValue <= 225) $DirStr = "S";
				if($sensorValue > 225 && $sensorValue <= 270) $DirStr = "SV";
				if($sensorValue > 270 && $sensorValue <= 315) $DirStr = "V";
				if($sensorValue > 315 && $sensorValue <= 360) $DirStr = "NV";
				$sensorValue= number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),0).'° '.$DirStr;
				$t = new Text($sensorValue,350-$O,$infoStart_Y+140 + 90 + 33);
			}
			
			$t->SetFont(FF_ARIAL,FS_BOLD,15);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
		}
		$senNo++;
	}
		
	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
    	$graph->img->Stream($path2);
		$utr = time()-$time;
		print ", "."$utr"."s, sleep "."$sleepTime"."s\n";
		exit(1);
	}
	else
	{
        
      $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		$graph->img->Stream($path2);
   }
   sleep($sleepTime);

}while (true);
	
?>
