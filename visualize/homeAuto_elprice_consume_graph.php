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
define('BUCKET_SEC', 900);                 // 15-minute buckets
define('NBUCKETS', (24 * 3600) / BUCKET_SEC);  // 96 buckets over 24h

/**
 * Build NBUCKETS 15-minute buckets covering exactly [now-24h, now). Returns:
 *   [ labels[NBUCKETS], priceAvg[NBUCKETS], consumeKwh[NBUCKETS] ]
 * priceAvg  = mean of Pris samples whose timestamp falls in the bucket (0 if none).
 * consumeKwh= (max(El counter) - min(El counter)) / PULSES_PER_KWH in the bucket
 *             (0 if fewer than 2 samples). Finer buckets => a smoother curve.
 * Labels are the 'HH' hour only at buckets that start on a 2-hour boundary,
 * blank elsewhere (so SetTextLabelInterval can show every 2nd hour).
 */
function getLast24h($username, $password, $database, $serverHostName)
{
    $labels   = [];
    $priceAvg = array_fill(0, NBUCKETS, 0.0);
    $consume  = array_fill(0, NBUCKETS, 0.0);

    // Window anchored to NOW: exactly [now-24h, now). Bucket i spans
    // [start0 + i*BUCKET_SEC, start0 + (i+1)*BUCKET_SEC); the last ends at now.
    $now    = time();
    $start0 = $now - NBUCKETS * BUCKET_SEC;    // = now - 24h

    for ($i = 0; $i < NBUCKETS; $i++) {
        $labels[$i] = date('H', $start0 + $i * BUCKET_SEC);  // hour label per bucket
    }

    $priceId = getSensorId('Pris', $username, $password, $database, $serverHostName);
    $elId    = getSensorId('El',   $username, $password, $database, $serverHostName);

    $fdate = date('Y-m-d', $start0);
    $tdate = date('Y-m-d', $now);

    // --- Prices: average per bucket ------------------------------------------
    if ($priceId !== null && $priceId !== '') {
        list($py, $pt) = getDataFromDb($username, $password, $database,
                                       $fdate . " 00:00:00", $tdate . " 23:59:59",
                                       $priceId, $serverHostName);
        $sum = array_fill(0, NBUCKETS, 0.0);
        $cnt = array_fill(0, NBUCKETS, 0);
        for ($k = 0; $k < count($py); $k++) {
            $b = (int) floor(($pt[$k] - $start0) / BUCKET_SEC);
            if ($b < 0 || $b >= NBUCKETS) continue;
            $sum[$b] += (float) $py[$k];
            $cnt[$b]++;
        }
        for ($b = 0; $b < NBUCKETS; $b++) {
            if ($cnt[$b] > 0) $priceAvg[$b] = $sum[$b] / $cnt[$b];
        }
        // Prices change slowly (hourly/15-min steps) and some buckets may have
        // no sample; carry the last known price forward so bars are continuous.
        $last = 0.0;
        for ($b = 0; $b < NBUCKETS; $b++) {
            if ($priceAvg[$b] > 0) $last = $priceAvg[$b];
            elseif ($last > 0)     $priceAvg[$b] = $last;
        }
    }

    // --- Consumption: counter delta per bucket -------------------------------
    if ($elId !== null && $elId !== '') {
        list($ey, $et) = getDataFromDb($username, $password, $database,
                                       $fdate . " 00:00:00", $tdate . " 23:59:59",
                                       $elId, $serverHostName);
        $minC = array_fill(0, NBUCKETS, null);
        $maxC = array_fill(0, NBUCKETS, null);
        for ($k = 0; $k < count($ey); $k++) {
            $b = (int) floor(($et[$k] - $start0) / BUCKET_SEC);
            if ($b < 0 || $b >= NBUCKETS) continue;
            $v = (float) $ey[$k];
            if ($minC[$b] === null || $v < $minC[$b]) $minC[$b] = $v;
            if ($maxC[$b] === null || $v > $maxC[$b]) $maxC[$b] = $v;
        }
        for ($b = 0; $b < NBUCKETS; $b++) {
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

    // Width 410 (vs 395 for the other graphs) to give the right (Y2) side room
    // for the 'kWh' title to sit to the RIGHT of the tick numbers. The right
    // margin grows by the same amount as the width, so the PLOT position is
    // unchanged (right edge stays at 410-55 = 355, same as 395-40).
    $graph = new Graph(410, 219);
    $graph->ClearTheme();
    $graph->SetColor('gray:0.43');
    $graph->SetBackgroundGradient('black:1.1', 'black:1.1', GRAD_HOR, BGRAD_MARGIN);
    // Extra right margin for the secondary (consumption) axis + its 'kWh' title.
    // Right margin = 55 (= 40 + the 15px added to the width) keeps the plot's
    // right edge at x=355, unchanged from the 395-wide layout.
    $graph->SetMargin(40, 55, 10, 25);

    $havePrice = (count(array_filter($prices, fn($p) => $p > 0)) > 0);

    if (!$havePrice) {
        $graph->SetScale('textlin', 0, 1);
        $t = new Text("Ingen prisdata", 205, 100);
        $t->SetFont(FF_ARIAL, FS_BOLD, 10);
        $t->SetColor('gray:1.2');
        $t->Align('center', 'center');
        $graph->AddText($t);
    } else {
        $min   = min($prices);
        $max   = max($prices);
        $range = $max - $min;
        if ($range <= 0) $range = 1;

        // Four equal price bands across [min, max], as a GRAYSCALE ramp:
        // light gray = cheapest quarter ... dark gray = dearest quarter.
        // jpgraph 'gray:<intensity>' — higher intensity = lighter.
        $barcolors = [];
        foreach ($prices as $p) {
            $q = ($p - $min) / $range;
            if      ($q < 0.25) $barcolors[] = 'gray:1.5';   // lightest
            elseif  ($q < 0.50) $barcolors[] = 'gray:1.15';
            elseif  ($q < 0.75) $barcolors[] = 'gray:0.9';
            else                $barcolors[] = 'gray:0.7';   // darkest (kept above the black bg)
        }

        // Left axis: price (bars). Right axis (Y2): consumption kWh (curve).
        // The curve is drawn AFTER the bars (AddY2 below) so it renders IN FRONT
        // of them. Scale Y2 to the data with a little headroom so the curve uses
        // the chart height and stays readable where it overlaps the bars.
        $graph->SetScale('textlin', 0, ceil($max * 10) / 10);
        $maxC = max($consume);
        if ($maxC <= 0) $maxC = 1;
        $graph->SetY2Scale('lin', 0, $maxC * 1.15);
        // Draw Y2 plots (the consumption line) AFTER the Y1 bars, so the curve
        // renders IN FRONT of the bars. jpgraph defaults y2orderback=true, which
        // strokes Y2 first (behind the bars) — exactly what we must avoid.
        $graph->SetY2OrderBack(false);

        $graph->xgrid->Show(true);
        $graph->xaxis->SetColor('black:1.5', 'gray');
        $graph->xaxis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->xaxis->SetTickLabels($labels);
        // 96 fifteen-minute buckets. Tick every 8 buckets (= 2 hours), with the
        // first tick on the earliest bucket that starts on a 2-hour boundary
        // (:00 of an even hour), and a label on every tick. Compute that start
        // offset from the window start.
        $start0 = time() - NBUCKETS * BUCKET_SEC;
        $tickStart = 0;
        for ($i = 0; $i < NBUCKETS; $i++) {
            $ts = $start0 + $i * BUCKET_SEC;
            if ((int) date('i', $ts) === 0 && ((int) date('G', $ts)) % 2 === 0) { $tickStart = $i; break; }
        }
        $graph->xaxis->SetTextTickInterval(8, $tickStart);
        $graph->xaxis->SetTextLabelInterval(1);

        $graph->yaxis->SetColor('gray');
        $graph->yaxis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->yaxis->title->Set('SEK/kWh');
        $graph->yaxis->title->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->yaxis->title->SetColor('gray:1.2');
        $graph->yaxis->SetTitleMargin(28);
        $graph->yaxis->SetTitleSide(SIDE_LEFT);

        $graph->y2axis->SetColor('white');
        $graph->y2axis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->y2axis->title->Set('kWh');
        $graph->y2axis->title->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->y2axis->title->SetColor('white');
        // Push the 'kWh' title right of the tick numbers, but keep it inside
        // the 40px right margin so it is not clipped at the image edge.
        $graph->y2axis->SetTitleMargin(2);

        $bplot = new BarPlot($prices);
        $bplot->SetFillColor($barcolors);
        // 96 thin bars: a lighter/no outline keeps them from looking muddy.
        $bplot->SetColor('black@0.85');
        $bplot->SetWidth(1.0);
        $graph->Add($bplot);

        // Consumption curve in front of the bars (SetY2OrderBack(false) above).
        // With 96 points, drop the per-point markers for a clean smooth line.
        $lplot = new LinePlot($consume);
        $lplot->SetColor('white');
        $lplot->SetWeight(2);
        $graph->AddY2($lplot);
    }

    $t2 = new Text(date("Y-m-d H:i"), 368, 209);
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
