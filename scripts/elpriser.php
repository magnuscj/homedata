<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
include ("jpgraph_date.php"); 
include ("jpgraph_regstat.php");

$file = explode('.', __FILE__);
$file = explode('/', $file[0]);
$fileName = $file[sizeof($file)-1].".png";

$datay = file("elpriser20250104.txt");

$i = 1;
do
{
   $time = time();
   print date('H:i:s',$time).", ".$fileName;
   

   // Create the graph.
   $graph = new Graph(296,109);
   $graph->SetScale("intlin",0,2);
   #$graph->yaxis->scale->ticks->Set(10,2);
   
   $graph->ClearTheme();

   $graph->SetBox(false);
   $graph->SetColor('gray:0.43');
   $graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
   $graph->SetMargin(40,22,10,25);
	
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

 	
   $graph->xgrid->Show(true);
   $graph->xaxis->SetColor('black:1.5','gray');   
   
   $graph->yaxis->SetTitleMargin(18);
   $graph->yaxis->SetColor('gray'); 
   $graph->yaxis->SetTitleSide(SIDE_LEFT);
   $graph->yaxis->title->SetColor('red');
   $graph->yaxis->title->SetMargin(0);
   
     // Create the line
   $p1 = new LinePlot(array_map('floatval',$datay));
   $p1->SetColor('yellow');
   $p1->SetStepStyle();
   $graph->Add($p1);

   $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
   $graph->img->Stream($fileName);
   $utr = time()-$time;
   
   $i = 0;

}while ($i);
?>
