<?php
include("command.php");
#header('Content-Type: text/html; charset=UTF-8');

function return_if_not_logged_in() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true) {
                echo ret_enum::RET_FAIL;
                return;
        }
}



session_start();
if (!isset($_SESSION['new_ss'])) {
  $ss_id = session_id();
  error_log(print_r("First connection,\nSessionID : $ss_id", TRUE), 0);
  $_SESSION['new_ss'] = true;  
}

$json = file_get_contents('php://input');
if (!isset($json)) {
  // It looks just first connection
  echo ret_enum::RET_OK;
  exit;
}


$obj = json_decode($json);
$command = $obj->{"command"};
unset($obj->{'command'});
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
	return_if_not_logged_in();
	$obj->{'id'} = $_SESSION['logged_in_id']; // Fill id
	$ret = SELECT($obj, DB::member_table, "user_mode");//getUserMode($obj);
	error_log($ret);
	echo $ret;
	break;

  case 'ADD_NEW_REQUEST': // call by Requester
	return_if_not_logged_in();
	if (ret_enum::RET_OK != SIZE(DB::work_table, $size)) {
		echo ret_enum::RET_FAIL;
		break;
	}

	// Insert new request
	$obj->{'id'} = $size + 1; // Expect unique request ID
	$obj->{'requester_id'} = $_SESSION['logged_in_id']; // Fill id
	$ret = INSERT($obj, DB::work_table);
	if ($ret != ret_enum::RET_OK) {
		echo $ret;
		break;
	}

	/* Search candidates by language, and 
	 Set 'new_request' flag to each candidates in member_table*/
	$filter = (object)array('user_mode' => MEMBER::TRANSLATOR);
	$filter->{'language'} = $obj->{'target_language'};
	$result = UPDATE($filter, DB::member_table, "new_request", $obj->{'id'});
	echo json_encode($obj); // To return request_id
	break;

  case 'GET_NEW_REQUEST': // call by Translator or Reviewer group
        return_if_not_logged_in();
	//$filter->{'id'} = $_SESSION['logged_in_id']; // Fill id
	$filter = array('id'=>$_SESSION['logged_in_id']);
        $ret = SELECT($filter, DB::member_table, "new_request"); // Get new request which is applicable to me.
	UPDATE($filter, DB::member_table, "new_request", 0); // reset it to avoid duplicated notification
        error_log($ret); // RETURN REQUEST_ID
        echo $ret;
        break;

  case 'BID': // call by Translator or Reviewer group
	return_if_not_logged_in();
	
	break;

  case 'GET_BID_RESULT': // call by Translator or Reviewer group
	return_if_not_logged_in();

	break;

  default:
	break;
}

?>

