<?php
include_once "controllers/api_github_oAuth.php";
include_once "controllers/db_connection.php";
include_once "controllers/db_controller.php";
include_once "controllers/event_handler.php";

$headers = getallheaders();
$x_github_delivery = $headers['X-Github-Delivery'];
$x_hub_signature = $headers['X-Hub-Signature'];
$x_github_event = $headers['X-Github-Event'];
$payload = file_get_contents('php://input');

insertPayload($x_github_delivery, $x_hub_signature, $x_github_event, $payload);

switch ($x_github_event) {
  case "project":
    projectEventHandler(json_decode_nice($payload));
    break;
  case "project_column":
    projectColumnEventHandler(json_decode_nice($payload));
    break;
  case "project_card":
    projectCardEventHandler(json_decode_nice($payload));
    break;
  case "issues":
    issuesEventHandler(json_decode_nice($payload));
    break;
  default:
}

/**
* Decodifica las cadenas JSON escapando correctamente algunos caracteres especiales
* @param json el string JSON a decodificar
* @return json_decode el objeto JSON decodificado
*/
function json_decode_nice($json) {
  $json = str_replace("\n","\\n",$json);
  $json = str_replace("\r","",$json);
  $json = str_replace("\'","",$json);
  //$json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
  //$json = preg_replace('/(,)\s*}$/','}',$json);
  return json_decode($json);
}
  ?>
