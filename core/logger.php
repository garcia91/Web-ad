<?php
/**
 * Created by PhpStorm.
 * User: fedotov
 * Date: 14.03.16
 * Time: 15:22
 */

namespace webad;


class logger
{
    /**
     * @var string
     */
    protected $filename = "webad.log";

    /**
     * @var string
     */
    protected $path = __DIR__ . "/";

    /**
     * @var resource
     */
    protected $file;


    public function __construct()
    {
        $this->file = fopen($this->path . $this->filename, 'a');
    }


}