<?php
include("command.php");
#header('Content-Type: text/html; charset=UTF-8');

function return_if_not_logged_in() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true) {
		error_log("Not logged in state!!");
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


$input_json = json_decode($json);
$command = $input_json->{'command'};
unset($input_json->{'command'});
error_log("command=$command", 0);

switch ($command) {

  case 'REGISTER':
	$ret = register($input_json);
	echo $ret;
	break;

 case 'CHECKID':
	$ret = checkid($input_json);
	echo $ret;
	break;

  case 'LOGIN':
        $ret = login($input_json);
	if ($ret == ret_enum::RET_OK) {
		$_SESSION['logged_in'] = true;
		$_SESSION['logged_in_id'] = $input_json->{'id'};
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
	$input_json->{'id'} = $_SESSION['logged_in_id']; // Fill id
	$ret = SELECT($input_json, DB::member_table, "user_mode");//getUserMode($obj);
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
	$input_json->{'id'} = $size + 1; // Expect unique request ID
	$input_json->{'requester_id'} = $_SESSION['logged_in_id']; // Fill id
	$ret = INSERT($input_json, DB::work_table);
	if ($ret != ret_enum::RET_OK) {
		echo $ret;
		break;
	}

	/* Search candidates by language, and 
	 Set 'new_request' flag to each candidates in member_table*/
	$filter = (object)array('user_mode' => MEMBER::TRANSLATOR);
	$filter->{'language'} = $input_json->{'target_language'};
	$result = UPDATE($filter, DB::member_table, "new_request", $input_json->{'id'}, UPDATE_MODE::OVERWRITE);
	$result = UPDATE($filter, DB::member_table, "_notified_new_request", 0, UPDATE_MODE::OVERWRITE); // Set 'Not notified yet'
	echo json_encode($input_json); // To return request_id
	break;

  case 'GET_NEW_TRANSLATION_REQUEST': // call by Translator or Reviewer group
        return_if_not_logged_in();
	$filter = (object)array('id'=>$_SESSION['logged_in_id']);
	
	// Get new request which is applicable to me.
        $value_new_request = SELECT($filter, DB::member_table, "new_request"); 
	if (gettype($value_new_request) == "integer") {// Failed to SELECT
		echo $value_new_request;
		break;
	}
        error_log("ret: ".$value_new_request);
	$decoded_json = json_decode($value_new_request);
	if ($decoded_json->{'new_request'} == '0') { // There is no new request
		echo ret_enum::RET_FAIL;
		break;
	}
	
	// Confirm whether it's notified or not.
        $value_notified_new_request = SELECT($filter, DB::member_table, "_notified_new_request"); 
	$decoded_json2 = json_decode($value_notified_new_request);
	if ($decoded_json2->{'_notified_new_request'} == '1') { // If notified already
		echo ret_enum::RET_FAIL;
		break;
	}

	// Set 'Notified' to avoid duplicated notification
	UPDATE($filter, DB::member_table, "_notified_new_request", 1); 
        echo $value_new_request;
        break;

  case 'BID': // call by Translator or Reviewer group
	return_if_not_logged_in();
	if ($input_json->{'id'} < 1) {
		echo ret_enum::RET_FAIL;
		break;
	}
	$filter = (object)array('id'=>$input_json->{'id'}); // request_id
	$ret = UPDATE($filter, DB::work_table, "translator_candidate_list", $_SESSION['logged_in_id'], UPDATE_MODE::ATTACH);
	error_log("ret: $ret");
	echo $ret;
	break;

  case 'GET_BID_RESULT': // call by Translator or Reviewer group
	return_if_not_logged_in();

	break;

  default:
	break;
}

?>

