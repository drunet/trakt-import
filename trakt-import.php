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
define('BATCH_SIZE', 15);

//get movies
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
	else {
		$movies_library[] = $a;
	}
	
	if(empty($a['imdb_id'])) $missing_imdb[] = $a;
	if(empty($a['title'])) $missing_title[] = $a;
	if(empty($a['year'])) $missing_year[] = $a;
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
echo sizeof($movies_library) . " unwatched movies\n";
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

//debug
echo "\n##############################\n\nMovies missing titles: " . sizeof($missing_title) . "\n";
foreach($missing_title as $movie) {
	echo "\t" . $movie['title'] . "\n";
}
echo "\nMovies missing years: " . sizeof($missing_year) . "\n";
foreach($missing_year as $movie) {
	echo "\t" . $movie['title'] . "\n";
}
echo "\nMovies missing imdb ids: " . sizeof($missing_imdb) . "\n";
foreach($missing_imdb as $movie) {
	echo "\t\t".$movie['title']."\n";
}
echo "\n##############################\n";
?>