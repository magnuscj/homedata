<?php
date_default_timezone_set('Europe/Stockholm');
require_once ("jpgraph.php");
require_once ("jpgraph_line.php");
require_once ('jpgraph_bar.php');
include ("jpgraph_date.php"); 
include ("jpgraph_regstat.php");
include ("homeFunctions.php");

$fileName = "prices_".date("Y_m_d", mktime(0,0,0,date("m"),date("d")+1,date("Y")));

$timestamps = [];
$prices = [];

//if (($handle = fopen($fileName.".txt", "r")) !== FALSE) {
//    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
//       if (count($data) == 2) {
//            $timestamps[] = trim($data[0]);
//            $prices[] = floatval(trim($data[1]));
//        }
//    }
//    fclose($handle);
//}

$fdate          = date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
$tdate          = date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
$ttime = $ftime = date('H:i',time());
$username       = getConfig("DBUSN");
$password       = getConfig('DBPSW');
$database       = getConfig('DBNAME');
$serverHostName = getConfig('DBIP');
$prices = getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime, "electricityprice", $serverHostName);

$dta = array();
$i=0;
foreach($prices[1] as $dt) {
        $dta[$i] = date('H', $dt);
        $i++;
}

$time = time();

$MAX=max($prices[0]);
// Create the graph.
$graph = new Graph(500,300);
$graph->SetScale("intlin",0,ceil(max($prices[0])));
$graph->ClearTheme();
$graph->SetBox(false);
$graph->SetColor('gray:0.43');
$graph->SetBackgroundGradient('black:1.1','black:1.1',GRAD_HOR,BGRAD_MARGIN);
$graph->SetMargin(40,22,10,35);

// Configure the x-axis
$graph->xgrid->Show(true);
$graph->xaxis->SetColor('black:1.5','gray');   
$graph->xaxis->SetTickLabels($dta);

// Configure the y-axis
$graph->yaxis->SetTitleMargin(18);
$graph->yaxis->SetColor('gray'); 
$graph->yaxis->SetTitleSide(SIDE_LEFT);
$graph->yaxis->title->SetColor('red');
$graph->yaxis->title->SetMargin(0);
   
$bplot = new BarPlot($prices[0]);


foreach ($prices[0] as $price) {
	    if ($price < '0.3'*$MAX) $barcolors[]='green';
	    elseif ($price >= '0.3'*$MAX && $price < '0.5'*$MAX) $barcolors[]='yellow';
	    elseif ($price >= '0.5'*$MAX && $price < '0.75'*$MAX) $barcolors[]='orange';
	    elseif ($price >= '0.75'*$MAX) $barcolors[]='red';
}

$bplot->SetFillColor($barcolors);
$bplot->SetWidth(1);
$graph->Add($bplot);

$retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime,'11502682451740542577', $serverHostName)));

$H_tot = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
$i=0;
$j=0;
$hour1 = $hour2 = (int)(date('H', $retXY[1][$i]));

foreach($retXY[0] as $x_valX) {
        
        if ($hour1 == $hour2) {
                $H_tot[$j] = $H_tot[$j] + $x_valX/20000;
        }
        else {
                $hour1 = $hour2;
                $j++;
        }
        $i++;
        $hour2 = (int)(date('H', $retXY[1][$i]));
}
print_r($H_tot);


$bplot2 = new BarPlot($H_tot);
$bplot2->SetFillColor('azure4');
$bplot2->SetWidth(1);
$bplot2->SetColor('gray3');
$bplot2->SetWeight(2);

$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
$graph->img->Stream("/var/www/html/picture/".$fileName.".png");
   
?>
