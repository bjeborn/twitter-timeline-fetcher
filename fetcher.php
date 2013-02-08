#!/usr/bin/php
<?php

/*
* Small script for fetching a users limeline from twitter using Twitter API 1.1 and OAuth
* Outputs a semicolon separated list with tweet id, timestamp, and text
*/

// Configuration
$oauth_access_token = '';
$oauth_access_token_secret = '';
$consumer_key = '';
$consumer_secret = '';
// Configuration end

$screen_name = $argv[1];

function buildBaseString($base_uri, $method, $params) {
  $r = array();
  ksort($params);
  foreach($params as $key=>$value)
    $r[] = "$key=" . rawurlencode($value);
  return $method."&" . rawurlencode($base_uri) . '&' . rawurlencode(implode('&', $r));
}

function buildAuthorizationHeader($oauth) {
  $r = 'Authorization: OAuth ';
  $values = array();
  foreach($oauth as $key=>$value)
    $values[] = "$key=\"" . rawurlencode($value) . "\"";
  $r .= implode(', ', $values);
  return $r;
}

function getTweets($query_params) {
  $url = "https://api.twitter.com/1.1/statuses/user_timeline.json";
  global $oauth_access_token;
  global $oauth_access_token_secret;
  global $consumer_key;
  global $consumer_secret;
  $oauth = array(
    'oauth_consumer_key' => $consumer_key,
    'oauth_nonce' => time(), 
    'oauth_signature_method' => 'HMAC-SHA1', 
    'oauth_token' => $oauth_access_token, 
    'oauth_timestamp' => time(), 
    'oauth_version' => '1.0'
  );

  $base_info = buildBaseString($url, 'GET', $oauth + $query_params); 
  $composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_access_token_secret); 
  $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true)); 
  $oauth['oauth_signature'] = $oauth_signature;

  $header = array(buildAuthorizationHeader($oauth), 'Expect:'); 

  $query_params_kvurl = array();
  foreach($query_params as $key=>$value)
    $query_params_kvurl[] = "$key=" . rawurlencode($value);

  $options = array( 
    CURLOPT_HTTPHEADER => $header, 
    CURLOPT_HEADER => false, 
    CURLOPT_URL => $url . '?' . implode('&', $query_params_kvurl), 
    CURLOPT_RETURNTRANSFER => true, 
    CURLOPT_SSL_VERIFYPEER => false
  );

  $feed = curl_init(); 
  curl_setopt_array($feed, $options); 
  $json = curl_exec($feed); 
  curl_close($feed);

  $twitter_data = json_decode($json);
  return $twitter_data;
}

$tweets = array();
$lastid = '';
$params = array(
  'screen_name' => $screen_name,
  'count' => 200,
);

while ($twitter_data = getTweets($params)) {
  foreach ($twitter_data as $tweet) {
    if (isset($tweets["$tweet->id_str"]))
      break 2;
    $tweets["$tweet->id_str"] = true;
    print($tweet->id_str . ';' . strtotime($tweet->created_at) . ';' . $tweet->text . "\n");
    $lastid = $tweet->id_str;
  }
  $params['max_id'] = bcsub($lastid, 1);  
}
