<?php

date_default_timezone_set(timezone_name_from_abbr('CET'));

include_once 'config.inc.php';
include_once 'db.inc.php';

$query = mysql_query("SELECT twitter_screen_name, kscore, kchange FROM users ORDER BY last_update ASC LIMIT 0, 1000"); // only 1000 at a time

$usernames = array();
$scores = array();
$results = array();
$kclasses = array();

while ($user = mysql_fetch_assoc($query)) {
	$usernames[] = $user['twitter_screen_name'];
	
	// Array with additonal information which will be used the calculate the change
	$scores[$user['twitter_screen_name']] = array(
		'score' => $user['kscore'],
		'change' => $user['kchange']
	);
}

$users_chunked = array_chunk($usernames, 10);

foreach ($users_chunked as $chunk) {	
	$result = json_decode(file_get_contents($api_endpoint.'?key='.$key.'&users='.urlencode(implode(',', $chunk))), true);
	
	if ($result['users']) {
		foreach ($result['users'] as $item) {
			$results[$item['twitter_screen_name']] = $item['score']['kscore'];
			$kclasses[$item['twitter_screen_name']] = $item['score']['kclass'];
		}
	}
	
	// Avoid Klout API limitations - max 10/req second
	sleep(0.5);
}

$now = date('Y-m-d H:i:s');

// Run trough the users to update them
foreach ($usernames as $username) {
	// Check if there is a result for this user
	if(isset($results[$username])) {
		// Get the user's previous score
		$old_score = $scores[$username]['score'];
		
		// Get the Klout query result
		$score = $results[$username];
		
		// Calculate the change if the score changed
		if(!empty($old_score) && $score != $old_score) {
			// Calculate the different between the old and the new score
			$change = $score - $old_score;
		}
		else {
			// Put the old change back
			$change = $scores[$username]['change'];
		}
		$kclass = $kclasses[$username];
	}
	else {
		$score = -1;
		$change = 0;
		$kclass= '';
	}
	
	// Compose the fields that need to be updated
	$set_sql = sprintf('kscore = \'%f\', kchange=\'%f\', kclass=\'%s\', ', $score, $change, $kclass);
	
// echo $set_sql;
	// Update the user
	mysql_query("UPDATE users SET $set_sql last_update = '$now' WHERE twitter_screen_name = '$username'");
}
