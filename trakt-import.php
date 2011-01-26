<?php
//change these
define('XBMC_USERNAME', 'username');
define('XBMC_PASSWORD', 'password');
define('XBMC_IP', '192.168.0.12');
define('XBMC_PORT', '8080');
define('TRAKT_APIKEY', 'key');
define('TRAKT_USERNAME', 'username');
define('TRAKT_PASSWORD', 'password');

########## leave this alone

error_reporting(E_ALL);
define('BATCH_SIZE', 1000);

//get shows
echo "========\nTV SHOWS\n========\n\n";
$ch = curl_init();
curl_setopt_array($ch, array(
	CURLOPT_URL => 'http://' . XBMC_USERNAME . ':' . XBMC_PASSWORD . '@' . XBMC_IP . ':' . XBMC_PORT . '/xbmcCmds/xbmcHttp?command=QueryVideoDatabase(' . urlencode('SELECT idShow, c00, c12, c05 FROM tvshow ORDER BY c00 ASC') . ')',
	CURLOPT_RETURNTRANSFER => 1
));
$shows_raw = curl_exec($ch);
preg_match_all('/<field>([^<]*)<\/field><field>([^<]*)<\/field><field>([^<]*)<\/field><field>([^<]*)<\/field>/', $shows_raw, $shows_matches);

for($i = 0; $i < sizeof($shows_matches[0]); $i++) {
	//get episodes
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://' . XBMC_USERNAME . ':' . XBMC_PASSWORD . '@' . XBMC_IP . ':' . XBMC_PORT . '/xbmcCmds/xbmcHttp?command=QueryVideoDatabase(' . urlencode('SELECT c12, c13, playCount FROM episodeview WHERE idShow = ' . $shows_matches[1][$i]) . ')',
		CURLOPT_RETURNTRANSFER => 1
	));
	$episodes_raw = curl_exec($ch);
	preg_match_all('/<field>([^<]*)<\/field><field>([^<]*)<\/field><field>([^<]*)<\/field>/', $episodes_raw, $episodes_matches);
		
	$episodes_library = array();
	$episodes_seen = array();
	
	for($j = 0; $j < sizeof($episodes_matches[0]); $j++) {
		$a = array(
			'season' => $episodes_matches[1][$j],
			'episode' => $episodes_matches[2][$j],
			'plays' => intval($episodes_matches[3][$j])
		);
		if($a['plays'] > 0) {
			$episodes_seen[] = $a;
		}
		$episodes_library[] = $a;
	}
		
	$data = array(
		'username' => TRAKT_USERNAME,
		'password' => sha1(TRAKT_PASSWORD),
		'title' => $shows_matches[2][$i],
		'tvdb_id' => $shows_matches[3][$i],
		'year' => date('Y', strtotime($shows_matches[4][$i])),
		'episodes' => $episodes_seen
	);
	
	echo $shows_matches[2][$i] . "\n";
	
	//add seen
	if(!empty($episodes_seen)) {
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => 'http://api.trakt.tv/show/episode/seen/' . TRAKT_APIKEY,
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_POST => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT => 0
		));
		echo "\tseen: " . curl_exec($ch) . "\n";
	}
	
	//add to library
	if(!empty($episodes_library)) {
		$data['episodes'] = $episodes_library;
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => 'http://api.trakt.tv/show/episode/library/' . TRAKT_APIKEY,
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_POST => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT => 0
		));
		echo "\tlibrary: " . curl_exec($ch) . "\n";
	}
}

//get movies
echo "\n\n======MOVIES======\n\n";
$ch = curl_init();
curl_setopt_array($ch, array(
	CURLOPT_URL => 'http://' . XBMC_USERNAME . ':' . XBMC_PASSWORD . '@' . XBMC_IP . ':' . XBMC_PORT . '/xbmcCmds/xbmcHttp?command=QueryVideoDatabase(' . urlencode('SELECT c09, c00, c07, playCount, lastPlayed FROM movieview') . ')',
	CURLOPT_RETURNTRANSFER => 1
));
$movies_raw = curl_exec($ch);
preg_match_all('/<field>([^<]*)<\/field><field>([^<]*)<\/field><field>([^<]*)<\/field><field>([^<]*)<\/field><field>([^<]*)<\/field>/', $movies_raw, $movies_matches);

$movies_library = array();
$movies_seen = array();
$missing_title = array();
$missing_year = array();
$missing_imdb = array();

for($i = 0; $i < sizeof($movies_matches[0]); $i++) {
	$a = array(
		'imdb_id' => $movies_matches[1][$i],
		'title' => $movies_matches[2][$i],
		'year' => $movies_matches[3][$i],
		'plays' => empty($movies_matches[4][$i]) ? 0 : intval($movies_matches[4][$i]),
		'last_played' => empty($movies_matches[5][$i]) ? 0 : strtotime($movies_matches[5][$i])
	);
	if($a['plays'] > 0) {
		$movies_seen[] = $a;
	}
	$movies_library[] = $a;
}

//add seen
echo sizeof($movies_seen) . " watched movies\n";
$offset = 0;
while($offset < sizeof($movies_seen)) {
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://api.trakt.tv/movie/seen/' . TRAKT_APIKEY,
		CURLOPT_POSTFIELDS => json_encode(array(
			'username' => TRAKT_USERNAME,
			'password' => sha1(TRAKT_PASSWORD),
			'movies' => array_slice($movies_seen, $offset, BATCH_SIZE)
		)),
		CURLOPT_POST => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_TIMEOUT => 0
	));
	echo "\t" . $offset . '-' . ($offset + BATCH_SIZE) . ' ' . curl_exec($ch) . "\n";
	$offset += BATCH_SIZE;
}

//add to library
echo sizeof($movies_library) . " movies in your library\n";
$offset = 0;
while($offset < sizeof($movies_library)) {
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://api.trakt.tv/movie/library/' . TRAKT_APIKEY,
		CURLOPT_POSTFIELDS => json_encode(array(
			'username' => TRAKT_USERNAME,
			'password' => sha1(TRAKT_PASSWORD),
			'movies' => array_slice($movies_library, $offset, BATCH_SIZE)
		)),
		CURLOPT_POST => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_TIMEOUT => 0
	));
	echo "\t" . $offset . '-' . ($offset + BATCH_SIZE) . ' ' . curl_exec($ch) . "\n";
	$offset += BATCH_SIZE;
}
?>