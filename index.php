<?php
/**
 *
 * Date: 11.03.16
 * Time: 9:49
 */

//load libraries
$sstart = microtime(true);

require __DIR__ . '/vendor/autoload.php';

$autoloadtime = microtime(true)-$sstart;

use webad\core;

$istart = microtime(true);
core::init();
$inittime = microtime(true)-$istart;


$rstart = microtime(true);
core::render();

$rendertime = microtime(true)-$rstart;



core::$session->set('user_ip', $_SERVER['REMOTE_ADDR'], true);

//var_dump(core::$twVars['data']);

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

$stime = microtime(true)-$sstart;

/*
echo 'fulltime='.$stime;
echo '<br>aload='.$autoloadtime;
echo '<br>init='.$inittime;
echo '<br>render='.$rendertime;

*/