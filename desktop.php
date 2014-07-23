<?php
// http://wezfurlong.org/blog/2006/nov/http-post-from-php-without-curl/
function do_post_request($url, $data, $optional_headers = null)
{
  $params = array('http' => array(
              'method' => 'POST',
              'content' => $data
            ));
  if ($optional_headers !== null) {
    $params['http']['header'] = $optional_headers;
  }
  $ctx = stream_context_create($params);
  $fp = @fopen($url, 'rb', false, $ctx);
  if (!$fp) {
    throw new Exception("Problem with $url, $php_errormsg");
  }
  $response = @stream_get_contents($fp);
  if ($response === false) {
    throw new Exception("Problem reading data from $url, $php_errormsg");
  }
  return $response;
}

if($_REQUEST['test'] && $_REQUEST['token']) {
  $data = array(
    "title" => strip_tags($_REQUEST['title']),
    "body" => strip_tags($_REQUEST['body']),
    "button" => strip_tags($_REQUEST['button']),
    "urlargs" => "/",
    "auth" => AUTHORISATION_CODE
  );
  $pushurl = WEBSERVICE_URL."/v1/push/".strip_tags($_REQUEST['token']);
  echo do_post_request($pushurl, http_build_query($data));
  exit();
}
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Safari Push Notification Service</title>

<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.min.css" rel="stylesheet">
<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/js/bootstrap.min.js"></script>

<style type="text/css">
body {
  margin: 0;
  padding: 0;
  background: #EDEDED;
  font-family: "Avenir Next", "Helvetica Neue", Helvetica, sans-serif;
}

.box {
  border-radius: 8px;
  background: #fff;
  width: 600px;
  height: 300px;
  position: absolute;
  top: 50%;
  left: 50%;
  margin-left: -300px;
  margin-top: -150px;
  border: 1px solid #CECECE;
  box-shadow: rgba(255,255,255,0.7) 0 1px 0, inset rgba(0,0,0,0.1) 0 1px 2px;
}
</style>


<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>
<script type="text/javascript">
var token = "";
var id = "<?php echo $id; ?>";
window.onload = function() {
  var ua = window.navigator.userAgent,
    safari = ua.indexOf ( "Safari" ),
    chrome = ua.indexOf ( "Chrome" ),
    version = ua.substring(0,safari).substring(ua.substring(0,safari).lastIndexOf("/")+1);

  if(chrome ==-1 && safari > 0 && parseInt(version, 10) >=7) {
    checkPerms();
  }
  else {
    document.getElementById("old").style.display = "";
  }
};

function checkPerms() {
  document.getElementById("reqperm").style.display = "none";
  document.getElementById("granted").style.display = "none";
  document.getElementById("denied").style.display = "none";

  var pResult = window.safari.pushNotification.permission('<?php echo WEBSITE_UID; ?>');

  if(pResult.permission === 'default') {
    //request permission
    document.getElementById("reqperm").style.display = "";
    requestPermissions();
  }
  else if(pResult.permission === 'granted') {
    document.getElementById("granted").style.display = "";
    token = pResult.deviceToken;
  }
  else if(pResult.permission === 'denied') {
    document.getElementById("denied").style.display = "";
  }
}

function requestPermissions() {
  window.safari.pushNotification.requestPermission('<?php echo WEBSERVICE_URL; ?>', '<?php echo WEBSITE_UID; ?>', {}, function(c) {
    if(c.permission === 'granted') {
      document.getElementById("reqperm").style.display = "none";
      document.getElementById("granted").style.display = "";
      token = c.deviceToken;
    }
    else if(c.permission === 'denied') {
      document.getElementById("reqperm").style.display = "none";
      document.getElementById("denied").style.display = "";
    }
  });
}

function do_push() {
  var checksOut = true;
  $("#form input").each(function(index, element) {
    $(this).parents(".control-group").removeClass("error");
        if(element.value == "") {
      $(this).parents(".control-group").addClass("error");
      checksOut = false;
    }
    });
    p = window.safari.pushNotification.permission('<?php echo WEBSITE_UID; ?>');
    if(p.permission != 'granted') checksOut = false;
  if(checksOut == true) {
    var data = {"test":1, "token":p.deviceToken, "title": document.getElementById("not_title").value, "body": document.getElementById("not_body").value, "button": document.getElementById("not_button").value};
    var jqxhr = $.post("/", data)
      .fail(function(){alert("The server responded with an error")})
      .always(function(){console.log(data)});
    $("#form input").val("");
    $("#modal_scrim").fadeOut(300);
  }
}
</script>

</head>

<body>
  <div style="background: rgba(0,0,0,0.8); position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: none; z-index: 899;" id="modal_scrim">
      <div class="modal">
          <div class="modal-header">
              <button type="button" class="close" onClick="$('#modal_scrim').fadeOut(300);" aria-hidden="true">&times;</button>
              <h3>Create Push Notification</h3>
            </div>
          <div class="modal-body form-horizontal" id="form">
                <div class="control-group">
                  <label class="control-label" for="not_title">Title</label>
                  <div class="controls">
                    <input type="text" id="not_title">
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label" for="not_title">Body</label>
                  <div class="controls">
                    <input type="text" id="not_body">
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label" for="not_title">Button Label</label>
                  <div class="controls">
                    <input type="text" id="not_button">
                  </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="btn btn-primary btn-small" onClick="do_push();">Push!</div>
            </div>
        </div>
    </div>
  <div class="box">
      <div style="font-weight: 500; font-size: 20px; margin: 10px;">Safari Push Notification Service for <?php echo WEBSITE_NAME; ?></div>
        <!-- old safari lolz -->
        <div style="margin-top: 100px; text-align: center; display: none;" id="old">
          You need Safari 7.0+ on Mac OS 10.9+ to view your notification status.
        </div>
        <!-- checking permissions -->
        <div style="margin-top: 100px; text-align: center; display: none;" id="reqperm">
          <img src="loader.gif">
            <div>Requesting permission...</div>
        </div>
        <!-- denied permissions -->
        <div style="margin-top: 100px; text-align: center; display: none;" id="denied">
            <div>You have denied this website permission to send push notifications.</div>
            <div class="btn btn-primary btn-small" onClick="checkPerms();">I've changed my mind...</div>
        </div>
        <!-- granted permissions -->
        <div style="margin-top: 20px; text-align: center; display: none;" id="granted">
           <div>You have granted this website permission to send push notifications.</div>
          <div class="btn btn-primary" onClick="$('#modal_scrim').fadeIn(300); document.getElementById('not_title').focus();">Send Yourself a Push Notification</div>
        </div>
    </div>
   
    </div>
</body>
</html>