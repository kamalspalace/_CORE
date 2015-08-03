<?php
class DB {
  static $link = null;
  static function getLink()
  {
    if(self::$link)
    {
      return self::$link;
    }

    self::$link = new PDO('mysql:host=localhost;dbname=shipon_bol-master;charset=utf8','shipon_bol','b0l4321!');
    self::$link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    self::$link->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return self::$link;
  }

  public static function __callStatic($name,$args)
  {
    $callback = array(self::getLink(), $name);
    return call_user_func_array($callback,$args);
  }

}
?>
