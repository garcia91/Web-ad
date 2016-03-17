<?php
/**
 * Created by PhpStorm.
 * User: fedotov
 * Date: 17.03.16
 * Time: 9:33
 */

namespace webad;

class Ini_Exception extends \Exception
{
}

class iniConfig implements \ArrayAccess, \Countable
{
    private static $instance;

    private $autosave = false;

    private $ext = '';

    private $delimiter;

    private $file;

    private $data;

    public function __construct($file, $delimiter = '.', $autocreate = false)
    {
        if ($autocreate && !file_exists($file . $this->ext))
            file_put_contents($file . $this->ext, '');
        $this->file = realpath($file . $this->ext);
        $this->data = parse_ini_file($this->file, 1);
        $this->delimiter = $delimiter;
    }

    public static function factory($file = null, $delimiter = '.', $autocreate = false)
    {
        if (!self::$instance && $file)
            self::$instance = new self($file, $delimiter, $autocreate);
        return self::$instance;
    }

    public function setAutosave($status = false)
    {
        if (!is_bool($status))
            throw new Ini_Exception('Invalid parameter data type');
        $this->autosave = $status;
        return $this;
    }

    public function getAutosaveStatus()
    {
        return $this->autosave;
    }

    public function get($path = null)
    {
        if (!$path) return $this->data;
        $path = explode($this->delimiter, $path);
        if (count($path) == 1) {
            return isset($this->data[$path[0]]) ? $this->data[$path[0]] : null;
        } elseif (count($path) == 2) {
            return isset($this->data[$path[0]][$path[1]]) ? $this->data[$path[0]][$path[1]] : null;
        }
    }

    public function set($path, $value)
    {
        $path = explode($this->delimiter, $path);
        if (count($path) == 1) {
            if (!is_array($value))
                throw new Ini_Exception('Incorrect data type values');
            $this->data[$path[0]] = $value;
        } elseif (count($path) == 2) {
            if (is_array($value) || is_object($value))
                throw new Ini_Exception('Incorrect data type values');
            $this->data[$path[0]][$path[1]] = $value;
        }

        if ($this->autosave)
            $this->save();

        return $this;
    }

    public function del($path)
    {
        if (!$path) return $this->data;
        $path = explode($this->delimiter, $path);
        if (count($path) == 1) {
            unset($this->data[$path[0]]);
        } elseif (count($path) == 2) {
            unset($this->data[$path[0]][$path[1]]);
        }

        if ($this->autosave)
            $this->save();

        return $this;
    }

    public function is($path)
    {
        if (!$path) return $this->data;
        $path = explode($this->delimiter, $path);
        if (count($path) == 1) {
            return isset($this->data[$path[0]]);
        } elseif (count($path) == 2) {
            return isset($this->data[$path[0]][$path[1]]);
        }
    }

    public function size($path)
    {
        if (!$path) return $this->data;
        $path = explode($this->delimiter, $path);
        if (count($path) !== 1)
            throw new Ini_Exception('Incorrect path');

        return count($this->data[$path[0]]);
    }

    public function offsetExists($offset)
    {
        return $this->is($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->del($offset);
    }

    public function count()
    {
        return count($this->data);
    }

    public function save()
    {
        $str = '';
        foreach ($this->data as $k => $v) {
            $str .= "[$k]\n";
            if (is_array($v)) {
                foreach ($v as $k_ => $v_) {
                    $str .= "$k_=$v_\n";
                }
            }
        }
        file_put_contents($this->file, $str);
    }
}