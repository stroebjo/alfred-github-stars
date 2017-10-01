<?php

// check for username
if (empty($_ENV['username'])) {
	echo json_encode([
		'items' => [[
			"arg"      => "https://www.alfredapp.com/help/workflows/advanced/variables/",
			"title"    => "Please set your GitHub username first",
			"subtitle" => "Hit enter to open an introduction to variables."
		],],
	]);

	exit(1);
}

$cache_path = $_ENV['alfred_workflow_cache'];
$cache_response = $cache_path . '/cache.json';
$cache_icons = $cache_path . '/icons/';

// check first if caching directory exists.
if (!is_dir($cache_path)) {
	mkdir($cache_path);
}
if (!is_dir($cache_icons )) {
	mkdir($cache_icons);
}

$username    = trim($_ENV['username']); // set inside workflow variables
$token       = trim($_ENV['token']); // set inside workflow variables
$starred_url = sprintf('https://api.github.com/users/%s/starred', $username);
$cache_ttl   = (empty($_ENV['cache_ttl'])) ? 3600 * 24 : (int) $_ENV['cache_ttl']; // in seconds
$query       = trim($argv[1]); // optional text search
$http_status = 200; // default status code, so when using cache it doesn't run into error handling


// check if we hafe a cache
// if not load stars from github API
if (file_exists($cache_response) && filemtime($cache_response) > (time() - $cache_ttl)) {
	$resp_json = json_decode(file_get_contents($cache_response), true);
} else {
	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $starred_url);
	curl_setopt($curl, CURLOPT_ENCODING, "");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, 'GitHub Stars Alfred workflow for: ' . $username );

	if (!empty($token)) {
		curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $token);
	}

	$resp = curl_exec($curl);

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header      = substr($resp, 0, $header_size);
	$resp        = substr($resp, $header_size);
	$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	$resp_json   = json_decode($resp, true);

	// check if there are headers indication pagination
	// => make multiple requests to fetch ALL the stars.
	if (preg_match('/Link: .*?page=([0-9]+)>; rel="last"/', $header, $m)) {
		$last_page = (int) $m[1];

		for ($i = 2; $i <= $last_page; $i++) {
			$page_url = $starred_url . '?page=' . $i;
			curl_setopt($curl, CURLOPT_URL, $page_url);
			$resp     = curl_exec($curl);

			$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
			$header      = substr($resp, 0, $header_size);
			$loop        = substr($resp, $header_size);

			$loop_json   = json_decode($loop, true);
			$resp_json   = array_merge($resp_json, $loop_json);
		}
	}

	curl_close($curl);

	// cache response
	if ($http_status == 200) {
		file_put_contents($cache_response, json_encode($resp_json, JSON_PRETTY_PRINT));
	}
}

$items = [];

// Github API returened some sort of error.
// Also check for presence of `message` key, if HTTP Status
// code was not set to an error.
if (200 !== (int) $http_status OR isset($resp_json['message'])) {
	echo json_encode([
		'items' => [
			[
				"arg"      => $resp_json['documentation_url'],
				"title"    => sprintf("GitHub Response Error (%s)", $http_status),
				"subtitle" => $resp_json['message'],
			],
		],
	]);

	exit(1);
}

// Search through the results.
foreach ($resp_json as $i => $star){
	$url      = $star['html_url'];
	$title    = $star['name'];
	$subtitle = $star['description'];

	if ($query) {
		$search_string = $star["full_name"] . ' ' . $star['description'];
		$query_matched = stripos($search_string, $query);

		if ($query_matched === false) {
			continue;
		}
	}

	$icon_url = $star['owner']['avatar_url'];
	$icon     = $cache_icons . $star['id'] . '.png';

	if (!is_file($icon)) {
		$fp = fopen ($icon, 'w+');

		$ch = curl_init($icon_url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
		curl_exec($ch);

		curl_close($ch);
		fclose($fp);

		echo json_encode([
			'rerun' => .1,
			'variables' => [
				//'resp_json' => $resp_json,
				'amount'    => count($resp_json),
				'current'   => $i,
			],
			'items' => [
				[
					"arg"      => null,
					"title"    => sprintf("Downloading GitHub avatars…"),
					"subtitle" => sprintf('Downloading image %s of %s', $i + 1, count($resp_json)),
				],
			],
		]);

		exit(0);

	}

	$items['items'][] = [
		'arg'          => $url,
		'quicklookurl' => $url,
		'title'        => $title,
		'subtitle'     => $subtitle,

		'text' => [
			'largetype' => $title,
		],

		'icon' => [
			'path' => $icon
		],
	];
}

echo json_encode($items);
