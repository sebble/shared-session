<?php
// from https://gist.github.com/mscdex/9507b0d8df42e0aec825
 
// from https://github.com/TheDeveloper/redis-session-php
require('redis-session-php/redis-session.php');
RedisSession::start();
 
@$_SESSION["php"] = "Hello from PHP";
@$_SESSION["php_count"] += 1;
 
// `cookie` is needed by express-session to store information about the session cookie
//if (!isset($_SESSION["cookie"]))
//$_SESSION["cookie"] = array('httpOnly'=>false);

if (!isset($_SESSION['HTTP_SHIB_EP_EMAILADDRESS'])) {
  header('Location: https://resviz.ncl.ac.uk/signin?redirect=https://resviz.ncl.ac.uk/session/php/redis.php');
  exit;
}


header('Content-type: application/json');
echo json_encode($_SESSION);
 
