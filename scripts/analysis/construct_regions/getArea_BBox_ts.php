<?php


	// ssh grantm@located-platial.geog.mcgill.ca -L 5555:localhost:5432
    $dbconn = pg_connect("host=localhost port=5555 dbname=tweets user=tw password=JyecmADa2WcE-c9");

    $file = fopen('bbox_ts.csv','w');

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
		    	$query = "select ST_NumGeometries(st_collect(geom)) as cnt, st_area(st_transform(st_setsrid(st_envelope(st_extent(geom)),4326),3857)) as area from (select a.fsqid as f1, b.fsqid as f2, b.geom, st_distance(a.geom, b.geom) dist from (select fsqid, geom from (select fsqid, st_setsrid(st_makepoint(lng, lat),4326) as geom, dist from checkins_nearby_ts_distinct where fsqid_match = '".$id."' order by dist limit 2) a order by dist desc limit 1) a, (select fsqid, st_setsrid(st_makepoint(lng,lat),4326) as geom, (fsts[extract(dow from '".$row->ts."' at time zone '".$row->tz."') * 24 + extract(hour from '".$row->ts."' at time zone '".$row->tz."') + 1])::float8 as fs from checkins_nearby_ts_distinct where fsqid_match = '".$id."') b where fs >= ".$fs." order by dist, fs limit ".$k.") g";

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

	// select dist from (select a.fsqid, b.fsqid, st_setsrid(st_makepoint(b.lat, b.lng),4326) as geom, ST_DistanceSpheroid(st_setsrid(st_makepoint(a.lat, a.lng),4326), st_setsrid(st_makepoint(b.lat, b.lng),4326),'SPHEROID["WGS 84",6378137,298.257223563]') as dist from (select fsqid, lat, lng from (select fsqid, lat, lng, dist from checkins_nearby_ts_distinct where fsqid_match = '3fd66200f964a5200eec1ee3' order by dist asc limit 5) a order by random() limit 1) a, (select fsqid, lat, lng, dist from checkins_nearby_ts_distinct where fsqid_match = '3fd66200f964a5200eec1ee3') b order by dist limit 10) g order by dist desc limit 1;


	//select fsqid, lat, lng, dist from checkins_nearby_ts_distinct where fsqid_match = '3fd66200f964a5200eec1ee3' where st_distance

?>