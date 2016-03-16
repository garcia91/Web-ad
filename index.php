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

echo core::$session->user_ip;
echo core::$param->lang;

//echo core::$twig->render("index.html", array("username"=>"Igor"));


//echo core::$config->general->lang->getValue();



$stime = microtime(true)-$sstart;

/*
echo 'fulltime='.$stime;
echo '<br>aload='.$autoloadtime;
echo '<br>init='.$inittime;
echo '<br>render='.$rendertime;

*/