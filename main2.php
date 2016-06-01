<?php
include("command.php");
#header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!isset($_SESSION['new_ss'])) {
  $ss_id = session_id();
  error_log(print_r("First connection,\nSessionID : $ss_id", TRUE), 0);
  $_SESSION['new_ss'] = true;  
}

#print_r($_POST);
$json = file_get_contents('php://input');
if (!isset($json)) {
  // It looks just first connection
  echo ret_enum::RET_OK;
  exit;
}


$obj = json_decode($json);
$command = $obj->{"command"};
error_log("command=$command", 0);

switch ($command) {

  case 'REGISTER':
	$ret = register($obj);
	echo $ret;
	break;

  case 'LOGIN':
        $ret = login($obj);
	if ($ret == ret_enum::RET_OK) {
		$_SESSION['logged_in'] = true;
		$_SESSION['logged_in_id'] = $obj->{'id'};
	}
        echo $ret;
	break;

  case 'LOGOUT':
	if ($_SESSION['logged_in'] != true
		|| !isset($_SESSION['logged_in_id'])) {
		echo ret_enum::RET_FAIL;
		break;
	}
	unset($_SESSION['logged_in']);
	unset($_SESSION['logged_in_id']);
	$ret = ret_enum::RET_OK;
	echo $ret;
	break;

  case 'GET_USER_MODE':
        if ($_SESSION['logged_in'] != true) {
                echo ret_enum::RET_FAIL;
                break;
        }
	$obj->{'id'} = $_SESSION['logged_in_id']; // Fill id
	$ret = getInfo($obj, "members", "user_mode");//getUserMode($obj);
	error_log($ret);
	echo $ret;
	break;

  case 'ADD_NEW_REQUEST': // call by Requester
	if (!isset($_SESSION['logged_in']) || $_SESSOIN['logged_in'] != true) {
		echo ret_enum::RET_FAIL;
		break;
	}
	$ret = addNewRequest($obj);
	echo $ret;
	break;

  case 'GET_NEW_REQUEST': // call by Translator or Reviewer group
        if ($_SESSION['logged_in'] != true) {
                echo ret_enum::RET_FAIL;
                break;
        }
        $obj->{'id'} = $_SESSION['logged_in_id']; // Fill id
        $ret = getInfo($obj, "members", "new_request"); // Get new request which is applicable to me.
	setInfo($obj, "members", "new_request", 0); // reset it to avoid duplicated notification
        error_log($ret);
        echo $ret;
        break;

  case 'BID': // call by Translator or Reviewer group
	break;

  case 'GET_BID_RESULT': // call by Translator or Reviewer group
	break;

  default:
	break;
}

#print_r($json);
?>

