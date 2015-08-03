<?php
class Settings {
  static $link = null;
  static function get($tenant) 
  {
    if(self::$link)
      return self::$link;

    self::$link = new Config($tenant);
    return self::$link;
  }

  public static function __callStatic($name,$args)
  {
    $callback = array(self::get(), $name);
    return call_user_func_array($callback,$args);
  }
}
?>
