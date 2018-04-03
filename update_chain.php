<?php
$bot_api = require('api_key.php');
$chat_id = -1001180504638;
$mysql = require('mysql_credentials.php');
$conn = new mysqli($mysql['servername'], $mysql['username'], $mysql['password'], $mysql['database']);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

$conn->close();
?>
