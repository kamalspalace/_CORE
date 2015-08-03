<?php
class Shipment {
	
   public function __construct($shipment_id = "",$db_name)
   {
     $this->user = $db_name; 
     $this->db   = $db_name['database'];
     $this->dbc  = DB::getLink();

     if(!empty($shipment_id))
     {
        $this->id = $shipment_id;
        $this->initObject();
        $this->Read();
     }
     else
       $this->initObject();
   }  

   public function clearGoods()
   {
      	  $statement = "DELETE FROM `".$this->db."`.shipment_goods WHERE shipment_id = ?";
 	  $sql = $this->dbc->prepare($statement);
	  $sql->bindValue(1,$this->id,PDO::PARAM_INT);	
	  $sql->execute();
   }

   public function Create()
   {
	  $statement = "INSERT INTO `".$this->db."`.shipments (";
	  
          foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'total_pieces' || $key == 'total_weight' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= $key.",";
	  }
	  $statement  = rtrim($statement,",").")";
	  $statement .= " VALUES (";

	  foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'total_pieces' || $key == 'total_weight' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= "?,";
	  } 
          $statement = rtrim($statement,",").")";

 	  $sql = $this->dbc->prepare($statement);
	  
	  $p = 1;
	  foreach($this->data as $key => $value)
	  {
	     if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'total_pieces' || $key == 'total_weight' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
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
	  return "No shipment id set for retrieval.";

	foreach($this->data as $key => $value)
	{
	  if($key != 'db' && $key != 'dbc' && $key != 'id')
	    unset($this->data[$key]);
	} 

   	// Grab all columns from table related to shipment id
	$sql = $this->dbc->prepare("SELECT * FROM `".$this->db."`.shipments WHERE id = ?");
	$sql->bindValue(1,$this->id,PDO::PARAM_INT);
	$sql->execute();
	$col = $sql->fetchAll(PDO::FETCH_ASSOC);

	if(! count($col))
	  return 0;

        foreach($col[0] as $key => $value)
	  $this->data[$key] = stripslashes(stripslashes($value));

	$col = "";

	$this->data['goods'] = array();

	$sql = $this->dbc->prepare("SELECT * FROM `".$this->db."`.shipment_goods WHERE shipment_id = ? ORDER BY id ASC");
	$sql->bindValue(1,$this->id,PDO::PARAM_INT);
	$sql->execute();
	$col = $sql->fetchAll(PDO::FETCH_ASSOC);

	if(! count($col))
	  return 0;
	elseif(count($col) == 1)	
	  array_push($this->data['goods'],$col[0]);
	else
	  foreach($col as $good)
	    array_push($this->data['goods'],$good);

	$this->data['total_pieces'] = 0;
	$this->data['total_weight'] = 0;

	foreach($this->data['goods'] as $good)
	{
		$this->data['total_pieces'] = $this->data['total_pieces'] + $good['pieces'];
		$this->data['total_weight'] = floatval($this->data['total_weight']) + floatval($good['weight']);
	}

	$this->data['total_weight'] = number_format(floatval($this->data['total_weight']),2,'.','');

	if(! $this->del_appt)
		$this->del_date = "";

     return $this->id;
   }


   public function Update()
   {
	  $statement = "UPDATE `".$this->db."`.shipments SET";
	  
      foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'total_pieces' || $key == 'total_weight' || gettype($value) == 'array' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= " ".$key." = ?,";
	  }
	  $statement  = rtrim($statement,",");
      $statement .= " WHERE id = ?";

	  $sql = $this->dbc->prepare($statement);
	  
	  $p = 1;
	  foreach($this->data as $key => $value)
	  {
	     if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'total_pieces' || $key == 'total_weight' || gettype($value) == 'array' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
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


	  $sql->bindValue($p,$this->id,PDO::PARAM_INT);
 
	  $sql->execute();
	  
	  return $this->Read($this->id);
   }

   public function fetchTMS()
   {
     if(! $this->ext_id)
       return 0;

     $req = array();
     $req['bill_to_code'] = $this->bill_account;
     $req['pbnum'] 	  = $this->ext_id;

     $exTMS  = new IntegrationHandler($this->user);
     $data   = $exTMS->process_request('tracking_details_request',$req);

     return $data;

	/*
     $return = array();
     $return['ship_name']	= $data->
     $return['ship_street1']	=
     $return['ship_street2']	=
     $return['ship_city']	=
     $return['cons_name']	=
     $return['cons_street1']	=
     $return['cons_street2']	=
     $return['cons_city']	=
	*/
     
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
     if($name == 'pup_area_other')
       $this->data['pup_area'] = $value;
     elseif($name == 'del_area_other')
       $this->data['del_area'] = $value; 
     else
     {
       if($value == 'on') $value = 1;
       $this->data[$name] = $value;
     }
   }


   private function initObject()
   {
        // Grab list of columns (schema) from shipments table
        $sql = $this->dbc->prepare("SHOW COLUMNS FROM `".$this->db."`.shipments");
        $sql->execute();

        $tbl = $sql->fetchAll();

        // Initialize object properties, based on columns in table
        foreach($tbl as $col)
        {
          if($col['Field'] == 'id' || $col['Field'] == 'timestamp') continue;
          $this->validCols[$col['Field']] = "";
        }
     
   }

   public function toArray()
   {
     $return = $this->data;
     unset($return['dbc']);
     unset($return['db']);
     return $return;
   }
   
   public function Delete()
   {
      $this->clearGoods(); 
      $statement = "DELETE FROM `".$this->db."`.shipments WHERE id = ?";
      $sql = $this->dbc->prepare($statement);
      $sql->bindValue(1,$this->id,PDO::PARAM_INT);	
      $sql->execute();
   }   

   private $data = array();
   private $validCols = array();
}
?>
