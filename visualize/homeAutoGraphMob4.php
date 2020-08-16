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

$fileName = $path.$fileName;
$sleepTime = getConfig("SLEEP")+30;

do
{
	if(isCli())
   {
      $time = time();
      print date('H:i:s',$time).", Working with ".$fileName;
   }

   // Create the graph.
   $graph = new Graph(395,219);
   $graph->SetScale("datint");
   $graph->ClearTheme();
   $graph->xaxis->SetPos("min");
   $graph->SetMargin(40,42,10,25);
   $graph->xgrid->Show(true);
   $graph->xaxis->scale-> SetDateFormat('H'); 
   $graph->xaxis->SetLabelAngle(0);
   $graph->yaxis->SetTitleMargin(29);
   $graph->yaxis->title->Set('°C');
   $graph->yaxis->title->SetColor('gray');
   $graph->yaxis->title->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->yaxis->SetColor('black:1.5','gray'); 
   $graph->xaxis->SetColor('black:1.5','gray'); 
   $graph->xaxis->SetTitleMargin(1);
   $graph->xaxis->SetFont(FF_VERDANA, FS_BOLD,8);	
   $graph->yaxis->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->xgrid->SetColor('black:1.5');
   $graph->ygrid->SetColor('black:1.5');
   $graph->SetColor('gray:0.43');
   $graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
	
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
    
	$max		   = array("-",  "-","-","-","-");
	$min		   = array("-",  "-","-","-","-");
	$curr		   = array("-",  "-","-","-","-");
	$username	   = getConfig("DBUSN");
	$password	   = getConfig('DBPSW');
	$database	   = getConfig('DBNAME');
	$serverHostName= getConfig('DBIP');
   
	waitDbAlive($serverHostName,$username,$password,$database);

	$sensors 	   = getSensorNames($username,$password,$database,$serverHostName);
	
	$i=0;
	foreach($sensors[1] as $sensorName) //make all sensors invisible but one
	{
	   $sensorShow[$i] 	= "off";	
		if(($sensorName == "Ute" ) || ($sensorName == "El"))
			$sensorShow[$i] = "on"; //Make this one visible
		$i++;
	}
	$fdate 		= date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	$tdate 		= date("Y-m-d", mktime(0,0,0,date("m")+0,date("d"),date("Y")));		
	$Nowtime 	= date('H:i:s',time());
	
	$fsplited 	= preg_split ( '/-/' ,$fdate  );
	$tsplited 	= preg_split ( '/-/' ,$tdate  );
	
	$frommonth = (int)$fsplited[1];
	$tomonth   = (int)$tsplited[1];
	$fromyear  = (int)$fsplited[0];
	$toyear	   = (int)$tsplited[0];
	$ttime = $ftime = date('H:i',time());
	$i = 0;	
    
	foreach($sensors[0] as $sensorId)
	{
		if(($sensorShow[$i] == "on") && ($i==2))
		{
	      $ydata_temptot = array();
			$xdata_timeTot = array();
         if($sensors[1][$i]=="El")
            $retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, 
			           $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName)));
         else
			  $retXY = getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." "
			           .$ttime, $sensorId, $serverHostName);

			$ydata_temptot = $retXY[0];
			$xdata_timeTot = $retXY[1];
				
			if(($ydata_temptot != null && $xdata_timeTot) && $xdata_timeTot[0])
			{
            $confNo = 1;
				$ydata_temptot = floatAvg(10, $ydata_temptot);
				$lineplot = new LinePlot($ydata_temptot, $xdata_timeTot);              
				$lineplot->SetColor($configuration[$confNo][0]);
				$lineplot->SetWeight(2);
            if($sensors[1][$i]=="El")
            {
              $lineplot->SetFillGradient('aquamarine1','black:1.1');
            }
				$graph->Add($lineplot);
            $graph->yaxis->title->Set($configuration[$confNo][1]);
            $graph->yaxis->title->SetColor($configuration[$confNo][2]);
				$graph->yaxis->title->SetMargin(0);
            $graph->yaxis->scale->ticks->Set(20,10); 
            $graph->yaxis->SetColor($configuration[$confNo][3]); 
            $graph->yaxis->SetTitleSide(SIDE_LEFT);              
			}
		}
      
		if(($sensorShow[$i] == "on") && ($i==0))
		{
         $confNo = 0;
         if($sensors[1][$i]=="El")
         {
            $retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName)));
         }
         else
         {
            $retXY = getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName);
         }

		   $lineplot2=new LinePlot(floatAvg(5, $retXY[0]),$retXY[1] );
		   $lineplot2->SetColor($configuration[$confNo][0]);
         $lineplot2->SetWeight(2);

         if($sensors[1][$i]=="El")
         {
           $lineplot2->SetFillGradient('aquamarine1','black:1.1');		
         }
            
         $noOf_Y_FlowGraphs = 0;
         if($sensors[1][$i]=="El")
         {
           $graph->SetYScale($noOf_Y_FlowGraphs,'lin',0,220);
         }
         else
         {
           $graph->SetYScale($noOf_Y_FlowGraphs,'lin');
         }
		    
         $graph->ynaxis[$noOf_Y_FlowGraphs]->title->Set($configuration[$confNo][1]);
         $graph->ynaxis[$noOf_Y_FlowGraphs]->title->SetColor($configuration[$confNo][2]);
         $graph->ynaxis[$noOf_Y_FlowGraphs]->SetFont(FF_VERDANA, FS_BOLD, 8);
         $graph->ynaxis[$noOf_Y_FlowGraphs]->title->SetFont(FF_VERDANA, FS_BOLD,8);
         $graph->ynaxis[$noOf_Y_FlowGraphs]->SetTitleMargin(36);
		   $graph->ynaxis[$noOf_Y_FlowGraphs]->title->SetMargin(4);
		   $graph->ynaxis[$noOf_Y_FlowGraphs]->scale->ticks->Set(20,10); 
		   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetColor($configuration[$confNo][3]);
		   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetPos('max');				
		   $graph->ynaxis[$noOf_Y_FlowGraphs]->SetTitleSide(SIDE_RIGHT);
		   $graph->AddY($noOf_Y_FlowGraphs,$lineplot2);
		}	
		$i++;
	}
	
	if(isCli())
	{
		$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		$graph->img->Stream($fileName);
	
		$utr = time()-$time;
		print ", it took "."$utr"." seconds. Next run will be in ".$sleepTime." seconds. \n";
		sleep($sleepTime);
	}
	
	if(!isCli())
	{
		// Display the graph
		
		$graph->Stroke();
		
	}
}while (isCli());
?>
