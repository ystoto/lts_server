<?php

class ret_enum {
	const RET_NETWORK_ERROR = 2;
	const RET_DATABASE_ERROR = 3;
	const RET_DATABASE_DUPLICATED_ERROR = 4;
	const RET_OK = 0;
}

function register(&$var) {
	$retval = ret_enum::RET_OK;
	$link = mysqli_connect("localhost", "root", "se2016", "LTS");
	if ($dberr=mysqli_connect_errno()) {
		error_log("mysqli_connect error = $dberr", 0);
		return ret_enum::RET_DATABASE_ERROR;	
	}
	if (mysqli_select_db($link, "LTS") == false) {
		error_log("mysqli_select_db error", 0);
		return ret_enum::RET_DATABASE_ERROR;	
	}
	$query = "insert into `members` (";
	$field = array('id', 'password', 'first_name', 'family_name', 'email',
			'phone', 'country', 'sex', 'birthday', 'user_mode',
			'degree', 'college', 'graduate', 'certification',
			'resume', 'account', 'worklist');
	for($cnt=0; $cnt<count($field); $cnt++) {
		$query .= "`".$field[$cnt]."`";
		if($cnt+1 < count($field))
			$query .= ",";
	}
	$query .=") values (";
	for($cnt=0; $cnt<count($field); $cnt++) {
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
?>
