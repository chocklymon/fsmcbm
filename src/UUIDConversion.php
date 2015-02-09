<?php
/* 
 * The MIT License
 *
 * Copyright 2014 Curtis.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require_once('Settings.php');
require_once('Database.php');

$profiles_per_request = 100;
$profile_url = 'https://api.mojang.com/profiles/minecraft';

function doJSONPost($url, $payload) {
    $curl = curl_init($url);
    $headers = array(
        'Content-Type: application/json',
    );
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $curl_result = curl_exec($curl);
    return $curl_result;
}

$settings = new Settings();
$db = new Database($settings);

$select_without_uuid_sql = <<<SQL
SELECT `username`
FROM `users`
WHERE `uuid` IS NULL
LIMIT {$profiles_per_request}
SQL;

// Let's start
echo "<!DOCTYPE html><html><head><title>Convert Username to UUID</title></head><body><pre>";
flush();

// Loop until we have processed all profiles or we reach a limit.
// Limits to 100 loops (so at 100 per batch that is 10,000) our five invalid
// responses from the minecraft API.
$profiles_processed = 0;
$bad_response_count = 0;
$max_iterations = 100;
for ($i=0; $i < $max_iterations && $bad_response_count < 5; $i++) {
    $query_result = $db->query($select_without_uuid_sql);
    
    $profiles = array();
    while ($row = $query_result->fetch_assoc()) {
        $profiles[] = $row['username'];
    }
    $query_result->free();
    if (empty($profiles)) {
        break;
    }
    
    $profile_json = json_encode($profiles);
    $curl_result = doJSONPost($profile_url, $profile_json);
    if ($settings->debugMode()) {
        print_r($profiles);
        echo "$curl_result\n";
    }
    $result = json_decode($curl_result, true);
    
    if (empty($result)) {
        $bad_response_count++;
    }
    
    foreach ($result as $info) {
        $uuid = $db->sanitize($info['id']);
        $username = $db->sanitize($info['name']);
        $sql = "UPDATE `users` SET `uuid`=UNHEX('{$uuid}') WHERE `username`='{$username}'";
        $db->query($sql);
        $profiles_processed++;
    }
}

echo "Updated the UUID of {$profiles_processed} users\n";

// Check if we still have work to do
$sql = <<<SQL
SELECT COUNT(*) AS count
FROM `users`
WHERE `uuid` IS NULL
SQL;

$count = $db->querySingleRow($sql);
if ($count['count'] > 0) {
    echo "There are usernames that still need a UUID. Please re-run.\n";
}

echo "</pre></body></html>";