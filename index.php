<?php
/**
 *
 * Date: 11.03.16
 * Time: 9:49
 */

//load libraries

require __DIR__ . '/vendor/autoload.php';


use webad\core;


core::init();

core::render();


//var_dump(core::$twVars);

//echo core::$session->user_ip;
//echo core::$param->lang;
