<?php
include("models/reporting.php");
class Cyfe extends Reporting
{

   public function __construct($tenant)
   {
      parent::__construct('shipon_bol-'.$tenant);
      $this->db = 'shipon_bol-'.$tenant;
   }

   public function salesThisWeek()
   {
	$dayOfWeek = date('w');
	$statsData = array();
	
	for($d = 1; $d < ($dayOfWeek + 1); $d++)
        {
	  $date = date("Y-m-d",strtotime($d." day ago midnight"));
          $statsData[($d - 1)] = parent::salesByDate($date);
	}

	$statsData = array_reverse($statsData);

	for($d; $d < 8; $d++)
	  $statsData[$d] = 0;

	

   }

}
?>
