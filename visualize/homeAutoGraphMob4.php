<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
include ("jpgraph_date.php"); 
include ("jpgraph_regstat.php");
include ("homeFunctions.php");

$file = explode('.', __FILE__);
$file = explode('/', $file[0]);
$fileName = $file[sizeof($file)-1].".png";
$path = getConfig("PATH");

$sleepTime = getConfig("SLEEP")+30;

do
{
	if(isCli())
   {
      $time = time();
      print date('H:i:s',$time).", ".$fileName;
   }

   // Create the graph.
   $graph = new Graph(395,219);
   $graph->SetScale("datint");
   $graph->ClearTheme();
   $graph->SetColor('gray:0.43');
   $graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
   $graph->SetMargin(40,42,10,25);
	
   $color         = array("black", "blue","red","green","brown");
	$configuration = array(array("orange",           /*Line color*/
                                    "°C",           /*Y-axis name*/
                                "orange",           /*Y-axix title color*/
                                "orange",           /*Y-axix color */
                                 "xxxx"),
                           array("darkslategray:1.2",/*Line color*/
                            "wh",               /*Y-axis name*/
                            "aquamarine3",      /*Y-axix title color*/
                            "aquamarine3",      /*Y-axix color */
                            "xxxx"));
	
	$username	   = getConfig("DBUSN");
	$password	   = getConfig('DBPSW');
	$database	   = getConfig('DBNAME');
	$serverHostName= getConfig('DBIP');
   
	waitDbAlive($serverHostName,$username,$password,$database);

	$sensors 	   = getSensorNames($username,$password,$database,$serverHostName);
	
	$fdate 		= date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	$tdate 		= date("Y-m-d", mktime(0,0,0,date("m")+0,date("d"),date("Y")));		
	$Nowtime 	= date('H:i:s',time());
	
	$fsplited 	= preg_split ( '/-/' ,$fdate  );
	$tsplited 	= preg_split ( '/-/' ,$tdate  );
	
	$frommonth  = (int)$fsplited[1];
	$tomonth    = (int)$tsplited[1];
	$fromyear   = (int)$fsplited[0];
	$toyear	   = (int)$tsplited[0];
	$ttime = $ftime = date('H:i',time());
	
     
   $graph->xgrid->Show(true);
   $graph->xaxis->scale-> SetDateFormat('H');
   $graph->xaxis->SetColor('black:1.5','gray');   
   $graph->xaxis->SetFont(FF_VERDANA, FS_BOLD,8);

   /*------------- El  -------------*/
   $graph->yaxis->SetTitleMargin(28);
   $graph->yaxis->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->yaxis->SetColor($configuration[1][3]); 
   $graph->yaxis->SetTitleSide(SIDE_LEFT);
   $graph->yaxis->title->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->yaxis->title->Set($configuration[1][1]);
   $graph->yaxis->title->SetColor($configuration[1][2]);
   $graph->yaxis->title->SetMargin(0);
   $graph->yaxis->scale->ticks->Set(20,10); 
   
   /*------------- Ute -------------*/
   $noOf_Y_FlowGraphs = 0;
   $graph->SetYScale($noOf_Y_FlowGraphs,'lin');
   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetTitleMargin(32);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetFont(FF_VERDANA, FS_BOLD, 8);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetColor($configuration[0][3]);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetTitleSide(SIDE_RIGHT);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->title->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->title->Set($configuration[0][1]);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->title->SetColor($configuration[0][2]);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->title->SetMargin(4);
   $graph->ynaxis[$noOf_Y_FlowGraphs]->scale->ticks->Set(20,10); 

   $colName       = 1;
   $i = 0;
	foreach($sensors[0] as $sensorId)
	{
		if($sensors[$colName][$i]=="El")
		{
         $confNo = 1;
         $retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName)));
         $lineplot = new LinePlot(floatAvg(10, $retXY[0]), $retXY[1]);              
         $lineplot->SetColor($configuration[$confNo][0]);
         $lineplot->SetWeight(2);
         $lineplot->SetFillGradient('aquamarine1','black:1.1');
		}
      
		if($sensors[$colName][$i]=="Ute")
		{
         $confNo = 0;
         $retXY = getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName);
		   $lineplot2=new LinePlot(floatAvg(5, $retXY[0]),$retXY[1] );
		   $lineplot2->SetColor($configuration[$confNo][0]);
         $lineplot2->SetWeight(2);
		}	
		$i++;
	}
   
   $graph->Add($lineplot);
   $graph->AddY($noOf_Y_FlowGraphs,$lineplot2);

	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		$graph->img->Stream($path.$fileName);
	
		$utr = time()-$time;
		print ", "."$utr"."s, sleep ".$sleepTime."s\n";
      exit(1);
		sleep($sleepTime);
	}
	
	if(!isCli())
	{
		$graph->Stroke();
	}
}while (isCli());
?>
