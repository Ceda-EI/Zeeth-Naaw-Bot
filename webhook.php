<?php
$bot_name = "zeeth_naaw_bot";
$bot_api = require('api_key.php');

// Checks whether the given command is the same as the entered command
function check_command($command) {
  global $bot_name;
  global $decoded;
  $command_list = explode(" ", $decoded->{"message"}->{"text"});
  if ($command_list[0] == $command || $command_list[0] == $command . "@" . $bot_name) {
    return True;
  }
  else {
    return False;
  }
}

// Send html back to the sender.
function send_html($post_message, $reply=false) {
  global $decoded;
  global $bot_api;
  global $chat_id;
  $url = 'https://api.telegram.org/bot' . $bot_api . '/sendMessage';
  $post_msg = array('chat_id' => $chat_id, 'text' =>$post_message, 'parse_mode' => 'html');
  if ($reply != false) {
    if ($reply === true){
      $post_msg['reply_to_message_id'] = $decoded->{'message'}->{'message_id'};
    }
    else {
      $post_msg['reply_to_message_id'] = $reply;
    }
  }
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

function new_member() {
  global $decoded;
  foreach ($decoded->{'message'}->{'new_chat_members'} as $member){
    if ($member->{'is_bot'}) {
      continue;
    }
    $username = $member->{"username"};
    $user_id = $member->{"id"};
    $query = "INSERT INTO users (user_id, username, follows) values($user_id, '$username', -1);";
    $mysql = require('mysql_credentials.php');
    $conn = new mysqli($mysql['servername'], $mysql['username'], $mysql['password'], $mysql['database']);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->query($query);
    $conn->close();
    $lastmember = include('lastmember.php');
    $text = "Welcome @$username,\n";
    $text .= "\n";
    $text .= "Congratulations for following the chain all the way to here.\n";
    $text .= "To get started, read the rules @Bio_Chain_2_Rules and add <pre>@$lastmember</pre> to your bio.\n";
    $text .= "You can run /update to regenerate the chain.\n";
    $text .= "\n";
    $text .= "Have Fun";
    send_html($text);
  }
}

function member_exit() {
}

function update() {
  send_html("Update started. New chain will only be send if the chain has changed. Please wait as this takes about a minute.");
  exec('echo php ' . __DIR__ . '/update_chain.php send | at now');
}

function allchains() {
  exec('echo php ' . __DIR__ . '/update_chain.php all | at now');
}

function dnf() {
  $mysql = require('mysql_credentials.php');
  $conn = new mysqli($mysql['servername'], $mysql['username'], $mysql['password'], $mysql['database']);
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }
  $query = "SELECT username from users where follows = -1 ;";
  $result = $conn->query($query);
  $text = "The following users follow no other user. \n";
  while ($row = $result->fetch_assoc()) {
    $text .= $row['username'] . "\n";
  }
  send_html($text);
  $conn->close();
}

// Get JSON from post, store it and decode it.
$var = file_get_contents('php://input');
$decoded = json_decode($var);

// Store the chat ID
$chat_id = $decoded->{"message"}->{"chat"}->{"id"};

if ($chat_id != -1001180504638){
  die();
}

$modules = array(
  array(
    "command" => "/update",
    "function" => "update();"
  ),
  array(
    "command" => "/doesnotfollow",
    "function" => "dnf();"
  ),
  array(
    "command" => "/allchains",
    "function" => "allchains();"
  )
);


$command_list = explode(" ", $decoded->{"message"}->{"text"});

# Run new_member function for a new member
if (isset($decoded->{'message'}->{'new_chat_members'})) {
  new_member();
}

# Run member_exit function when a member leaves
if (isset($decoded->{'message'}->{"left_chat_member"})) {
  member_exit();
}

foreach ($modules as $module ) {
  if (check_command($module["command"])) {
    eval($module["function"]);
    exit();
  }
}
?>
