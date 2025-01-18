<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
include ("jpgraph_date.php"); 
include ("jpgraph_regstat.php");

$fileName = "prices_".date("Y_m_d", mktime(0,0,0,date("m"),date("d")+1,date("Y"))).".txt";

$timestamps = [];
$prices = [];

if (($handle = fopen($fileName, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) == 2) {
            $timestamps[] = trim($data[0]);
            $prices[] = floatval(trim($data[1]));
        }
    }
    fclose($handle);
}


$time = time();

// Create the graph.
$graph = new Graph(296,109);
$graph->SetScale("intlin",0,2);
$graph->ClearTheme();
$graph->SetBox(false);
$graph->SetColor('gray:0.43');
$graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
$graph->SetMargin(40,22,10,25);

// Configure the x-axis
$graph->xgrid->Show(true);
$graph->xaxis->SetColor('black:1.5','gray');   

// Configure the y-axis
$graph->yaxis->SetTitleMargin(18);
$graph->yaxis->SetColor('gray'); 
$graph->yaxis->SetTitleSide(SIDE_LEFT);
$graph->yaxis->title->SetColor('red');
$graph->yaxis->title->SetMargin(0);
   
$p1 = new LinePlot(array_map('floatval',$prices));
$p1->SetColor('yellow');
$p1->SetStepStyle();
$graph->Add($p1);

$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
$graph->img->Stream($fileName.".png");
   
?>
