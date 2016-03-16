<?php
/**
 * Created by PhpStorm.
 * User: fedotov
 * Date: 16.03.16
 * Time: 15:16
 */

namespace webad;


class request
{
    public function __construct()
    {
    }

    /**
     * Magic PHP function to return request param
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @param string $type
     * @return mixed
     */
    public function get($name, $type = 'mixed')
    {
        return $this->_get($name, $type);
    }

    /**
     * Base get-function
     *
     * @param string $name
     * @param string $type
     * @return mixed|false
     */
    private function _get($name, $type)
    {
        if (isset($_REQUEST[$name])) {
            $r = $_REQUEST[$name];
        } else {
            return false;
        }
        if ($type != 'mixed') {
            settype($r, $type);
        }
        return $r;
    }

    /**
     * @param string $name
     * @return int
     */
    public function getInt($name)
    {
        return $this->_get($name, 'int');
    }

    /**
     * @param string $name
     * @return double
     */
    public function getDouble($name)
    {
        return $this->_get($name, 'double');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function getBool($name)
    {
        return $this->_get($name, 'bool');
    }

    /**
     * @param string $name
     * @return string
     */
    public function getString($name)
    {
        return addslashes($this->_get($name, 'string'));
    }


    /**
     * @param string $name
     * @return array
     */
    public function &getArray($name)
    {
        return $this->_get($name, 'array');
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getMixed($name)
    {
        return $this->_get($name, 'mixed');
    }

}