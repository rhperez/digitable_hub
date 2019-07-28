<?php

/**
* Maneja los eventos recibidos del webhook relacionados con los proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectEventHandler ($json_payload) {
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
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
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
function projectColumnEventHandler ($json_payload) {
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
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }
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
function projectCardEventHandler ($json_payload) {
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
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
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
function issuesEventHandler ($json_payload) {
  $action = $json_payload->action;
  $action_details = null;
  switch ($action) {
    case "opened":
    case "closed":
    case "reopened":
    case "deleted":
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

      }

  }
  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
  }
  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }
  if ($json_payload->repository) {
    upsertRepository($json_payload->repository);
    $repository_id = $json_payload->repository->id;
    insertActionLog('issue', $json_payload->issue->id, "linked", "repo_id: ".$repository_id);
  }
  if ($json_payload->issue->milestone) {
    upsertMilestone($json_payload->issue->milestone, $repository_id);
    $milestone_id = $json_payload->issue->milestone->id;
    insertActionLog('issue', $json_payload->issue->id, "linked", "milestone_id: ".$milestone_id);
  }
  unlabelIssue($json_payload->issue->id);
  foreach ($json_payload->issue->labels as $json_label) {
    upsertLabel($json_label, $repository_id);
    upsertIssueLabel($json_payload->issue->id, $json_label->id);
    insertActionLog('issue', $json_payload->issue->id, "linked", "label_id: ".$json_label->id);
  }
  if (upsertIssue($json_payload->issue, $repository_id, $milestone_id) < 0) {
    return false;
  }
  insertActionLog('issue', $json_payload->issue->id, $action, $action_details, $created_by);
  return true;
}
 ?>
