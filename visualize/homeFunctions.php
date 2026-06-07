<?php
date_default_timezone_set('Europe/Stockholm');

function filterUnionQuery($con, $query) {
    $parts = array_filter(explode(" UNION ", $query), function($p) use ($con) {
        preg_match("/FROM (\w+)/", $p, $m);
        return $m && mysqli_num_rows(mysqli_query($con, "SHOW TABLES LIKE '" . $m[1] . "'")) > 0;
    });
    $q = implode(" UNION ", $parts);
    return $q === "" ? null : $q;
}

// Builds a UNION query across monthly tables for the given date range.
// $select is the SELECT clause, e.g. "SELECT data,curr_timestamp"
// $fdate_e, $tdate_e, $sensor_e must already be escaped.
// $suffix is appended to each WHERE clause, e.g. " 00:00:00" / " 23:59:59" or "".
function buildMonthlyUnion($select, $fromyear, $toyear, $frommonth, $tomonth,
                            $fdate_e, $tdate_e, $sensor_e,
                            $fdate_suffix = "", $tdate_suffix = "") {
    $query      = "";
    $tomonthT   = $tomonth;
    $frommonthT = $frommonth;

    for ($ycont = $fromyear; $ycont <= $toyear; $ycont++) {
        if ($fromyear != $toyear) {
            if ($ycont < $toyear) {
                $tomonth = 12;
            } else {
                $tomonth   = $tomonthT;
                $frommonth = 1;
            }
        }

        for ($mcont = $frommonth; $mcont <= $tomonth; $mcont++) {
            $union = (($fromyear < $toyear && $ycont != $toyear) || ($frommonth < $tomonth && $mcont != $tomonth))
                ? " UNION " : "";
            $zero   = ($mcont <= 9) ? "0" : "";
            $table  = "table" . (string)$ycont . $zero . (string)$mcont;
            $query .= "$select FROM $table"
                . " WHERE curr_timestamp >= '$fdate_e$fdate_suffix'"
                . " AND curr_timestamp <= '$tdate_e$tdate_suffix'"
                . " AND sensorid ='$sensor_e'" . $union;
        }
    }
    return $query;
}

function UnixTime($mysql_timestamp) {
    if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $mysql_timestamp, $pieces)
        || preg_match('/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $mysql_timestamp, $pieces)) {
        $unix_time = mktime($pieces[4], $pieces[5], $pieces[6], $pieces[2], $pieces[3], $pieces[1]);
    } elseif (preg_match('/\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}/', $mysql_timestamp)
        || preg_match('/\d{2}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}/', $mysql_timestamp)
        || preg_match('/\d{4}\-\d{2}\-\d{2}/', $mysql_timestamp)
        || preg_match('/\d{2}\-\d{2}\-\d{2}/', $mysql_timestamp)) {
        $unix_time = strtotime($mysql_timestamp);
    } elseif (preg_match('/(\d{4})(\d{2})(\d{2})/', $mysql_timestamp, $pieces)
        || preg_match('/(\d{2})(\d{2})(\d{2})/', $mysql_timestamp, $pieces)) {
        $unix_time = mktime(0, 0, 0, $pieces[2], $pieces[3], $pieces[1]);
    } else {
        return null;
    }
    return $unix_time;
}

function getDataFromDb($username, $password, $database, $fdate, $tdate, $sensor, $serverHostName) {
    $fsplited  = preg_split('/-/', $fdate);
    $tsplited  = preg_split('/-/', $tdate);
    $frommonth = (int)$fsplited[1];
    $tomonth   = (int)$tsplited[1];
    $fromyear  = (int)$fsplited[0];
    $toyear    = (int)$tsplited[0];

    return getPower($fromyear, $toyear, $frommonth, $tomonth, $username, $password, $database, $fdate, $tdate, $sensor, $serverHostName);
}

function zeroAdjust($no) {
    return sprintf('%02d', $no);
}

function getPower($fromyear, $toyear, $frommonth, $tomonth, $username, $password, $database, $fdate, $tdate, $sensor, $serverHostName) {
    $ydata    = array();
    $UNIXdata = array();

    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $fdate_e  = mysqli_real_escape_string($con, $fdate);
    $tdate_e  = mysqli_real_escape_string($con, $tdate);
    $sensor_e = mysqli_real_escape_string($con, $sensor);

    $query = buildMonthlyUnion("SELECT data,curr_timestamp", $fromyear, $toyear, $frommonth, $tomonth,
                                $fdate_e, $tdate_e, $sensor_e);

    $_q = filterUnionQuery($con, $query);
    if ($_q === null) {
        mysqli_close($con);
        return array(array(), array());
    }
    $result = mysqli_query($con, $_q . " ORDER BY curr_timestamp ASC");

    $myrow = $result ? mysqli_fetch_array($result) : false;
    $i     = 0;
    if ($myrow) {
        do {
            if ($myrow['data'] != 0) {
                $ydata[]      = $myrow['data']; // It would not create the graphs without using '[]'
                $UNIXdata[$i] = strtotime($myrow['curr_timestamp']);
                $i++;
            }
        } while ($myrow = mysqli_fetch_array($result));
        mysqli_free_result($result);
    }

    mysqli_close($con);
    return array($ydata, $UNIXdata);
}

function reduceData($windowSize, $valueArray) {
    $windowSize             = (int)$windowSize;
    $ydata2_floatingAverage = array();
    $floatingAverage        = (double)0.0;

    if (sizeof($valueArray[0]) > 0 && $windowSize > 0) {
        if ($valueArray[0][0] !== null) {
            for ($f = 0; $f < (sizeof($valueArray[0]) - $windowSize); $f = $f + $windowSize) {
                for ($k = 0; $k < $windowSize; $k++) {
                    $floatingAverage += (double)($valueArray[0][$f + $k]);
                }
                $ydata2_floatingAverage[0][] = (double)$floatingAverage / $windowSize;
                $ydata2_floatingAverage[1][] = $valueArray[1][$f];
                $floatingAverage             = (double)0.0;
            }
        }
    }

    return ($windowSize > 0) ? $ydata2_floatingAverage : $valueArray;
}

/*******************************************************************/
// Function: getSensorNames
// Description: Connects to the database and retrieves all sensor
//              configuration data.
/*******************************************************************/
function getSensorNames($username, $password, $database, $serverHostName) {
    $ids     = array();
    $names   = array();
    $color   = array();
    $visible = array();
    $type    = array();

    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $result = mysqli_query($con, "SELECT sensorid, sensorname, color, visible, type FROM sensorconfig");

    if ($result) {
        while ($myrow = mysqli_fetch_array($result, MYSQLI_BOTH)) {
            $ids[]     = $myrow['sensorid'];
            $names[]   = $myrow['sensorname'];
            $color[]   = $myrow['color'];
            $visible[] = $myrow['visible'];
            $type[]    = $myrow['type'];
        }
        mysqli_free_result($result);
    }

    mysqli_close($con);
    return array($ids, $names, $color, $visible, $type);
}

function getSensorId($name, $username, $password, $database, $serverHostName) {
    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $stmt = mysqli_prepare($con, "SELECT sensorid FROM sensorconfig WHERE sensorname = ?");
    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $id = null;
    if ($result) {
        while ($myrow = mysqli_fetch_array($result, MYSQLI_BOTH)) {
            $id = $myrow['sensorid'];
        }
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($con);
    return $id;
}

function onlyPowerType($sensors) {
    for ($i = 0; $i < sizeof($sensors[0]); $i++) {
        if ($sensors[4][$i] == "temp" && $sensors[3][$i] == "True") {
            return false;
        }
    }
    return true;
}

function floatAvg($windowSize, $valueArray) {
    $ydata2_floatingAverage = array();
    $floatingAverage        = (double)0.0;

    if (sizeof($valueArray) > 0) {
        if ($valueArray[0] !== null) {
            for ($f = 0; $f < (sizeof($valueArray) - $windowSize); $f++) {
                for ($k = 0; $k < $windowSize; $k++) {
                    $floatingAverage += (double)($valueArray[$f + $k]);
                }
                $ydata2_floatingAverage[] = (double)$floatingAverage / $windowSize;
                $floatingAverage          = (double)0.0;
            }

            for ($k = 0; $k < $windowSize; $k++) {
                if ($f > 0) {
                    $ydata2_floatingAverage[] = $ydata2_floatingAverage[$f - 1];
                }
            }
        }
    }
    return $ydata2_floatingAverage;
}

function getCurr($sensor, $username, $password, $serverHostName, $database) {
    $con   = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $table = $database . ".table" . date("Ym");
    $stmt  = mysqli_prepare($con, "SELECT data FROM $table WHERE sensorid = ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $sensor);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $curr   = $result ? mysqli_fetch_array($result) : null;

    mysqli_stmt_close($stmt);
    mysqli_close($con);
    return $curr ? $curr[0] : null;
}

function getLatestTime($sensor, $username, $password, $serverHostName, $database) {
    $con   = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $table = $database . ".table" . date("Ym");
    $stmt  = mysqli_prepare($con, "SELECT curr_timestamp FROM $table WHERE sensorid = ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $sensor);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $curr   = $result ? mysqli_fetch_array($result) : null;

    mysqli_stmt_close($stmt);
    mysqli_close($con);
    return $curr ? $curr[0] : null;
}

function getCurrByName($sensorName, $username, $password, $serverHostName, $database) {
    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $stmt = mysqli_prepare($con, "SELECT sensorid FROM " . $database . ".sensorconfig WHERE sensorname = ?");
    mysqli_stmt_bind_param($stmt, "s", $sensorName);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = $result ? mysqli_fetch_array($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        mysqli_close($con);
        return null;
    }
    $sensorId = $row[0];

    $table = $database . ".table" . date("Ym");
    $stmt  = mysqli_prepare($con, "SELECT data FROM $table WHERE sensorid = ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $sensorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $curr   = $result ? mysqli_fetch_array($result) : null;

    mysqli_stmt_close($stmt);
    mysqli_close($con);
    return $curr ? $curr[0] : null;
}

function scaleChange($factor, $valueArray) {
    if (sizeof($valueArray) > 0 && $valueArray[0] !== null) {
        for ($f = 0; $f < sizeof($valueArray); $f++) {
            $valueArray[$f] = (double)$factor * $valueArray[$f];
        }
    }
    return $valueArray;
}

function getMax($fdate, $tdate, $sensor, $username, $password, $serverHostName, $database) {
    $fsplited  = preg_split('/-/', $fdate);
    $tsplited  = preg_split('/-/', $tdate);
    $frommonth = (int)$fsplited[1];
    $tomonth   = (int)$tsplited[1];
    $fromyear  = (int)$fsplited[0];
    $toyear    = (int)$tsplited[0];

    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $fdate_e  = mysqli_real_escape_string($con, $fdate);
    $tdate_e  = mysqli_real_escape_string($con, $tdate);
    $sensor_e = mysqli_real_escape_string($con, $sensor);

    $query = buildMonthlyUnion("SELECT MAX(data)", $fromyear, $toyear, $frommonth, $tomonth,
                                $fdate_e, $tdate_e, $sensor_e, " 00:00:00", " 23:59:59");

    $_q = filterUnionQuery($con, $query);
    if ($_q === null) {
        mysqli_close($con);
        return 0;
    }
    $result = mysqli_query($con, $_q);

    if (!$result) {
        mysqli_close($con);
        return 0;
    }

    $myrow = mysqli_fetch_array($result);
    $ydata = $myrow[0];
    do {
        if ($myrow[0] > $ydata) {
            $ydata = $myrow[0];
        }
    } while ($myrow = mysqli_fetch_array($result));
    mysqli_free_result($result);

    mysqli_close($con);
    return $ydata;
}

function getCnt($fdate, $tdate, $sensor, $username, $password, $serverHostName, $database) {
    $fsplited  = preg_split('/-/', $fdate);
    $tsplited  = preg_split('/-/', $tdate);
    $frommonth = (int)$fsplited[1];
    $tomonth   = (int)$tsplited[1];
    $fromyear  = (int)$fsplited[0];
    $toyear    = (int)$tsplited[0];

    $con = mysqli_connect($serverHostName, $username, $password);
    mysqli_select_db($con, $database) or die("Unable to select database");

    $fdate_e  = mysqli_real_escape_string($con, $fdate);
    $tdate_e  = mysqli_real_escape_string($con, $tdate);
    $sensor_e = mysqli_real_escape_string($con, $sensor);

    $query = buildMonthlyUnion("SELECT COUNT(*)", $fromyear, $toyear, $frommonth, $tomonth,
                                $fdate_e, $tdate_e, $sensor_e, " 00:00:00", " 23:59:59");

    $_q = filterUnionQuery($con, $query);
    if ($_q === null) {
        mysqli_close($con);
        return 0;
    }
    $result = mysqli_query($con, $_q);
    $res    = mysqli_fetch_array($result);
    mysqli_free_result($result);
    mysqli_close($con);
    return $res[0];
}

function getAvg($fdate, $tdate, $sensor, $username, $password, $serverHostName, $database) {
    $fsplited  = preg_split('/-/', $fdate);
    $tsplited  = preg_split('/-/', $tdate);
    $frommonth = (int)$fsplited[1];
    $tomonth   = (int)$tsplited[1];
    $fromyear  = (int)$fsplited[0];
    $toyear    = (int)$tsplited[0];
    $res       = array(null, null);

    $con = mysqli_connect($serverHostName, $username, $password);
    mysqli_select_db($con, $database) or die("Unable to select database");

    $fdate_e  = mysqli_real_escape_string($con, $fdate);
    $tdate_e  = mysqli_real_escape_string($con, $tdate);
    $sensor_e = mysqli_real_escape_string($con, $sensor);

    $query = buildMonthlyUnion("SELECT AVG(data)", $fromyear, $toyear, $frommonth, $tomonth,
                                $fdate_e, $tdate_e, $sensor_e, " 00:00:00", " 23:59:59");

    $_q = filterUnionQuery($con, $query);
    if ($_q === null) {
        mysqli_close($con);
        return null;
    }
    $result = mysqli_query($con, $_q);
    if ($result != false) {
        $res = mysqli_fetch_array($result);
        mysqli_free_result($result);
    }
    mysqli_close($con);
    return $res[0];
}

function getPowerAvg($fdate, $tdate, $sensor, $username, $password, $serverHostName, $database) {
    /* Not finished */
    $query1    = "";
    $query2    = "";
    $avgP      = 0;
    $fsplited  = preg_split('/-/', $fdate);
    $tsplited  = preg_split('/-/', $tdate);
    $frommonth = (int)$fsplited[1];
    $tomonth   = (int)$tsplited[1];
    $fromyear  = (int)$fsplited[0];
    $toyear    = (int)$tsplited[0];
    $ydata     = array();
    $UNIXdata  = array();

    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $fdate_e  = mysqli_real_escape_string($con, $fdate);
    $tdate_e  = mysqli_real_escape_string($con, $tdate);
    $sensor_e = mysqli_real_escape_string($con, $sensor);

    $query1 = buildMonthlyUnion("SELECT MAX(curr_timestamp), MAX(data)", $fromyear, $toyear, $frommonth, $tomonth,
                                 $fdate_e, $tdate_e, $sensor_e);
    $query2 = buildMonthlyUnion("SELECT MIN(curr_timestamp), MIN(data)", $fromyear, $toyear, $frommonth, $tomonth,
                                 $fdate_e, $tdate_e, $sensor_e);

    $result = mysqli_query($con, filterUnionQuery($con, $query1));
    if ($result === false) {
        mysqli_close($con);
        return array(array(), array());
    }
    while ($myrow = mysqli_fetch_array($result)) {
        $ydata[]    = $myrow['MAX(data)'];
        $UNIXdata[] = UnixTime($myrow['MAX(curr_timestamp)']);
    }
    mysqli_free_result($result);

    $result = mysqli_query($con, filterUnionQuery($con, $query2));
    if ($result === false) {
        mysqli_close($con);
        return array(array(), array());
    }
    while ($myrow = mysqli_fetch_array($result)) {
        $ydata[]    = $myrow['MIN(data)'];
        $UNIXdata[] = UnixTime($myrow['MIN(curr_timestamp)']);
    }
    mysqli_free_result($result);

    if (sizeof($UNIXdata) >= 2 && sizeof($ydata) >= 2
        && $UNIXdata[0] > 0 && $UNIXdata[1] > 0
        && $ydata[0] > 0 && $ydata[1] > 0
        && $UNIXdata[0] != $UNIXdata[1]) {
        $seconds = abs($UNIXdata[0] - $UNIXdata[1]);
        $counts  = $ydata[0] - $ydata[1];
        $avgP    = $counts / $seconds; // counter steps / T(s)
    } else {
        $avgP = 0;
        print "Error: Not enough data\n";
    }

    mysqli_close($con);
    return $avgP;
}

function getMin($fdate, $tdate, $sensor, $username, $password, $serverHostName, $database) {
    $fsplited  = preg_split('/-/', $fdate);
    $tsplited  = preg_split('/-/', $tdate);
    $frommonth = (int)$fsplited[1];
    $tomonth   = (int)$tsplited[1];
    $fromyear  = (int)$fsplited[0];
    $toyear    = (int)$tsplited[0];

    $con = mysqli_connect($serverHostName, $username, $password);
    @mysqli_select_db($con, $database) or die("Unable to select database");

    $fdate_e  = mysqli_real_escape_string($con, $fdate);
    $tdate_e  = mysqli_real_escape_string($con, $tdate);
    $sensor_e = mysqli_real_escape_string($con, $sensor);

    $query = buildMonthlyUnion("SELECT MIN(data)", $fromyear, $toyear, $frommonth, $tomonth,
                                $fdate_e, $tdate_e, $sensor_e, " 00:00:00", " 23:59:59");

    $_q = filterUnionQuery($con, $query);
    if ($_q === null) {
        mysqli_close($con);
        return 0;
    }
    $result = mysqli_query($con, $_q);
    $myrow  = mysqli_fetch_array($result);
    $ydata  = $myrow[0];
    do {
        if ($myrow[0] < $ydata) {
            $ydata = $myrow[0];
        }
    } while ($myrow = mysqli_fetch_array($result));
    mysqli_free_result($result);

    mysqli_close($con);
    return $ydata;
}

function sum($valueArray, $accumulate) {
    // If $accumulate     => [5,4,3,2,2,3] => 5+4+3+2+2+3
    // If not $accumulate => [5,4,3,2,2,3] => 4-5 + 3-4 + 2-3 ... best for counters
    $sum = (double)0.0;

    if (sizeof($valueArray) > 0 && $valueArray[0] !== null) {
        for ($f = 0; $f < (sizeof($valueArray) - 1); $f++) {
            if ($valueArray[$f + 1] !== null) {
                if ($accumulate) {
                    $sum = $valueArray[$f] + $sum;
                } else {
                    $sum = $valueArray[$f + 1] - $valueArray[$f] + $sum;
                }
            }
        }
    }
    return $sum;
}

function addMissingTime($retXY) {
    $ydata2_temptot         = $retXY[0]; // Data, accumulative
    $xdata2_timeTot         = $retXY[1]; // Time
    $ydata2_calcTotAvRobust = array();
    $xdata2_timeTotRobust   = array();

    if (!empty($xdata2_timeTot)) {
        $xdata2_timeTotRobust[] = $xdata2_timeTot[0];
    }
    if (!empty($ydata2_temptot)) {
        $ydata2_calcTotAvRobust[] = $ydata2_temptot[0];
    }

    for ($i = 1; $i < sizeof($ydata2_temptot); $i++) {
        $minutes = (int)(($xdata2_timeTot[$i] - $xdata2_timeTot[$i - 1]) / 60) - 1; // Missing minutes

        if ($minutes > 0) {
            $averagePower = ($ydata2_temptot[$i] - $ydata2_temptot[$i - 1]) / $minutes;
            for ($j = 0; $j < $minutes; $j++) {
                $last                     = count($ydata2_calcTotAvRobust) - 1;
                $ydata2_calcTotAvRobust[] = $ydata2_calcTotAvRobust[$last] + $averagePower;
                $xdata2_timeTotRobust[]   = $xdata2_timeTotRobust[count($xdata2_timeTotRobust) - 1] + 60;
            }
        }

        $ydata2_calcTotAvRobust[] = $ydata2_temptot[$i];
        $xdata2_timeTotRobust[]   = $xdata2_timeTot[$i];
    }

    $retXY[0] = $ydata2_calcTotAvRobust;
    $retXY[1] = $xdata2_timeTotRobust;
    return $retXY;
}

function windAddMissingTime($retXY) {
    $ydata2_temptot         = $retXY[0]; // Data, accumulative
    $xdata2_timeTot         = $retXY[1]; // Time
    $ydata2_calcTotAvRobust = array();
    $xdata2_timeTotRobust   = array();

    if (!empty($xdata2_timeTot)) {
        $xdata2_timeTotRobust[] = $xdata2_timeTot[0];
    }
    if (!empty($ydata2_temptot)) {
        $ydata2_calcTotAvRobust[] = $ydata2_temptot[0];
    }

    for ($i = 1; $i < sizeof($ydata2_temptot); $i++) {
        $minutes = (int)(($xdata2_timeTot[$i] - $xdata2_timeTot[$i - 1]) / 60) - 1; // Missing minutes

        if ($minutes > 0) {
            for ($j = 0; $j < $minutes; $j++) {
                $last                     = count($ydata2_calcTotAvRobust) - 1;
                $ydata2_calcTotAvRobust[] = number_format($ydata2_calcTotAvRobust[$last], 3, '.', '');
                $xdata2_timeTotRobust[]   = $xdata2_timeTotRobust[count($xdata2_timeTotRobust) - 1] + 60;
            }
        }

        $ydata2_calcTotAvRobust[] = $ydata2_temptot[$i];
        $xdata2_timeTotRobust[]   = $xdata2_timeTot[$i];
    }

    $retXY[0] = $ydata2_calcTotAvRobust;
    $retXY[1] = $xdata2_timeTotRobust;
    return $retXY;
}

function deltaChange($retXY) {
    $ydata2_temptot         = array();
    $xdata2_timeTot         = array();
    $ydata2_calcTotAvRobust = array();
    $xdata2_timeTotRobust   = array();

    for ($i = 1; $i < sizeof($retXY[0]); $i++) {
        if ($retXY[0][$i] != 0) {
            $ydata2_temptot[] = $retXY[0][$i];
            $xdata2_timeTot[] = $retXY[1][$i];
        }
    }

    for ($i = 1; $i < sizeof($ydata2_temptot); $i++) {
        if ($xdata2_timeTot[$i] != intval($xdata2_timeTot[$i - 1])) {
            if (intval($ydata2_temptot[$i]) < intval($ydata2_temptot[$i - 1])) { // counter restarted
                $ydata2_calcTotAvRobust[] = 0;
            } else {
                $ydata2_calcTotAvRobust[] = doubleval($ydata2_temptot[$i] - $ydata2_temptot[$i - 1]);
            }
            $xdata2_timeTotRobust[] = $xdata2_timeTot[$i];
        }
    }

    $retXY[0] = $ydata2_calcTotAvRobust;
    $retXY[1] = $xdata2_timeTotRobust;
    return $retXY;
}


function removeInvalidZeroes($retXY) {
    for ($i = 1; $i < sizeof($retXY[0]); $i++) {
        if ($retXY[0][$i] == 0 || $retXY[0][$i] === null) {
            unset($retXY[0][$i]);
            unset($retXY[1][$i]);
        }
    }

    $retXY[0] = array_values($retXY[0]);
    $retXY[1] = array_values($retXY[1]);
    return $retXY;
}

function getConfig($confKey) {
    static $config = null;
    if ($config === null) {
        $config = array();
        foreach (file(__DIR__ . '/config.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $config[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    return $config[$confKey] ?? '';
}

function isCli() {
    return defined('STDIN');
}

function getSwichStatus($serverHostName, $username, $password, $database, $swichname) {
    $sensors = array();

    $con  = mysqli_connect($serverHostName, $username, $password);
    mysqli_select_db($con, $database) or die("Unable to select database");

    $stmt = mysqli_prepare($con, "SELECT * FROM switchstatus WHERE switchname = ? ORDER BY changedtime DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $swichname);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $myrow = mysqli_fetch_array($result);
        if ($myrow) {
            $sensors[0] = $myrow['switchname'];
            $sensors[1] = $myrow['status'];
            $sensors[2] = $myrow['changedtime'];
        }
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($con);
    return $sensors;
}

function getSwiches($serverHostName, $username, $password, $database) {
    $con    = mysqli_connect($serverHostName, $username, $password);
    mysqli_select_db($con, $database) or die("Unable to select database");
    $result = mysqli_query($con, "SELECT DISTINCT switchname FROM switchstatus");

    $names = array();
    if ($result) {
        while ($myrow = mysqli_fetch_array($result)) {
            $names[] = $myrow['switchname'];
        }
        mysqli_free_result($result);
    }

    mysqli_close($con);
    return $names;
}

function windMilesTometers($retXY) {
    $ydata2_floatingAverage = array();
    $xdata2_floatingAverage = array();
    $valueArray             = $retXY[0];
    $timeArray              = $retXY[1];

    if (sizeof($valueArray) > 0 && $valueArray[0] !== null) {
        for ($f = 1; $f < (sizeof($valueArray) - 1); $f++) {
            $dt = $timeArray[$f] - $timeArray[$f - 1];
            if ($dt == 0) {
                continue;
            }
            // WS = 2.5 * Counts / T  miles/hour; 1 mph = 0.44704 m/s
            $xdata2_floatingAverage[] = $timeArray[$f];
            $ydata2_floatingAverage[] = 2.5 * 0.44704 * $valueArray[$f] / $dt;
        }
    }

    $retXY[0] = $ydata2_floatingAverage;
    $retXY[1] = $xdata2_floatingAverage;
    return $retXY;
}

function waitDbAlive($serverHostName, $username, $password, $database) {
    try {
        $con = mysqli_connect("127.0.0.1", $username, $password, $database);
    } catch (Exception $e) {
        print($e);
    }

    while (!@mysqli_select_db($con, $database)) {
        sleep(5);
    }
}
?>
