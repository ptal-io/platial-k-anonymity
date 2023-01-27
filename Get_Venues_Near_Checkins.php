<?php

    /* ==========================================================================
        @author Grant McKenzie
        @date May 2022
        @details Get the closest ~50 venues nearest the checkin location
    ========================================================================== */

    require('db.php')

    $dbconn = pg_connect("host=localhost port=5555 dbname=$dbname user=$dbuser password=$dbpass");

    // The checkins are in the database, pull them out.
    $query = "SELECT fullurl, lat, lng, count(*) as cnt FROM checkins WHERE nearby is null group by fullurl, lat, lng order by cnt desc";
    $results = pg_query($query) or die(pg_last_error());
    while($row = pg_fetch_object($results)) {
        getPlaces($row);
    }

    // Given the latitude and longitude of the checkin, search for nearby venues using the Foursquare Nearby API endpoint.
    function getPlaces($ll) {

        // search by lat/lng, sort by distance, limit to 50 (max possible), set radius to 10km.
        $url = "https://api.foursquare.com/v3/places/nearby?ll=".$ll->lat.",".$ll->lng."&sort=distance&limit=50&radius=10000";

        // Curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: '.$apikey
        ));

        $json=curl_exec($ch);

        $a = json_decode($json);

        // Update the checkins table to include the JSON object containing the closest venues for each checkin.
        $query = "UPDATE checkins SET nearby = '".pg_escape_string($json)."' WHERE fullurl = '".$ll->fullurl."'";
        pg_query($query) or die(pg_last_error());

    }
?>
