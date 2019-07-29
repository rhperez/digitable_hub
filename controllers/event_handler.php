<?php

/**
* Maneja los eventos recibidos del webhook relacionados con los proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectEventHandler ($json_payload) {
  if (!$json_payload || !$json_payload->project) {
    return false;
  }
  $module = 'projectEventHandler';
  $action = $json_payload->action;

  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
    $organization = $json_payload->organization->login;
  }

  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }

  if ($json_payload->repository) {
    $affected_rows = upsertRepository($json_payload->repository);
    $repository_id = $json_payload->repository->id;
    $details = "repo_id: ".$repository_id;
    activity_log($module, $action, 'upsertRepository', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  }

  $project_id = $json_payload->project->id;
  $details = "project_id: ".$project_id;
  if ($json_payload->changes) {
    $details .= detail_changes_log($json_payload->changes);
  }
  $affected_rows = upsertProject($json_payload->project, $repository_id);
  activity_log($module, $action, 'upsertProject', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  if ($affected_rows < 0) {
    return false;
  }
  //insertActionLog('project', $json_payload->project->id, $action, $action_details, $created_by);
  if ($organization_id) {
    $affected_rows = insertOrganizationProject($organization_id, $project_id);
    $details = "organization_id: ".$organization_id;
    $details .= "\nproject_id: ".$project_id;
    activity_log($module, $action, 'insertOrganizationProject', transaction_result_log('insert', $affected_rows), $details, $created_by, $organization);
  }

  return true;
}

/**
* Maneja los eventos recibidos del webhook relacionados a las columnas de proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectColumnEventHandler ($json_payload) {
  if (!$json_payload || !$json_payload->project_column) {
    return false;
  }
  $module = 'projectColumnEventHandler';
  $action = $json_payload->action;

  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
    $organization = $json_payload->organization->login;
  }

  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }

  if ($json_payload->repository) {
    $affected_rows = upsertRepository($json_payload->repository);
    $repository_id = $json_payload->repository->id;
    $details = "repo_id: ".$repository_id;
    activity_log($module, $action, 'upsertRepository', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  }

  $column_id = $json_payload->project_column->id;
  $details = "column_id: ".$column_id;
  if ($json_payload->changes) {
    $details .= detail_changes_log($json_payload->changes);
  }
  if ($after_id = $json_payload->project_column->after_id) {
    $details .= "\nafter_id: ".$after_id;
  }

  $affected_rows = upsertProjectColumn($json_payload->project_column);
  activity_log($module, $action, 'upsertProjectColumn', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  if ($affected_rows < 0) {
    return false;
  }

  return true;
}

/**
* Maneja los eventos recibidos del webhook relacionados a las tarjetas de proyectos
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function projectCardEventHandler ($json_payload) {
  if (!$json_payload || !$json_payload->project_card) {
    return false;
  }
  $module = 'projectCardEventHandler';
  $action = $json_payload->action;

  if ($json_payload->organization) {
    // TODO upsertOrganization
    $organization_id = $json_payload->organization->id;
    $organization = $json_payload->organization->login;
  }

  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }

  if ($json_payload->repository) {
    $affected_rows = upsertRepository($json_payload->repository);
    $repository_id = $json_payload->repository->id;
    $details = "repo_id: ".$repository_id;
    activity_log($module, $action, 'upsertRepository', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  }

  $card_id = $json_payload->project_card->id;
  $details = "card_id: ".$card_id;
  if ($json_payload->changes) {
    $details .= detail_changes_log($json_payload->changes);
  }

  $affected_rows = upsertProjectCard($json_payload->project_card);
  activity_log($module, $action, 'upsertProjectCard', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  if ($affected_rows < 0) {
    return false;
  }

  return true;
}

/**
* Maneja los eventos recibidos del webhook relacionados con issues de repositorios
* @param json_payload el payload recibido del webhook
* @return true si y sólo si el evento se manejó exitosamente
*/
function issuesEventHandler ($json_payload) {
  if (!$json_payload || !$json_payload->issue) {
    return false;
  }
  $module = 'issuesEventHandler';
  $action = $json_payload->action;

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
      if ($changes) {
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
    $organization = $json_payload->organization->login;
  }

  if ($json_payload->sender) {
    // TODO upsertSender
    $created_by = $json_payload->sender->login;
  }

  if ($json_payload->repository) {
    $affected_rows = upsertRepository($json_payload->repository);
    $repository_id = $json_payload->repository->id;
    $details = "repo_id: ".$repository_id;
    activity_log($module, $action, 'upsertRepository', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  }

  if ($json_payload->issue->milestone) {
    $affected_rows = upsertMilestone($json_payload->issue->milestone, $repository_id);
    $milestone_id = $json_payload->issue->milestone->id;
    $details = "milestone_id: ".$milestone_id;
    activity_log($module, $action, 'upsertMilestone', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  }

  $issue_id = $json_payload->issue->id;
  $details = "issue_id: ".$issue_id;

  $affected_rows = unlabelIssue($issue_id);
  activity_log($module, $action, 'unlabelIssue', transaction_result_log('update', $affected_rows), $details, $created_by, $organization);

  if ($json_payload->changes) {
    $details .= detail_changes_log($json_payload->changes);
  }
  $affected_rows = upsertIssue($json_payload->issue, $repository_id, $milestone_id);
  activity_log($module, $action, 'upsertIssue', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  if ($affected_rows < 0) {
    return false;
  }

  foreach ($json_payload->issue->labels as $json_label) {
    $affected_rows = upsertLabel($json_label, $repository_id);
    $details = "label_id: ".$json_label->id;
    activity_log($module, $action, 'upsertLabel', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
    $affected_rows = upsertIssueLabel($issue_id, $json_label->id);
    $details = "issue_id: ".$issue_id;
    $details .= "\nlabel_id: ".$json_label->id;
    activity_log($module, $action, 'upsertIssueLabel', transaction_result_log('upsert', $affected_rows), $details, $created_by, $organization);
  }

  return true;
}
 ?>
