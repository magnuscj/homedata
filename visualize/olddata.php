<?php
date_default_timezone_set('Europe/Stockholm');
include ("homeFunctions.php");



//do 
//{
	$time = time();
  //print date('Y-m-d H:i:s',$time)."\n";

	$debug          = false;
	$username		= getConfig("DBUSN");
	$password		= getConfig('DBPSW');
	$database		= getConfig('DBNAME');
	$serverHostName	= getConfig('DBIP');
  waitDbAlive($serverHostName,$username,$password,$database);
	$sensors 	= getSensorNames($username,$password,$database, $serverHostName); //From sensor configuration
//	$sensorId = getSensorId($sensorNameToShow, $username,$password,$database,$serverHostName);
  //	Kyl_ko
  $sensorId = getSensorId("Sovrum", $username,$password,$database,$serverHostName);
  $value = getLatestTime($sensorId, $username, $password, $serverHostName, $database);

  //print $value."\n";

  $datetime1 = new DateTime(date('Y-m-d H:i:s',$time));
  $datetime2 = new DateTime($value);
  $interval = $datetime1->diff($datetime2);
  print $interval->format('%Y-%m-%d %H:%i:%s')."\n";
//  print $interval;
//}while (true);
	
?>
