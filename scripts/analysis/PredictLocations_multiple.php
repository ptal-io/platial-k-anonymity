<?php
  
    /* ==========================================================================
        @date May 2022
        @details Loop through all combinations of weights for:
            - Foursquare TS
            - Google PopTimes
            - Hours of Operation
            - Popularity
        for each checking POI and time.  This outputs a file showing the percentage
        of time these combined weights are correct.
    ========================================================================== */

    require('db.php')

    // Expects output filename as only argument
    if (!isset($argv[1]))
        exit;

    $filename = $argv[1];

    error_reporting(E_ALL);
    ini_set("display_errors", 1);

    $file = fopen($filename,'w');
    fwrite($file, "k,random");
    $w = 1;
    for($a=0;$a<=$w;$a+=0.1) {
        $aa = 1 - $a;
        for($b=0;$b<=$a;$b+=0.1) {
            $bb = $a - $b;
            for($c=0;$c<=$b;$c+=0.1) {
                $cc = $b - $c;
                $sum = 0;
                $match = 0;
                $w1 = round($aa*100)/100;
                $w2 = $bb;
                $w3 = $cc;
                $w4 = $c;
                fwrite($file, ",".$w1."|".$w2."|".$w3."|".$w4);
            }
        }
    }
    fwrite($file,"\n");
    fclose($file);

    for($k=2;$k<=30;$k++) {
        doAnalysis($k);
    }

    function po($val) {
        echo "===================\n";
        foreach($val as $k=>$v) {
            echo $v[1] ."\t" . $v[3] . "\t" . $v[4];
            echo "\n";
        }
        
    }
    function doAnalysis($k) {
        global $filename;
        global $dbname;
        global $dbuser;
        global $dbpass;

        $dbconn = pg_connect("host=localhost port=5555 dbname=$dbname user=$dbuser password=$dbpass");


        // FOR POPULARITY * Foursquare (change sql query depending on attributes you want to include)
        $query = "SELECT a.fsqid_match, a.fsqid, a.cat, (a.fsts[extract(dow from b.ts at time zone b.tz) * 24 + extract(hour from b.ts at time zone b.tz) + 1]::float8) as fs, (a.gpts[extract(dow from b.ts at time zone b.tz) * 24 + extract(hour from b.ts at time zone b.tz) + 1]::float8) as gp, (a.avghours[extract(dow from b.ts at time zone b.tz) * 24 + extract(hour from b.ts at time zone b.tz) + 1]::float8) as hr, (a.popularity_avg) as popavg, a.dist from checkins_nearby_ts_distinct a, checkins_distinct b where b.cnt_nearby >= 30 and a.fsqid_match = b.fsqid order by a.fsqid_match, a.fsqid";


        $results = pg_query($query) or die(pg_last_error());

        $data = array();

        while($row = pg_fetch_object($results)) {
            
            if (!isset($data[$row->fsqid_match]))
                $data[$row->fsqid_match] = array();

            if ($row->fsqid == $row->fsqid_match)
                $m = 1;
            else
                $m = 0;
            $data[$row->fsqid_match][] = array($row->fsqid, $m, $row->cat, floatval($row->gp), floatval($row->fs), floatval($row->hr), floatval($row->dist), floatval($row->popavg));

        }


        echo "\nK:\t".$k . "\n";

    
        // CLEAN THE DATA
        // Reduce the set of POI to the k closest.
        $data2 = array();
        foreach($data as $key=>$val) {
            // Sort by distance
            usort($val, function ($a, $b) {
                return $a[6] <=> $b[6];
            });
            // Some sets of nearby don't have a matching venue in the nearby.
            // Make sure that the closest POI is the one that matches the query POI.
            // Add it to the new dataset if it does.  If it doesn't ignore it.
            if ($val[0][1] == 1) {
                $g = array_slice($val, 0, $k);
                $data2[$key] = $g;
            }
        }
        // Update our dataset after clearning non POI matches
        $data = $data2;



        // RANDOM
        $sum = 0;
        $match = 0;
        for($j=0;$j<100;$j++) {
            foreach($data as $key=>$val) {
                shuffle($val);
                if ($val[0][1] == 1) {
                    $match++;
                }
                $sum++;
            }
        }

        $ratio_random = round($match / $sum * 10000)/100;
        echo "\tRandom:\t\t". $ratio_random . "\n";  
      

        $file = fopen($filename,'a');
        fwrite($file, $k.",".$ratio_random);
    
        $w = 1;
        for($a=0;$a<=$w;$a+=0.1) {
            $aa = 1 - $a;
            for($b=0;$b<=$a;$b+=0.1) {
                $bb = $a - $b;
                for($c=0;$c<=$b;$c+=0.1) {
                    $cc = $b - $c;
                    $sum = 0;
                    $match = 0;
                    $w1 = round($aa*100)/100;
                    $w2 = $bb;
                    $w3 = $cc;
                    $w4 = $c;

                    for($j=0;$j<100;$j++) {
                        foreach($data as $key=>$val) {
                            shuffle($val);
                            for($i=0;$i<count($val);$i++) {
                                // weight the temporal variable and the popularity variable
                                $val[$i][8] = ($val[$i][3] * $w1) + ($val[$i][4] * $w2) + ($val[$i][5] * $w3) + ($val[$i][7] * $w4);
                            }
                            usort($val, function ($a, $b) {
                                return $b[8] <=> $a[8];
                            });

                            if ($val[0][1] == 1) {
                                $match++;
                            }
                            $sum++;
                            //$dcg = ndcg($val);
                            //$adcg[] = $dcg;
                        }
                    }
                    //echo $match . "\t" . $sum . "\n";
                    $ratio_1 = $match / $sum;
                    echo "\tWeight ".$w1.",".$w2.",".$w3.",".$w4."\t". round($ratio_1*10000)/100 . "\n";
                    fwrite($file, ",".$ratio_1);
                }
            }

            //$ratio_ndcg = array_sum($adcg)/count($adcg);
            //echo "\tActual nDCG:\t". round($ratio_ndcg*100)/100 . "\n";
            //fwrite($file, ",".$ratio_ndcg);
        }
        fwrite($file, "\n");

        fclose($file);
        

    }

    function ndcg($rank) {
        for($i=0;$i<count($rank);$i++) {
            if ($rank[$i][1] == 1) {
                $pos = $i + 1;
            }
        }
        $dcg = 1 / log($pos+1,2);
        $idcg = 1 / log(2,2);
        return $dcg / $idcg;
    }

    function sd($arr) {
        $num_of_elements = count($arr);
          
        $variance = 0.0;
          
                // calculating mean using array_sum() method
        $average = array_sum($arr)/$num_of_elements;
          
        foreach($arr as $i)
        {
            // sum of squares of differences between 
                        // all numbers and means.
            $variance += pow(($i - $average), 2);
        }
          
        return (float)sqrt($variance/$num_of_elements);
    }



    // create view vw_nearby as select * from checkins_nearby where tweetid in (select tweetid from (select tweetid, count(*) as cnt from checkins_nearby group by tweetid) b where cnt >= 30)
  

    //update fs2gp_unique set ts = a.ts from gp_temp a where a.cat = fs2gp_unique.gp;
?>
