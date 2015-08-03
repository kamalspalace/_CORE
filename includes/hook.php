<?php
class Hook {
	public static function Run($type,$method,$user,&$arg1 = NULL,&$arg2 = NULL,&$arg3 = NULL,&$arg4 = NULL,&$arg5 = NULL)
	{
		$func = "hook_".$method."_".$type;
		$file = $user."/hooks/".$method."_".$type.".php";
		if(! file_exists($file))
		  return; // No hook exists for this method
		else
		  include($file);

		$args = array();
		for($a = 1; $a <= 6; $a++)	
		{
		  $arg = 'arg' . $a;
		  if(isset($$arg) && $$arg != NULL)
		    $args[$a - 1] = &$$arg;
		}

		return call_user_func_array($func,$args);
	}

	public static function Log($type,$method,$user,$args)
	{
		$func = "hook_".$method."_".$type;
		$file = $user."/hooks/".$method."_".$type.".php";
		if(! file_exists($file))
		  return; // No hook exists for this method
		else
		  include($file);

       $data[0] = $args; 

        return call_user_func_array($func,$data);
	}
    
}
?>
