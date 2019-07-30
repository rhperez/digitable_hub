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

function getLastIssues() {
  $json_query = 'query {
    repository(owner:\"rhperez\", name:\"digitable_hub\") {
      issues(last:20, states:OPEN) {
        edges {
          node {
            title
            url
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

function getUserProjects($user_login, $last=20, $statesArray=array('OPEN')) {
  $states = implode(",", $statesArray);
  $json_query = 'query {
    user(login:\"'.$user_login.'\") {
      projects(last:'.$last.', states:['.$states.']) {
        edges {
          node {
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

function addIssueToProject() {

}

function testQuery() {
  $json_query = getUserProjects('rhperez', 5, array('OPEN', 'CLOSED'));
  return execute_graphQL_query($json_query);
}

?>
