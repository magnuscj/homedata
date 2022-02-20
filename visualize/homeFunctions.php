<?php
date_default_timezone_set('Europe/Stockholm');


	function UnixTime($mysql_timestamp)
	{ 
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
	    } 
	    else
	    {   
	    	return $unix_time;
	    }
	  	return $unix_time; 
	}	
	
	function getDbData($fromyear,$toyear, $frommonth, $tomonth,$username,$password,$database, $fdate,$tdate,$sensor, $serverHostName)
	{
		$query="";
		$ydata = array();
		$UNIXdata = array();
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
						
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
				$query= $query."SELECT * FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." 00:00:00' AND curr_timestamp <= '".$tdate." 23:59:59' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db($database) or die( "Unable to select database");
		$result = mysql_query($query." ORDER BY curr_timestamp ASC");
		$myrow=mysql_fetch_array($result);
		$i=0;
		if ($myrow) 
		{	 	
		do
		{
		   	$ydata[]        = $myrow['data'];  //It would not create the graphs without using '[]'
		    $datedata[]    	= $myrow['curr_timestamp'];  //It would not create the graphs without using '[]'
  	 		$UNIXdata[$i] 	= UnixTime($datedata[$i]);
  	 		$i++;
		}while ($myrow=mysql_fetch_array($result));
		mysql_free_result($result);
		}
		$ret = array();
		$ret[0]= floatAvg(5, $ydata);
		$ret[1]= $UNIXdata;
		
		mysql_close();
		return $ret;
	}
	
	function getDataFromDb($username,$password,$database, $fdate,$tdate,$sensor, $serverHostName)
	{
		$fsplited 	= preg_split ( '/-/' ,$fdate  );
		$tsplited 	= preg_split ( '/-/' ,$tdate  );

		$frommonth = (int)$fsplited[1];
		$tomonth   = (int)$tsplited[1];
		$fromyear  = (int)$fsplited[0];
		$toyear	   = (int)$tsplited[0];
		
		return getPower($fromyear,$toyear, $frommonth, $tomonth,$username,$password,$database, $fdate,$tdate,$sensor, $serverHostName);
	}
	
	function zeroAdjust($no)
	{
		$str = '';
				
		if($no <= 9)
		{
			$str = "0".$no;
		}
		else
		{
			$str= $no;
		}	
			
		return (string)$str;
	}
	
	function getPower($fromyear,$toyear, $frommonth, $tomonth,$username,$password,$database, $fdate,$tdate,$sensor, $serverHostName)
	{
		$query      = "";
		$ydata      = array();
		$UNIXdata   = array();
		$myrow      = array();
		$tid        = localtime();
		$ftime      = zeroAdjust($tid[2]).":".zeroAdjust($tid[1]);
		$ttime      = $ftime;
		$tomonthT   = $tomonth;
		$frommonthT = $frommonth;	
		
		
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			if ($fromyear != $toyear)
				if($ycont<$toyear)
					$tomonth = 12;
				else
				{
					$tomonth = $tomonthT;
					$frommonth= 1;
				}
			
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
				$query= $query."SELECT data,curr_timestamp  FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ."' AND curr_timestamp <= '".$tdate."' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		$con = mysqli_connect($serverHostName,$username,$password);
		@mysqli_select_db($con, $database) or die( "Unable to select database");
		$result = mysqli_query($con, $query." ORDER BY curr_timestamp ASC");
		
		if($result != false)
			$myrow=mysqli_fetch_array($result);
			
		$i=0;
		 if ($myrow) 
		 {
		   	do
		   	{	
		   		if($myrow['data'] != 0)
		   		{
		   			$ydata[]        = $myrow['data'];  //It would not create the graphs without using '[]'
			     	$UNIXdata[$i]	= strtotime($myrow['curr_timestamp']);
	  	 			$i++;
		   		}
		   	}while ($myrow=mysqli_fetch_array($result));
		   	mysqli_free_result($result);
		 }
		
		$ret    = array();
		$ret[0] = $ydata;
		$ret[1] = $UNIXdata;
		
		mysqli_close($con);
        

		return $ret;
	}
	
    function reduceData( $windowSize, $valueArray)
	{
        $windowSize = (int) $windowSize;
		$ydata2_floatingAverage = array();
		$floatingAverage = (double) 0.0;
		if(sizeof($valueArray[0])>0 && $windowSize>0)
		{
			if($valueArray[0][0] !== null)
			{
				for ($f=0;$f<(sizeof($valueArray[0])-$windowSize);$f=$f+$windowSize)
				{
					for($k=0;$k<$windowSize;$k++)
					{
						$floatingAverage+=(double)($valueArray[0][$f+$k]);
					}
                    
                                       
					$ydata2_floatingAverage[0][]= (double) $floatingAverage/$windowSize;
                    $ydata2_floatingAverage[1][]=$valueArray[1][$f];
					$floatingAverage= (double)0.0;
				}
			}
		}
        
        if($windowSize>0)
		    return $ydata2_floatingAverage;
        else
            return $valueArray;
	}
    
    
	
	/*******************************************************************/
	// Function: getSensorNames
	// Description: The function will connect to the database on the
	//				server and retreive all sensors configuration data.
	/*******************************************************************/
	function getSensorNames($username,$password,$database,$serverHostName)
	{
		$sensors  = array();//The array will contain arrays
		$errConDb = "Unable to select database";
		$query    = "SELECT * FROM sensorconfig;";
		$con = mysqli_connect($serverHostName, $username,$password);
		@mysqli_select_db($con,$database) or die($errConDb);
			
		$result = mysqli_query($con, $query); //Sending the query.
		//Now lets walk trough the result array and store the data according
		//to the table layout.
		if ($result) 
		{
			$myrow=mysqli_fetch_array($result, MYSQLI_BOTH);
		   	do
		   	{	
		   	$ids[]          = $myrow['sensorid']; 
		     	$names[]        = $myrow['sensorname'];
		     	$color[]   	= $myrow['color'];  
		     	$visible[]	= $myrow['visible'];
		     	$type[]		= $myrow['type'];
		   	}while ($myrow=mysqli_fetch_array($result, MYSQLI_BOTH));
			mysqli_free_result($result);
		}
		$sensors[0] = $ids;
		$sensors[1] = $names;
		$sensors[2] = $color;
		$sensors[3] = $visible;
		$sensors[4] = $type;
		mysqli_close($con);
		return $sensors;
	}

	function getSensorId($name, $username,$password,$database,$serverHostName)
	{
		$sensors  = array();//The array will contain arrays
		$errConDb = "Unable to select database";
		$query    = "SELECT sensorid FROM sensorconfig WHERE sensorname='".$name."';";
		$con = mysqli_connect($serverHostName, $username,$password);
		@mysqli_select_db($con,$database) or die($errConDb);
			
		$result = mysqli_query($con, $query); //Sending the query.
		//Now lets walk trough the result array and store the data according
		//to the table layout.
		if ($result) 
		{
			$myrow=mysqli_fetch_array($result, MYSQLI_BOTH);
		   	do
		   	{	
		   		$id[]      = $myrow['sensorid']; 
		   	}while ($myrow=mysqli_fetch_array($result, MYSQLI_BOTH));
			mysqli_free_result($result);
		}

		mysqli_close($con);
		return $id[0];
	}
	
	function onlyPowerType($sensors)
	{
		for ($i=0;$i<sizeof($sensors[0]);$i++)
		{
			if($sensors[4][$i] == "temp" && $sensors[3][$i] == "True")
			{
				return false;
			}
		}
		return true;
	}
	
	function getWebConfig($username,$password,$database,$serverHostName)
	{
		$webItems = array();
		$fDate = "";
		$tDate = "";
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db($database) or die( "Unable to select database");
		$query = "SELECT * FROM webConfig;";
		$result = mysql_query($query);
		
		 if ($result) 
		 {
		 	$myrow=mysql_fetch_array($result);
		   	do
		   	{	
		   		$fDate     = $myrow['startDate']; 
		     	$tDate   = $myrow['endDate']; 		
		     	
		   	}while ($myrow=mysql_fetch_array($result));
		   	mysql_free_result($result);
		 }
		$webItems[0] = $fDate;
		$webItems[1] = $tDate;
		
		mysql_close();
		return $webItems;
	}
	
	function upDateWebConfig($username,$password,$fdate,$tdate, $serverHostName)
	{
		
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db("test") or die( "Unable to select database");
		//$query = "INSERT INTO `".$database."`.`webconfig` (`startDate`, `endDate`) VALUES ('".$fdate."', '".$tdate."');";
		$query = "UPDATE `".$database."`.`webconfig` SET `startDate`='".$fdate."' WHERE `id`='1';";
		$result = mysql_query($query);
		
		mysql_free_result($result);
		mysql_close();
	
	
	}
	
	function floatAvg($windowSize, $valueArray)
	{
		$ydata2_floatingAverage = array();
		$floatingAverage = (double) 0.0;
		if(sizeof($valueArray)>0)
		{
			if($valueArray[0] !== null)
			{
				for ($f=0;$f<(sizeof($valueArray)-$windowSize);$f++)
				{
					for($k=0;$k<$windowSize;$k++)
					{
						$floatingAverage+=(double)($valueArray[$f+$k]);
					}
					$ydata2_floatingAverage[]= (double) $floatingAverage/$windowSize;
					$floatingAverage= (double)0.0;
				}
				
				for($k=0;$k<$windowSize;$k++)
				{
                    if($f>0)
					    $ydata2_floatingAverage[] =$ydata2_floatingAverage[$f-1];
				}
			}
		}
		return $ydata2_floatingAverage;
	}
	
	function getTimeDate($fromyear,$toyear, $frommonth, $tomonth,$username,$password,$database, $fdate,$tdate,$sensor,$Nowtime,$Totime, $serverHostName)
	{
		$query = "";
		$ydata = array();
		$time  = array();
		
		print "from: ".$fromyear." To".$toyear;
		
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
				$query= $query."SELECT * FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." ".$Totime."' AND curr_timestamp <= '".$tdate." ".$Nowtime."' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db($database) or die( "Unable to select database");
		$result = mysql_query($query." ORDER BY curr_timestamp ASC");
		$myrow=mysql_fetch_array($result);
		$i=0;
		 if ($myrow) 
		 {
		 	
		   	do
		   	{
		   		$ydata[]    = $myrow['data'];  
		     	$datedata[] = $myrow['curr_timestamp']; 
  	 			$time[$i] 	= UnixTime($datedata[$i]);
  	 			$i++;
		   	}while ($myrow=mysql_fetch_array($result));
		   	mysql_free_result($result);
		 }
		 
		$ret = array();
		$ret[0]= floatAvg(5, $ydata);
		$ret[1]= $time;
		
		mysql_close();
		return $ret;
	}
	
	function currentTemp($sensors,$username,$password,$serverHostName,$database )
	{
		$tdate    = date("Y-m-d", mktime(0,0,0,date("m"),date("d"),date("Y")));
		$tsplited = preg_split ( '/-/' ,$tdate  );
		
		$curr = array();
		for ($i=0;$i<sizeof($sensors[0]);$i++)
		{

			$con=mysqli_connect($serverHostName,$username,$password);
			@mysql_select_db($con, $database) or die( "Unable to select database");
			$query = "SELECT data FROM".$database." table".$tsplited[0].$tsplited[1]." WHERE sensorid='".$sensors[0][$i]."' ORDER BY id DESC LIMIT 1";
			$result = mysqli_query($query);
			$curr[$i]= mysql_fetch_array($result);
			mysqli_free_result($result);
			mysqli_close($con);
		}
		return $curr;
	}

	function getCurr($sensor,$username,$password,$serverHostName,$database )
	{
		
		$con=mysqli_connect($serverHostName,$username,$password);
		@mysqli_select_db($con, $database) or die( "Unable to select database");
		
		$tdate = date("Ym", mktime(0,0,0,date("m"),date("d"),date("Y")));
		$query  = "SELECT data FROM ".$database.".table".$tdate." WHERE sensorid='".$sensor."' ORDER BY id DESC LIMIT 1";
		$result = mysqli_query($con,$query);
		if($result === FALSE) 
		{
			die(mysql_error()); // TODO: better error handling
		}

		$curr   = mysqli_fetch_array($result);
		mysqli_free_result($result);
		mysqli_close($con);
		
		return $curr[0];
	}

	function getCurrByName($sensorName,$username,$password,$serverHostName,$database )
	{
		
		$con=mysqli_connect($serverHostName,$username,$password);
		@mysqli_select_db($con, $database) or die( "Unable to select database");
		
		$tdate = date("Ym", mktime(0,0,0,date("m"),date("d"),date("Y")));
		$query  = "SELECT data FROM ".$database.".table".$tdate." WHERE sensorid='".$sensor."' ORDER BY id DESC LIMIT 1";
		$result = mysqli_query($con,$query);
		if($result === FALSE) 
		{
			die(mysql_error()); // TODO: better error handling
		}

		$curr   = mysqli_fetch_array($result);
		mysqli_free_result($result);
		mysqli_close($con);
		
		return $curr[0];
	}
	
	
	function scaleChange($factor, $valueArray)
	{
		$ydata2_floatingAverage = array();
		
		if(sizeof($valueArray)>0)
		{
			if($valueArray[0] !== null)
			{
				for ($f=0;$f<(sizeof($valueArray));$f++)
				{
						$valueArray[$f]= (double)$factor*$valueArray[$f];
				}
			}
		}
		return $valueArray;
	}

	function getMax($fdate,$tdate,$sensor,$username,$password,$serverHostName,$database)
	{
		//SELECT MAX(data) FROM table201208 WHERE curr_timestamp >= '2012-08-01 00:00:00' AND curr_timestamp <= '2012-08-31 23:59:59' AND sensorid ='C9000002D6613
		$query="";
		$fsplited 	= preg_split ( '/-/' ,$fdate  );
		$tsplited 	= preg_split ( '/-/' ,$tdate  );
		
		$frommonth = (int)$fsplited[1];
		$tomonth   = (int)$tsplited[1];
		$fromyear  = (int)$fsplited[0];
		$toyear	   = (int)$tsplited[0];
		
		
		$tomonthT = $tomonth;
		$frommonthT = $frommonth;	
		
		
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			if ($fromyear != $toyear)
				if($ycont<$toyear)
					$tomonth = 12;
				else
				{
					$tomonth = $tomonthT;
					$frommonth= 1;
				}
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
				$query= $query."SELECT MAX(data) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." 00:00:00' AND curr_timestamp <= '".$tdate." 23:59:59' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		$con=mysqli_connect($serverHostName,$username,$password);
		@mysqli_select_db($con, $database) or die( "Unable to select database");
		$result = mysqli_query($con,$query);
        
        if($result)
        {
            $myrow = mysqli_fetch_array($result);
        }
        else
        {
            return null;
        }
        
        $ydata  = $myrow[0];  
        if ($myrow) 
        {
            
            do
            {
                if($myrow[0]>$ydata)
                    $ydata  = $myrow[0];  
                
            }while ($myrow=mysqli_fetch_array($result));
            mysqli_free_result($result);
        }
        
		
        
        
		mysqli_close($con);
		return $ydata;
	}
	
	function getCnt($fdate,$tdate,$sensor,$username,$password,$serverHostName,$database)
	{
		//SELECT MAX(data) FROM table201208 WHERE curr_timestamp >= '2012-08-01 00:00:00' AND curr_timestamp <= '2012-08-31 23:59:59' AND sensorid ='C9000002D6613
		$query="";
		$fsplited 	= preg_split ( '/-/' ,$fdate  );
		$tsplited 	= preg_split ( '/-/' ,$tdate  );
		
		$frommonth = (int)$fsplited[1];
		$tomonth   = (int)$tsplited[1];
		$fromyear  = (int)$fsplited[0];
		$toyear	   = (int)$tsplited[0];
		
		
		$tomonthT = $tomonth;
		$frommonthT = $frommonth;	
		
		
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			if($ycont<$toyear)
				$tomonth = 12;
			else
			{
				$tomonth = $tomonthT;
				$frommonth= 1;
			}
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
				$query= $query."SELECT COUNT(*) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." 00:00:00' AND curr_timestamp <= '".$tdate." 23:59:59' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db($database) or die( "Unable to select database");
		$result = mysql_query($query);
		$res = mysql_fetch_array($result);
		//$curr[$i]=mysql_fetch_array($result);
		//mysql_free_result($result);
		mysql_close();
		return $res[0];
	}
	
	function getAvg($fdate,$tdate,$sensor,$username,$password,$serverHostName,$database)
	{
		//SELECT MAX(data) FROM table201208 WHERE curr_timestamp >= '2012-08-01 00:00:00' AND curr_timestamp <= '2012-08-31 23:59:59' AND sensorid ='C9000002D6613'
		$query="";
		$fsplited 	= preg_split ( '/-/' ,$fdate  );
		$tsplited 	= preg_split ( '/-/' ,$tdate  );
		
		$frommonth = (int)$fsplited[1];
		$tomonth   = (int)$tsplited[1];
		$fromyear  = (int)$fsplited[0];
		$toyear	   = (int)$tsplited[0];
		$res		= array(null,null);
		
		$tomonthT = $tomonth;
		$frommonthT = $frommonth;	
		
		
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			if ($fromyear != $toyear)
				if($ycont<$toyear)
					$tomonth = 12;
				else
				{
					$tomonth = $tomonthT;
					$frommonth= 1;
				}
				
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
				$query= $query."SELECT AVG(data) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." 00:00:00' AND curr_timestamp <= '".$tdate." 23:59:59' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db($database) or die( "Unable to select database");
		$result = mysql_query($query);
		if($result!=false)
			$res = mysql_fetch_array($result);
		//$curr[$i]=mysql_fetch_array($result);
		//mysql_free_result($result);
		mysql_close();
		return $res[0];
	}
	
	function getPowerAvg($fdate,$tdate,$sensor,$username,$password,$serverHostName,$database)
	{
		/*Not finished*/
		//SELECT MAX(data) FROM table201208 WHERE curr_timestamp >= '2012-08-01 00:00:00' AND curr_timestamp <= '2012-08-31 23:59:59' AND sensorid ='C9000002D6613'
		$query1="";
		$query2="";
		$query3="";
		$avgP  =0; //Returnvalue
		$fsplited  = preg_split ( '/-/' ,$fdate  );
		$tsplited  = preg_split ( '/-/' ,$tdate  );
		
		$frommonth = (int)$fsplited[1];
		$tomonth   = (int)$tsplited[1];
		$fromyear  = (int)$fsplited[0];
		$toyear	   = (int)$tsplited[0];
		$res	   = array(null,null);
		
		$querries  = array();
		$ydata	   = array();
		$datedata  = array();
		$UNIXdata  = array();
		
		$tomonthT  = $tomonth;
		$frommonthT= $frommonth;	
		

		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			if ($fromyear != $toyear)
				if($ycont<$toyear)
					$tomonth = 12;
				else
				{
					$tomonth = $tomonthT;
					$frommonth= 1;
				}
				
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
					
				$query1 = $query1."SELECT MAX(curr_timestamp), MAX(data) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ."' AND curr_timestamp <= '".$tdate."' AND sensorid ='".$sensor."'".$union;
				$query2 = $query2."SELECT MIN(curr_timestamp), MIN(data) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ."' AND curr_timestamp <= '".$tdate."' AND sensorid ='".$sensor."'".$union;
				$query3 = $query3."SELECT COUNT(*) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." ' AND curr_timestamp <= '".$tdate."' AND sensorid ='".$sensor."'".$union;
			}
		}
		

		$con = mysqli_connect($serverHostName,$username,$password);
		@mysqli_select_db($con, $database) or die( "Unable to select database");
		
		$result = mysqli_query($con, $query1);		
		$myrow = mysqli_fetch_array($result);
	
		$i=0;
		if($result!=false)
		{
			if ($myrow) 
			{
			 	
			   	do
			   	{
			   		$ydata[]        = $myrow['MAX(data)'];  //It would not create the graphs without using '[]'
					$datedata[]    	= $myrow['MAX(curr_timestamp)'];  //It would not create the graphs without using '[]'
	  	 			$UNIXdata[] 	= UnixTime($datedata[0]);
	  	 			$i++;
			   	}while ($myrow=mysqli_fetch_array($result));
			   	mysqli_free_result($result);
			}
			 
			 
			$result = mysqli_query($con, $query2);		
			$myrow=mysqli_fetch_array($result);
			$i=0;

			if($result!=false)
			{
				if ($myrow) 
				{
				   	do
				   	{
				   		$ydata[]        = $myrow['MIN(data)'];  //It would not create the graphs without using '[]'
				     	$datedata[]    	= $myrow['MIN(curr_timestamp)'];  //It would not create the graphs without using '[]'
		  	 			$UNIXdata[] 	= UnixTime($datedata[1]);
		  	 			$i++;
				   	}while ($myrow=mysqli_fetch_array($result));
				   	mysqli_free_result($result);
				}

				if(((sizeof($UNIXdata)>= 2) && (sizeof($ydata) >= 2)) &&
				 	    ($UNIXdata[0]>0 && $UNIXdata[1]>0 && $ydata[0]>0 && $ydata[1]>0) &&
				 	    ($UNIXdata[0] != $UNIXdata[1]))
				{
				$seconds= date($UNIXdata[0]-$UNIXdata[1]);
				$counts = $ydata[0]-$ydata[1];
				$avgP   = $counts/$seconds; //  counter steps/ T(s)
				}
				else 
				{
				$avgP=0;
				print "Error: Not enough data\n";
				}
			}	 
		}	 
		mysqli_close($con);
		return $avgP;
	}
	
	function getMin($fdate,$tdate,$sensor,$username,$password,$serverHostName,$database)
	{
		$query="";
		$fsplited 	= preg_split ( '/-/' ,$fdate  );
		$tsplited 	= preg_split ( '/-/' ,$tdate  );
		
		$frommonth = (int)$fsplited[1];
		$tomonth   = (int)$tsplited[1];
		$fromyear  = (int)$fsplited[0];
		$toyear	   = (int)$tsplited[0];
			
		$tomonthT = $tomonth;
		$frommonthT = $frommonth;	
			
		for($ycont = $fromyear; $ycont <= $toyear; $ycont++)
		{
			if ($fromyear != $toyear)
				if($ycont<$toyear)
					$tomonth = 12;
				else
				{
					$tomonth = $tomonthT;
					$frommonth= 1;
				}
				
			for($mcont = $frommonth; $mcont <= $tomonth; $mcont++)
			{
				if((($fromyear < $toyear) && ($ycont!=$toyear)) || (($frommonth < $tomonth) && ($mcont!=$tomonth)))
					$union = " UNION ";
				else
					$union = "";
					
				if($mcont <=9)
					$zero ="0";
				else
					$zero ="";
					
				$query= $query."SELECT MIN(data) FROM table".(string)$ycont.$zero.(string)$mcont." WHERE curr_timestamp >= '".$fdate ." 00:00:00' AND curr_timestamp <= '".$tdate." 23:59:59' AND sensorid ='".$sensor."'".$union;
			}
		}
		
		$con=mysqli_connect($serverHostName,$username,$password);
		@mysqli_select_db($con, $database) or die( "Unable to select database");
		$result = mysqli_query($con, $query);
        
		$myrow = mysqli_fetch_array($result);     
        $ydata  = $myrow[0];  
        if ($myrow) 
        {
            
            do
            {
                if($myrow[0]<$ydata)
                    $ydata  = $myrow[0];  
                
            }while ($myrow=mysqli_fetch_array($result));
            mysqli_free_result($result);
        }
        
		
        
        
		mysqli_close($con);
		return $ydata;
	}
	
	function sum($valueArray, $accumulate)
	{
		//If $accumulate     => [5,4,3,2,2,3] => 5+4+3+2+2+3=
		//If not $accumulate => [5,4,3,2,2,3] => 4-5 + 3-4 + 2-3 ... best for counters 
		
		$ydata2_floatingAverage = array();
		$floatingAverage = (double) 0.0;
		$sum = (double) 0.0;
		if(sizeof($valueArray)>0)
		{
			if($valueArray[0] !== null)
			{
				for ($f=0;$f<(sizeof($valueArray)-1);$f++)
				{
					if($accumulate)
					{
						if($valueArray[$f+1] !== null)
							$sum = $valueArray[$f]+ $sum ;
					}
					else 
					{
						if($valueArray[$f+1] !== null)
							$sum = $valueArray[$f+1]-$valueArray[$f]+ $sum ;
					}
				}
			}
		}
		return $sum;
	}
	
	function addMissingTime($retXY)
	{
		$ydata2_temptot 		= array();
		$xdata2_timeTot 		= array();
		$ydata2_calcTotAvRobust = array();
		$xdata2_timeTotRobust 	= array();
		$ydata2_temptot         = $retXY[0]; //Data, accumulative 
		$xdata2_timeTot         = $retXY[1]; //Time
				
		$xdata2_timeTotRobust[]   = $xdata2_timeTot[0];
		$ydata2_calcTotAvRobust[] = $ydata2_temptot[0];
		//------------ Robust Start--------------
		
		for($i=1;$i<(sizeof($ydata2_temptot));$i++)
		{
			$minutes = (int)(($xdata2_timeTot[$i]-$xdata2_timeTot[$i-1])/60)-1;	//Missing minutes
			
			if($minutes > 0)//If missing minutes 
			{				
				$averagePower = ($ydata2_temptot[$i]-$ydata2_temptot[$i-1])/$minutes;
				
				for($j=0;$j<$minutes;$j++)
				{						
					$ydata2_calcTotAvRobust[]= $ydata2_calcTotAvRobust[sizeof($ydata2_calcTotAvRobust)-1] + $averagePower;
					
					$xdata2_timeTotRobust[] = $xdata2_timeTotRobust[sizeof($xdata2_timeTotRobust)-1]+60;
				}		
			}
			
			$ydata2_calcTotAvRobust[]= ($ydata2_temptot[$i]);
			$xdata2_timeTotRobust[]= $xdata2_timeTot[$i];				
				
		}
		$retXY[0] = $ydata2_calcTotAvRobust;
		$retXY[1] = $xdata2_timeTotRobust;
	
		return $retXY;
	}
    
    function windAddMissingTime($retXY)
	{
		$ydata2_temptot 		= array();
		$xdata2_timeTot 		= array();
		$ydata2_calcTotAvRobust = array();
		$xdata2_timeTotRobust 	= array();
		$ydata2_temptot         = $retXY[0]; //Data, accumulative 
		$xdata2_timeTot         = $retXY[1]; //Time
        
		$xdata2_timeTotRobust[]   = $xdata2_timeTot[0];
		$ydata2_calcTotAvRobust[] = $ydata2_temptot[0];
		//------------ Robust Start--------------
		
		for($i=1;$i<(sizeof($ydata2_temptot));$i++)
		{
			$minutes = (int)(($xdata2_timeTot[$i]-$xdata2_timeTot[$i-1])/60)-1;	//Missing minutes
			
			if($minutes > 0)//If missing minutes 
			{				
                
				for($j=0;$j<$minutes;$j++)
				{						
					$ydata2_calcTotAvRobust[]= number_format($ydata2_calcTotAvRobust[sizeof($ydata2_calcTotAvRobust)-1],3,'.','');
					
					$xdata2_timeTotRobust[] = $xdata2_timeTotRobust[sizeof($xdata2_timeTotRobust)-1]+60;
				}		
			}
			
			$ydata2_calcTotAvRobust[]= ($ydata2_temptot[$i]);
			$xdata2_timeTotRobust[]= $xdata2_timeTot[$i];				
            
		}
		$retXY[0] = $ydata2_calcTotAvRobust;
		$retXY[1] = $xdata2_timeTotRobust;
		
		
		return $retXY;
	}
    
	function deltaChange($retXY)
	{		
		$ydata2_temptot 		= array();
		$xdata2_timeTot 		= array();
		$ydata2_calcTotAvRobust 	= array();
		$xdata2_timeTotRobust 		= array();
		$ydata2_temptot  		= array();
		$xdata2_timeTot  		= array();
		$offset			 	= 0;     
		
		for($i=1;$i<(sizeof($retXY[0]));$i++)
		{
			if($retXY[0][$i] != 0)
			{
				$ydata2_temptot[]	= $retXY[0][$i];
				$xdata2_timeTot[]	= $retXY[1][$i];
			}
			else
			{
				
			}
		}
		
		for($i=1;$i<(sizeof($ydata2_temptot));$i++)
		{		
			if(($ydata2_temptot[$i] != 0) && /*(intval($ydata2_temptot[$i]) - intval($ydata2_temptot[$i-1]))!=0*/ ($xdata2_timeTot[$i] != intval($xdata2_timeTot[$i-1])))
			{
								
				if(intval($ydata2_temptot[$i])<intval($ydata2_temptot[$i-1]))//If the counter has restarted.
				{		
					$ydata2_calcTotAvRobust[]= 0 /*intval($ydata2_temptot[$i])*/;
				}
				else 
				{
					$ydata2_calcTotAvRobust[]= doubleval(($ydata2_temptot[$i]-$ydata2_temptot[$i-1]));
				}
			
				$xdata2_timeTotRobust[]  = $xdata2_timeTot[$i];	
			}
			
		}
		
		$retXY[0] = $ydata2_calcTotAvRobust;
		$retXY[1] = $xdata2_timeTotRobust;	
							
		return $retXY;
	}
	
	function removeInvalidValues($retXY)
	{
		$ydata2_temptot 		= array();
		$xdata2_timeTot 		= array();
		$ydata2_calcTotAvRobust = array();
		$xdata2_timeTotRobust 	= array();
		$ydata2_temptot  = 		$retXY[0];
		$xdata2_timeTot  = 		$retXY[1];
	
		
		for($i=1;$i<(sizeof($ydata2_temptot));$i++)
		{
			$tmp1 = $retXY[0][$i-1];
			$tmp2 = $retXY[0][$i];
			
			if($i ==  2)
				if($tmp1<$tmp2)
					$retXY[1][$i-1]=$retXY[1][$i];	
		
		}
		$retXY[0] = $ydata2_calcTotAvRobust;
		$retXY[1] = $xdata2_timeTotRobust;
		
		return $retXY;
	}
    
    function removeInvalidZeroes($retXY)
	{
		$ydata2_temptot 		= array();
		$xdata2_timeTot 		= array();
		$ydata2_calcTotAvRobust = array();
		$xdata2_timeTotRobust 	= array();
		$ydata2_temptot  = 		$retXY[0];
		$xdata2_timeTot  = 		$retXY[1];
        
		
		for($i=1;$i<(sizeof($retXY[0]));$i++)
		{
			if($retXY[0][$i]==0 || $retXY[0][$i]==null )
            {
                unset($retXY[0][$i]);
                unset($retXY[1][$i]);
            }
			
            
		}
		
		$retXY[0] = array_values ($retXY[0]);
        $retXY[1] = array_values ($retXY[1]);
		return $retXY;
	}
		
	function getConfig($confKey)
	{
		$lines = file('config.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) 
		{
			
			$lineParts = preg_split('/:/',$line);
			$int = strcmp($lineParts[0],$confKey);
    		if(strcmp($lineParts[0],$confKey)==0)
    			return $lineParts[1];  		
		}
		return "";
	}
	
	function isCli()
    {
        if(defined('STDIN') )
        {
            return true;    
        }
        return false;
    }    

    function getSwichStatus($serverHostName,$username,$password, $database, $swichname)
    {
    	//SELECT switchname, changedtime FROM switchstatus WHERE switchname='test6' ORDER BY( changedtime) DESC LIMIT 1
    	$sensors = array();
    	
		mysql_connect($serverHostName,$username,$password);
		@mysql_select_db($database) or die( "Unable to select database");
		$query = "SELECT * FROM switchstatus WHERE switchname='$swichname' ORDER BY(changedtime) DESC LIMIT 1;";
		$result = mysql_query($query);
		
		 if ($result) 
		 {
		 	$myrow=mysql_fetch_array($result);
		   	do
		   	{	
		   		$names[]      = $myrow['switchname'];  //It would not create the graphs without using '[]'
		     	$status[]     = $myrow['status'];  //It would not create the graphs without using '[]'
		     	$chktime[]    = $myrow['changedtime'];  //It would not create the graphs without using '[]'
		   	}while ($myrow=mysql_fetch_array($result));
		   	mysql_free_result($result);
		 }
		
		
		 
		$sensors[0] = $names[0];
		$sensors[1] = $status[0];
		$sensors[2] = $chktime[0];
		
				
		mysql_close();
		return $sensors;
    }
	
    function getSwiches($serverHostName,$username,$password, $database)
    {
    	   	 
    	mysql_connect($serverHostName,$username,$password);
    	@mysql_select_db($database) or die( "Unable to select database");
    	$query = "SELECT DISTINCT switchname FROM switchstatus;";
    	$result = mysql_query($query);
    
    	if ($result)
    	{
    		$myrow=mysql_fetch_array($result);
    		do
    		{
    			$names[]      = $myrow['switchname'];  //It would not create the graphs without using '[]'
    			
    		}while ($myrow=mysql_fetch_array($result));
    		mysql_free_result($result);
    	}
     
    	mysql_close();
    	return $names;
    }
    
    function rm($serverHostName,$username,$password, $database)
    {
    	mysql_connect($serverHostName,$username,$password);
    	@mysql_select_db($database) or die( "Unable to select database");
    	$query = "SELECT DISTINCT switchname FROM switchstatus;";
    	$result = mysql_query($query);
    
    	if ($result)
    	{
    		$myrow=mysql_fetch_array($result);
    		do
    		{
    			$names[]      = $myrow['switchname']; 
                
    		}while ($myrow=mysql_fetch_array($result));
    		mysql_free_result($result);
    	}
     
    	mysql_close();
    	return $names[1];
    }
    
    function windMilesTometers($retXY)
    {
    	$ydata2_meters = array();
    	$valueArray    = $retXY[0];
    	$timeArray     = $retXY[1];
    	//date("Y-m-d H:i:s", strtotime(2.5* $valueArray[$f]/($timeArray[$f+1]-$timeArray[$f])));
    	if(sizeof($valueArray)>0)
    	{
    		if($valueArray[0] !== null)
    		{
    			for ($f=1;$f<(sizeof($valueArray)-1);$f++)
    			{
    				//WS = 2.5*Counts/T miles/hour
    				//1 mile = 1609.344 meters
    				//miles/hour ==> 1609.344/3600 == 0,44704 m/s
    				
    				$tmp = 2.5*0.44704*$valueArray[$f]/($timeArray[$f]-$timeArray[$f-1]);
    				$xdata2_floatingAverage[] = $timeArray[$f];
	    			$ydata2_floatingAverage[] = $tmp;
	    			//print date("H:i:s", $timeArray[$f])." --- ". date("H:i:s", $timeArray[$f+1])."	". number_format($timeArray[$f]-$timeArray[$f-1],1)."s ";
	    			//print " ".$valueArray[$f]."tics, ".number_format($tmp,1)."m/s. \n";
	    			    			
	    			
    			}
    		}
    	}
    	$retXY[0] = $ydata2_floatingAverage;
    	$retXY[1] = $xdata2_floatingAverage;
    	return $retXY;
    }
    
    function waitDbAlive($serverHostName,$username,$password,$database)
    {
	try
	{
		$con =  mysqli_connect("127.0.0.1",$username,$password,$database);//$serverHostName,$username,$password);
		
	}
	catch(Exception $e)
	{
		print($e);
	}
	
        while(!@mysqli_select_db($con, $database))
        {
            sleep(5);   
        }
    }
	?>
				
