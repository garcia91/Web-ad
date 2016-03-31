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

//echo core::$twig->render("index.html", array("username"=>"Igor"));

/*
echo core::$config['dc'][0];
core::$config['dc.2'] = "dc.veronet.local";
core::$config['user.lang'] = "de";
core::$config->set('user.name', 'igor');
core::$config->set('temp', array('cyclop','sdc','qwe'));
core::$config['temp2.name.id'] = 'temp2id';
core::$config->save();

*/