<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("access-control-allow-origin: *");

require_once ("mysqli.inc.php");

if(!function_exists('apache_request_headers')) {
  function apache_request_headers() {
    $arh = array();
    $rx_http = '/\AHTTP_/';
    foreach($_SERVER as $key => $val) {
      if( preg_match($rx_http, $key) ) {
        $arh_key = preg_replace($rx_http, '', $key);
        $rx_matches = array();
        // do some nasty string manipulations to restore the original letter case
        // this should work in most cases
        $rx_matches = explode('_', $arh_key);
        if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
          foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
          $arh_key = implode('-', $rx_matches);
        }
        $arh[$arh_key] = $val;
      }
    }
    return( $arh );
  }
}

$path = parse_url($_SERVER['REQUEST_URI']);
$path = explode("/", substr($path["path"], 1));
$version = $path[0];
$function = $path[1];

if ($function == "pushPackages") { //Build and output push package to Safari
  $body = @file_get_contents("php://input");
  $body = json_decode($body, true);
  global $userid;
  $userid = $body["id"];

  // return pushPackage

  include ("createPushPackage.php");

  $package_path = create_push_package();
  if (empty($package_path)) {
    http_response_code(500);
    die;
  }

  header("Content-type: application/zip");
  echo file_get_contents($package_path);
  unlink($package_path); //http://stackoverflow.com/questions/1217636/remove-file-after-time-in-php
  die;
}
else if ($function == "devices") { // safari is adding or deleting the device
  $userid = 0;
  foreach(apache_request_headers() as $header=>$value) { // this is the authorization key we packaged in the website.json pushPackage
    if($header == "Authorization") {
      $value = explode("_", $value);
      if(isset($value[1]) && $value[1] >0) $userid = filter_var($value[1], FILTER_SANITIZE_NUMBER_INT);
      break;
    }
  }
  $token = filter_var($path[2], FILTER_SANITIZE_STRING);
  if ($_SERVER['REQUEST_METHOD'] == "POST") { //Adding
    $r = mysqli_do("SELECT * FROM push WHERE token='$token'");
    if (mysqli_num_rows($r) == 0) {
      mysqli_do("INSERT INTO push (token, userid) VALUES ('$token', '$userid')");
    }
  }
  else if ($_SERVER['REQUEST_METHOD'] == "DELETE") { //Deleting
    mysqli_do("DELETE FROM push WHERE token='$token' LIMIT 1");
  }
}
else if ($function == "verifyCode") { //verify a token
  $token = filter_var($path[2], FILTER_SANITIZE_STRING);
  $r = mysqli_do("SELECT * FROM push WHERE token='$token'");
  if(mysqli_num_rows($r) > 0) {
    echo("valid");
  }
  else {
    echo ("invalid");
  }
}
else if ($function == "push") { //pushes a notification
  $title = $_REQUEST["title"];
  $body = $_REQUEST["body"];
  $button = $_REQUEST["button"];
  $urlargs = $_REQUEST["urlargs"];
  $auth = $_REQUEST["auth"];
  if($auth == AUTHORISATION_CODE) {
    $query = "SELECT * FROM push";
    if(isset($path[2]) && $path[2]) {
      $token = filter_var($path[2], FILTER_SANITIZE_STRING);
      $query .= " WHERE token='$token'";// notify specific user
    }
    $result = mysqli_do($query);

    $deviceTokens = array();

    while ($r = $result->fetch_assoc()) {
      $deviceTokens[] = $r["token"];
    }
    $payload['aps']['alert'] = array(
      "title" => $title,
      "body" => $body,
      "action" => $button
    );
    $payload['aps']['url-args'] = array(
      $urlargs
    );
    $payload = json_encode($payload);
    if(strlen($payload)>256) {
      echo "Payload too large";
      exit();
    }

    $success = 0;
    $retryAttempts = 3;
    $batchSize = 100; // 100 seems to be the magic number, see https://github.com/surrealroad/Safari-Push-Notifications/issues/13#issuecomment-45958321
    $batchNo = 0;
    $apns = connect_apns(APNS_HOST, APNS_PORT, PRODUCTION_CERTIFICATE_PATH);

    foreach($deviceTokens as $deviceToken) {
      $retries = 0;
      while($retries < $retryAttempts):
        if($batchNo >= $batchSize) { // APNS seems to ignore push requests after a certain point, so we need to reconnect
          $batchNo = 0;
          fclose($apns);
          $apns = connect_apns(APNS_HOST, APNS_PORT, PRODUCTION_CERTIFICATE_PATH);
        } elseif(!send_payload($apns, $deviceToken, $payload)) {
          fclose($apns);
          $apns = connect_apns(APNS_HOST, APNS_PORT, PRODUCTION_CERTIFICATE_PATH);
          $retries++;
        } else {
          $success++;
          break;
        }
        $batchNo++;
      endwhile;
    }
    fclose($apns);
    echo $success." of ".count($deviceTokens)." device(s) notified";
    exit();
  } else {
    echo "Invalid authorisation";
    exit();
  }
}
else if ($function == "log") { //writes a log message
  $title = $_REQUEST["title"];
  $body = $_REQUEST["body"];
  $log = json_decode($body);
  $fp = fopen('logs/request.log', 'a');
  fwrite($fp, $log['logs']);
  fclose($fp);
}
else if ($function == "list") { //return a list of subscribers
  $auth = $_REQUEST["auth"];
  if($auth == AUTHORISATION_CODE) {
    $query = "SELECT * FROM push";
    $result = mysqli_do($query);

    $rows = array();

    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    echo json_encode($rows);
  }
}
else if ($function == "count") { //return the count of subscribers
  $query = "SELECT * FROM push";
  $result = mysqli_do($query);

  $rows = array();

  while ($r = $result->fetch_assoc()) {
      $rows[] = $r;
  }
  echo json_encode(count($rows));
}
else { // just other demo-related stuff
  // if($_SERVER["HTTPS"] != "on") {
  //    header("HTTP/1.1 301 Moved Permanently");
  //    header("Location: https://" . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"]);
  //    exit();
  // }
  include ("desktop.php");
}

function connect_apns($apnsHost, $apnsPort, $apnsCert) {
  $streamContext = stream_context_create();
  stream_context_set_option($streamContext, 'ssl', 'local_cert', PRODUCTION_CERTIFICATE_PATH);
  return stream_socket_client('ssl://' . $apnsHost . ':' . $apnsPort, $error, $errorString, 60, STREAM_CLIENT_CONNECT, $streamContext);
}

function send_payload($handle, $deviceToken, $payload) {
  // https://developer.apple.com/library/mac/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101
  $apnsMessage = chr(0) . chr(0) . chr(32) . pack('H*', str_replace(' ', '', $deviceToken)) . chr(0) . chr(strlen($payload)) . $payload;
  return @fwrite($handle, $apnsMessage);
}

?>
