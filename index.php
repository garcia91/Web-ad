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
use webad\ad;

$istart = microtime(true);
core::init();
$inittime = microtime(true)-$istart;


$rstart = microtime(true);
core::render();

$rendertime = microtime(true)-$rstart;

//echo core::$twig->render("index.html", array("username"=>"Igor"));


//echo core::$config->general->lang->getValue();

$adstart = microtime(true);

$config = [
    'account_suffix'        => '@s-tech.ru',
    'domain_controllers'    => ['cyclop.s-tech.ru'],
    'base_dn'               => 'dc=s-tech,dc=ru',
    'admin_username'        => '',
    'admin_password'        => '',
];

//$ad = new ad($config);

$adtime = microtime(true)-$adstart;


$stime = microtime(true)-$sstart;

/*
echo 'fulltime='.$stime;
echo '<br>aload='.$autoloadtime;
echo '<br>init='.$inittime;
echo '<br>render='.$rendertime;
echo '<br>ad='.$adtime;

*/