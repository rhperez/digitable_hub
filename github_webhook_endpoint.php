<?php
  include_once "scripts/api_github_requests.php";
  include_once "controllers/api_github_ctrlr.php";

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
    default:
  }

  function json_decode_nice($json){
    $json = str_replace("\n","\\n",$json);
    $json = str_replace("\r","",$json);
    $json = str_replace("\'","",$json);
    //$json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
    //$json = preg_replace('/(,)\s*}$/','}',$json);
    return json_decode($json);
  }

  function projectEventHandler($json_payload) {
    switch ($json_payload->action) {
      case "created":
      case "closed":
      case "reopened":
      case "deleted":
        upsertProject($json_payload->action, null, $json_payload->project, $json_payload->organization, $json_payload->sender);
        break;
      case "edited":
        $changes = $json_payload->changes;
        if (sizeof($changes) > 0) {
          $action = "edited";
          $action_details = "Last values:";
          foreach ($changes as $key => $value) {
            $action_details .= "\n".$key.": ".$value->from;
          }
        } else {
          $action = "updated";
          $action_details = null;
        }
        upsertProject($action, $action_details, $json_payload->project, $json_payload->organization, $json_payload->sender);
        break;
    }
  }

  function projectColumnEventHandler($json_payload) {
    switch ($json_payload->action) {
      case "created":
      case "deleted":
        upsertProjectColumn($json_payload->action, null, $json_payload->project_column, $json_payload->organization, $json_payload->sender);
        break;
      case "edited":
        $changes = $json_payload->changes;
        if (sizeof($changes) > 0) {
          $action = "edited";
          $action_details = "Last values:";
          foreach ($changes as $key => $value) {
            $action_details .= "\n".$key.": ".$value->from;
          }
        } else {
          $action = "updated";
          $action_details = null;
        }
        upsertProjectColumn($action, $action_details, $json_payload->project_column, $json_payload->organization, $json_payload->sender);
        break;
      case "moved":
        $action_details = null;
        if ($after_id = $json_payload->project_column->after_id) {
          $action_details = "After_id: ".$after_id;
        }
        upsertProjectColumn($json_payload->action, $action_details, $json_payload->project_column, $json_payload->organization, $json_payload->sender);
        break;
    }
  }

  function projectCardEventHandler($json_payload) {
    switch ($json_payload->action) {
      case "created":
      case "deleted":
        upsertProjectCard($json_payload->action, null, $json_payload->project_card, $json_payload->organization, $json_payload->sender);
        break;
      case "edited":
      case "moved":
      case "converted":
        $changes = $json_payload->changes;
        if (sizeof($changes) > 0) {
          $action = $json_payload->action;
          $action_details = "Last values:";
          foreach ($changes as $key => $value) {
            $action_details .= "\n".$key.": ".$value->from;
          }
        } else {
          $action = "updated";
          $action_details = null;
        }
        upsertProjectCard($action, $action_details, $json_payload->project_card, $json_payload->organization, $json_payload->sender);
      default:
      break;
    }
  }
?>
