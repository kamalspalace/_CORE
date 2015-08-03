<?php

class Address {
    
   public function __construct($db_name,$address_id="")
   {
        $this->db  = $db_name;
        $this->dbc = DB::getLink();

        if(!empty($address_id))
        {
           $this->id = $address_id;
           $this->initObject();
           $this->Read();
        }
        else {
          $this->initObject();   
        }
   }  
    
   private function initObject()
   {
        // Grab list of columns (schema) from shipments table
        $sql = $this->dbc->prepare("SHOW COLUMNS FROM `".$this->db."`.address_book");
        $sql->execute();

        $tbl = $sql->fetchAll();

        // Initialize object properties, based on columns in table
        foreach($tbl as $col)
        {
          if($col['Field'] == 'id') continue;
          $this->validCols[$col['Field']] = "";
        }
     
   }
   
   public function clearAddress()
   {
      $statement = "DELETE FROM `".$this->db."`.address_book WHERE id = ?";
      $sql = $this->dbc->prepare($statement);
      $sql->bindValue(1,$this->id,PDO::PARAM_INT);	
      $sql->execute();
   }
   
   public function Create()
   {
	  $statement = "INSERT INTO `".$this->db."`.address_book (";
	  
          foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= $key.",";
	  }
	  $statement  = rtrim($statement,",").")";
	  $statement .= " VALUES (";

	  foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= "?,";
	  } 
          $statement = rtrim($statement,",").")";

 	  $sql = $this->dbc->prepare($statement);
	  
	  $p = 1;
	  foreach($this->data as $key => $value)
	  {
	     if($key == 'db' || $key == 'dbc' || $key == 'id' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	     switch(gettype($value))
	     {
		case "string":
		  $sql->bindValue($p,$value,PDO::PARAM_STR);
		break;

		case "integer":
		  $sql->bindValue($p,$value,PDO::PARAM_INT);
		break;
	
		default:
		  $sql->bindValue($p,$value,PDO::PARAM_STR);
		break;
             }
	    $p++;
	  }
 
	  $sql->execute();
	  $this->id = $this->dbc->lastInsertId();
	
	  if($this->id)
	    return $this->id;
	  
	  return 0;
   }
   
   public function Read()
   {
	if(! $this->id)
	  return "No address id set for retrieval.";

	foreach($this->data as $key => $value)
	{
	  if($key != 'db' && $key != 'dbc' && $key != 'id')
	    unset($this->data[$key]);
	} 

   	// Grab all columns from table related to shipment id
	$sql = $this->dbc->prepare("SELECT * FROM `".$this->db."`.address_book WHERE " . $key . " = ?");
	$sql->bindValue(1,$this->id,PDO::PARAM_INT);
	$sql->execute();
	$col = $sql->fetchAll(PDO::FETCH_ASSOC);

	if(! count($col))
	  return 0;

        foreach($col[0] as $key => $value)
	  $this->data[$key] = $value;

        return $this->id;
   }
   
   public function Update()
   {
      $statement = "UPDATE `".$this->db."`.address_book SET";
	  
      foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || gettype($value) == 'array' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= " ".$key." = ?,";
	  }
	  $statement  = rtrim($statement,",");
          $statement .= " WHERE id = ?";

	  $sql = $this->dbc->prepare($statement);
	  
	  $p = 1;
	  foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || gettype($value) == 'array' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    switch(gettype($value))
	    {
			case "string":
			  $sql->bindValue($p,$value,PDO::PARAM_STR);
			  $p++;
			break;

			case "integer":
			  $sql->bindValue($p,$value,PDO::PARAM_INT);
			  $p++;
			break;
		
			default:
			  $sql->bindValue($p,$value,PDO::PARAM_STR);
			  $p++;
			break;
        }
	  }

	  $sql->bindValue($p,$this->id,PDO::PARAM_INT);
 
	  $sql->execute();
	  
	  return $this->Read($this->id);
   }
   
   public function Find()
   {
        if ($this->street1 != '' && $this->city != '') {        
            $statement = "SELECT * FROM `".$this->db."`.address_book WHERE street1 = ? AND city = ? AND group_id = ?";
	    $sql = $this->dbc->prepare($statement);

	    $sql->bindValue(1,$this->street1,PDO::PARAM_STR);
	    $sql->bindValue(2,$this->city,PDO::PARAM_STR);
	    $sql->bindValue(3,$this->group_id,PDO::PARAM_INT);
        } else {
            $statement = "SELECT id, name, city FROM `".$this->db."`.address_book WHERE group_id = ? AND name LIKE ? LIMIT 10";
	
	   $sql = $this->dbc->prepare($statement);

	   $sql->bindValue(1,$this->group_id,PDO::PARAM_INT);
	   $sql->bindValue(2,"%".$this->name."%",PDO::PARAM_STR);
        }   
        

        $sql->execute();
	$col = $sql->fetchAll(PDO::FETCH_ASSOC);

        if(! count($col))
	  return 0;


        return $col;
   }
   
   
   public function toArray()
   {
     $return = $this->data;
     unset($return['dbc']);
     unset($return['db']);
     return $return;
   }
   
   public function __get($name)
   {
     if (array_key_exists($name, $this->data)) {
        return $this->data[$name];
     }
     return 0;
   }

   public function __set($name, $value)
   {
       $this->data[$name] = $value;
   }
   

   private $data = array();
   private $validCols = array();
   
}
?>
