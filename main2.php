<?php
include("command.php");
#header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!isset($_SESSION['new_ss'])) {
  $ss_id = session_id();
  error_log(print_r("First connection,\nSessionID : $ss_id", TRUE), 0);
  $_SESSION['new_ss'] = true;  
  exit;
}

#print_r($_POST);
$json = file_get_contents('php://input');
$obj = json_decode($json);
$command = $obj->{"command"};
error_log("command=$command", 0);


switch ($command) {
  case 'REGISTER':
	$ret = register($obj);
	echo $ret;
	break;
  case 'LOGIN':
	break;
  default:
	break;
}

#print_r($json);
?>

