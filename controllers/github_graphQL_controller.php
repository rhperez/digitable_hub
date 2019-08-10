<?php

function parse_graphQL_transaction($json_transaction) {
  $json_transaction = '{
    "query": "'.$json_transaction.'"
  }';
  $json_transaction = trim(preg_replace('/\s\s+/', ' ', $json_transaction));
  return $json_transaction;
}

/**
* Solicita la ejecución de un query en la API de graphQL de GitHub
* @param json_transaction el string JSON de la transacción a realizar
* @return response la respuesta de la API, en una cadena en formato JSON.
* En caso de no recibir respuesta, se genera una cadena en formato JSON con un mensaje de error.
*/
function execute_graphQL_query($json_transaction) {
  $json_transaction = parse_graphQL_transaction($json_transaction);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/graphql');
  curl_setopt($ch, CURLOPT_USERPWD, getOAuth_username().":".getOAuth_access_token());
  if ($json_transaction) {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_transaction);
  } else {
    curl_setopt($ch, CURLOPT_POST, 0);
  }
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
  $response = curl_exec($ch);
  if (!$response) {
    return '{"message":"No hubo respuesta del servidor"}';
  }
  return $response;
}

/**
* Función auxiliar para dar formato a los argumentos de los campos de las transacciones
* @param field_name el nombre del campo
* @param value el valor del campo. Puede ser un arreglo de valores
* @param add_quotes (opcional) indica si se deben colocar comillas alrededor del valor o valores
* @return formated_arg el argumento con formato
*/
function field_args($field_name, $value, $add_quotes = true) {
  $formated_arg = '';
  if (!$value) { // campo vacío
    return $formated_arg;
  }
  $quotes = $add_quotes ? '\"' : '';
  if (!is_array($value)) { // valor individual
    $formated_arg = $field_name.':'.$quotes.$value.$quotes;
    return $formated_arg;
  }
  // es un arreglo de valores
  $item_ids = "";
  foreach ($value as $item) {
    $item_ids .= $quotes.$item.$quotes.' ';
  }
  $formated_arg = $field_name.':['.$item_ids.']';
  return $formated_arg;
}

function getViewerRateLimit() {
  $json_query = 'query {
    viewer {
      login
    }
    rateLimit {
      limit
      cost
      remaining
      resetAt
    }
  }';
  return $json_query;
}

function getUser($login) {
  $json_query = 'query {
    user(login:\"'.$login.'\") {
      login
      id
      name
      bio
      company
      avatarUrl
      email
      location
      createdAt
      updatedAt
      websiteUrl
    }
  }';
  return $json_query;
}

function getOrganizationId($login) {
  $json_query = 'query {
    organization(login:\"'.$login.'\") {
      id
    }
  }';
  return $json_query;
}

/**
* Genera un query en formato JSON para devolver un listado de repositorios de un usuario
* @param login el login del usuario
* @param is_fork (opcional) indica si devuelve los repositorios que derivan de otros repositorios. Este indicador es falso por default.
* @param is_locked (opcional) indica si devuelve los repositorios con candado. Este indicador es falso por default.
* @param privacy (opcional) indica si debe filtrar por repositorios con privacidad PUBLIC o PRIVATE. Si es null, devuelve todos
* @param last (opcional) la cantidad máxima de registros devueltos. Si es null, devuelve 20
* @param order_field (opcional) el campo por el que se ordenarán los repositorios. Por default se ordenan por PUSHED_AT
* @param order_direction (opcional) la dirección de ordenamiento. Por default se ordena como DESC
* @return json_query el JSON de la transacción generada
*/
function listUserRepositories($login, $is_fork = 'false', $is_locked = 'false', $privacy = null, $last = 20, $order_field = 'PUSHED_AT', $order_direction = 'DESC') {
  $input_args  = field_args('isFork', $is_fork, false).' ';
  $input_args .= field_args('isLocked', $is_locked, false).' ';
  $input_args .= field_args('privacy', $privacy, false).' ';
  $input_args .= field_args('last', $last, false).' ';
  $input_args .= ' orderBy:{'.field_args('field', $order_field, false).' '.field_args('direction', $order_direction, false).'}';
  $json_query = 'query {
    user('.field_args('login', $login).') {
      repositories('.$input_args.') {
        edges {
          node {
            id
            name
            url
          }
        }
      }
    }
  }';
  return $json_query;
}

/**
* Genera un query en formato JSON para devolver un listado de repositorios de una organización
* @param login el login de la organización
* @param is_fork (opcional) indica si devuelve los repositorios que derivan de otros repositorios. Este indicador es falso por default.
* @param is_locked (opcional) indica si devuelve los repositorios con candado. Este indicador es falso por default.
* @param privacy (opcional) indica si debe filtrar por repositorios con privacidad PUBLIC o PRIVATE. Si es null, devuelve todos
* @param last (opcional) la cantidad máxima de registros devueltos. Si es null, devuelve 20
* @param order_field (opcional) el campo por el que se ordenarán los repositorios. Por default se ordenan por PUSHED_AT
* @param order_direction (opcional) la dirección de ordenamiento. Por default se ordena como DESC
* @return json_query el JSON de la transacción generada
*/
function listOrganizationRepositories($login, $is_fork = 'false', $is_locked = 'false', $privacy = null, $last = 20, $order_field = 'PUSHED_AT', $order_direction = 'DESC') {
  $input_args  = field_args('isFork', $is_fork, false).' ';
  $input_args .= field_args('isLocked', $is_locked, false).' ';
  $input_args .= field_args('privacy', $privacy, false).' ';
  $input_args .= field_args('last', $last, false).' ';
  $input_args .= ' orderBy:{'.field_args('field', $order_field, false).' '.field_args('direction', $order_direction, false).'}';
  $json_query = 'query {
    organization('.field_args('login', $login).') {
      repositories('.$input_args.') {
        edges {
          node {
            id
            name
            url
          }
        }
      }
    }
  }';
  return $json_query;
}

/**
* Genera un query en formato JSON para devolver los últimos issues de un repositorio
* @param owner_login el login del propietario del repositorio (user, organization)
* @param name el nombre del repositorio
* @param labelsArray (opcional) arregla de etiquetas de issues a devolver
* @param statesArray (opcional) el arreglo de enums de estados de issues a devolver
* @param last (opcional) la cantidad de issues a devolver. Por default se devuelven 20
* @param order_field (opcional) el campo por el que se ordenarán los proyectos. Por default se ordenan por UPDATED_AT
* @param order_direction (opcional) la dirección de ordenamiento. Por default se ordena como DESC
* @return json_query el JSON de la transacción generada
*/
function listRepositoryIssues($owner_login, $name, $labelsArray = array(), $statesArray = array(), $last = 20, $order_field = 'UPDATED_AT', $order_direction = 'DESC') {
  $input_args  = field_args('labels', $labelsArray).' ';
  $input_args .= field_args('states', $statesArray, false).' ';
  $input_args .= field_args('last', $last, false).' ';
  $input_args .= ' orderBy:{'.field_args('field', $order_field, false).' '.field_args('direction', $order_direction, false).'}';
  $json_query = 'query {
    repository('.field_args('owner', $owner_login).', '.field_args('name', $name).') {
      issues('.$input_args.') {
        edges {
          node {
            title
            url
            state
            labels(first:5) {
              edges {
                node {
                  name
                }
              }
            }
          }
        }
      }
    }
  }';
  return $json_query;
}

/**
* Genera un query en formato JSON para devolver los proyectos de un usuario
* @param login el login del usuario
* @param statesArray el arreglo de enums de estados de proyectos a devolver. Puede incluirse OPEN y/o CLOSED. Por defecto devuelve OPEN
* @param last (opcional) la cantidad de proyectos a devolver. Por default se devuelven 20
* @param order_field (opcional) el campo por el que se ordenarán los proyectos. Por default se ordenan por UPDATED_AT
* @param order_direction (opcional) la dirección de ordenamiento. Por default se ordena como DESC
* @return json_query el JSON de la transacción generada
*/
function listUserProjects($login, $statesArray = array('OPEN'), $last = 20, $order_field = 'UPDATED_AT', $order_direction = 'DESC') {
  $input_args = field_args('states', $statesArray, false).' ';
  $input_args .= field_args('last', $last, false).' ';
  $input_args .= ' orderBy:{'.field_args('field', $order_field, false).' '.field_args('direction', $order_direction, false).'}';
  $json_query = 'query {
    user('.field_args('login', $login).') {
      projects('.$input_args.') {
        edges {
          node {
            id
            databaseId
            name
            body
            number
            updatedAt
          }
        }
      }
    }
  }';
  return $json_query;
}

/**
* Genera un query en formato JSON para devolver los proyectos de una organización
* @param login el login de la organización
* @param statesArray el arreglo de enums de estados de proyectos a devolver. Puede incluirse OPEN y/o CLOSED. Por defecto devuelve OPEN
* @param last (opcional) la cantidad de proyectos a devolver. Por default se devuelven 20
* @param order_field (opcional) el campo por el que se ordenarán los proyectos. Por default se ordenan por UPDATED_AT
* @param order_direction (opcional) la dirección de ordenamiento. Por default se ordena como DESC
* @return json_query el JSON de la transacción generada
*/
function listOrganizationProjects($login, $statesArray = array('OPEN'), $last = 20, $order_field = 'UPDATED_AT', $order_direction = 'DESC') {
  $input_args = field_args('states', $statesArray, false).' ';
  $input_args .= field_args('last', $last, false).' ';
  $input_args .= ' orderBy:{'.field_args('field', $order_field, false).' '.field_args('direction', $order_direction, false).'}';
  $json_query = 'query {
    organization('.field_args('login', $login).') {
      projects('.field_args('last', $last, false).', '.field_args('states', $statesArray, false).') {
        edges {
          node {
            id
            databaseId
            name
            body
            number
            updatedAt
          }
        }
      }
    }
  }';
  return $json_query;
}

/**
* Genera una mutation en formato JSON para agregar un comentario
* @param subject_id el Node ID del asunto a comentar (issue, commit, comentarios)
* @param body el cuerpo del comentario
* @return json_query el JSON de la transacción generada
*/
function addComment($subject_id, $body) {
  $input_args  = field_args('subjectId', $subject_id);
  $input_args .= ', '.field_args('body', $body);
  $json_mutation = 'mutation AddComment {
    addComment(input:{'.$input_args.'}) {
      commentEdge {
        node {
          id
        }
      }
    }
  }';
  return $json_mutation;
}

/**
* Genera una mutation en formato JSON para crear un repositorio
* @param owner_id el Node ID del actor (user, organization)
* @param name el nombre del repositorio
* @param description (opcional) la descripción del proyecto
* @param homepage_url (opcional) la homepage del repositorio
* @param team_id (opcional) el Node ID del equipo asociado al repositorio
* @param has_issues_enabled (opcional) indica si el repositorio puede reportar issues. Habilitado por default
* @param has_wiki_enabled (opcional) indica si el repositorio puede incluir una wiki. Habilitado por default
* @param template (opcional) indica si el repositorio funciona como plantilla. Desabilitado por defecto
* @param visibility (opcional) indica el nivel de visibilidad del repositorio (PUBLIC, PRIVATE, INTERNAL). Hbailitado como PUBLIC por default
* @return json_query el JSON de la transacción generada
*/
function createRepository($owner_id, $name, $description = null, $homepage_url = null, $team_id = null, $has_issues_enabled = 'true', $has_wiki_enabled = 'true', $template = 'false', $visibility = 'PUBLIC') {
  $input_args  = field_args('ownerId', $owner_id);
  $input_args .= ', '.field_args('name', $name);
  $input_args .= ', '.field_args('description', $description);
  $input_args .= ', '.field_args('homepageUrl', $homepage_url);
  $input_args .= ', '.field_args('teamId', $team_id);
  $input_args .= ', '.field_args('hasIssuesEnabled', $has_issues_enabled, false);
  $input_args .= ', '.field_args('hasWikiEnabled', $has_wiki_enabled, false);
  $input_args .= ', '.field_args('template', $template, false);
  $input_args .= ', '.field_args('visibility', $visibility, false);
  $json_mutation = 'mutation createRepository {
    createRepository(input:{'.$input_args.'}) {
      repository {
        id
      }
    }
  }';
  return $json_mutation;
}

/**
* Genera una mutation en formato JSON para crear un proyecto
* @param owner_id el Node ID del actor propietario del proyecto
* @param name el nombre del proyecto
* @param body (opcional) el cuerpo del proyecto
* @param repo_ids_array (opcional) arreglo de Node IDs de los repositorios asociados al proyecto
* @param template (opcional) plantilla a utilizar
* @return json_mutation el JSON de la transacción generada
*/
function createProject($owner_id, $name, $body=null, $repo_ids_array = array(), $template = null) {
  $input_args  = field_args('ownerId', $owner_id);
  $input_args .= ', '.field_args('name', $name);
  $input_args .= ', '.field_args('body', $body);
  $input_args .= ', '.field_args('repositoryIds', $repo_ids_array);
  $json_mutation = 'mutation CreateProject {
    createProject(input:{'.$input_args.'}) {
      project {
        id
      }
    }
  }';
  return $json_mutation;
}

function updateProject() {

}

/**
* Genera una mutation en formato JSON para agregar una columna a un proyecto
* @param project_id el Node ID del proyecto
* @param name el nombre de la columna
* @return json_mutation el JSON de la transacción generada
*/
function addProjectColumn($project_id, $name) {
  $input_args  = field_args('projectId', $project_id);
  $input_args .= ', '.field_args('name', $name);
  $json_mutation = 'mutation AddProjectColumn {
    addProjectColumn(input:{'.$input_args.'}) {
      columnEdge {
        node {
          id
        }
      }
      project {
        id
      }
    }
  }';
  return $json_mutation;
}

/**
* Genera una mutation en formato JSON para agregar una tarjeta a una columna
* @param project_column_id el Node ID de la columna
* @param note (opcional) la nota de la tarjeta
* @param content_id (opcional) el Node ID del contenido de la tarjeta (un Issue o un PullRequest)
* @return json_mutation el JSON de la transacción generada
*/
function addProjectCard($project_column_id, $note = null, $content_id = null) {
  $input_args  = field_args('projectColumnId', $project_column_id);
  $input_args .= ', '.field_args('note', $note);
  $input_args .= ', '.field_args('contentId', $content_id);
  $json_mutation = 'mutation AddProjectCard {
    addProjectCard(input:{'.$input_args.'}) {
      cardEdge {
        node {
          id
        }
      }
      projectColumn {
        id
      }
    }
  }';
  return $json_mutation;
}

/**
* Genera una mutation en formato JSON para mover una columna de un proyecto
* @param column_id el Node ID de la columna
* @param after_column_id (opcional) el Node ID de la columna anterior a la que se colocará la columna. Dejar en null para colocar al inicio
* @return json_mutation el JSON de la transacción generada
*/
function moveProjectColumn($column_id, $after_column_id = null) {
  $input_args  = field_args('columnId', $column_id);
  $input_args .= ', '.field_args('afterColumnId', $after_column_id);
  $json_mutation = 'mutation MoveProjectColumn {
    moveProjectColumn(input:{'.$input_args.'}) {
      columnEdge {
        node {
          id
        }
      }
      project {
        id
      }
    }
  }';
  return $json_mutation;
}

/**
* Genera una mutation en formato JSON para mover una columna de un proyecto
* @param card_id el Node ID de la tarjeta a mover
* @param column_id el Node ID de la columna en donde se moverá la tarjeta
* @param after_card_id (opcional) el Node ID de la tarjeta anterior a donde se moverá la tarjeta. Dejar en null para colocar al inicio
* @return json_mutation el JSON de la transacción generada
*/
function moveProjectCard($card_id, $column_id, $after_card_id = null) {
  $input_args  = field_args('cardId', $card_id);
  $input_args  = field_args('columnId', $column_id);
  $input_args .= ', '.field_args('afterCardId', $after_card_id);
  $json_mutation = 'mutation MoveProjectCard {
    moveProjectCard(input:{'.$input_args.'}) {
      cardEdge {
        node {
          id
        }
      }
      projectColumn {
        id
      }
    }
  }';
  return $json_mutation;
}
/**
*  Genera una mutation en formato JSON para crear un issue asociado a un repositorio
* @param repository_id el Node ID del repositorio al que está asociado el issue
* @param title el título del issue
* @param body el cuerpo del issue
* @param milestone_id el Node ID del milestone asociado al issue
* @param label_ids_array arreglo de Node IDs de etiquetas asociadas al issue
* @param assignee_ids_array arreglo de Node IDs de actores asignados al issue
* @param project_id_array arreglo de Node IDs de projectos asociados al issue
* @return json_mutation el JSON de la transacción generada
*/
function createIssue($repository_id, $title, $body=null, $milestone_id = null, $label_ids_array = array(), $assignee_ids_array = array(), $project_ids_array = array()) {
  $input_args  = field_args('repositoryId', $repository_id);
  $input_args .= ', '.field_args('title', $title);
  $input_args .= ', '.field_args('body', $body);
  $input_args .= ', '.field_args('milestoneId', $milestone_id);
  $input_args .= ', '.field_args('labelIds', $label_ids_array);
  $input_args .= ', '.field_args('assigneeIds', $assignee_ids_array);
  $input_args .= ', '.field_args('projectIds', $project_ids_array);
  $json_mutation = 'mutation CreateIssue {
    createIssue(input:{'.$input_args.'}) {
      issue {
        id
      }
    }
  }';
  return $json_mutation;
}

function testQuery() {
  $json_transaction = getUser('rhperez');
  //$json_transaction = listRepositoryIssues('digitablemx', 'base_repo', 20);
  //$json_transaction = listOrganizationRepositories('digitablemx');
  //$json_transaction = addComment('MDU6SXNzdWU0NzQ4OTIxNjM=', 'Hello world');
  //$json_transaction = listUserProjects('rhperez', 5, array('OPEN', 'CLOSED'));
  //$json_transaction = listOrganizationProjects('digitablemx', 5, array('OPEN', 'CLOSED'));
  //$json_transaction = createRepository('MDQ6VXNlcjc5NTkwODI=', 'GraphQL Repo', 'A GraphQL example repository', null, null, 'true', 'false', 'true', 'PUBLIC');
  //$json_transaction = createIssue('MDEwOlJlcG9zaXRvcnkxOTkwNjUwNTc=', 'GraphQL Issue 3', 'A new issue', null, null, null, array('MDc6UHJvamVjdDI5ODI5MzE='));
  //$json_transaction = createProject('MDEyOk9yZ2FuaXphdGlvbjUzMjMxMDgx', 'GraphQL Project', 'A new project', array('MDEwOlJlcG9zaXRvcnkxOTkwNjUwNTc='));
  //$json_transaction = getOrganizationId('digitablemx');
  return execute_graphQL_query($json_transaction);
}

?>
