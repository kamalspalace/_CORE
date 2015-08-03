<?php
class Config {

   public function __construct($tenant)
   {
     $this->dbn    = $tenant;
     $this->dbc    = DB::getLink();
     $this->initObject();
   }

   public function set($controller,$property,$value)
   {
   
   }

   public function __get($name)
   {
     if (array_key_exists($name, $this->data)) {
        return $this->data[$name];
     }
     return 0;
   }

   public function __set($name,$value)
   {
     $this->data[$name] = $value;
   }

   private function initObject()
   {
     // General Settings
     $sql = $this->dbc->prepare("SELECT * FROM `".$this->dbn."`.settings");
     $sql->execute();

     $res = $sql->fetchAll();

     foreach($res as $row)
     {
       $controller = $row['controller'];
       if($row['property'] == 'dbn') continue;

       if($controller != 'global')
         $this->data[$controller][$row['property']] = $row['value'];
       else
	 $this->$row['property'] = $row['value'];
     }
 
     // Service Levels
     $sql = $this->dbc->prepare("SELECT * FROM `".$this->dbn."`.settings_service_levels");
     $sql->execute();
     
     $res = $sql->fetchAll();

     $this->services = array();

     foreach($res as $row)
     {
	$this->data['services'][$row['code']] = $row['name'];
     }

     // Order Statuses
     $sql = $this->dbc->prepare("SELECT * FROM `".$this->dbn."`.settings_order_statuses");
     $sql->execute();
    
     $res = $sql->fetchAll();

     $this->stauses = array();

     foreach($res as $row)
     {
        $this->data['statuses'][$row['code']] = $row['value'];
     }


   }

   public function toArray()
   {
     $return = $this->data;
     unset($return['dbc']);
     unset($return['dbn']);
     return $return;  
   }

   private $data = array();
}
?>
