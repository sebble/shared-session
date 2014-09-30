<?php

require_once('vendor/autoload.php');
#if(!class_exists('\Predis\Client')){
#  require_once('modules/predis/lib/Predis/Autoloader.php');
#  Predis\Autoloader::register();
#}

function json_decode_array($d){
  return json_decode($d, true);
}

/**
 * By default this is a no-op but can be replaced by a custom function if REDIS_SESSION_ID_MUTATOR constant is defined
 * to the custom function name. This can be useful if you'd like to filter or change the session ID prior to it
 * being saved in Redis.
 * @param  string $id The session ID
 * @return string     Unaltered session ID
 */
function redis_session_id_mutator($id){
  return $id;
}

//class RedisSession implements SessionHandlerInterface{ // only PHP 5.4.0+
class RedisSession{
  private $serializer;
  private $unserializer;
  private $unpackItems;
  private $id_mutator;

  static function start($redis_conf = array(), $unpackItems = array()){
    if(!defined('REDIS_SESSION_PREFIX'))
      #define('REDIS_SESSION_PREFIX', 'session:php:');
      define('REDIS_SESSION_PREFIX', 'session:');
    if(!defined('REDIS_SESSION_SERIALIZER'))
      define('REDIS_SESSION_SERIALIZER', 'json_encode');
    if(!defined('REDIS_SESSION_UNSERIALIZER'))
      define('REDIS_SESSION_UNSERIALIZER', 'json_decode_array');
    if(!defined('REDIS_SESSION_ID_MUTATOR'))
      define('REDIS_SESSION_ID_MUTATOR', 'redis_session_id_mutator');
    $obj = new self($redis_conf, $unpackItems);
    session_set_save_handler(
      array($obj, "open"),
      array($obj, "close"),
      array($obj, "read"),
      array($obj, "write"),
      array($obj, "destroy"),
      array($obj, "gc"));
    // SJIM: override session name
    session_name('session');
    // SJIM: create signed session_id
    session_id((!isset($_COOKIE['session']))?gen_sid(uniqid().uniqid()):decode_flask_cookie($_COOKIE['session']));
    session_start(); // Because we start the session here, any other modifications to the session must be done before this class is started
    // SJIM: express needs this
    if (!isset($_SESSION["cookie"])) $_SESSION["cookie"] = array();
    return $obj;
  }

  function __construct($redis_conf, $unpackItems){
    $this->serializer = function_exists(REDIS_SESSION_SERIALIZER) ? REDIS_SESSION_SERIALIZER : 'json_encode';
    $this->unserializer = function_exists(REDIS_SESSION_UNSERIALIZER) ? REDIS_SESSION_UNSERIALIZER : 'json_decode_array';
    $this->id_mutator = function_exists(REDIS_SESSION_ID_MUTATOR) ? REDIS_SESSION_ID_MUTATOR : 'redis_session_id_mutator';
    $this->unpackItems = $unpackItems;

    $this->redis = new \Predis\Client($redis_conf);
  }

  function serializer(){
    return call_user_func_array($this->serializer, func_get_args());
  }

  function unserializer(){
    return call_user_func_array($this->unserializer, func_get_args());
  }

  function id_mutator(){
    return call_user_func_array($this->id_mutator, func_get_args());
  }

  function read($id) {
    $d = $this->unserializer($this->redis->get(REDIS_SESSION_PREFIX . $this->id_mutator($id)));
    // Revive $_SESSION from our array
    $_SESSION = $d;
  }


  function write($id, $data) {
    /**
     * RANT: It's seemingly impossible to parse the value in $data.
     * Example:
     *
     * Serialising the following:
     * $_SESSION['test'] = "ohai";
     * $_SESSION['md'] = array('test2' => array('multidimensional' => 'array'));
     * $_SESSION['more'] = new stdClass;
     *
     * Gives:
     *
     * test|s:4:"ohai";md|a:1:{s:5:"test2";a:1:{s:16:"multidimensional";s:5:"array";}}more|O:8:"stdClass":0:{}
     *
     * Where are the delimeters between keys? I'm testing this on PHP 5.3.8 with
     * Suhosin patch, and session_decode() gives false.
     *
     * This is why, on write, we have to access $_SESSION and encode that into
     * a format which is more generic and world readable
     */
    $data = $_SESSION;
    $ttl = ini_get("session.gc_maxlifetime");
    $unpackItems = $this->unpackItems;
    $serializer = $this->serializer;
    $id_mutator = $this->id_mutator;

    $this->redis->pipeline(function ($r) use (&$id, &$data, &$ttl, &$unpackItems, &$serializer, &$id_mutator) {
      $r->setex(REDIS_SESSION_PREFIX . $id_mutator($id), $ttl, $serializer($data));

      // Unpack individual properties into their own keys, if we want
      //foreach ($unpackItems as $item) {
        //$keyname = REDIS_SESSION_PREFIX . $id . ":" . $item;

        //if (isset($_SESSION[$item])) {
          //$r->setex($keyname, $ttl, $_SESSION[$item]);

        //} else {
          //$r->del($keyname);
        //}
      //}
    });
  }


  function destroy($id) {
    $this->redis->del(REDIS_SESSION_PREFIX . $this->id_mutator($id));

    //$unpacked = $this->redis->keys(REDIS_SESSION_PREFIX . $id . ":*");

    //foreach ($unpacked as $unp) {
      //$this->redis->del($unp);
    //}
  }


  // These functions are all noops for various reasons... opening has no practical meaning in
  // terms of non-shared Redis connections, the same for closing. Garbage collection is handled by
  // Redis anyway.
  function open($path, $name) {}
    function close() {}
    function gc($age) {}
}

// the following prevents unexpected effects when using objects as save handlers
register_shutdown_function('session_write_close');

## signed sessions to match express.js
#re.sub('=+$','',base64.b64encode(hmac.new(key,msg,hashlib.sha256).digest()))
#def gen_sig(sid, secret):
#    return re.sub('=+$','',base64.b64encode(hmac.new(secret,sid,hashlib.sha256).digest()))
#def gen_sid(sid, secret):
#    return 's:%s.%s'%(sid, gen_sig(sid, secret))
#def check_sid(sid, secret):
#    sid,sig = re.match('s:([^\.]+)\.(.+)+',sid).groups()
#    return sid if gen_sig(sid, secret) == sig else False

function gen_sig($sid, $secret='qSFgQ4PIA90uodyDA9DUhXaqK4gH2kEc') {
  return preg_replace('!=+$!','',base64_encode(hash_hmac('sha256',$sid,$secret,true)));
}

function gen_sid($sid, $secret='qSFgQ4PIA90uodyDA9DUhXaqK4gH2kEc') {
  return 's:'.$sid.'.'.gen_sig($sid, $secret);
}

function check_sid($sid, $secret='qSFgQ4PIA90uodyDA9DUhXaqK4gH2kEc') {
  preg_match('!s:([^\.]+)\.(.+)+!', $sid, $m);
  return (gen_sig($m[1],$secret)==$m[2])?$m[1]:false;
}

function decode_flask_cookie($val) {
  if ($val[0] == '"')
    return substr($val,1,-1);
  else
    return $val;
}

define('REDIS_SESSION_ID_MUTATOR', 'check_sid');

?>
