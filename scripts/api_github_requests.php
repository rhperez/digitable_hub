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
* @param user_name el username del usuario
* @return json_user La información del usuario en formato JSON
*/
function requestUser($user_name) {
  $url = "https://api.github.com/users/".$user_name;
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
* @param user el id del usuario propietario de los repositorios
* @return json_repos La información de los repositorios, en formato JSON
*/
function requestRepos($user) {
  $url = "https://api.github.com/users/".$user."/repos";
  $response = executeRequest($url);
  $json_repos = json_decode($response);
  $repos_unchanged = 0;
  $repos_inserted = 0;
  $repos_updated = 0;
  if ($json_repos->message) {
    echo "Error: ".$json_repos->message;
    die();
  }
  foreach($json_repos as $repo) {
    $rows_affected = insertRepo($repo);
    if (!$rows_affected) { // no changes
      $repos_unchanged++;
      continue;
    }
    requestBranches($user, $repo->name);
    if ($rows_affected == 1) { // repo inserted
      $repos_inserted++;
    } else { // repo updated
      $repos_updated++;
    }
  }
  echo "Repos Inserted: ".$repos_inserted."<br/>";
  echo "Repos Updated: ".$repos_updated."<br/>";
  echo "Repos Unchanged: ".$repos_unchanged."<br/>";
  return $json_repos;
}

/**
* Solicita a la API la información de las ramas de un repositorio en formato JSON y la almacena en la base de datos
* @param user el id del usuario propietario del repositorio
* @param repo_name el nombre del repositorio
* @return json_branches La información de las ramas del repositorio, en formato JSON
*/
function requestBranches($user, $repo_name) {
  $url = "https://api.github.com/repos/".$user."/".$repo_name."/branches";
  $response = executeRequest($url);
  $json_branches = json_decode($response);
  if ($json_branches->message) {
    echo "Error: ".$json_branches->message;
    die();
  }
  $repo_id = getRepo_id($user, $repo_name);
  foreach ($json_branches as $branch) {
    insertBranch($branch, $repo_id);
  }
  return $json_branches;
}

/**
* Solicita a la API la información de los colaboradores de un repositorio en formato JSON y la almacena en la base de datos
* @param user el id del usuario propietario del repositorio
* @param repo_name el nombre del repositorio
* @return json_collaborators La información de los colaboradores del repositorio, en formato JSON
*/
function requestCollaborators($user, $repo_name) {
  $url = "https://api.github.com/repos/".$user."/".$repo_name."/collaborators";
  $response = executeRequest($url);
  $json_collaborators = json_decode($response);
  if ($json_collaborators->message) {
    echo "Error: ".$json_collaborators->message;
    die();
  }
  $repo_id = getRepo_id($user, $repo_name);
  foreach ($json_collaborators as $collaborator) {
    insertCollaborator($collaborator, $repo_id);
  }
  return $json_collaborators;
}

/**
* Solicita a la API la información de los commits de una rama en formato JSON y la almacena en la base de datos
* @param user el id del usuario propietario del repositorio
* @param repo_name el nombre del repositorio al que pertenece la rama
* @param branch_sha el identificador SHA de la rama
* @param last_update Opcional, la fecha de última actualización del repositorio
* @return json_commits La información de los commits de la rama, en formato JSON
*/
function requestCommits($user, $repo_name, $branch_sha, $last_update = null) {
  //if ($updated_at = getRepoLastUpdate($user, $repo_name)) {
  if ($since) {
    $since = "&since=".str_replace(' ', 'T', $last_update);
  } else {
    $since = '';
  }
  $url = "https://api.github.com/repos/".$user."/".$repo_name."/commits?sha=".$branch_sha.$since;
  $response = executeRequest($url);
  $json_commits = json_decode($response);
  if ($json_commits->message) {
    echo "Error: ".$json_commits->message;
    die();
  }
  foreach ($json_commits as $commit) {
    insertCommit($commit, $branch_sha);
  }
  return $json_commits;
}

?>
