<?php
class ShipmentGood {
	
   public function __construct(&$objShipment,$good_id = "")
   {
     $this->db  = $objShipment->db;
     $this->dbc = $objShipment->dbc;
     $this->shipment = $objShipment;
     $this->shipment_id = intval($objShipment->id);

     if($good_id)
     {
	$this->id = $good_id;
        $this->Read();
     }
     else
       $this->initObject();
   }  

   private function initObject()
   {
        // Grab list of columns (schema) from shipments table
        $sql = $this->dbc->prepare("SHOW COLUMNS FROM `".$this->db."`.shipment_goods");
        $sql->execute();

        $tbl = $sql->fetchAll();

        // Initialize object properties, based on columns in table
        foreach($tbl as $col)
        {
          if($col['Field'] == 'id' || $col['Field'] == 'timestamp') continue;
          $this->validCols[$col['Field']] = "";
        }
     
   }

   public function Read()
   {
	if(! $this->id)
	  return "No shipment good id set for retrieval.";
 
   	// Grab all columns from table related to shipment id
	$sql = $this->dbc->prepare("SELECT * FROM `".$this->db."`.shipments WHERE id = ?");
	$sql->bindValue(1,$this->id,PDO::PARAM_INT);
	$col = $sql->fetchAll();

	if(! count($col))
	  return 0;

        foreach($col as $key => $value)
	  $this->data[$key] = $value;

     return 1;
   }

   public function Create()
   {
	  $statement = "INSERT INTO `".$this->db."`.shipment_goods (";
	  
          foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= $key.",";
	  }
	  $statement  = rtrim($statement,",").")";
	  $statement .= " VALUES (";

	  foreach($this->data as $key => $value)
	  {
	    if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'shipment' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
	    $statement .= "?,";
	  } 
          $statement = rtrim($statement,",").")";

 	  $sql = $this->dbc->prepare($statement);
	  
	  $p = 1;
	  foreach($this->data as $key => $value)
	  {
	     if($key == 'db' || $key == 'dbc' || $key == 'id' || $key == 'shipment' || $value == '' || ! array_key_exists($key,$this->validCols)) continue;
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
	  {
	    $this->shipment->Read($this->shipment_id);
	    return $this->id;
	  }
		  
	  return 0;
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

   private $data = array();
   private $validCols = array();
}
?>
