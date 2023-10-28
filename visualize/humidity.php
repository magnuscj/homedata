<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
include ("jpgraph_date.php"); 
include ("jpgraph_regstat.php");
include ("homeFunctions.php");



$sleepTime = getConfig("SLEEP")+30;

do
{
	$time = time();

   
	
   $color         = array("black", "blue","red","green","brown");
	$configuration = array(array("orange",           /*Line color*/
                                    "°C",           /*Y-axis name*/
                                "orange",           /*Y-axix title color*/
                                "orange",           /*Y-axix color */
                                 "xxxx"),
                           array("cornflowerblue:1.2",/*Line color*/
                            "%",               /*Y-axis name*/
                            "cornflowerblue",      /*Y-axix title color*/
                            "cornflowerblue",      /*Y-axix color */
                            "xxxx"));
	
	$username	   = getConfig("DBUSN");
	$password	   = getConfig('DBPSW');
	$database	   = getConfig('DBNAME');
	$serverHostName= getConfig('DBIP');
   
	waitDbAlive($serverHostName,$username,$password,$database);

	$sensors 	= getSensorNames($username,$password,$database,$serverHostName);
	$fdate 		= date("Y-m-d", mktime(0,0,0,date("m"),date("d")-3,date("Y")));
	$tdate 		= date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));		
	$Nowtime 	= date('H:i:s',time());
	$fsplited 	= preg_split ( '/-/' ,$fdate  );
	$tsplited 	= preg_split ( '/-/' ,$tdate  );
	$frommonth  = (int)$fsplited[1];
	$tomonth    = (int)$tsplited[1];
	$fromyear   = (int)$fsplited[0];
	$toyear	   = (int)$tsplited[0];
	$ttime = $ftime = date('H:i',time());


   
   $colName       = 1;
   $i = 0;
   $channel=1;
	foreach($sensors[0] as $sensorId)
	{
		if($sensors[4][$i] == "soilmoist")
		{
         sleep(2);
         // Create the graph.
         $graph = new Graph(400,200);
         $graph->SetScale("datlin",0,100);
         $graph->yaxis->scale->ticks->Set(10,2);

         $graph->ClearTheme();
         $graph->SetColor('gray:0.43');
         $graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
         $graph->SetMargin(40,22,10,25);
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
         $file = explode('.', __FILE__);
         $file = explode('/', $file[0]);
         $fileName = "CH".strval($channel).$file[sizeof($file)-1].".png";
         $path = getConfig("PATH");

         #$sensorId2 = getSensorId("WiSDir", $username,$password,$database,$serverHostName);
         #$deg = number_format(getCurr($sensorId2, $username, $password, $serverHostName, $database),0);

         $confNo = 1;
         $retXY = addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, $sensorId, $serverHostName));
         $lineplot = new LinePlot(floatAvg(10, $retXY[0]), $retXY[1]);              
         $lineplot->SetColor($configuration[$confNo][0]);
         $lineplot->SetWeight(2);
         $lineplot->SetFillGradient('cornflowerblue','black:1.1');

         $value = number_format(getCurr($sensorId, $username, $password, $serverHostName, $database),1);
         $te = new Text($value."%", 380, 27);
         $te->SetFont(FF_ARIAL,FS_BOLD,12);
         $te->SetColor("white");
         $te->Align('right','bottom');
         $te->ParagraphAlign('left');
         $graph->AddText($te);

         $nam = new Text($sensors[1][$i], 45, 27);
         $nam->SetFont(FF_ARIAL,FS_BOLD,12);
         $nam->SetColor("white");
         $nam->Align('left','bottom');
         $nam->ParagraphAlign('left');
         $graph->AddText($nam);

         $graph->Add($lineplot);
         $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
		   $graph->img->Stream($path.$fileName);
         $channel++;
		}
      
		$i++;
	}
   exit(1);

}while (isCli());
?>
