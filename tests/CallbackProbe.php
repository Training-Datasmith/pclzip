<?php

namespace PclZip\Tests;

class CallbackProbe
{
    public static $calls = array();

    public static function reset()
    {
        self::$calls = array();
    }

    public static function record($event, $name)
    {
        self::$calls[] = array('event' => $event, 'name' => $name);
    }
}
