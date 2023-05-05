<?php

    /* ==========================================================================
        @date May 2022
        @details Average area of buffer circle (including POI above ts threshold)
    ========================================================================== */

    require('db.php')

    $dbconn = pg_connect("host=localhost port=5555 dbname=$dbname user=$dbuser password=$dbpass");

    $file = fopen('circles_ts.csv','w');

    for($k=1;$k<=20;$k++) {

	    $areas = array();

	    $query = "select fsqid, ts, tz from checkins_distinct order by fsqid limit 500";
	    $result = pg_query($query) or die(pg_last_error());
	    while($row = pg_fetch_object($result)) {
	    	$id = $row->fsqid; // 3fd66200f964a5200eec1ee3
	    	$q2 = "select (fsts[extract(dow from '".$row->ts."' at time zone '".$row->tz."') * 24 + extract(hour from '".$row->ts."' at time zone '".$row->tz."') + 1])::float8 as fs from checkins_nearby_ts_distinct where fsqid = '".$id."'";
	    	//echo $q2 . "\n";
	    	$r = pg_query($q2) or die(pg_last_error());
	    	$fs = null;
	    	while($row2 = pg_fetch_object($r)) {
	    		$fs = $row2->fs;
	    	}
	    	if ($fs) {
		    	$query = "select ST_NumGeometries(st_collect(geom)) as cnt, st_area(st_transform(st_buffer((ST_MinimumBoundingRadius(st_collect(geom))).center,(ST_MinimumBoundingRadius(st_collect(geom))).radius),3857)) as area from (select a.fsqid as f1, b.fsqid as f2, b.geom, st_distance(a.geom, b.geom) dist from (select fsqid, geom from (select fsqid, st_setsrid(st_makepoint(lng, lat),4326) as geom, dist from checkins_nearby_ts_distinct where fsqid_match = '".$id."' order by dist limit 2) a order by dist desc limit 1) a, (select fsqid, st_setsrid(st_makepoint(lng,lat),4326) as geom, ((fsts[extract(dow from '".$row->ts."' at time zone '".$row->tz."') * 24 + extract(hour from '".$row->ts."' at time zone '".$row->tz."') + 1])::float8*0.3 + (gpts[extract(dow from '".$row->ts."' at time zone '".$row->tz."') * 24 + extract(hour from '".$row->ts."' at time zone '".$row->tz."') + 1])::float8*0.3 + (avghours[extract(dow from '".$row->ts."' at time zone '".$row->tz."') * 24 + extract(hour from '".$row->ts."' at time zone '".$row->tz."') + 1])::float8*0.3 + popularity_avg*0.1) as fs from checkins_nearby_ts_distinct where fsqid_match = '".$id."') b where fs >= ".$fs." order by dist, fs limit ".$k.") g";

		    	$result2 = pg_query($query) or die(pg_last_error());
		    	while($row = pg_fetch_object($result2)) {
		    		$cnt = $row->cnt;
		    		if ($cnt == $k)
		    			$areas[] = $row->area;
		    	}
		    }
	    }

	    //var_dump($areas);
	    $length = count($areas);
	    sort($areas);
	    $mean = round(array_sum($areas) / $length, 2);
		$half_length = $length / 2;
		$median_index = (int) $half_length;
		$median = round($areas[$median_index],2);
		//$sd = sd($areas);
		echo $k . "\t" . $mean . "\t" . $median . "\t".$length."\n"; // . $sd . "\n";
		fwrite($file,$k.",".$mean.",".$median.",".$length."\n");

	}
	fclose($file);

	function sd_square($x, $mean) { return pow($x - $mean,2); }

	// Function to calculate standard deviation (uses sd_square)   
	function sd($array) {
	   
	// square root of sum of squares devided by N-1
	return sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
	}

?>