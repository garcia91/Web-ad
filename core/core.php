<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:50
 */

namespace webad;


use LdapTools\Exception\LdapConnectionException;
use LdapTools\Exception\LdapBindException;


class core
{
    /**
     * @var iniConfig|null Object of configuration from ini class
     */
    public static $config = null;

    /**
     * @var string Path to configuration ini-file
     */
    protected static $iniFile = __DIR__ . "/config.ini";

    /**
     * @var string Path to twig templates
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
        //'cache'=>'./cache/twig' //disable it while dev
        'cache' => false
    );

    /**
     * @var session Object of session class
     */
    public static $session;

    /**
     * @var \i18n Object of i18n class
     */
    protected static $i18n;

    /**
     * @var string Path to l10n ini-files
     */
    protected static $i18nLangPath = "./l10n/lang_{LANGUAGE}.ini";

    /**
     * !important: do not disable i18n cache!
     *
     * @var string Path to i18n class cache dir
     */
    protected static $i18nCachePath = "./cache/l10n/";

    /**
     * @var array Array of variables for twig templates
     */
    public static $twVars = array();

    /**
     * @var logger
     */
    protected static $log;


    /**
     * @var request Object of request class.
     */
    public static $param;


    /**
     * @var string Value of current (active) twig template
     */
    private static $currTemplate = 'auth.twig';


    /**
     * @var ldapttols|adldap2 Object of ad class (extends adldap class)
     */
    private static $ad;


    //###########################################################
    /**
     * core constructor.
     *
     */
    public function __construct()
    {
        self::init();
    }


    /**
     * Main initialization function
     */
    public static function init()
    {
        self::$session = new session();
        self::$param = new request();
        //self::$log = new logger();
        self::connectAd();
        self::checkParam();
        self::initConfiguration(self::$iniFile);
        self::initI18n(self::$i18nLangPath, self::$i18nCachePath);
        self::initTwig(self::$twigTemplates, self::$twigConfig);

        self::checkLogon();
    }

    /**
     * Check request vars
     */
    private static function checkParam()
    {
        if ($action = self::$param->act) {
            switch ($action) {
                case 'auth': //authenticate
                    self::$session->clearAll(true);
                    self::$session->dc = self::$param->dc;
                    self::$session->username = self::$param->login;
                    self::$session->userpass = self::$param->password;
                    self::initConfiguration(self::$iniFile);
                    self::connectAd();
                    break;
                case 'exit':
                    self::$session->destroy();
                    self::$ad = null;
                    break;
                case 'get_folders': //request for list of ad folders for building tree
                    $path = self::$param->get('path') ?: null;
                    echo self::$ad->getFolders($path);
                    exit;
                    break;
                case 'get_objects': //request for list of ad objects in current folder
                    $path = self::$param->get('path') ?: null;
                    echo self::$ad->getObjects($path);
                    exit;
                    break;
                case 'change_page': //change active page (auth, ad, config, etc)
                    $page = self::$param->get('page');
                    self::setPage($page);
                    exit;
                    break;
                case "check_locked": //check for existing locked users (return a number)
                    echo self::$ad->checkLocked();
                    exit;
                    break;
                case "get_locked": //request for list of locked users in AD
                    echo json_encode(self::$ad->getLocked());
                    exit;
                    break;
                case "unlock": //request for unlock users (array)
                    $lUsers = self::$param->get("ul");
                    foreach ($lUsers as $lUser) {
                        $result = self::$ad->unlockUser($lUser);
                        if ($result != "ok") {
                            echo $result;
                            exit;
                        }
                    }
                    echo "ok";
                    exit;
                    break;
                case "change_dcs":
                    echo self::change_dcs();
                    exit;
                    break;
                case "change_settings":
                    echo self::change_settings();
                    exit;
                    break;
                case "change_lib":
                    echo self::change_lib();
                    exit;
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
        if (self::$ad) {
            self::$session->user_logon = true;
        }
        if (self::$session->user_logon) {
            self::addVar('user', self::$ad->getUserFullName());
            self::$session->logintime = self::$session->logintime ?: date("j.m.Y H:i:s");
            self::addVar('logintime', self::$session->logintime);
            if (self::$session->get("page")) {
                self::setPage(self::$session->get("page"));
            } else {
                self::setPage("ad");
            }
            return true;
        } else {
            self::setPage();
            $dcs = self::$config['dc'];
            if (count($dcs) == 1) {
                $dn = substr($dcs[0], strpos($dcs[0], '.') + 1);
                self::addVar('dn', '@' . $dn);
                self::addVar('dc', $dcs[0]);
            } elseif (count($dcs) > 1) {
                self::addVar('dc', $dcs);
            }
            return false;
        }
    }


    /**
     * Set current twig template
     *
     * @param string $template
     * @return bool
     */
    public function setPage($template = "auth")
    {
        if (file_exists("./templates/" . $template . ".twig")) {
            self::$currTemplate = $template . ".twig";
            self::$session->set("page", $template);
            return true;
        } else {
            self::addVar('error', array("code" => 404, "message" => "Page not found: " . $template . ".twig"));
            return false;
        }
    }


    /**
     * Reads settings from config ini-file
     *
     * @param $file string
     */
    private function initConfiguration($file)
    {
        if (!is_null(self::$config)) return;
        self::$config = new iniConfig($file);
        self::$session->set("lang", self::$config["general"]["lang"]);
        self::$session->set("adlib", self::$config["general"]["lib"]);
        self::addVar("server", self::$session->get("dc"));
        self::addVar("adlib", self::$session->get("adlib"));
        self::addVar("notifInterval", self::$config["general"]["notifInterval"]);
        //if current page is "settings" that read needed section
        if (self::$session->get("page") == "settings") {
            self::addVar("adlib", self::$session->adlib);
            $dcs = self::$config['dc'];
            self::addVar('dc', $dcs);
            // get array of existing langs from list of files of lang path
            $langPath = self::$i18nLangPath;
            $path = substr($langPath, 0, strrpos($langPath, "/"));
            $files = scandir($path);
            $langs = array();
            foreach ($files as $file) {
                $file = explode(".", $file);
                if (stripos($file[1], "ini") !== false) {
                    $langs[] = explode("_", $file[0])[1];
                }
            }
            self::addVar("langs", $langs);
        }
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
        //get all constans from i18n-class cache...
        $l = new \ReflectionClass('L');
        $arrL = $l->getConstants();
        $arr2 = array();
        foreach ($arrL as $index => $item) {
            $k = explode('_', $index);
            self::addVar($k, $item, 'L');
            $arr2[$k[0]][$k[1]] = $item;
        }
        //...and add them to twig vars
        self::$twVars["L"] = $arr2;
        self::addVar('lang', self::$i18n->getAppliedLang());
    }


    public static function render()
    {
        echo self::$twig->render(self::$currTemplate, self::$twVars);
    }


    /**
     * Adding your own var to the $twVars array for twig
     *
     * @param string $var Name of variable
     * @param mixed $value Value of variable
     * @param string $node
     * @return bool
     */
    public static function addVar($var = '', $value = '', $node = 'data')
    {
        if ($var != '' and $value != '') {
            self::$twVars[$node][$var] = $value;
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
     * try to connect to DC, using credentials form
     */
    private static function connectAd()
    {
        if (self::$session->dc
            && self::$session->username
            && self::$session->userpass
            && self::$session->adlib
        ) {
            try {
                // "adldap2" or "ldaptools"
                $lib = "webad\\".self::$session->adlib;
                self::$ad = new $lib(self::$session->username, self::$session->userpass, self::$session->dc);
            } catch (LdapConnectionException $e) {
                self::$session->del('dc');
                self::$session->del('username');
                self::$session->del('userpass');
                $m = $e->getMessage();
                self::addVar('error', array("code" => 99, "message" => $m));
                self::$session->user_logon = false;
            } catch (LdapBindException $e) {
                self::$session->del('dc');
                self::$session->del('username');
                self::$session->del('userpass');
                $c = $e->getCode();
                $m = $e->getMessage();
                self::addVar('error', array("code" => $c, "message" => $m));
                self::$session->user_logon = false;
            }
        }
    }


    /**
     * Saving list of domain controllers from setting page
     *
     * @return string
     */
    private function change_dcs()
    {
        self::initConfiguration(self::$iniFile);
        $dcs = self::$param->get("dc");
        if (is_array($dcs)) {
            self::$config->del("dc");
            foreach ($dcs as $index => $dc) {
                self::$config["dc." . $index] = $dc;
            }
            $result = self::$config->save();
            if ($result === false) {
                return "Error of saving config file";
            } else {
                return "ok";
            }
        } else {
            return "Bad data from client";
        }
    }

    private function change_settings()
    {
        self::initConfiguration(self::$iniFile);
        $lang = self::$param->get("language");
        $interval = self::$param->get("notifInterval");
        if ($lang) {
            self::$config["general.lang"] = $lang;
        }
        if ($interval) {
            self::$config["general.notifInterval"] = $interval;
        }
        $result = self::$config->save();
        if ($result === false) {
            return "Error of saving config file";
        } else {
            return "ok";
        }
    }


    private function change_lib()
    {
        self::initConfiguration(self::$iniFile);
        $lib = self::$param->get('lib');
        if ($lib) {
            self::$config["general.lib"] = $lib;
        } else {
            return "Lib was not sent";
        }
        $result = self::$config->save();
        if ($result === false) {
            return "Error of saving config file";
        } else {
            return "ok";
        }
    }


}


