<?php
/**
 * Simple session class to set session and cookies variables
 * User: veron
 * Date: 13.03.16
 * Time: 17:06
 */

namespace webad;


class session
{

    /**
     * Time of expire Cookie
     * @var integer
     */
    protected $cTime = 604800; //7 days


    /**
     * session constructor.
     */
    public function __construct()
    {
        session_set_cookie_params($this->cTime);
        session_start();
    }

    /**
     * Set session/cookie value
     *
     * @param $key string
     * @param $value mixed
     * @param $cookie bool
     * @return bool
     */
    public function set($key, $value, $cookie = false)
    {
        $_SESSION[$key]=$value;
        if ($cookie) $this->setCookie($key, $value);
        return true;
    }

    /**
     * @param $key string
     * @return string
     */
    public function get($key)
    {
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else return null;
    }

    /**
     * @param $key string
     * @return string
     */
    public function getCookie($key)
    {
        if (isset($_COOKIE[$key])) {
            return $_COOKIE[$key];
        } else return null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setCookie($key, $value)
    {
        return setcookie($key, $value);
    }


    /**
     * @param $key
     * @param bool $unsetCookie
     */
    public function del($key, $unsetCookie = false)
    {
        if (isset($_SESSION[$key])) unset($_SESSION[$key]);
        if ($unsetCookie) $this->delCookie($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function delCookie($key) {
        if (isset($_COOKIE[$key])) return $this->setCookie($key, '');
        return false;
    }


    /**
     * set cookie live time in seconds (from now)
     *
     * @param int $sec
     * @return bool
     */
    public function setCookieExpire($sec)
    {
        if (is_int($sec) && $sec>60) {
            $this->cTime = $sec;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Clear all variables in session and cookies (if set arg to true)
     *
     * @param bool $onlySession
     */
    public function clearAll($onlySession = false)
    {
        session_unset();
        if (!$onlySession && isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
            foreach($cookies as $cookie) {
                $parts = explode('=', $cookie);
                $name = trim($parts[0]);
                setcookie($name, '', time()-1000);
                setcookie($name, '', time()-1000, '/');
            }
        }
    }


    public function __destruct()
    {
        $this->destroy();
    }

    /**
     * Destroy session and cookies
     */
    public function destroy(){
        $this->clearAll();
        session_destroy();
    }
}