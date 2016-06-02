<?php

class ret_enum {
	const RET_OK = 0;
	const RET_FAIL = -1;
	const RET_NETWORK_ERROR = -2;
	const RET_DATABASE_ERROR = -3;
	const RET_DATABASE_DUPLICATED_ERROR = -4;
	const RET_PARAM_ERROR = -5;
}

class DB{
	const ip = "localhost";
	const id = "root";
	const pw = "se2016";
	const name = "LTS";
	const member_table = "members";
	const work_table = "workitems";
}

class MEMBER{
	const REQUESTER = 0;
	const TRANSLATOR = 1;
	const REVIEWER = 2;
}

class LANG{
	const KOREAN = 0;
	const ENGLISH = 1;
	const CHINESE = 2;
	const JAPANESE = 3;
}

function connect_db(/*OUTPUT*/ &$link) {
        $ret = ret_enum::RET_OK;
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
	$ret = ret_enum::RET_OK;
	if (!isset($var->{'id'}))
		return ret_enum::RET_PARAM_ERROR;
	if (($ret = connect_db($link)) != ret_enum::RET_OK)
		return $ret;

	$query = "insert into `".DB::member_table."` (";
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
			$ret = ret_enum::RET_DATABASE_DUPLICATED_ERROR;
		}else{
			error_log("ERROR!!", 0);
			$ret = ret_enum::RET_DATABASE_ERROR;
		}
	}
	mysqli_close($link);
	return $ret;
}


function login(&$var) {
        $ret = ret_enum::RET_OK;
        if (($ret = connect_db($link)) != ret_enum::RET_OK)
                return $ret;
        
	$query = "SELECT `password` FROM `".DB::member_table."` WHERE `id` = '".$var->{'id'}."'";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
	$row = mysqli_fetch_array($result);
	
	$hashed_password = hash("SHA256", $var->{'password'});
	if (strcmp($row['password'], $hashed_password) != 0) {
		error_log("Wrong password!! INP : ".$hashed_password.", pw : ".$var->{'password'}, 0);
		$ret = ret_enum::RET_PARAM_ERROR;
	}
        
	mysqli_close($link);
        return $ret;
}

function INSERT(&$var, $table) {
	$ret = ret_enum::RET_OK;
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }

	//// Generate mysql insert sentence
        $query = "INSERT INTO `".$table."` (";

	// Count elements
	$cnt = 0;
	foreach($var as $key) $cnt++;

	// Fill Columns
	$idx = 1;
	foreach($var as $key => $value) {
                $query .= "`".$key."`";
                if(($idx++) < $cnt)
                        $query .= ",";
	}
        $query .=") VALUES (";

	// Fill Values
	$idx = 1;
	foreach($var as $key => $value) {
                $query .= "'".$value."'";
                if(($idx++) < $cnt)
                        $query .= ",";
	}
        $query .=")";
        error_log("count: ".$cnt.", query: $query", 0);

        $result = mysqli_query($link, $query);
        if ($result == false) {
                if (mysqli_errno($link) == 1062){
                        $id = $var->{'id'};
                        error_log("Duplicated ID : $id", 0);
                        $ret = ret_enum::RET_DATABASE_DUPLICATED_ERROR;
                }else{
                        error_log("ERROR!!", 0);
                        $ret = ret_enum::RET_DATABASE_ERROR;
                }
        }
        mysqli_close($link);
        return $ret;
}


function SELECT(&$filter, $table, $column) {
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }

        $query = "SELECT `".$column."` FROM `".$table."` WHERE `id` = '".$filter->{'id'}."'";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
        $row = mysqli_fetch_array($result);
        mysqli_close($link);

        $ret[$column] = $row[$column];
        return json_encode($ret);
}

function UPDATE(&$filter, $table, $column, $value) {
	$ret = ret_enum::RET_OK;
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }

	// TODO: Trace all input $filter
        $query = "UPDATE `".$table."` SET `".$column."` = '".$value."' WHERE ";

	// Count elements
	$cnt = 0;
	foreach($filter as $key) $cnt++;

	// Fill 'WHERE' conditions
	$idx = 1;
	foreach($filter as $key => $value) {
                $query .= "`".$key."` = '".$value."'";
		if (($idx++) < $cnt)
			$query .= " and ";
	}
        error_log("count: ".$cnt.", query: $query", 0);

        $result = mysqli_query($link, $query);
	if ($result == false) {
		error_log("ERROR!!", 0);
		$ret = ret_enum::RET_DATABASE_ERROR;
	}
        mysqli_close($link);

        return $ret;
}

function SIZE($table, /*OUTPUT*/&$size) {
        if (($result = connect_db($link)) != ret_enum::RET_OK) {
                return $result;
        }

        $query = "SELECT * FROM `".$table."`";
        error_log("query: $query", 0);

        $result = mysqli_query($link, $query);
        $size = mysqli_num_rows($result);
        mysqli_close($link);

        return ret_enum::RET_OK;
}


?>
