<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
require_once ('jpgraph_bar.php');
include ("jpgraph_date.php"); 
include ("jpgraph_regstat.php");

$fileName = "prices_".date("Y_m_d", mktime(0,0,0,date("m"),date("d")+0,date("Y")));

$timestamps = [];
$prices = [];

if (($handle = fopen($fileName.".txt", "r")) !== FALSE) {
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
$graph = new Graph(500,300);
$graph->SetScale("intlin",0,1);
$graph->ClearTheme();
$graph->SetBox(false);
$graph->SetColor('gray:0.43');
$graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
$graph->SetMargin(40,22,10,35);

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

$bplot = new BarPlot($prices);
$graph->Add($bplot);

foreach ($prices as $price) {
	    if ($price < '0.3') $barcolors[]='green';
	    elseif ($price >= '0.3' && $price < '0.4') $barcolors[]='yellow';
	    elseif ($price >= '0.4' && $price < '0.6') $barcolors[]='orange';
	    elseif ($price >= '0.6') $barcolors[]='red';
}

$bplot->SetFillColor($barcolors);
$bplot->SetWidth(1);

$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
$graph->img->Stream($fileName.".png");
   
?>
