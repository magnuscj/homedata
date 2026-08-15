<?php
date_default_timezone_set('Europe/Stockholm');
require_once("jpgraph.php");
require_once("jpgraph_line.php");
require_once('jpgraph_plotline.php');
require_once("jpgraph_date.php");
require_once("jpgraph_regstat.php");
require_once("jpgraph_bar.php");
require_once('jpgraph_canvas.php');
include("homeFunctions.php");

$file     = explode('.', __FILE__);
$file     = explode('/', $file[0]);
$fileName = $file[sizeof($file) - 1] . ".png";

$path2     = "/var/www/html/picture/" . $fileName;

$username       = getConfig("DBUSN");
$password       = getConfig('DBPSW');
$database       = getConfig('DBNAME');
$serverHostName = getConfig('DBIP');
waitDbAlive($serverHostName, $username, $password, $database);
$textColor     = 'gray:2.7';
$frameColor    = 'black:1.1';
$backGroundClr = 'gray:0.43';
$sensors       = getSensorNames($username, $password, $database, $serverHostName);
    // Index names for the sensor configuration db table
    $colID          = 0;
    $colName        = 1;
    $colColor       = 2;
    $colVisible     = 3;
    $colType        = 4;
    $noOfFlowGraphs = 0;
    $txt            = "";
    $txt2           = "";
    $i              = 0; // General counter/index variable

    $senNo         = 0;
    $ttime         = $ftime = date('H:i', time());
    $tdate         = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
    $fdate         = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
    $kwhPosDelta   = 0;
    $moiPosDelta   = 0;
    $col_1         = 10;
    $col_2         = 160;
    $col_3         = 310;
    $row_1         = 225;
    $row_2         = 245;
    $row_3         = 256;
    $row_4         = 275;
    $row_5         = 295;
    $row_6         = 307;
    $windDir       = "";
    $windMax       = "";
    $miniListDelta = 0;

    $graph = new CanvasGraph(405, 315, 'auto');
    $graph->SetMarginColor($frameColor);
    $graph->SetMargin(5, 6, 6, 6);
    $graph->SetColor($backGroundClr);
    $graph->initFrame();

    $t2 = new Text($tdate . ", " . date("H:i"), 356, 409);
    $t2->SetFont(FF_ARIAL, FS_NORMAL, 8);
    $t2->SetColor('gray:0.63');
    $t2->Align('center', 'top');
    $t2->ParagraphAlign('center');
    $graph->AddText($t2);

    $i = 0;
    foreach ($sensors[$colID] as $sensorId) {
        $infoStart_Y = -56;
        $name        = $sensors[$colName][$senNo];
        $currValue   = getCurr($sensorId, $username, $password, $serverHostName, $database);

        if ($sensors[$colType][$senNo] == "temp") {
            if (
                $name == "Fry_gr"
                || $name == "Garage"
                || ($name == "Skorst" && ($currValue >= 30))
            ) {
                $value      = number_format($currValue, 0);
                $sensorName = $sensors[$colName][$senNo];
                $t          = new Text($sensorName . ": " . $value, $col_3, $row_4 + $miniListDelta);
                $t->SetFont(FF_ARIAL, FS_BOLD, 12);
                $t->SetColor($textColor);
                $t->Align('left', 'bottom');
                $t->ParagraphAlign('left');
                $graph->AddText($t);
                $miniListDelta = $miniListDelta + 15;
            }
        }

        if ($sensors[$colType][$senNo] == "temp") {
            $infoStart_Y = 40;
            if (
                ($name == "Fry_gr" && ($currValue > -15))
                || $name == "Inne"
                || $name == "Ute"
                || ($name == "Skorst" && ($currValue >= 30))
            ) {
                $sensorName = $sensors[$colName][$senNo];
                $t          = new Text($sensorName, 10, $infoStart_Y + $i * 70 - 30);
                $t->SetFont(FF_ARIAL, FS_BOLD, 15);
                $t->SetColor($textColor);
                $t->Align('left', 'top');
                $t->ParagraphAlign('left');
                $graph->AddText($t);

                $sensorValue = number_format($currValue, 1) . '°';
                $t           = new Text($sensorValue, 240, $infoStart_Y - 32 + $i * 70);
                $t->SetFont(FF_ARIAL, FS_BOLD, 50);
                $t->SetColor($textColor);
                $t->Align('right', 'top');
                $t->ParagraphAlign('left');
                $graph->AddText($t);

                $max = "Max: " . number_format(getMax($fdate, $tdate, $sensorId, $username, $password, $serverHostName, $database), 1) . '°';
                $t   = new Text($max, 285, $infoStart_Y - 32 + $i * 70);
                $t->SetFont(FF_ARIAL, FS_BOLD, 15);
                $t->SetColor('red:1.6');
                $t->Align('left', 'top');
                $t->ParagraphAlign('left');
                $graph->AddText($t);

                $min = "Min: " . number_format(getMin($fdate, $tdate, $sensorId, $username, $password, $serverHostName, $database), 1) . '°';
                $t   = new Text($min, 285, $infoStart_Y + 3 + $i * 70);
                $t->SetFont(FF_ARIAL, FS_BOLD, 15);
                $t->SetColor('blue:1.6');
                $t->Align('left', 'top');
                $t->ParagraphAlign('left');
                $graph->AddText($t);

                $next = 68 * $i;
                $p    = [
                    10,  $infoStart_Y + 28 + $next,
                    10,  $infoStart_Y + 30 + $next,
                    385, $infoStart_Y + 30 + $next,
                    385, $infoStart_Y + 28 + $next,
                    10,  $infoStart_Y + 28 + $next,
                ];
                $graph->img->SetColor('gray:0.47');
                $graph->img->FilledPolygon($p);

		$i++;
		$next = 68 * $i;
                $p    = [
                    10,  $infoStart_Y + 28 + $next,
                    10,  $infoStart_Y + 30 + $next,
                    385, $infoStart_Y + 30 + $next,
                    385, $infoStart_Y + 28 + $next,
                    10,  $infoStart_Y + 28 + $next,
                ];
                $graph->img->SetColor('gray:0.47');
                $graph->img->FilledPolygon($p);

            }
        }

        if ($sensors[$colType][$senNo] == "power") {
            $row1 = ("El" == $sensors[$colName][$senNo]) ? $row_1 : $row_4;
            $row2 = ("El" == $sensors[$colName][$senNo]) ? $row_2 : $row_5;
            $row3 = ("El" == $sensors[$colName][$senNo]) ? $row_3 : $row_6;

            $ttimeP  = $ftimeP = date('H:i', time());
            $fdateP  = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
            $tdateP  = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
            $retXY_P = addMissingTime(removeInvalidZeroes(deltaChange(getDataFromDb($username, $password, $database, $fdateP . " " . $ftimeP, $tdateP . " " . $ttimeP, $sensorId, $serverHostName))));

            $maxP      = max($retXY_P[0]);
            $maxPIndex = array_search($maxP, $retXY_P[0]);
            $toMaxTime = date('Y-m-d H:i:s', $retXY_P[1][$maxPIndex] + 90);
            $frMaxTime = date('Y-m-d H:i:s', $retXY_P[1][$maxPIndex] - 90);
            $avgMax    = 60 * 60 * getPowerAvg($frMaxTime, $toMaxTime, $sensorId, $username, $password, $serverHostName, $database) / 1000;

            $minP      = min($retXY_P[0]);
            $minPIndex = array_search($minP, $retXY_P[0]);
            $toMinTime = date('Y-m-d H:i:s', $retXY_P[1][$minPIndex] + 90);
            $frMinTime = date('Y-m-d H:i:s', $retXY_P[1][$minPIndex] - 90);
            $avgMin    = 60 * 60 * getPowerAvg($frMinTime, $toMinTime, $sensorId, $username, $password, $serverHostName, $database) / 1000;

            $t = new Text(number_format($avgMin, 1) . "/" . number_format($avgMax, 1), $col_1, $row3);
            $t->SetFont(FF_ARIAL, FS_BOLD, 9);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $sensorName = ("El" == $sensors[$colName][$senNo]) ? "El" : "Värme";
            $t          = new Text($sensorName, $col_1, $row1);
            $t->SetFont(FF_ARIAL, FS_BOLD, 12);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $time   = time();
            $frdate = date('Y-m-d H:i:s', $time - 180);
            $todate = date('Y-m-d H:i:s', $time);
            $avg    = strval(60 * 60 * getPowerAvg($frdate, $todate, $sensorId, $username, $password, $serverHostName, $database) / 1000);
            $txt    = number_format($avg, 2);

            $t = new Text($txt, $col_1, $row2);
            $t->SetFont(FF_ARIAL, FS_BOLD, 18);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $t = new Text("kwh", 58, $row2);
            $t->SetFont(FF_ARIAL, FS_BOLD, 12);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $kwhPosDelta = $kwhPosDelta + 45;
        }

        if ($sensors[$colType][$senNo] == "moisture" && $name == "Fukt") {
            $sensorName = $sensors[$colName][$senNo];
            $t          = new Text($sensorName, $col_2, $row_1);
            $t->SetFont(FF_ARIAL, FS_BOLD, 12);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $time   = time();
            $frdate = date('Y-m-d H:i:s', $time - 180);
            $todate = date('Y-m-d H:i:s', $time);
            $avg    = strval($currValue);
            $txt    = number_format($avg, 1);

            $t = new Text($txt, $col_2, $row_2);
            $t->SetFont(FF_ARIAL, FS_BOLD, 18);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $t = new Text("%", $col_2 + 45, $row_2);
            $t->SetFont(FF_ARIAL, FS_BOLD, 12);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);
        }

        if ($sensors[$colType][$senNo] == "rain") {
            $sensorName = $sensors[$colName][$senNo];
            $t          = new Text($sensorName, $col_2, $row_4);
            $t->SetFont(FF_ARIAL, FS_BOLD, 12);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $fdate . " " . $ftime, $tdate . " " . $ttime, $sensorId, $serverHostName)));
            $avg   = strval(number_format(sum($retXY[0], true) * 0.254, 1));
            $day   = number_format($avg, 1);
            $txt2  = "mm";

            $tdate  = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
            $wfdate = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 7, date("Y")));
            $mfdate = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 30, date("Y")));

            $retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $wfdate . " " . $ftime, $tdate . " " . $ttime, $sensorId, $serverHostName)));
            $avg   = strval(number_format(sum($retXY[0], true) * 0.254, 1));
            $week  = number_format($avg, 1);

            $retXY = deltaChange(addMissingTime(getDataFromDb($username, $password, $database, $mfdate . " " . $ftime, $tdate . " " . $ttime, $sensorId, $serverHostName)));
            $avg   = strval(number_format(sum($retXY[0], true) * 0.254, 1));
            $month = number_format($avg, 1);

            $weeklyRain  = getCurrByName("regnW", $username, $password, $serverHostName, $database);
            $monthlyRain = getCurrByName("regnM", $username, $password, $serverHostName, $database);

            $t = new Text(number_format($weeklyRain, 1) . "/" . number_format($monthlyRain, 1), $col_2, $row_6);
            $t->SetFont(FF_ARIAL, FS_BOLD, 9);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $rainNow = number_format($currValue, 1) . '';
            $t       = new Text($rainNow, $col_2, $row_5);
            $t->SetFont(FF_ARIAL, FS_BOLD, 18);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);

            $t = new Text($txt2, $col_2 + 37, $row_5);
            $t->SetFont(FF_ARIAL, FS_BOLD, 12);
            $t->SetColor($textColor);
            $t->Align('left', 'bottom');
            $t->ParagraphAlign('left');
            $graph->AddText($t);
        }

        if ($sensors[$colType][$senNo] == "Wind") {
            if ($sensors[$colName][$senNo] == "WiSpeed") {
                $t = new Text("Vind", $col_3, $row_1);
                $t->SetFont(FF_ARIAL, FS_BOLD, 12);
                $t->SetColor($textColor);
                $t->Align('left', 'bottom');
                $t->ParagraphAlign('left');
                $graph->AddText($t);

                $sensorValue = number_format($currValue, 1) . '';
                $t           = new Text($sensorValue, $col_3, $row_2);
                $t->SetFont(FF_ARIAL, FS_BOLD, 18);
                $t->SetColor($textColor);
                $t->Align('left', 'bottom');
                $t->ParagraphAlign('left');
                $graph->AddText($t);

                $t = new Text("m/s", $col_3 + 45, $row_2);
                $t->SetFont(FF_ARIAL, FS_BOLD, 12);
                $t->SetColor($textColor);
                $t->Align('left', 'bottom');
                $t->ParagraphAlign('left');
                $graph->AddText($t);
            }

            if ($sensors[$colName][$senNo] == "WiSMax") {
                $windMax = number_format($currValue, 1);
            }

            if ($sensors[$colName][$senNo] == "WiSDir") {
                $sensorValue = number_format($currValue, 0);
                $dirMap = [45=>'N', 90=>'NO', 135=>'O', 180=>'SO', 225=>'S', 270=>'SV', 315=>'V', 360=>'NV'];
                $DirStr = '';
                foreach ($dirMap as $limit => $label) {
                    if ($sensorValue <= $limit) { $DirStr = $label; break; }
                }
                $windDir = $sensorValue . '° ' . $DirStr;
            }

            if ($windDir != "" && $windMax != "") {
                $s = new Text($windDir . "/" . $windMax, $col_3, $row_3);
                $s->SetFont(FF_ARIAL, FS_BOLD, 9);
                $s->SetColor($textColor);
                $s->Align('left', 'bottom');
                $s->ParagraphAlign('left');
                $graph->AddText($s);
            }
        }
        $senNo++;
    }

    if (isCli()) {
        $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
        $graph->img->Stream($path2);
        $utr = time() - $time;
        exit(0);
    } else {
        $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
        $graph->img->Stream($path2);
        exit(0);
    }
