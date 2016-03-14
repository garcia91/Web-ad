<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 17:06
 */

namespace webad;


class session
{

    /**
     * session constructor.
     */
    public function __construct()
    {
        session_start();
    }

    /**
     * @param $key string
     * @param $value string
     * @return bool
     */
    public function set($key, $value)
    {
        $_SESSION[$key]=$value;
        return true;
    }

    /**
     * @param $key string
     * @return bool
     */
    public function get($key)
    {
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else return false;
    }
}