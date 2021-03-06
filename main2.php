<?php
include("command.php");
#header('Content-Type: text/html; charset=UTF-8');

function return_if_not_logged_in() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true) {
		error_log("Not logged in state!!");
                echo ret_enum::RET_NOT_LOGGED_IN;
                exit;
        }
}

function return_if_invalid_request_id(&$input_json) {
        if ($input_json->{'id'} < 1) {
		error_log("invalid request id :".$input_json->{'id'});
                echo ret_enum::RET_PARAM_ERROR;
                exit;
        }
}

function getUserMode() {
	$where_condition = (object)array('id'=>$_SESSION['logged_in_id']); // Fill id
	$ret = SELECT($where_condition, DB::member_table, "user_mode");//getUserMode($obj);
	$decoded_json = json_decode($ret);
	return $decoded_json->{'user_mode'};
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
	if ($ret == ret_enum::RET_OK) {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in_id'] = $input_json->{'id'};
    }
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
	//$input_json->{'request_date'} = date("Y-m-d H:i:s");
	$ret = INSERT($input_json, DB::work_table);
	if ($ret != ret_enum::RET_OK) {
		echo $ret;
		break;
	}

	/* Search candidates by language, and
	 Set 'new_request' flag to each candidates in member_table*/
	$where_condition = (object)array('user_mode' => MEMBER::TRANSLATOR);
	$where_condition->{'language'} = $input_json->{'target_language'};
	$result = UPDATE($where_condition, DB::member_table, "new_request", $input_json->{'id'}, UPDATE_MODE::ATTACH);

	$where_condition = (object)array('user_mode' => MEMBER::REVIEWER);
	$where_condition->{'language'} = $input_json->{'target_language'};
	$result = UPDATE($where_condition, DB::member_table, "new_request", $input_json->{'id'}, UPDATE_MODE::ATTACH);

	// Add this new request_id into my worklist
	$where_condition = (object)array('id' => $_SESSION['logged_in_id']);
	$result = UPDATE($where_condition, DB::member_table, "worklist", $input_json->{'id'}, UPDATE_MODE::ATTACH);

	echo json_encode($input_json); // To return request_id
	break;

 case 'UPDATE_PROFILES': // call by Requester
    error_log("UPDATE Profiles");
	return_if_not_logged_in();

	// Insert new request
	$input_json->{'id'} = $_SESSION['logged_in_id']; // Fill id

	$where_condition = (object)array('id' => $_SESSION['logged_in_id']);

	// Update Phone
	$result = UPDATE($where_condition, DB::member_table, "family_name", $input_json->{'family_name'}, UPDATE_MODE::OVERWRITE);
	$result = UPDATE($where_condition, DB::member_table, "first_name", $input_json->{'first_name'}, UPDATE_MODE::OVERWRITE);
	$result = UPDATE($where_condition, DB::member_table, "email", $input_json->{'email'}, UPDATE_MODE::OVERWRITE);
	$result = UPDATE($where_condition, DB::member_table, "phone", $input_json->{'phone'}, UPDATE_MODE::OVERWRITE);
	$result = UPDATE($where_condition, DB::member_table, "country", $input_json->{'country'}, UPDATE_MODE::OVERWRITE);
	$result = UPDATE($where_condition, DB::member_table, "address", $input_json->{'address'}, UPDATE_MODE::OVERWRITE);

	if (ret_enum::RET_OK != $result) {
		echo ret_enum::RET_FAIL;
		error_log("Update profile failed!!");
		exit;
	}

	echo json_encode($input_json); // To return request_id
	break;

  case 'GET_NEW_REQUEST': // call by Translator or Reviewer group
        return_if_not_logged_in();
	$where_condition = (object)array('id'=>$_SESSION['logged_in_id']);
	
	// Get new request which is applicable to me.
        $value_new_request = SELECT($where_condition, DB::member_table, "new_request");
	if (gettype($value_new_request) == "integer") {// Failed to SELECT
		echo $value_new_request;
		break;
	}
	
	// Confirm whether it's notified or not.
	$value_notified_request = SELECT($where_condition, DB::member_table, "_notified_new_request");
        error_log("new_request: ".$value_new_request.", notified: ".$value_notified_request);

	$decoded_new_request = json_decode($value_new_request);
	$decoded_notified_request = json_decode($value_notified_request);
	$arr_new_request = explode(";", $decoded_new_request->{'new_request'});
	$arr_notified_request = explode(";", $decoded_notified_request->{'_notified_new_request'});
	$diff = array_diff($arr_new_request, $arr_notified_request);
	if (count($diff) == 0) {
		error_log("cnt:".count($arr_new_request).", ".count($arr_notified_request));
		echo ret_enum::RET_FAIL;
		break;
	}

	$real_new_request = implode(";", $diff);
	$output = (object)array('new_request' => $real_new_request);

	// Set 'Notified' to avoid duplicated notification
	UPDATE($where_condition, DB::member_table, "_notified_new_request", $real_new_request, UPDATE_MODE::ATTACH);
	echo json_encode($output);
	break;

  case 'GET_REQUEST_INFO':
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
        $ret = SELECT($input_json, DB::work_table, "*");
        error_log($ret);
        echo $ret;
	break;

  case 'BID': // call by Translator or Reviewer group
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	
	// Fill candidate ID to that column
	$column = "translator_candidate_list";
	if (getUserMode() == MEMBER::REVIEWER)
		$column = "reviewer_candidate_list";
	$where_condition = (object)array('id'=>$input_json->{'id'}); // request_id
	$ret = UPDATE($where_condition, DB::work_table, $column, $_SESSION['logged_in_id'], UPDATE_MODE::ATTACH);
	error_log("ret: $ret");

	// Fill candidate ID to that column
	$column = "_applied_request";
	$where_condition = (object)array('id'=>$_SESSION['logged_in_id']); // request_id
	$ret = UPDATE($where_condition, DB::member_table, $column, $input_json->{'id'}, UPDATE_MODE::ATTACH);
	error_log("ret: $ret");
	echo $ret;
	break;

  case 'GET_LIST_OF_CANDIDATES':
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	$requested_date = json_decode(SELECT($input_json, DB::work_table, "request_date"));
	error_log("requested_date: ".$requested_date->{'request_date'});
	$remain_time = strtotime("+20 second", strtotime($requested_date->{'request_date'})) - time();
	if ($remain_time > 0) {
		error_log("Bidding period is not expired yet!, $remain_time sec left.");
		echo ret_enum::RET_FAIL;
		break;
	}
	$column = "translator_candidate_list";
	if (getUserMode() == MEMBER::TRANSLATOR)
		$column = "reviewer_candidate_list";
	$ret = SELECT($input_json, DB::work_table, $column);
	error_log("ret: $ret");
	echo $ret;
 	break;

  case 'GET_MEMBER_INFO': // call by all, input_json must have 'id' (member_id) 
	return_if_not_logged_in();
	if (!isset($input_json->{'id'})) {
		error_log("No given ID, return current users's information");
		$input_json->{'id'} = $_SESSION['logged_in_id'];
	}
        $ret = SELECT($input_json, DB::member_table, "*");
	$decoded_json = json_decode($ret);
	unset($decoded_json->{'password'});
	//unset($decoded_json->{'_notified_new_request'});
	echo json_encode($decoded_json);
	break;

  case 'EMPLOY': // call by Requester or Translator
  	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	$column = "translator_id"; // Requester employ 'translator'
	if (getUserMode() == MEMBER::TRANSLATOR)
		$column = "reviewer_id"; // Translator employ 'reviewer'

	$where_condition = (object)array('id'=>$input_json->{'id'}); // request_id
	$ret = UPDATE($where_condition, DB::work_table, $column, $input_json->{'member_id'}, UPDATE_MODE::OVERWRITE);
	error_log("UPDATE work_table ret: $ret");
	$where_condition = (object)array('id'=>$input_json->{'member_id'});
	$ret = UPDATE($where_condition, DB::member_table, "worklist", $input_json->{'id'}, UPDATE_MODE::ATTACH);
	error_log("UPDATE member_table ret: $ret");
	echo $ret;
  	break;

  case 'GET_RESULT_OF_BID': // call by Translator or Reviewer group
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	$requested_date = json_decode(SELECT($input_json, DB::work_table, "request_date"));
	error_log("requested_date: ".$requested_date->{'request_date'});
	/*  SKIP THIS TIMEOUT FOR DEMO
	$remain_time = strtotime("+20 second", strtotime($requested_date->{'request_date'})) - time();
	if ($remain_time > 0) {
		error_log("Bidding period is not expired yet!, $remain_time sec left.");
		echo ret_enum::RET_FAIL;
		break;
	}
	*/
	$column = "translator_id";
	if (getUserMode() == MEMBER::REVIEWER)
		$column = "reviewer_id";

	$ret = SELECT($input_json/*include request_id*/, DB::work_table, $column);
	error_log("ret: $ret");
	echo $ret;
	break;

  case 'GET_RESULT_OF_WORK': // call by Requester or Translator
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	$column = "reviewed_doc_path";
	if (getUserMode() == MEMBER::REQUESTER)
		$column = "final_doc_path";

	$ret = SELECT($input_json/*include request_id*/, DB::work_table, $column);
	error_log("ret: $ret");
	$decoded_json = json_decode($ret);
	if (strlen($decoded_json->{$column}) < 2) {
		echo ret_enum::RET_FAIL;
		break;
	}
	echo ret_enum::RET_OK;
	break;

  case 'UPLOAD_DATA': // get uploaded_data path
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	error_log("input1:".$input_json->{'id'});
	error_log("input2:".$input_json->{'column'});
	error_log("input3:".$input_json->{'path'});
	$where_condition = (object)array('id'=>$input_json->{'id'}); // request_id
	$ret = UPDATE($where_condition, DB::work_table, $input_json->{'column'}, $input_json->{'path'});
	error_log("ret: $ret");
	echo $ret;
	break;

  case 'DOWNLOAD_DATA':	// return download data path
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	$where_condition = (object)array('id'=>$input_json->{'id'}); // request_id
	$ret = SELECT($where_condition/*include request_id*/, DB::work_table, $input_json->{'column'});
	error_log("ret: $ret");
	echo $ret;
	break;

  case 'EVALUATION': // update member info
	return_if_not_logged_in();
	return_if_invalid_request_id($input_json);
	$column = "reviewer_score";
	if (getUserMode() == MEMBER::REQUESTER)
		$column = "translator_score";

	$where_condition = (object)array('id'=>$input_json{'id'}); // request_id
	$ret = UPDATE($where_condition, DB::work_table, $input_json->{'column'}, $input_json->{'score'}, UPDATE_MODE::OVERWRITE);
	// TODO: UPDATE TOTAL/AVERAGE SCORE in DB::member_table, and re-calculate CompetenceLevel.
	error_log("ret: $ret");
	echo $ret;
	break;

  default:
	break;
}

?>

