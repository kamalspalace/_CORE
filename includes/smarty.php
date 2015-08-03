<?php
require('smarty/libs/Smarty.class.php');

class SmartyTpl extends Smarty {

  function __construct($tenant)
  {
    parent::__construct();

    $home = $_SERVER['DOCUMENT_ROOT']."/".$tenant;
    $this->setTemplateDir($home."/templates/");
    $this->setCompileDir($home."/templates_c/");
    $this->setConfigDir($home."/config/");
    $this->setCacheDir($home."/cache/");

    $this->caching = Smarty::CACHING_OFF;
    $this->error_reporting = E_ALL & ~E_NOTICE;
  }

}

class SmartyXml extends Smarty {

  function __construct($tenant,$integration)
  {
    parent::__construct();

    $home = $_SERVER['DOCUMENT_ROOT']."/".$tenant;
    $this->setTemplateDir($home."/integration/".$integration."/");
    $this->setCompileDir($home."/templates_c/");
    $this->setConfigDir($home."/config/");
    $this->setCacheDir($home."/cache/");

    $this->caching = Smarty::CACHING_OFF;
  }

}
?>
