<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:50
 */

namespace webad;

use Adldap\Connections\Configuration;
use Adldap\Exceptions\AdldapException;

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
    protected static $twigTemplates = "./templates/";

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


    public static $twVars = array();

    /**
     * @var logger
     */
    protected static $log;


    /**
     * @var request
     */
    public static $param;


    /**
     * @var string
     */
    private static $currTemplate = 'auth.twig';


    /**
     * @var ad
     */
    public static $ad;


    /**
     * @var Configuration
     */
    public static $adConfig;

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
        self::checkParam();
        self::checkLogon();
        self::connectAd();


    }

    private static function checkParam()
    {
        if ($action = self::$param->act) {
            switch ($action) {
                case 'auth':
                    self::$session->dc = self::$param->dc;
                    self::$session->username = self::$param->login;
                    self::$session->userpass = self::$param->password;
                    break;
            }

        }
    }

    /**
     * Check if user is authorized
     * and if he is than add array of dc's to twig var
     *
     * @return bool
     */
    private static function checkLogon()
    {
        if (self::$session->user_logon) {
            self::$currTemplate = "index.twig";
            return true;
        } else {
            self::$currTemplate = "auth.twig";
            $dcs = self::$config['dc'];
            if (count($dcs) == 1) {
                $dn = substr($dcs[0], strpos($dcs[0], '.') + 1);
                self::addVar('dn', '@' . $dn);
            } elseif (count($dcs) > 1) {
                self::addVar('dc', $dcs);
            }
            return false;
        }
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
        $arr1 = $l->getConstants();
        $arr2 = array();
        foreach ($arr1 as $index => $item) {
            $k = explode('_',$index);
            $arr2['L'][$k[0]][$k[1]] = $item;
        }
        self::$twVars = $arr2;
    }


    public static function render()
    {
        echo self::$twig->render(self::$currTemplate, self::$twVars);
    }


    /**
     * Adding your own var to the $twVars array for twig
     *
     * @param string|array $var Name of variable
     * @param mixed $value Value of variable
     * @return bool
     */
    public static function addVar($var = '', $value = '')
    {
        if ($var != '' and $value != '') {
            self::$twVars['data'][$var] = $value;
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
            return self::$twVars['data'][$var];
        }
        return false;
    }


    /**
     * Configure settings for connection to DC
     * @return bool
     * @throws \Adldap\Exceptions\ConfigurationException
     */
    private static function prepareAd()
    {
        if (self::$session->dc && self::$session->username && self::$session->userpass) {
            self::$adConfig = new Configuration();
            self::$adConfig->setDomainControllers(array(self::$session->dc));
            self::$adConfig->setAdminUsername(self::$session->username);
            self::$adConfig->setAdminPassword(self::$session->userpass);
            return true;
        } else {
            return false;
        }
    }

    /**
     * try to connect to DC, using credentials form $adConfig
     */
    private static function connectAd()
    {
        if (self::prepareAd()) {
            try {
                self::$ad = new ad(self::$adConfig);
            } catch (AdldapException $e) {
                $c = $e->getCode();
                if ($c == -1) $c=99;
                $m = $e->getMessage();
                self::addVar('error', array("code" => $c, "message" => $m));
            }
        }
    }


}
