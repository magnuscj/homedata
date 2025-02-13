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
$ttimep = $ftimep = date('H',time())-1;
$username       = getConfig("DBUSN");
$password       = getConfig('DBPSW');
$database       = getConfig('DBNAME');
$serverHostName = getConfig('DBIP');
$prices = getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttimep, "electricityprice", $serverHostName);

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
$bplot->SetColor('black');
$graph->Add($bplot);

$retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime,'11502682451740542577', $serverHostName)));
$heater = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $fdate." ".$ftime, $tdate." ".$ttime,'10871475366841829943', $serverHostName)));

$H_tot = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
$i=0;
$j=0;
$hour1 = $hour2 = (int)(date('H', $retXY[1][$i]));

foreach($retXY[0] as $x_valX) {
        if($j<24) {
                if ($hour1 == $hour2) {
                        $E_tot[$j] = $E_tot[$j] + $x_valX/25000;
                }
                else {
                        $hour1 = $hour2;
                        $j++;
                }
                $i++;
                if ($i < count($retXY[1])) {
                        $hour2 = (int)(date('H', $retXY[1][$i]));
                }
        }
}

$i=0;
$j=0;
$hour1 = $hour2 = (int)(date('H', $heater[1][$i]));
foreach($heater[0] as $x_valX) {
        if($j<24) {
                if ($hour1 == $hour2) {
                        $H_tot[$j] = $H_tot[$j] + $x_valX/25000;
                }
                else {
                        $hour1 = $hour2;
                        $j++;
                }
                $i++;
                if ($i < count($retXY[1])) {
                        $hour2 = (int)(date('H', $retXY[1][$i]));
                }
        }
}

for ($x= 0 ; $x < count($H_tot) ;$x++) {
        $E_tot[$x] = $E_tot[$x] - $H_tot[$x];
}

$lineplot1 = new BarPlot($H_tot);
$lineplot1->SetFillColor('gray3');
$lineplot1->SetWidth(1);
$lineplot1->SetColor('black');
$lineplot1->SetWeight(2);

$lineplot2 = new BarPlot($E_tot);
$lineplot2->SetFillColor('gray6');
$lineplot2->SetWidth(1);
$lineplot2->SetColor('black');
$lineplot2->SetWeight(2);
//$lineploit->SetFillGradient('aquamarine1','aquamarine1:0.2');
// Create the grouped bar plot
$gbplot = new AccBarPlot(array($lineplot1,$lineplot2));
$gbplot->SetWidth(0.8);
$graph->Add($gbplot);

$gdImgHandler = $graph->Stroke(_IMG_HANDLER);
$graph->img->Stream("/var/www/html/picture/".$fileName.".png");
?>
