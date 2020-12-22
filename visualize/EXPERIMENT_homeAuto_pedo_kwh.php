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
    $needleColor    = 'chartreuse4';
    $tickColor      = 'white';
    $dbgText        = 'gray:0.63';
	$sensors 		= getSensorNames($username,$password,$database, $serverHostName); //From sensor configuration
	
	//Index names for the sensor configuration db table
	$colID			= 0;
	$colName		= 1;
	$colColor		= 2;
	$colVisible		= 3;
	$colType		= 4;
	$noOfFlowGraphs = 0;
	$txt			= "";
	$txt2			= "";
	$i				= 0;	//General counter/index variable
	$infoStart_Y	= 195;
	$tempRead       = 0.0;
	$senNo 			= 0;
	$tdate 			= date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
	$avg            = 0;
    $sensorNameToShow = "El";
    $sensorType     = "power";
    
    $length     = 104.0;
    $length_M3  = ($length * 1.21);
    $length_M2  = ($length * 1.24);
    $length_M   = ($length * 1.29);
    $length_T   = ($length * 1.55);
    $ticScaleL  = 1.09;
	
    $baseLength = $length*0.1;
    $Y_offset   = $length*1.64;
    $X_offset   = $length*3.8/2.0;
    $X_start    = $X_offset;
    $Y_start    = $Y_offset+10;     //Graph Y-pos
    $X_stop     = $X_offset;        //
    $Y_stop     = $Y_offset - $length;
    $canLength  = $X_offset*2.0;
    $canHight   = $Y_offset*1.2;
    
	$graph = new CanvasGraph($canLength,$canHight,'auto');
	$graph->SetMargin(7,7,7,7);
	$graph->SetMarginColor($frameColor);
	$graph->SetColor($backGroundClr);
	
    //Graph time stamp 
	$t2 = new Text($tdate.", ".date("H:i"),$length*3.0,190);
	$t2->SetFont(FF_ARIAL,FS_NORMAL,7);
	$t2->SetColor($dbgText);
	//$t2->Align('right','top', 'right');   // How should the text box interpret the coordinates?
    $t2->ParagraphAlign('right');
	$graph->AddText($t2);
	
    
    
    
    //Determin which sensors that are valid for this view.
	//The information comes either from the web page or the
	//db.
	foreach($sensors[$colName] as $sensorName)
	{		
		if($sensors[$colVisible][$i] == 'True')
			$sensorShow[$i] 	= "on";
		else
			$sensorShow[$i] 	= "off";
		$i++;
		
	}

    $i=0;
	foreach($sensors[$colID] as $sensorId)
	{
        $name = $sensors[$colName][$senNo];
        if($name == $sensorNameToShow )          
        {
		    //if($sensorShow[$senNo] == "on" && $sensors[$colType][$senNo] == $sensorType)
		    //{			
			    $graph->InitFrame();
			    $txt= "kwh";//$sensors[$colName][$senNo];//"This\nis\na TEXT!!!";
                
                
			    $t = new Text($txt,12,$infoStart_Y-184);
			    $t->SetFont(FF_ARIAL,FS_BOLD,22);
			    $t->SetColor($textColor);
			    $t->Align('left','top');	// How should the text box interpret the coordinates?
			    $t->ParagraphAlign('left');	// How should the paragraph be aligned?
			    $graph->AddText($t);	// Stroke the text
               	
		            $time = time();
                	    $frdate = date('Y-m-d H:i:s',$time-180);
                	    $todate = date('Y-m-d H:i:s',$time);
                            $avg = 60*60*getPowerAvg($frdate,$todate,$sensorId,$username,$password,$serverHostName,$database)/1000;			

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
                                
                if($avg<$avgMin)
                    $avgMin=$avg;
                
                if($avg>$avgMax)
                    $avgMax=$avg;
               
                if($debug)
			    {
                    $t = new Text(number_format($avg,2),29,$infoStart_Y-187);
			        $t->SetFont(FF_ARIAL,FS_NORMAL,8);
			        $t->SetColor($dbgText);
			        $t->Align('left','top');	// How should the text box interpret the coordinates?
			        $t->ParagraphAlign('left');	// How should the paragraph be aligned?
			        $graph->AddText($t);
                }
			    $i++;
		    //}
        }
		$senNo++;
	}
 
    for($deg_M=180;$deg_M>=0;$deg_M = $deg_M-4.5)
    { 
        #------------------------------------------------------------------------------
        $X_coord= ($length_M3*cos(deg2rad($deg_M)));
        $Y_coord= (($length_M3)*sin(deg2rad($deg_M)));
        $X_stop_deg  = $X_offset + $X_coord;
        $Y_stop_deg  = $Y_start - $Y_coord;
        
        $X_coord= ($ticScaleL*$length*cos(deg2rad($deg_M)));
        $Y_coord= (($ticScaleL*$length)*sin(deg2rad($deg_M)));
        $X_stop_deg_M  = $X_offset + $X_coord;
        $Y_stop_deg_M  = $Y_start - $Y_coord;
        
	    $graph->img->SetColor($tickColor);
        $graph->img->Line($X_stop_deg_M,$Y_stop_deg_M,$X_stop_deg,$Y_stop_deg);     
        #------------------------------------------------------------------------------
    }
    
    for($deg_M=180;$deg_M>=0;$deg_M = $deg_M-9)
    {     
        #------------------------------------------------------------------------------
        
        $X_coord= ($length_M2*cos(deg2rad($deg_M)));
        $Y_coord= (($length_M2)*sin(deg2rad($deg_M)));
        $X_stop_deg  = $X_offset + $X_coord;
        $Y_stop_deg  = $Y_start - $Y_coord;
        
        $X_coord= ($ticScaleL*$length*cos(deg2rad($deg_M)));
        $Y_coord= (($ticScaleL*$length)*sin(deg2rad($deg_M)));
        $X_stop_deg_M  = $X_offset + $X_coord;
        $Y_stop_deg_M  = $Y_start - $Y_coord;
        
	    $graph->img->SetColor($tickColor);
        $graph->img->Line($X_stop_deg_M,$Y_stop_deg_M,$X_stop_deg,$Y_stop_deg);     
        #------------------------------------------------------------------------------
    }
    
    $deg_max= 180;
    $deg_min= 0;
    $max    = 20;
    $min    = 0 ;
    $tics   = $max;
   
    for($deg_M=180;$deg_M>=0;$deg_M = $deg_M-18)
    {        
        #------------------------------------------------------------------------------
        $X_coord= ($length_M*cos(deg2rad($deg_M)));
        $Y_coord= (($length_M)*sin(deg2rad($deg_M)));
        $X_stop_deg  = $X_offset + $X_coord;
        $Y_stop_deg  = $Y_start - $Y_coord;
        
        $X_coord= ($ticScaleL*$length*cos(deg2rad($deg_M)));
        $Y_coord= (($ticScaleL*$length)*sin(deg2rad($deg_M)));
        $X_stop_deg_M  = $X_offset + $X_coord;
        $Y_stop_deg_M  = $Y_start - $Y_coord;
        
	    $graph->img->SetColor($tickColor);
        $graph->img->Line($X_stop_deg_M,$Y_stop_deg_M,$X_stop_deg,$Y_stop_deg);     
        #------------------------------------------------------------------------------
              
        $degTxt = $tics;
        $tics = $tics-($max-$min)/10;
        $M =($length*-0.089+11.34);
        $X_coord= (($length_T-5)*cos(deg2rad($deg_M)));
        $Y_coord= (($length_T-17)*sin(deg2rad($deg_M)));
        $X_stop_deg  = $X_offset+ $M + $X_coord;
        $Y_stop_deg  = $Y_start+5 - $Y_coord;
      
      #  $t = new Text(strval($deg_M*0.1-9.0),$X_stop_deg,$Y_stop_deg);       
        $t = new Text(strval($degTxt),$X_stop_deg,$Y_stop_deg);       
        $t->SetPos($X_stop_deg-10,$Y_stop_deg,'center');	// How should the paragraph be aligned?
        $t->SetFont(FF_ARIAL,FS_BOLD,$length*0.155);
        $t->SetColor($textColor);
        $t->Align('left','bottom');	// How should the text box interpret the coordinates?
       
        $graph->AddText($t);	// Stroke the text
    }
    
   
    
    $deg = $avgMin*$deg_max/($max-$min)-$min*$deg_max/($max-$min);
    
    $X_B_coord= (0.4*$baseLength*cos(deg2rad(90-$deg)));
    $Y_B_coord= ((0.4*$baseLength)*sin(deg2rad(90-$deg)));   
    $X_coord  = ($ticScaleL*$length*cos(deg2rad($deg)));
    $Y_coord  = (($ticScaleL*$length)*sin(deg2rad($deg)));
    
    $X_stop_deg= $X_offset + $X_coord;
    $Y_stop_deg= $Y_start - $Y_coord;
        
    $p = array( $X_start-$X_B_coord,$Y_start-$Y_B_coord, 
                $X_stop_deg,$Y_stop_deg, 
                $X_start+$X_B_coord,$Y_start+$Y_B_coord,
                $X_start,$Y_start); 
    $graph->img->SetColor('steelblue4:0.9');
    $graph->img->FilledPolygon($p);
    //$graph->img->FilledCircle($length*1.48,$length*1.49,$baseLength-0);
    
    
    //$avgMax  = 10.0;
   
    $deg = $avgMax*$deg_max/($max-$min)-$min*$deg_max/($max-$min);
    
    
    $X_B_coord= (0.4*$baseLength*cos(deg2rad(90-$deg)));
    $Y_B_coord= ((0.4*$baseLength)*sin(deg2rad(90-$deg)));   
    $X_coord  = ($ticScaleL*$length*cos(deg2rad($deg)));
    $Y_coord  = (($ticScaleL*$length)*sin(deg2rad($deg)));
    
    $X_stop_deg= $X_offset + $X_coord;
    $Y_stop_deg= $Y_start - $Y_coord;
    
    $p = array( $X_start-$X_B_coord,$Y_start-$Y_B_coord, 
                $X_stop_deg,$Y_stop_deg, 
                $X_start+$X_B_coord,$Y_start+$Y_B_coord,
                $X_start,$Y_start); 
    $graph->img->SetColor('orangered3:0.75');
    $graph->img->FilledPolygon($p);
    //$graph->img->FilledCircle($length*1.48,$length*1.49,$baseLength-0);
    
    
    #$avg  = 5.0;
    $deg = $avg*$deg_max/($max-$min)-$min*$deg_max/($max-$min);
    
    $X_B_coord= ($baseLength*cos(deg2rad(90-$deg)));
    $Y_B_coord= (($baseLength)*sin(deg2rad(90-$deg)));   
    $X_coord  = ($ticScaleL*$length*cos(deg2rad($deg)));
    $Y_coord  = (($ticScaleL*$length)*sin(deg2rad($deg)));
    
    $X_stop_deg= $X_offset + $X_coord;
    $Y_stop_deg= $Y_start - $Y_coord;
    
    $p = array( $X_start-$X_B_coord,$Y_start-$Y_B_coord, 
                $X_stop_deg,$Y_stop_deg, 
                $X_start+$X_B_coord,$Y_start+$Y_B_coord,
                $X_start,$Y_start); 
    $graph->img->SetColor($needleColor);
    $graph->img->FilledPolygon($p);
    $graph->img->FilledCircle($X_start,$Y_start,$baseLength-0);
    
    
    
    
    
	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		//$graph->img->Stream($path);
                $graph->img->Stream($path2);		
		$utr = time()-$time;
		print ", "."$utr"."s sleep "."$sleepTime"."s\n";
		sleep($sleepTime);
	}
    else
    {
        $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		//$graph->img->Stream($path);
                $graph->img->Stream($path2);
		sleep($sleepTime);
    }
		
}while (true);
	
?>
