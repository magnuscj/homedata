<?php
date_default_timezone_set('Europe/Stockholm');
require_once("jpgraph.php");
require_once("jpgraph_bar.php");
include("homeFunctions.php");

// Output file name matches this script's basename (like the other graphs).
$file     = explode('.', __FILE__);
$file     = explode('/', $file[0]);
$fileName = $file[sizeof($file) - 1] . ".png";
$path     = getConfig("PATH");

// The elprice pod serves the forward-looking price curve as CSV over HTTP.
// (columns: interval_start,sek_per_kwh,recorded_at)
$csvUrl = getConfig("ELPRICEURL");

/**
 * Fetch and parse the price CSV. Returns [prices[], labels[], startTimes[]]
 * for all 15-min intervals whose start is now or in the future, in
 * chronological order.
 */
function getFuturePrices($csvUrl)
{
    $prices = [];
    $labels = [];
    $starts = [];

    $raw = @file_get_contents($csvUrl);
    if ($raw === false) {
        return [$prices, $labels, $starts];
    }

    $now   = time();
    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    foreach ($lines as $idx => $line) {
        if ($idx === 0) continue;              // header
        if ($line === '') continue;
        $cols = explode(',', $line);
        if (count($cols) < 2) continue;

        $ts = strtotime($cols[0]);             // interval_start (ISO 8601 w/ tz)
        if ($ts === false) continue;

        // Keep the interval that currently applies plus everything ahead of it.
        if ($ts + 15 * 60 <= $now) continue;   // interval already fully in the past

        $prices[] = (float) $cols[1];
        $labels[] = date('H', $ts);            // hour label
        $starts[] = $ts;
    }

    return [$prices, $labels, $starts];
}

$sleepTime = getConfig("SLEEP") + 30;

do {
    if (isCli()) {
        $time = time();
        print date('H:i:s', $time) . ", " . $fileName;
    }

    list($prices, $labels, $starts) = getFuturePrices($csvUrl);

    // Same size as homeAutoGraphMob4.php.
    $graph = new Graph(395, 219);
    $graph->ClearTheme();
    $graph->SetColor('gray:0.43');
    $graph->SetBackgroundGradient('black:1.1', 'black:1.1', GRAD_HOR, BGRAD_MARGIN);
    $graph->SetMargin(40, 20, 10, 25);

    if (count($prices) === 0) {
        // No data: render an empty themed frame with a note.
        $graph->SetScale('textlin', 0, 1);
        $t = new Text("Ingen prisdata", 197, 100);
        $t->SetFont(FF_ARIAL, FS_BOLD, 10);
        $t->SetColor('gray:1.2');
        $t->Align('center', 'center');
        $graph->AddText($t);
    } else {
        $min = min($prices);
        $max = max($prices);
        $range = $max - $min;
        // Guard against a flat curve (all bars would otherwise be green).
        if ($range <= 0) $range = 1;

        // Four equal price bands across [min, max]: green (lowest quarter),
        // then yellow, orange, red (highest quarter).
        $barcolors = [];
        foreach ($prices as $p) {
            $q = ($p - $min) / $range;         // 0..1 within the current range
            if ($q < 0.25)      $barcolors[] = 'green';
            elseif ($q < 0.50)  $barcolors[] = 'yellow';
            elseif ($q < 0.75)  $barcolors[] = 'orange';
            else                $barcolors[] = 'red';
        }

        $graph->SetScale('textlin', 0, ceil($max * 10) / 10);

        $graph->xgrid->Show(true);
        $graph->xaxis->SetColor('black:1.5', 'gray');
        $graph->xaxis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->xaxis->SetTickLabels($labels);
        // Thin the vertical grid: draw a tick + gridline every 4th bar (~hourly,
        // since bars are 15-min), and label those ticks.
        $graph->xaxis->SetTextTickInterval(4);
        $graph->xaxis->SetTextLabelInterval(4);

        $graph->yaxis->SetColor('gray');
        $graph->yaxis->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->yaxis->title->Set('SEK/kWh');
        $graph->yaxis->title->SetFont(FF_VERDANA, FS_BOLD, 8);
        $graph->yaxis->title->SetColor('gray:1.2');
        $graph->yaxis->SetTitleMargin(28);
        $graph->yaxis->SetTitleSide(SIDE_LEFT);

        $bplot = new BarPlot($prices);
        $bplot->SetFillColor($barcolors);
        $bplot->SetColor('black@0.6');
        $bplot->SetWidth(1.0);
        $graph->Add($bplot);
    }

    // Time stamp (bottom right), same style as the other graphs.
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
