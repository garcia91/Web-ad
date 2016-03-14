<?php
/**
 *
 * Date: 11.03.16
 * Time: 9:49
 */

//load libraries
require __DIR__ . '/vendor/autoload.php';

use webad\core;
use webad\ad;


core::init();
$test1='testing.................';

core::render();

//echo core::$twig->render("index.html", array("username"=>"Igor"));


//echo core::$config->general->lang->getValue();


$config = [
    'account_suffix'        => '@gatech.edu',
    'domain_controllers'    => ['whitepages.gatech.edu'],
    'base_dn'               => 'dc=whitepages,dc=gatech,dc=edu',
    'admin_username'        => '',
    'admin_password'        => '',
];

$ad = new ad($config);




/*
//reading configuration
$conf = new ini(__DIR__."/core/config.ini");

echo $conf->section('general')->key("lang")->getValue();
$conf->general->lang->setValue("de");
$conf->save();



*/
