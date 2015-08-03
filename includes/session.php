<?php
class Session {

   var $dbc;
   var $dbn;
   var $uid;
   var $id;
   public $data = array();

   function __construct($user)
   {
    if(! $user)
  	 return false;

     $this->id  = $_REQUEST['sid']; 
     $this->dbc = DB::getLink();
     $this->dbn = $user['database'];
     $this->uid = $user['uid'];
     $this->data['name'] = $user['name'];
     $this->data['username'] = $user['username'];
     $this->data['email'] = $user['email'];
     $this->data['phone'] = $user['phone'];
     $this->data['ext']   = $user['ext'];
   }

   function Init()
   {
     $temp = $this->data;

     $sql = $this->dbc->prepare("DELETE FROM `".$this->dbn."`.sessions WHERE session_id = ? OR id = ?");
     $sql->bindValue(1,$this->id,PDO::PARAM_STR);
     $sql->bindValue(2,$this->uid,PDO::PARAM_INT);
     $sql->execute();

     $sql = $this->dbc->prepare("DELETE FROM `".$this->dbn."`.session_data WHERE session_id = ?");
     $sql->bindValue(1,$this->id,PDO::PARAM_STR);
     $sql->execute();
 
     $sql = $this->dbc->prepare("INSERT INTO `".$this->dbn."`.sessions VALUES(?,?,?)");
     $sql->bindValue(1,$this->uid,PDO::PARAM_INT);
     $sql->bindValue(2,$this->id,PDO::PARAM_STR);
     $sql->bindValue(3,time(),PDO::PARAM_INT);
     $sql->execute();

     $sql = $this->dbc->prepare("INSERT INTO `".$this->dbn."`.session_data VALUES (?,?)");
     $sql->bindValue(1,$this->id,PDO::PARAM_STR);
     $sql->bindValue(2,json_encode($temp),PDO::PARAM_STR);
     $sql->execute();

     $this->timestamp = time();

     return true;
   }

   function initByID($session_id)
   {
     $sql = $this->dbc->prepare("SELECT session_id,timestamp FROM `".$this->dbn."`.sessions WHERE session_id = ?");
     $sql->bindValue(1,$session_id,PDO::PARAM_INT);
     $sql->execute();

     if($sql->rowCount())
       $col = $sql->fetchAll();
     else
       return false;

     $this->data['id'] = $col[0]['session_id'];
     $this->data['timestamp'] = $col[0]['timestamp'];
   

     $sql = $this->dbc->prepare("SELECT data FROM `".$this->dbn."`.session_data WHERE session_id = ?");
     $sql->bindValue(1,$this->id,PDO::PARAM_STR);
     $sql->execute();

     if($sql->rowCount())
       $data = json_decode($sql->fetchColumn());
     else // no prior data has ever existed for this session
     {
       return false;
     }

     foreach($data as $key => $val)
       $this->data[$key] = $val;

     return true;
   }
 
   public function Destroy()
   {
     $sql = $this->dbc->prepare("DELETE FROM `".$this->dbn."`.sessions WHERE session_id = ?");
     $sql->bindValue(1,$this->id,PDO::PARAM_STR);
     $sql->execute();

     $sql = $this->dbc->prepare("DELETE FROM `".$this->dbn."`.session_data WHERE session_id = ?");
     $sql->bindValue(1,$this->id,PDO::PARAM_STR);
     $sql->execute();

     return true;
   }


   public function toArray()
   { 
     $return = $this->data;
     return $return;
   }

   public function keepAlive()
   {
     $sql = $this->dbc->prepare("UPDATE `".$this->dbn."`.sessions SET timestamp = ? WHERE session_id = ?");
     $sql->bindParam(1,time(),PDO::PARAM_STR);
     $sql->bindParam(2,$this->id,PDO::PARAM_STR);
     $sql->execute();
     return "OK";
   }

   public function getTTL()
   {
     $time_now = time();
     $time_ses = $this->timestamp;
     $time_dif = $time_now - $time_ses;

     if($time_dif < 1800)
	return "OK";
     else
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
     $this->data[$name] = $value;
     
     $json = $this->data;
     $json = json_encode($json);
     $sql = $this->dbc->prepare("UPDATE `".$this->dbn."`.session_data SET data = ? WHERE session_id = ?");
     $sql->bindParam(1,$json,PDO::PARAM_STR);
     $sql->bindParam(2,$this->id,PDO::PARAM_STR);
     $sql->execute();
   }

}
?>
