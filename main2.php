<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!isset($_SESSION['new_ss'])) {
  $ss_id = session_id();
  error_log(print_r("First connection:$ss_id", TRUE), 0);
  $_SESSION['new_ss'] = true;  
  exit;
}

#print_r($_POST);
$command = $_POST['command'];
$num_of_POST = count($_POST);
error_log("command=$command, numof_POST=$num_of_POST", 0);
$json_string = $_POST['message'];
$json = json_decode($json_string);
#print_r($json);
echo "command=$command";
?>

