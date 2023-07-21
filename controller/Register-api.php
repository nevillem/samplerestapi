<?php

require_once('../core/initialize.php');
require_once('Database.php');
require_once('../model/Response.php');


// attempt to set up connections to db connections
try {

  $writeDB = DB::connectWriteDB();

}
catch(PDOException $ex) {
  // log connection error for troubleshooting and return a json error response
  error_log("Connection Error: ".$ex, 0);
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("Database connection error");
  $response->send();
  exit;
}

// check to make sure the request is POST only - else exit with error response
if($_SERVER['REQUEST_METHOD'] !== 'POST'):
  $response = new Response();
  $response->setHttpStatusCode(405);
  $response->setSuccess(false);
  $response->addMessage("Request method not allowed");
  $response->send();
  exit;
endif;

// check request's content type header is JSON
if($_SERVER['CONTENT_TYPE'] !== 'application/json'):
  // set up response for unsuccessful request
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  $response->addMessage("Content Type header not set to JSON");
  $response->send();
  exit;
endif;

// get POST request body as the POSTed data will be JSON format
$rawPostData = file_get_contents('php://input');

if(!$jsonData = json_decode($rawPostData)):
  // set up response for unsuccessful request
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  $response->addMessage("Request body is not valid JSON");
  $response->send();
  exit;
endif;

// check if post request contains email, name and password in body as they are mandatory
if(!isset($jsonData->name) || !isset($jsonData->email) || !isset($jsonData->password)|| !isset($jsonData->contact)):
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  // add message to message array where necessary
  (!isset($jsonData->name) ? $response->addMessage("name  not supplied") : false);
  (!isset($jsonData->email) ? $response->addMessage(" email not supplied") : false);
  (!isset($jsonData->contact) ? $response->addMessage(" contact not supplied") : false);
  (!isset($jsonData->password) ? $response->addMessage("Password not supplied") : false);
  $response->send();
  exit;
endif;

// check to make sure that  name email and password are not empty and less than 255 long
if(strlen($jsonData->name) < 1 || strlen($jsonData->name) > 255 || strlen($jsonData->email) < 1
|| strlen($jsonData->contact) < 1 || strlen($jsonData->contact)  >15|| !is_numeric($jsonData->contact)  >15
|| strlen($jsonData->password) < 1 || strlen($jsonData->password) > 100):
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  (strlen($jsonData->name) < 1 ? $response->addMessage(" name cannot be blank") : false);
  (strlen($jsonData->name) > 255 ? $response->addMessage(" name cannot be greater than 255 characters") : false);
  (strlen($jsonData->email) < 1 ? $response->addMessage("Email cannot be blank") : false);
  (strlen($jsonData->email) > 255 ? $response->addMessage("Email cannot be greater than 255 characters") : false);
  (strlen($jsonData->contact) < 1 ? $response->addMessage(" contact cannot be blank") : false);
  (strlen($jsonData->contact) > 15 ? $response->addMessage(" contact cannot be greater than 15 characters") : false);
  (!is_numeric($jsonData->contact) ? $response->addMessage(" contact must be numerical") : false);
  (strlen($jsonData->password) < 1 ? $response->addMessage("Password cannot be blank") : false);
  (strlen($jsonData->password) > 100 ? $response->addMessage("Password cannot be greater than 100 characters") : false);
  $response->send();
  exit;
endif;
if (!filter_var($jsonData->email, FILTER_VALIDATE_EMAIL) || !preg_match("@[0-9]@",$jsonData->password) || !preg_match("@[A-Z]@",$jsonData->password) || !preg_match("@[^\w]@",$jsonData->password) || !preg_match("@[a-z]@",$jsonData->password)):
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  (!filter_var($jsonData->email, FILTER_VALIDATE_EMAIL) ? $response->addMessage("Invalid email address") : false);
  (!preg_match("@[0-9]@",$jsonData->password) ? $response->addMessage("Password must contain a number") : false);
  (!preg_match("@[A-Z]@",$jsonData->password) ? $response->addMessage("Password must include a uppercase character") : false);
  (!preg_match("@[^\w]@",$jsonData->password) ? $response->addMessage("Password must include a special character") : false);
  (!preg_match("@[a-z]@",$jsonData->password) ? $response->addMessage("Password must include a lowercase character "): false);
  $response->send();
  exit;
endif;

// trim any leading and trailing blank spaces from email and  only - password may contain a leading or trailing space
$_name = trim($jsonData->name);
$_email = trim($jsonData->email);
$_password = $jsonData->password;
$_contact = $jsonData->contact;

try {
  // create db query to check if the user email already exists
  $query = $writeDB->prepare('SELECT id from users where email = :email');
  $query->bindParam(':email', $_email, PDO::PARAM_STR);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  if($rowCount !== 0):
    // set up response for username already exists
    $response = new Response();
    $response->setHttpStatusCode(409);
    $response->setSuccess(false);
    $response->addMessage("user email already exists");
    $response->send();
    exit;
  endif;


  // hash the password to store in the DB as plain text password stored in DB is bad practice
  $_hashed_password = password_hash($_password, PASSWORD_DEFAULT);

  // create db query to create user
  $query = $writeDB->prepare('INSERT into users (name, email,contact, password)
  values (:name, :email,:contact, :password)');
  $query->bindParam(':name', $_name, PDO::PARAM_STR);
  $query->bindParam(':email', $_email, PDO::PARAM_STR);
  $query->bindParam(':contact', $_contact, PDO::PARAM_STR);
  $query->bindParam(':password', $_hashed_password, PDO::PARAM_STR);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  if($rowCount === 0):
    // set up response for error
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("There was an error creating the user account - please try again");
    $response->send();
    exit;
  endif;

  $response = new Response();
  $response->setHttpStatusCode(201);
  $response->setSuccess(true);
  $response->addMessage("user has been registered");
  $response->send();
  exit;
}
catch(PDOException $ex) {
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("There was an issue creating user  account - please try again $ex");
  $response->send();
  exit;
}
