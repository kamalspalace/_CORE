<?php
$host = 'localhost';
$db_name = 'shipon_bol-master';
$db_user = 'shipon_bol';
$db_pass = 'b0l4321!';

$db = new PDO('mysql:host=localhost;dbname='.$db_name.';charset=utf8', $db_user, $db_pass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

function query($string, $values=null)
{
	$db = $GLOBALS['db'];

	$query = $db->prepare($string);

	if($values != null)
	{
		$i = 1;
		foreach($values as $var)
		{
			switch(gettype($var))
			{
				case 'integer': $type = PDO::PARAM_INT;
					break;
				case 'boolean': $type = PDO::PARAM_BOOL;
					break;
				case 'NULL':	$type = PDO::PARAM_NULL;
					break;
				
				default:	$type = PDO::PARAM_STR;
			}

			$query->bindValue($i, $var, $type);
			$i++;
		}
	}

	$query->execute();
	
	$error_code = $query->errorCode();
	if($error_code && $error_code != '00000')
	{
		$info = $query->errorInfo();
		print($query->errorCode);
	}

	return $query;
}

function query_r($string, $values=null)
{
        $db = $GLOBALS['db'];

	$query = $db->prepare($string);

	if($values != null)
	{
		$i = 1;
		foreach($values as $var)
		{
			switch(gettype($var))
			{
				case 'integer': $type = PDO::PARAM_INT;
					break;
				case 'boolean': $type = PDO::PARAM_BOOL;
					break;
				case 'NULL':	$type = PDO::PARAM_NULL;
					break;
				
				default:	$type = PDO::PARAM_STR;
			}

			$query->bindValue($i, $var, $type);
			$i++;
		}
	}

	$query->execute();

	$error_code = $query->errorCode();
	if($error_code && $error_code != '00000')
	{
		$info = $query->errorInfo();
		print($info[2]."</br>");
	}

	return $db->lastInsertId();
}

function fetch($query)
{
	return $query->fetch(PDO::FETCH_ASSOC);
}

function fetchAll($query)
{
	return $query->fetchAll();
}

function num_rows($query)
{
	return $query->rowCount();
}

?>
