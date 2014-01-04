<?php

// presets
$api_remain_limit = true;
$query            = trim($argv[1]);
$http_status      = 200;
$cache_treshold   = 3600 * 24; // in seconds


// check if we hafe a cache
// if not load stars from github API
if (file_exists('cache.json') && filemtime('cache.json') > (time() - $cache_treshold)) {

	$resp = file_get_contents('cache.json');

} else {



	// get starred URL
	$id          = file_get_contents('userid.txt');
	$user_name   = explode("\n",$id,2);
	$starred_url = 'https://api.github.com/users/' . $user_name[0] . '/starred';

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $starred_url);
	curl_setopt($curl, CURLOPT_ENCODING, "");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_USERAGENT,'GitHub Stars Alfred workflow for: ' . $user_name );
	$resp = curl_exec($curl);

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header      = substr($resp, 0, $header_size);
	$resp        = substr($resp, $header_size);
	$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

	curl_close($curl);

	// cache response
	file_put_contents('cache.json', $resp);


	// determine rate limit reset
	$api_remain_limit = 60;
	if (preg_match('/X-RateLimit-Remaining: ([0-9]+)/', $header, $m)) {
		$api_remain_limit = $m[1];
	}

}



$data = json_decode($resp, true);
$xml = "<?xml version=\"1.0\"?>\n<items>\n";

//
// Github API returened some sort of error.
//
if (200 !== (int) $http_status) {

    $xml .= "<item arg=\"http://developer.github.com/v3/#rate-limiting\">\n";
	$xml .= "<title>GitHub Response Error (" . $http_status . ")</title>\n";
	$xml .= "<subtitle>" . $resp .  "</subtitle>\n";
	$xml .= "<icon>icon.png</icon>\n";
	$xml .= "</item>\n";

	$xml .="</items>";

	echo $xml;
	return;
}


//
// If API limit is reached, print explanation.
//
if (!$api_remain_limit) {

	preg_match('/X-RateLimit-Reset: ([0-9]+)/', $header, $m1);
	preg_match('/X-RateLimit-Limit: ([0-9]+)/', $header, $m2);

	$reset_in = (int) (($m1[1] - time()) / 60);

    $xml .= "<item arg=\"http://developer.github.com/v3/#rate-limiting\">\n";
	$xml .= "<title>API limit will reset in " . $reset_in . " minutes.</title>\n";
	$xml .= "<subtitle>GitHub restricts the amount of request to " . $m2[1] . " calls per hour.</subtitle>\n";
	$xml .= "<icon>icon.png</icon>\n";
	$xml .= "</item>\n";
}

//
// Search through the results.
//
foreach ($data as $star){
	$url      = $star['html_url'];
	$title    = $star['name'];
	$subtitle = $star['description'];

	$search_string = $star["full_name"] . ' ' . $star['description'];
	$query_matched = stripos($search_string, $query);

	if (!($query_matched === false) ) {
		$icon_url = $star['owner']['avatar_url'];
		$icon     = 'icons/' . $star['owner']['gravatar_id'] . '.png';

		if (!is_file($icon)) {
			file_put_contents($icon, file_get_contents($icon_url));
		}

	    $xml .= "<item arg=\"$url\">\n";
		$xml .= "<title>$title</title>\n";
		$xml .= "<subtitle>$subtitle</subtitle>\n";
		$xml .= "<icon>$icon</icon>\n";
		$xml .= "</item>\n";
	}
}

$xml .="</items>";
echo $xml;
