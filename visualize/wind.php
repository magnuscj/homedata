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
   $graph = new Graph(296,109);
   $graph->SetScale("datlin",0,10);
   $graph->yaxis->scale->ticks->Set(10,2);
   
   $graph->ClearTheme();
   $graph->SetColor('gray:0.43');
   $graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
   $graph->SetMargin(40,22,10,25);
	
   $color         = array("black", "blue","red","green","brown");
	$configuration = array(array("orange",           /*Line color*/
                                    "°C",           /*Y-axis name*/
                                "orange",           /*Y-axix title color*/
                                "orange",           /*Y-axix color */
                                 "xxxx"),
                           array("cornflowerblue:1.2",/*Line color*/
                            "m/s",               /*Y-axis name*/
                            "cornflowerblue",      /*Y-axix title color*/
                            "cornflowerblue",      /*Y-axix color */
                            "xxxx"));
	
	$username	   = getConfig("DBUSN");
	$password	   = getConfig('DBPSW');
	$database	   = getConfig('DBNAME');
	$serverHostName= getConfig('DBIP');
   
	waitDbAlive($serverHostName,$username,$password,$database);

	$sensors 	= getSensorNames($username,$password,$database,$serverHostName);
	$fdate 		= date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	$tdate 		= date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));		
	$Nowtime 	= date('H:i:s',time());
	$fsplited 	= preg_split ( '/-/' ,$fdate  );
	$tsplited 	= preg_split ( '/-/' ,$tdate  );
	$frommonth  = (int)$fsplited[1];
	$tomonth    = (int)$tsplited[1];
	$fromyear   = (int)$fsplited[0];
	$toyear	   = (int)$tsplited[0];
	$ttime = $ftime = date('H:i',time());

   //Graph time stamp
   $length     = 104.0;
	$t2 = new Text($tdate.", ".date("H:i"),$length*2.94,209);
	$t2->SetFont(FF_ARIAL,FS_NORMAL,8);
	$t2->SetColor('gray:0.63');
   $t2->ParagraphAlign('right');
	$graph->AddText($t2);
	
   $graph->xgrid->Show(true);
   $graph->xaxis->scale-> SetDateFormat('H');
   $graph->xaxis->SetColor('black:1.5','gray');   
   $graph->xaxis->SetFont(FF_VERDANA, FS_BOLD,8);

   /*------------- El  -------------*/
   $graph->yaxis->SetTitleMargin(18);
   $graph->yaxis->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->yaxis->SetColor($configuration[1][3]); 
   $graph->yaxis->SetTitleSide(SIDE_LEFT);
   $graph->yaxis->title->SetFont(FF_VERDANA, FS_BOLD,8);
   $graph->yaxis->title->Set($configuration[1][1]);
   $graph->yaxis->title->SetColor($configuration[1][2]);
   $graph->yaxis->title->SetMargin(0);
   
   $colName       = 1;
   $i = 0;
	foreach($sensors[0] as $sensorId)
	{
		if($sensors[$colName][$i]=="WiSpeed")
		{
         $confNo = 1;
         $retXY = addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName));
         $lineplot = new LinePlot(floatAvg(10, $retXY[0]), $retXY[1]);              
         $lineplot->SetColor($configuration[$confNo][0]);
         $lineplot->SetWeight(2);
         $lineplot->SetFillGradient('cornflowerblue','black:1.1');
		}
      
		$i++;
	}
   
   $graph->Add($lineplot);
 
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
