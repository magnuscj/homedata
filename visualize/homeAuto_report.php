<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph/jpgraph.php");
require_once ("jpgraph/jpgraph_line.php");
require_once ('jpgraph/jpgraph_plotline.php');
require_once ("jpgraph/jpgraph_date.php"); 
require_once ("jpgraph/jpgraph_regstat.php");
require_once ("jpgraph/jpgraph_bar.php");
require_once ('jpgraph/jpgraph_canvas.php');
include ("homeFunctions.php");


$file = explode('.', __FILE__);
$file = explode('/', $file[0]);
$fileName = $file[sizeof($file)-1].".png";

if(isCli())
{
    $path = "pictures\\".$fileName;
    $path2 = "/var/www/html/pic/".$fileName; 
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
		print date('H:i:s',$time).", Working with ".$fileName;
	}
	
	$username       = getConfig("DBUSN");
	$password	= getConfig('DBPSW');
	$database	= getConfig('DBNAME');
	$serverHostName	= getConfig('DBIP');
	waitDbAlive($serverHostName,$username,$password,$database);
        $textColor      = 'gray:2.7';
        $frameColor     = 'black:1.1';
        $backGroundClr  = 'gray:0.43';
	$sensors        = getSensorNames($username,$password,$database, $serverHostName); //From sensor configuration
	//Index names for the sensor configuration db table
	$colID			= 0;
	$colName		= 1;
	$colColor		= 2;
	$colVisible		= 3;
	$colType		= 4;
	$noOfFlowGraphs         = 0;
	$txt			="";
	$txt2			="";
	$i			= 0;	//General counter/index variable
	$infoStart_Y	        = 47;
	
	$senNo 			= 0;
	
	$tdate 			= date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
        $fdate 			= date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	
	$graph = new CanvasGraph(405,425,'auto');
        $graph->SetMarginColor($frameColor);
	$graph->SetMargin(5,6,6,6);
        $graph->SetColor($backGroundClr);
        $graph->initFrame();

	$t2 = new Text($tdate.", ".date("H:i"),337,402);
	//$t2->SetFont(FF_ARIAL,FS_BOLD,10);
	$t2->SetColor('gray:0.63');
	$t2->Align('center','top');// How should the text box interpret the coordinates?
	$t2->ParagraphAlign('center');// How should the paragraph be aligned?
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
		if($sensors[$colType][$senNo] == "temp")
		{


        		if((   $name == "kylFrys" && (getCurr($sensorId, $username, $password, $serverHostName, $database)> -15)) 
            		|| $name == "Inne" 
            		|| $name == "Ute" 
            		||($name == "Skorst" && (getCurr($sensorId, $username, $password, $serverHostName, $database)> 30)))
        		{
			
                        	  

				if($sensorShow[$senNo] == "on" /*&& substr($sensors[$colType][$senNo],0,4)=="temp")*/ && $sensors[$colType][$senNo] == "temp")
		    		{			
			    		//degree sign &#176; &deg;
			    
			    	$txt= $sensors[$colName][$senNo];
			    	$t = new Text($txt,10,$infoStart_Y + $i*70-30);
			    	$t->SetFont(FF_ARIAL,FS_BOLD,15);
			    	$t->SetColor($textColor);
			    	$t->Align('left','top');	// How should the text box interpret the coordinates?
			    	$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			    	$graph->AddText($t);	// Stroke the text
			
			    	$txt= number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),1).'Â°';//"This\nis\na TEXT!!!";
			    	$t = new Text($txt,230,$infoStart_Y-32 + $i*70);
			    	$t->SetFont(FF_ARIAL,FS_BOLD,50);
			    	$t->SetColor($textColor);
			    	$t->Align('right','top');	// How should the text box interpret the coordinates?
			    	$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			    	$graph->AddText($t);	// Stroke the text
                
			    	$max= "Max: ".number_format(getMax($fdate,$tdate,$sensorId,$username,$password,$serverHostName,$database),1).'°';
                		$t = new Text($max,285,$infoStart_Y-32 + $i*70);
			    	$t->SetFont(FF_ARIAL,FS_BOLD,15);
			    	$t->SetColor('red:1.6');
			    	$t->Align('left','top');	// How should the text box interpret the coordinates?
			    	$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			    	$graph->AddText($t);	// Stroke the text
                
			    	$min= "Min: ".number_format(getMin($fdate,$tdate,$sensorId,$username,$password,$serverHostName,$database),1).'°';
                		$t = new Text($min,285,$infoStart_Y+3 + $i*70);
			    	$t->SetFont(FF_ARIAL,FS_BOLD,15);
			    	$t->SetColor('blue:1.6');
			    	$t->Align('left','top');	// How should the text box interpret the coordinates?
			    	$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			    	$graph->AddText($t);	// Stroke the text
                
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
        	}
        
	        if($sensorShow[$senNo] == "on" && $sensors[$colType][$senNo] == "power")
		{	
			$graph->InitFrame();
			$time = time();
		    	$frdate = date('Y-m-d H:i:s',$time-180);
			$todate = date('Y-m-d H:i:s',$time);
			$avg = strval(60*60*getPowerAvg($frdate,$todate,$sensorId,$username,$password,$serverHostName,$database)/1000);			
			$txt= number_format($avg,2);
			$txt2= "kwh";
						
			$t = new Text($txt,360,$infoStart_Y+227);
			$t->SetFont(FF_ARIAL,FS_BOLD,30);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
			
			
			$t = new Text($txt2,365,$infoStart_Y+227);
			$t->SetFont(FF_ARIAL,FS_BOLD,12);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
			
			//$t2->Stroke($graph->img);	// Stroke the text			
		}
/*		
		if($sensorShow[$senNo] == "on" && $sensors[$colType][$senNo] == "wind")
		{
			$graph->InitFrame();
			$time = time();
			$frdate = date('Y-m-d H:i:s',$time-3600);
			$todate = date('Y-m-d H:i:s',$time);
			$avg = strval(2.5*0.44704*getPowerAvg($frdate,$todate,$sensorId,$username,$password,$serverHostName,$database));
			$txt= number_format($avg,2);
			$txt2= "m/s";
		
			$t = new Text($txt,360,$infoStart_Y+227+40);
			$t->SetFont(FF_ARIAL,FS_BOLD,30);
			$t->SetColor($textColor);
			$t->Align('right','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
				
				
			$t = new Text($txt2,365,$infoStart_Y+227+40);
			$t->SetFont(FF_ARIAL,FS_BOLD,12);
			$t->SetColor($textColor);
			$t->Align('left','bottom');	// How should the text box interpret the coordinates?
			$t->ParagraphAlign('left');	// How should the paragraph be aligned?
			$graph->AddText($t);	// Stroke the text
				
			//$t2->Stroke($graph->img);	// Stroke the text
		}
 */		
		$senNo++;
	}
		
	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		//$graph->img->Stream($path);
                $graph->img->Stream($path2);
		//$graph->img->Stream("J:\\www\pictures\\homeauto_report.png");		

		$utr = time()-$time;
		print ", it took "."$utr"." seconds. Next run will be in "."$sleepTime"." seconds.\n";
        
		sleep($sleepTime);
	}
	else
	{
        
        $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		$graph->img->Stream($path);
		sleep($sleepTime);
    }
	
	
}while (true);
	
?>
