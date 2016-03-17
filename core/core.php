<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:50
 */

namespace webad;


class core
{
    /**
     * Object of configuration from ini class
     *
     * @var iniConfig
     */
    public static $config;

    /**
     * Path to configuration ini-file
     *
     * @var string
     */
    protected static $iniFile = __DIR__ . "/config.ini";

    /**
     * @var string
     */
    protected static $twigTemplates = "./templates";

    /**
     * @var \Twig_Loader_Array
     */
    protected static $twigLoader;

    /**
     * @var \Twig_Environment
     */
    public static $twig;

    /**
     * @var array
     */
    protected static $twigConfig = array(
        //'cache'=>'./cache/twig'
        'cache' => false
    );

    /**
     * @var session
     */
    public static $session;

    /**
     * @var \i18n
     */
    protected static $i18n;

    /**
     * @var string
     */
    protected static $i18nLangPath = "./l10n/lang_{LANGUAGE}.ini";

    /**
     * !important
     *
     * @var string
     */
    protected static $i18nCachePath = "./cache/l10n/";


    protected static $twVars = array();

    /**
     * @var logger
     */
    protected static $log;


    /**
     * @var request
     */
    public static $param;


    //###########################################################
    /**
     * core constructor.
     *
     */
    public function __construct()
    {
        self::init();
    }


    public static function init()
    {
        self::$session = new session();
        self::initConfiguration(self::$iniFile);
        self::initI18n(self::$i18nLangPath, self::$i18nCachePath);
        self::initTwig(self::$twigTemplates, self::$twigConfig);
        self::$log = new logger();
        self::$param = new request();
    }


    private function initConfiguration($file)
    {
        self::$config = new iniConfig($file);
    }

    private function initTwig($twTmplPath, $twConfig)
    {
        self::$twigLoader = new \Twig_Loader_Filesystem($twTmplPath);
        self::$twig = new \Twig_Environment(self::$twigLoader, $twConfig);
    }


    /**
     * !It's important to use cache for configuring Twig-vars
     *
     * @param string $langPath Path to lang directory
     * @param string $langCache Path to cache
     * @throws \Exception
     */
    private function initI18n($langPath, $langCache)
    {
        self::$i18n = new \i18n($langPath, $langCache);
        self::$i18n->init();
        $l = new \ReflectionClass('L');
        self::$twVars = $l->getConstants();
    }


    /**
     * @param string $template
     */
    public static function render($template = 'index.twig')
    {
        echo self::$twig->render($template, self::$twVars);
    }


    /**
     * Adding your own var to the $twVars array for twig
     *
     * @param string $var Name of variable
     * @param string $value Value of variable
     * @return bool
     */
    public static function addVar($var = '', $value = '')
    {
        if ($var != '' and $value != '') {
            self::$twVars[$var] = $value;
            return true;
        }
        return false;
    }


    /**
     * Returning value of specified twig var
     *
     * @param string $var
     * @return bool|string
     */
    public static function getVar($var = '')
    {
        if ($var != '' and array_key_exists($var, self::$twVars)) {
            return self::$twVars[$var];
        }
        return false;
    }




}
