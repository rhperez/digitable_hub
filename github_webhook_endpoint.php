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
function json_decode_nice($json){
  $json = str_replace("\n","\\n",$json);
  $json = str_replace("\r","",$json);
  $json = str_replace("\'","",$json);
  //$json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
  //$json = preg_replace('/(,)\s*}$/','}',$json);
  return json_decode($json);
}

/**
* Maneja los eventos recibidos del webhook relacionados con los proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectEventHandler($json_payload) {
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }
  $action = $json_payload->action;
  $action_details = null;
  switch ($action) {
    case "created":
    case "closed":
    case "reopened":
    case "deleted":
    break;
    case "edited":
    $changes = $json_payload->changes;
    if (sizeof($changes) > 0) {
      $action_details = "Last values:";
      foreach ($changes as $key => $value) {
        $action_details .= "\n".$key.": ".$value->from;
      }
    } else {
      $action = "updated";
    }
    break;
  }
  if (upsertProject($json_payload->project) < 0) {
    return false;
  }
  insertActionLog('project', $json_payload->project->id, $action, $action_details, $created_by);
  if ($organization_id && upsertOrganizationProject($organization_id, $json_payload->project->id) < 0) {
    return false;
  }
  insertActionLog('project', $json_payload->project->id, "linked", "organization_id: ".$organization_id, $created_by);
  return true;
}

/**
* Maneja los eventos recibidos del webhook relacionados a las columnas de proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectColumnEventHandler($json_payload) {
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }
  $action = $json_payload->action;
  $action_details = null;
  switch ($action) {
    case "created":
    case "deleted":
    break;
    case "edited":
    $changes = $json_payload->changes;
    if (sizeof($changes) > 0) {
      $action_details = "Last values:";
      foreach ($changes as $key => $value) {
        $action_details .= "\n".$key.": ".$value->from;
      }
    } else {
      $action = "updated";
    }
    break;
    case "moved":
    if ($after_id = $json_payload->project_column->after_id) {
      $action_details = "After_id: ".$after_id;
    }
    break;
  }
  $affected_rows = upsertProjectColumn($json_payload->project_column);
  if (upsertProjectColumn($json_payload->project_column) < 0) {
    return false;
  }
  insertActionLog('project_column', $json_payload->project_column->id, $action, $action_details, $created_by);
  return true;
}

/**
* Maneja los eventos recibidos del webhook relacionados a las tarjetas de proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectCardEventHandler($json_payload) {
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }
  $action = $json_payload->action;
  $action_details = null;
  switch ($action) {
    case "created":
    case "deleted":
    break;
    case "edited":
    case "moved":
    case "converted":
    $changes = $json_payload->changes;
    if (sizeof($changes) > 0) {
      $action_details = "Last values:";
      foreach ($changes as $key => $value) {
        $action_details .= "\n".$key.": ".$value->from;
      }
    } else {
      $action = "updated";
    }
  }
  if (upsertProjectCard($json_payload->project_card) < 0) {
    return false;
  }
  insertActionLog('project_card', $json_payload->project_card->id, $action, $action_details, $created_by);
  return true;
}

/**
* Maneja los eventos recibidos del webhook relacionados con issues de repositorios
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function issuesEventHandler($json_payload) {
  $action = $json_payload->action;
  switch ($json_payload->action) {
    case "opened":
    case "closed":
    case "reopened":
    case "deleted":
      upsertIssue($action, null, $json_payload->issue, $json_payload->organization, $json_payload->sender);
      break;
    case "edited":
    case "transferred":
    case "pinned":
    case "unpinned":
    case "assigned":
    case "unassigned":
    case "labeled":
    case "unlabeled":
    case "locked":
    case "unlocked":
    case "milestoned":
    case "unmilestoned":
      $changes = $json_payload->changes;
      if (sizeof($changes) > 0) {
        $action_details = "Last values:";
        foreach ($changes as $key => $value) {
          $action_details .= "\n".$key.": ".$value->from;
        }
      } else {
        $action = "updated";
        $action_details = null;
      }
      upsertIssue($action, $action_details, $json_payload->issue, $json_payload->organization, $json_payload->sender);
  }
  if ($json_payload->repository) {
    upsertRepository("Issue: ".$action, null, $json_payload->repository);
  }
}
?>
