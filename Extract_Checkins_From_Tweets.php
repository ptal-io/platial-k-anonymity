<?php

	/* ==========================================================================
		@author Grant McKenzie
		@date May 2022
		@details Look through tweet content and extract foursquare checkins
	========================================================================== */

	// Required scripts
	require('simple_html_dom.php');
	require('db.php')

	$dbconn = pg_connect("host=localhost port=5555 dbname=$dbname user=$dbuser password=$dbpass");

	// Since the script may be interupted, check to see what data is already there first.
	$existing = array();
	$handle = fopen("checkins.csv", "r");
	if ($handle) {
	    while (($line = fgets($handle)) !== false) {
	        $id = explode(",",$line)[0];
	        $existing[] = $id;
	    }
	    fclose($handle);
	} else {
	    echo "Error opening file.";
	} 


	// Get tweet id and content from database.  Match source to foursquare
	$query = "SELECT id, text FROM tweets where source = '<a href=\"http://foursquare.com\" rel=\"nofollow\">Foursquare</a>' ORDER BY id desc";
	$res = pg_query($query) or die(pg_last_error());

	// Open CSV file for writing (append)
	$file = fopen('checkins.csv','a');
	while($row = pg_fetch_object($res)) {

		// Get the first URL in the text
		preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $row->text, $match);
		$url = trim($match[0][0]);
		echo $row->id; 

		// If the tweet id isn't already in the CSV file
		if (!in_array($row->id, $existing)) {

			// Call to get details
			$v = getDetails($url);

			// If a valid latitude exists, write to the CSV file
			if ($v && property_exists($v, "lat")) {
				fwrite($file, $row->id . "," . $v->lat . "," . $v->lng . "," . $v->venue . "," . $v->ts . "," . $v->url . "\n");
				echo "\tNEW";
			}
			// Run every 1 second
			sleep(1);
		}
		echo "\n";
	}
	fclose($file);


	// CURL request to the URL extracted from the tweet to get the HTML content
	function getCheckin($url) {

		$agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
		//curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_URL,$url);
		$html=curl_exec($ch);

		return $html;
	}


	// Extract the details from the checkin page.  Parse the HTML content
	function getDetails($url) {

		$f = getCheckin($url);
		$html = str_get_html($f);

		$url = $html->find('title')[0]->plaintext;

		// Deal with the redirect
		$v = (Object)array();
		$v->url = $url;
		$e = getCheckin($url);
		$html = str_get_html($e);


		// Get the Venue ID
		foreach($html->find('meta[property=getswarm:place]') as $element)
	       $v->venue = $element->content;

	   	// Get the Longitude
	    foreach($html->find('meta[property=getswarm:location:longitude]') as $element)
	       $v->lng = $element->content;

	    // Get the Latitude
	    foreach($html->find('meta[property=getswarm:location:latitude]') as $element)
	       $v->lat = $element->content;

	   	// Get the time stamp of the checkin
	    foreach($html->find('meta[property=getswarm:date]') as $element)
	       $v->ts = $element->content;

	   	return $v;
	}


?>