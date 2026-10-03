<?php
// homeAuto_elprice_consume_graph.php
//
// A backward-looking companion to homeAuto_elprice_graph.php. Instead of the
// forward price curve (fetched from the elprice pod's CSV), this renders the
// LAST 24 HOURS from the database:
//   * bars  = average electricity spot price (SEK/kWh) per hour, same 4-colour
//             banding (green=cheapest quarter .. red=dearest), on the left Y axis;
//   * line  = electricity consumption (kWh) per hour, drawn ABOVE the bars on a
//             secondary (right) Y axis.
//
// Output: homeAuto_elprice_consume_graph.png (basename-derived, like the others).
//
// Data model (see homeFunctions.php / homeAuto_report.php):
//   - "Pris"  (type price)  stores the then-current SEK/kWh as `data` per sample.
//   - "El"    (type power)  stores a MONOTONIC pulse COUNTER as `data`; energy
//     over an interval = (counter_end - counter_start) / 1000 kWh  (1000 pulses
//     per kWh — consistent with homeAuto_report.php's 60*60*(Δcounts/Δs)/1000 kW).

date_default_timezone_set('Europe/Stockholm');
require_once("jpgraph.php");
require_once("jpgraph_bar.php");
require_once("jpgraph_line.php");
include("homeFunctions.php");

$file     = explode('.', __FILE__);
$file     = explode('/', $file[0]);
$fileName = $file[sizeof($file) - 1] . ".png";
$path     = getConfig("PATH");

$username       = getConfig("DBUSN");
$password       = getConfig('DBPSW');
$database       = getConfig('DBNAME');
$serverHostName = getConfig('DBIP');

define('PULSES_PER_KWH', 1000.0);  // El pulse counter constant (see header)
define('HOURS_BACK', 24);

/**
 * Build 24 hourly buckets covering [now-24h, now). Returns:
 *   [ labels[24], priceAvg[24], consumeKwh[24] ]
 * priceAvg  = mean of Pris samples whose timestamp falls in the hour (0 if none).
 * consumeKwh= (max(El counter) - min(El counter)) / PULSES_PER_KWH in the hour
 *             (0 if fewer than 2 samples, i.e. no measurable delta).
 */
function getLast24h($username, $password, $database, $serverHostName)
{
    $labels   = [];
    $priceAvg = array_fill(0, HOURS_BACK, 0.0);
    $consume  = array_fill(0, HOURS_BACK, 0.0);

    // Buckets anchored to NOW: the window is exactly [now-24h, now). Bucket i
    // covers [now-(24-i)h, now-(23-i)h); the LAST bucket ends exactly at now,
    // the FIRST begins exactly 24h ago. (Anchoring to the top of the current
    // hour instead would drop the earliest hour and misalign the axis.)
    $now        = time();
    $hourStart0 = $now - HOURS_BACK * 3600;      // start = now - 24h

    for ($i = 0; $i < HOURS_BACK; $i++) {
        $labels[$i] = date('H', $hourStart0 + $i * 3600);  // label = bucket start hour
    }

    $priceId = getSensorId('Pris', $username, $password, $database, $serverHostName);
    $elId    = getSensorId('El',   $username, $password, $database, $serverHostName);

    $fromTs = $hourStart0;
    $toTs   = $now;
    $fdate  = date('Y-m-d', $fromTs);
    $tdate  = date('Y-m-d', $toTs);

    // --- Prices: average per hour bucket -------------------------------------
    if ($priceId !== null && $priceId !== '') {
        list($py, $pt) = getDataFromDb($username, $password, $database,
                                       $fdate . " 00:00:00", $tdate . " 23:59:59",
                                       $priceId, $serverHostName);
        $sum = array_fill(0, HOURS_BACK, 0.0);
        $cnt = array_fill(0, HOURS_BACK, 0);
        for ($k = 0; $k < count($py); $k++) {
            $b = (int) floor(($pt[$k] - $hourStart0) / 3600);
            if ($b < 0 || $b >= HOURS_BACK) continue;
            $sum[$b] += (float) $py[$k];
            $cnt[$b]++;
        }
        for ($b = 0; $b < HOURS_BACK; $b++) {
            if ($cnt[$b] > 0) $priceAvg[$b] = $sum[$b] / $cnt[$b];
        }
    }

    // --- Consumption: counter delta per hour bucket --------------------------
    if ($elId !== null && $elId !== '') {
        list($ey, $et) = getDataFromDb($username, $password, $database,
                                       $fdate . " 00:00:00", $tdate . " 23:59:59",
                                       $elId, $serverHostName);
        $minC = array_fill(0, HOURS_BACK, null);
        $maxC = array_fill(0, HOURS_BACK, null);
        for ($k = 0; $k < count($ey); $k++) {
            $b = (int) floor(($et[$k] - $hourStart0) / 3600);
            if ($b < 0 || $b >= HOURS_BACK) continue;
            $v = (float) $ey[$k];
            if ($minC[$b] === null || $v < $minC[$b]) $minC[$b] = $v;
            if ($maxC[$b] === null || $v > $maxC[$b]) $maxC[$b] = $v;
        }
        for ($b = 0; $b < HOURS_BACK; $b++) {
            if ($minC[$b] !== null && $maxC[$b] !== null && $maxC[$b] >= $minC[$b]) {
                $consume[$b] = ($maxC[$b] - $minC[$b]) / PULSES_PER_KWH;
            }
        }
    }

    return [$labels, $priceAvg, $consume];
}

$sleepTime = getConfig("SLEEP") + 30;

do {
    if (isCli()) {
        $time = time();
        print date('H:i:s', $time) . ", " . $fileName;
    }

    list($labels, $prices, $consume) = getLast24h($username, $password, $database, $serverHostName);

    // Same size/theme as homeAuto_elprice_graph.php.
    $graph = new Graph(395, 219);
    $graph->ClearTheme();
    $graph->SetColor('gray:0.43');
    $graph->SetBackgroundGradient('black:1.1', 'black:1.1', GRAD_HOR, BGRAD_MARGIN);
    // Extra right margin for the secondary (consumption) axis.
    $graph->SetMargin(40, 40, 10, 25);

    $havePrice = (count(array_filter($prices, fn($p) => $p > 0)) > 0);

    if (!$havePrice) {
        $graph->SetScale('textlin', 0, 1);
        $t = new Text("Ingen prisdata", 197, 100);
        $t->SetFont(FF_ARIAL, FS_BOLD, 10);
        $t->SetColor('gray:1.2');
        $t->Align('center', 'center');
        $graph->AddText($t);
    } else {
        $min   = min($prices);
        $max   = max($prices);
        $range = $max - $min;
        if ($range <= 0) $range = 1;

        // Four equal price bands across [min, max].
        $barcolors = [];
        foreach ($prices as $p) {
            $q = ($p - $min) / $range;
            if      ($q < 0.25) $barcolors[] = 'green';
            elseif  ($q < 0.50) $barcolors[] = 'yellow';
            elseif  ($q < 0.75) $barcolors[] = 'orange';
            else                $barcolors[] = 'red';
        }

        // Left axis: price (bars). Right axis (Y2): consumption kWh (curve).
        // The curve is drawn AFTER the bars (AddY2 below) so it renders IN FRONT
        // of them. Scale Y2 to the data with a little headroom so the curve uses
        // the chart height and stays readable where it overlaps the bars.
        $graph->SetScale('textlin', 0, ceil($max * 10) / 10);
        $maxC = max($consume);
        if ($maxC <= 0) $maxC = 1;
        $graph->SetY2Scale('lin', 0, $maxC * 1.15);

        $graph->xgrid->Show(true);
        $graph->xaxis->SetColor('black:1.5', 'gray');
        $graph->xaxis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->xaxis->SetTickLabels($labels);
        // Label/tick every 2nd hour for readability (24 hourly bars).
        $graph->xaxis->SetTextTickInterval(2);
        $graph->xaxis->SetTextLabelInterval(2);

        $graph->yaxis->SetColor('gray');
        $graph->yaxis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->yaxis->title->Set('SEK/kWh');
        $graph->yaxis->title->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->yaxis->title->SetColor('gray:1.2');
        $graph->yaxis->SetTitleMargin(28);
        $graph->yaxis->SetTitleSide(SIDE_LEFT);

        $graph->y2axis->SetColor('lightblue');
        $graph->y2axis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->y2axis->title->Set('kWh');
        $graph->y2axis->title->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->y2axis->title->SetColor('lightblue');

        $bplot = new BarPlot($prices);
        $bplot->SetFillColor($barcolors);
        $bplot->SetColor('black@0.6');
        $bplot->SetWidth(1.0);
        $graph->Add($bplot);

        // Consumption curve above the bars, on the secondary axis.
        $lplot = new LinePlot($consume);
        $lplot->SetColor('lightblue');
        $lplot->SetWeight(2);
        $lplot->mark->SetType(MARK_FILLEDCIRCLE);
        $lplot->mark->SetColor('lightblue');
        $lplot->mark->SetFillColor('lightblue');
        $lplot->mark->SetSize(2);
        $graph->AddY2($lplot);
    }

    $t2 = new Text(date("Y-m-d H:i"), 353, 209);
    $t2->SetFont(FF_ARIAL, FS_NORMAL, 8);
    $t2->SetColor('gray:0.63');
    $t2->Align('right', 'top');
    $t2->ParagraphAlign('right');
    $graph->AddText($t2);

    if (isCli()) {
        $gdImgHandler = $graph->Stroke(_IMG_HANDLER);
        $graph->img->Stream($path . $fileName);
        $utr = time() - $time;
        print ", " . "$utr" . "s, sleep " . $sleepTime . "s\n";
        exit(0);
    } else {
        $graph->Stroke();
    }
} while (isCli());
?>
