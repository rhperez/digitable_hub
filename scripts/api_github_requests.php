<?php

/**
* Ejecuta un request a la API
* @param url la URL del request
* @return response la respuesta de la API, en una cadena en formato JSON.
* En caso de no recibir respuesta, se genera una cadena en formato JSON con un mensaje de error.
*/
function executeRequest($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_USERPWD, getOAuth_username().":".getOAuth_access_token());
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
  $response = curl_exec($ch);
  if (!$response) {
    return '{"message":"No hubo respuesta del servidor"}';
  }
  return $response;
}

/**
* Solicita a la API la información de la cuota límite de solicitudes disponibles para el usuario
* @return json_rate_limit la información de la cuota límite, en formato JSON
*/
function requestRateLimit() {
  $url = "https://api.github.com/rate_limit";
  $json_rate_limit = json_decode(executeRequest($url), JSON_PRETTY_PRINT);
  return $json_rate_limit;
}

/**
* Solicita a la API la información del usuario
* @return json_me la información del usuario en formato JSON
*/
function requestMe() {
  $url = "https://api.github.com/user";
  $json_me = json_decode(executeRequest($url));
  return $json_me;
}

/**
* Solicita a la API la información de un usuario en formato JSON y la almacena en la base de datos
* @param login el login del usuario
* @return json_user La información del usuario en formato JSON
*/
function requestUser($login) {
  $url = "https://api.github.com/users/".$login;
  $response = executeRequest($url);
  $json_user = json_decode($response);
  if ($json_user->message) {
    echo "Error: ".$json_user->message;
    die();
  }
  echo "Result: ".insertUser($json_user);
  return $json_user;
}

/**
* Solicita a la API la información de los repositorios de un usuario en formato JSON y la almacena en la base de datos
* @param login el login del usuario propietario de los repositorios
* @return json_repos La información de los repositorios, en formato JSON
*/
function requestRepos($login) {
  $url = "https://api.github.com/users/".$login."/repos";
  echo "Solicitando repositorios, usuario ".$login."...<br/>";
  $response = executeRequest($url);
  $json_repos = json_decode($response);
  $repos_unchanged = 0;
  $repos_inserted = 0;
  $repos_updated = 0;
  if ($json_repos->message) {
    echo "Error: ".$json_repos->message;
    die();
  }
  echo "Agregando repositorios, usuario ".$login."...<br/>";
  foreach($json_repos as $repo) {
    $rows_affected = insertRepo($repo);
    if (!$rows_affected) { // repositorio sin cambios
      $repos_unchanged++;
      echo "Repositorio ".$repo->name." sin cambios<br/>";
      continue;
    }
    $last_update = getRepoLastUpdate($login, $repo->name);
    requestBranches($login, $repo->name, $last_update);
    requestCollaborators($login, $repo->name);
    if ($rows_affected == 1) { // repositorio insertado
      $repos_inserted++;
      echo "Agregado nuevo repositorio: ".$repo->name."<br/>";

    } else { // repositorio actualizado
      $repos_updated++;
      echo "Actualizado repositorio: ".$repo->name."<br/>";

    }
  }
  echo "Repos agregados: ".$repos_inserted."<br/>";
  echo "Repos actualizados: ".$repos_updated."<br/>";
  echo "Repos sin cambios: ".$repos_unchanged."<br/>";
  $user_id = getUser_id($login);
  $repos_up_to_date = updateRepos($user_id);
  echo "Total de repos puestos al día: ".$repos_up_to_date."<br/>";
  return $json_repos;
}

/**
* Solicita a la API la información de las ramas de un repositorio en formato JSON y la almacena en la base de datos
* @param login el login del usuario propietario del repositorio
* @param repo_name el nombre del repositorio
* @param last_update Opcional, la fecha de última actualización del repositorio
* @return json_branches La información de las ramas del repositorio, en formato JSON
*/
function requestBranches($login, $repo_name, $last_update = null) {
  $url = "https://api.github.com/repos/".$login."/".$repo_name."/branches";
  echo "&nbsp;&nbsp;&nbsp;Solicitando ramas, repo ".$repo_name."...<br/>";
  $response = executeRequest($url);
  $json_branches = json_decode($response);
  if ($json_branches->message) {
    echo "Error: ".$json_branches->message;
    die();
  }
  $repo_id = getRepo_id($login, $repo_name);
  echo "&nbsp;&nbsp;&nbsp;Agregando ramas, repo ".$repo_name."...<br/>";
  foreach ($json_branches as $branch) {
    $rows_affected = insertBranch($branch, $repo_id);
    switch ($rows_affected) {
      case 1:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Nueva rama: ".$branch->commit->sha."<br/>";
      break;
      case 0:
      case 2:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Rama actualizada: ".$branch->commit->sha."<br/>";
      break;
      default:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Error en el query, rama".$branch->commit->sha."<br/>";
      break;
    }
    requestCommits($login, $repo_name, $branch->commit->sha, $last_update);
  }
  return $json_branches;
}

/**
* Solicita a la API la información de los colaboradores de un repositorio en formato JSON y la almacena en la base de datos
* @param login el login del usuario propietario del repositorio
* @param repo_name el nombre del repositorio
* @return json_collaborators La información de los colaboradores del repositorio, en formato JSON
*/
function requestCollaborators($login, $repo_name) {
  $url = "https://api.github.com/repos/".$login."/".$repo_name."/collaborators";
  echo "&nbsp;&nbsp;&nbsp;Solicitando colaboradores, repo ".$repo_name."...<br/>";
  $response = executeRequest($url);
  $json_collaborators = json_decode($response);
  if ($json_collaborators->message) {
    echo "Error: ".$json_collaborators->message;
    die();
  }
  $repo_id = getRepo_id($login, $repo_name);
  echo "&nbsp;&nbsp;&nbsp;Agregando colaboradores, repo ".$repo_name."...<br/>";
  foreach ($json_collaborators as $collaborator) {
    $rows_affected = insertCollaborator($collaborator, $repo_id);
    switch ($rows_affected) {
      case 0:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Colaborador sin cambios: ".$collaborator->id."<br/>";
      break;
      case 1:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Nuevo colaborador: ".$collaborator->id."<br/>";
      break;
      case 2:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Colaborador actualizado: ".$collaborator->id."<br/>";
      break;
      default:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Error en el query, colaborador".$collaborator->id."<br/>";
      break;
    }
  }
  return $json_collaborators;
}

/**
* Solicita a la API la información de los commits de una rama en formato JSON y la almacena en la base de datos
* @param login el login del usuario propietario del repositorio
* @param repo_name el nombre del repositorio al que pertenece la rama
* @param branch_sha el identificador SHA de la rama
* @param last_update Opcional, la fecha de última actualización del repositorio
* @return json_commits La información de los commits de la rama, en formato JSON
*/
function requestCommits($login, $repo_name, $branch_sha, $last_update = null) {
  //if ($updated_at = getRepoLastUpdate($user, $repo_name)) {
  if ($since) {
    $since = "&since=".str_replace(' ', 'T', $last_update);
  } else {
    $since = '';
  }
  $url = "https://api.github.com/repos/".$login."/".$repo_name."/commits?sha=".$branch_sha.$since;
  echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Solicitando commits, rama ".$branch_sha."...<br/>";
  $response = executeRequest($url);
  $json_commits = json_decode($response);
  if ($json_commits->message) {
    echo "Error: ".$json_commits->message;
    die();
  }
  echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Agregando commits, rama ".$branch_sha."...<br/>";
  foreach ($json_commits as $commit) {
    $rows_affected = insertCommit($commit, $branch_sha);
    switch ($rows_affected) {
      case 0:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Commit sin cambios: ".$commit->sha."<br/>";
      break;
      case 1:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Nuevo commit: ".$commit->sha."<br/>";
      break;
      case 2:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Commit actualizado: ".$commit->sha."<br/>";
      break;
      default:
      echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Error en el query, commit".$commit->sha."<br/>";
      break;
    }
  }
  return $json_commits;
}

?>
