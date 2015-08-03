<?php
class Reporting {

   public function __construct($db_name)
   {
     $this->db   = $db_name;
     $this->dbc  = DB::getLink();
   }

   // DATE FORMAT YYYY-MM-DD, will select 00:00 to 23:59
   public function salesByDate($date)
   {
     $statement = "SELECT COUNT(*) FROM `".$this->db."`.shipments WHERE timestamp > ? AND timestamp < ? AND sent = 1";
     $sql = $this->dbc->prepare($statement);
     $sql->bindValue(1,$date." 00:00:00",PDO::PARAM_STR);
     $sql->bindValue(2,$date." 23:59:59",PDO::PARAM_STR);
     $sql->execute();
     $res = $sql->fetchAll();
     return $res[0][0];
   }

}
?>
