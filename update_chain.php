<?php
$bot_api = require('api_key.php');
$chat_id = -1001180504638;
$mysql = require('mysql_credentials.php');
$conn = new mysqli($mysql['servername'], $mysql['username'], $mysql['password'], $mysql['database']);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

# Sends the given text wrapped in triple backticks as Markdown.
function send_code($post_message) {
  global $bot_api;
  global $chat_id;
  $url = 'https://api.telegram.org/bot' . $bot_api . '/sendMessage';
  $post_msg = array('chat_id' => $chat_id, 'text' => '```\n ' . $post_message . '```', 'parse_mode' => 'markdown' );
  $options = array(
    'http' => array(
      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
      'method' => 'POST',
      'content' => http_build_query($post_msg)
    )
  );
  $context = stream_context_create($options);
  $result = file_get_contents($url, false, $context);
}

# Takes a user array as input. Checks who follows him/her and adds
# him/her to $output array. Exits when a user is followed by no one.
function get_chain_from_user($user) {
  global $conn;
  $output = array($user);
  $last_user_id = $user['user_id'];
  while (true) {
    $query = "SELECT user_id, username from users where follows = $last_user_id";
    $result = $conn->query($query);
    if ($result->num_rows > 0){
      # Code executed if this isn't the last user
      $details = $result->fetch_assoc();
      array_push($output, $details );
      $last_user_id = $details['user_id'];
    }
    else {
      break;
    }
  }
  return $output;
}

# First generates a list of people not following anyone (end_points)
# Then runs get_chain_from_user on all of them and stores them in
# $chains array. Then compares the arrays inside $chains array and
# returns the longest one.
function get_longest_chain() {
  global $conn;
  $query = "SELECT user_id, username FROM users WHERE follows = -1;";
  $end_points = $conn->query($query);
  if ($end_points->num_rows == 0) {
    return array();
  }
  $chains = array();
  while ($end_point = $end_points->fetch_assoc()){
    $chain = get_chain_from_user($end_points);
    array_push($chains, $chain);
  }
  $longest_chain_index = 0;
  for ($i = 0; $i < count($chains); $i++){
    if (count($chains[$i]) > count($chains[$longest_chain_index])) {
      $longest_chain_index = $i;
    }
  }
  return $chains[$longest_chain_index];
}

# Converts chain to string for sending
function chain_to_string($chain) {
  $string = "";
  for ($i = count($chain) - 1; $i >= 0 ; $i--) {
    $string .= $chain[$i]['username'];
    if ($i != 0) {
      $string .= " > ";
    }
  }
  return $string;
}

function update_user_by_username($username) {
  global $conn;
  $html = file_get_contents("https://t.me/" . $username);
  $dom = new domDocument;
  $dom->loadHTML($html);
  $dom->preserveWhiteSpace = false;
  $xpath = new \DOMXPath($dom);
  foreach ($xpath->query("descendant-or-self::div[@class and contains(concat(' ', normalize-space(@class), ' '), ' tgme_page_description ')]/a") as $node){
    $username = preg_replace('/^@/', '', $node->nodeValue);
    $query = "SELECT user_id FROM users WHERE username = " .  $username ;
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $query = "UPDATE users SET follows = " . $row['user_id'] . " WHERE username =  " . $username . ";" ;
      $conn->query($query);
      return;
    }
  }
}

$conn->close();
?>
