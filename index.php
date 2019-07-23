<?php
  include_once "scripts/api_github_requests.php";
  include_once "controllers/api_github_ctrlr.php";
  //echo json_encode(requestUser("rhperez"), JSON_PRETTY_PRINT);
  //json_encode(requestRepos("rhperez"), JSON_PRETTY_PRINT);
  //echo json_encode(requestCommits("rhperez", "DevFolio", "f13bd213ab7b544cb25b2055ce28dff5f3649a92"), JSON_PRETTY_PRINT);
  //echo json_encode(requestCollaborators("rhperez", "DevFolio"), JSON_PRETTY_PRINT);
  //echo json_encode(createRepoProject("rhperez", "cripto", "Proyecto cripto", "Proyecto de criptomonedas", array("To Do", "In Process", "In Review", "Done")), JSON_PRETTY_PRINT);
  //echo json_encode(createUserProject("Cripto", "Proyecto de criptomonedas", array("To Do", "In Process", "In Review", "Done")), JSON_PRETTY_PRINT);
  //echo json_encode(requestProjects("rhperez", "closed"));
  echo json_encode(requestOrganization("digitablemx"));
  // json_encode(requestRateLimit(), JSON_PRETTY_PRINT);
?>
