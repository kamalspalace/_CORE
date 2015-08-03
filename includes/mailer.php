<?php
require('phpmailer/class.phpmailer.php');
class Mailer {

   public function __construct($tenant)
   {
     $this->mail     = new PHPMailer;
     $this->smarty   = new SmartyTpl("_pro-".$tenant);
     $this->settings = Settings::get('shipon_bol-'.$tenant);
     $this->smarty->assign('settings',$this->settings->toArray());
     $this->tenant   = $tenant;
     $this->initObject();
   }

   public function addBCC($email) {
     $this->mail->AddBCC($email);
   }

   public function addRcpt($name,$email)
   {
     $this->mail->AddAddress($email,$name);
   }

   public function addReplyTo($name,$email)
   {
      $this->mail->AddReplyTo($email,$name);
   }

   public function addStringEmbeddedImage($image,$cid,$name,$encoding,$type)
   {
      $this->mail->AddStringEmbeddedImage($image,$cid,$name,$encoding,$type);
   }

   public function send()
   {
     // Must provide a data object and template first
     if(! $this->dataObj || ! $this->template)
       return false;

     // Set the subject
     $this->mail->Subject = $this->subject;

     // Attach tenant logo to message
     if(file_exists("_pro-".$this->tenant.'/images/logo_email.png'))
        $this->mail->AddEmbeddedImage("_pro-".$this->tenant.'/images/logo_email.png', 'logo', 'attachment', 'base64', 'image/png');
     else
        $this->mail->AddEmbeddedImage("_pro-".$this->tenant.'/images/logo_email.gif', 'logo', 'attachment', 'base64', 'image/gif');
     $this->mail->AddEmbeddedImage("_pro-".$this->tenant.'/images/px.gif', 'px', 'attachment', 'base64', 'image/gif');

     // Set tenant from name/email
     $this->mail->SetFrom($this->settings->smtp_user,$this->settings->name);

     // Prepare msg contents
     $this->smarty->assign('subject',$this->subject);
     $this->smarty->assign('data',$this->dataObj);
     $this->smarty->assign('datetime',date('M d, Y - H:i:s'));
     $this->mail->MsgHTML($this->smarty->fetch('emails/'.$this->template.'.tpl'));

     return $this->mail->Send(); 
   }

   private function initObject()
   {
     // Setup default mailer settings

     $this->mail->IsSMTP();

     // SMTP Host
     if(trim($this->settings->smtp_host) != '')
       $this->mail->Host = $this->settings->smtp_host;
     else
       $this->mail->Host = '50.28.86.21';
 
     // SMTP Mode, tls or plain
     if(trim($this->settings->smtp_mode) == 'tls' ||
	      trim($this->settings->smtp_mode) == 'ssl' &&
             $this->settings->smtp_user != '' && 
             $this->settings->smtp_pass != '')
     {
       $this->mail->SMTPAuth   = true;
       $this->mail->SMTPSecure = $this->settings->smtp_mode;
       $this->mail->Username   = $this->settings->smtp_user;
       $this->mail->Password   = $this->settings->smtp_pass;
     }
   }


   private $db;
   private $dbc;
   public $mail;
   public $smarty;
   public $subject;
   public $dataObj;
   public $template;
  

}
?>
