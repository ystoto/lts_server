<?php

class ret_enum {
	const RET_OK = 0;
	const RET_FAIL = 1;
	const RET_NETWORK_ERROR = 2;
	const RET_DATABASE_ERROR = 3;
	const RET_DATABASE_DUPLICATED_ERROR = 4;
	const RET_PARAM_ERROR = 5;
}

class DB{
	const ip = "localhost";
	const id = "root";
	const pw = "se2016";
	const name = "LTS";
	const table = "members";
}

function connect_db(&$link) {
        $retval = ret_enum::RET_OK;
        $link = mysqli_connect(DB::ip, DB::id, DB::pw, DB::name);
        if ($dberr=mysqli_connect_errno()) {
                error_log("mysqli_connect error = $dberr", 0);
                return ret_enum::RET_DATABASE_ERROR;
        }
        if (mysqli_select_db($link, DB::name) == false) {
                error_log("mysqli_select_db error", 0);
                return ret_enum::RET_DATABASE_ERROR;
        }
	return ret_enum::RET_OK;
}

function register(&$var) {
	$retval = ret_enum::RET_OK;
	if (!isset($var->{'id'}))
		return ret_enum::RET_PARAM_ERROR;
	if (($retval = connect_db($link)) != ret_enum::RET_OK)
		return $retval;

	$query = "insert into `".DB::table."` (";
	$field = array('password', 'id', 'first_name', 'family_name', 'email',
			'phone', 'country', 'sex', 'birthday', 'user_mode',
			'degree', 'college', 'graduate', 'certification',
			'resume', 'account', 'worklist');

	// Fill column names
	for($cnt=0; $cnt<count($field); $cnt++) {
		$query .= "`".$field[$cnt]."`";
		if($cnt+1 < count($field))
			$query .= ",";
	}
	$query .=") values (";

	// Fill column values
	$query .= "'".hash("sha256", $var->{'password'})."', ";
	for($cnt=1; $cnt<count($field); $cnt++) {
		$query .= "'".$var->{$field[$cnt]}."'";
		if($cnt+1 < count($field))
			$query .= ",";
	}
	$query .=")";
	error_log("query: $query", 0);

	$result = mysqli_query($link, $query);
	if ($result == false) {
		if (mysqli_errno($link) == 1062){
			$id = $var->{'id'};
			error_log("Duplicated ID : $id", 0);
			$retval = ret_enum::RET_DATABASE_DUPLICATED_ERROR;
		}else{
			error_log("ERROR!!", 0);
			$retval = ret_enum::RET_DATABASE_ERROR;
		}
	}
	mysqli_close($link);
	return $retval;
}


function login(&$var) {
        $retval = ret_enum::RET_OK;
        if (($retval = connect_db($link)) != ret_enum::RET_OK)
                return $retval;
        
	$query = "SELECT `password` FROM `".DB::table."` WHERE `id` = '".$var->{'id'}."'";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
	$row = mysqli_fetch_array($result);
	
	$hashed_password = hash("SHA256", $var->{'password'});
	if (strcmp($row['password'], $hashed_password) != 0) {
		error_log("Wrong password!! INP : ".$hashed_password.", pw : ".$var->{'password'}, 0);
		$retval = ret_enum::RET_PARAM_ERROR;
	}
        
	mysqli_close($link);
        return $retval;
}
/*
function logout(&$var) {
	NO NEED!!	
}
*/

function getUserMode(&$var) {
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
	}

        $query = "SELECT `user_mode` FROM `".DB::table."` WHERE `id` = '".$var->{'id'}."'";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
        $row = mysqli_fetch_array($result);
        mysqli_close($link);

	// Fill requested data
	$retval['user_mode'] = $row['user_mode'];
        return json_encode($retval);
}

function addNewRequest(&$var) {
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }
	
	// Insert new-request into 'workitems' table

	// Search candidates by language

	// Foreach member in this group
	// 	Update new_job flag, then, they'll be informed soon.
}

function getInfo(&$var, $table, $column) {
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }

        $query = "SELECT `".$column."` FROM `".$table."` WHERE `id` = '".$var->{'id'}."'";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
        $row = mysqli_fetch_array($result);
        mysqli_close($link);

        $retval[$column] = $row[$column];
        return json_encode($retval);
}

function setInfo(&$var, $table, $column, $val) {
	$retval = ret_enum::RET_OK;
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }

        $query = "UPDATE `".$table."` SET `".$column."` = '".$val."' WHERE `id` ='".$var->{'id'}."'";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
        $row = mysqli_fetch_array($result);
        mysqli_close($link);

        return $retval;
}

?>
