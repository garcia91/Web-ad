<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:51
 */

namespace webad;

use Adldap\Connections\Provider;
use Adldap\Connections\Configuration;
use Adldap\Models;
use Adldap\Exceptions\AdldapException;
use Adldap\Contracts\Connections\ConnectionInterface;
use Adldap\Contracts\Schemas\SchemaInterface;
use Adldap\Adldap;


class ad extends Provider
{

    /**
     * Adldap instance
     *
     * @var Adldap
     */
    private $adInstance;



    /**
     * ad constructor.
     *
     * @param Configuration|array $configuration
     * @param ConnectionInterface|null $connection
     * @param SchemaInterface|null $schema
     */
    public function __construct($configuration = [], ConnectionInterface $connection = null, SchemaInterface $schema = null)
    {
        $suffix = $this->getAccountSuffix($configuration);
        $baseDN = $this->getBaseDN($configuration);
        if ($suffix) {
            $configuration->setAccountSuffix($suffix);
        }
        if ($baseDN) {
            $configuration->setBaseDn($baseDN);
        }
        parent::__construct($configuration);
        $this->adInstance = new Adldap();
        $this->adInstance->addProvider('default', $this);
        $this->adInstance->connect('default');
    }

    /* public function __construct($configuration, $connection = null, $autoConnect = true)
     {
         $suffix = $this->getAccountSuffix($configuration);
         $baseDN = $this->getBaseDN($configuration);
         if ($suffix) {
             $configuration->setAccountSuffix($suffix);
         }
         if ($baseDN) {
             $configuration->setBaseDn($baseDN);
         }
         parent::__construct($configuration);
     }

 */
    /**
     *
     * @param $config Configuration
     * @return string|bool
     */
    private function getAccountSuffix($config)
    {
        $dn = $this->getDomain($config->getDomainControllers()[0]);
        if ($dn) {
            return '@' . $dn;
        } else {
            return false;
        }
    }

    /**
     * @param $config Configuration
     * @return string|bool
     */
    private function getBaseDN($config)
    {
        $dn = $this->getDomain($config->getDomainControllers()[0]);
        if ($dn) {
            return "DC=" . implode(",DC=", explode(".", $dn));
        } else {
            return false;
        }
    }

    /**
     * @param $dc string
     * @return bool|string
     */
    private function getDomain($dc)
    {
        if ($dc) {
            return substr(strstr($dc, "."), 1);
        } else {
            return false;
        }
    }

    /**
     * Overriding parent method with adding error code to exception
     *
     * @param string $username
     * @param string $password
     * @param null $suffix
     * @return bool
     * @throws AdldapException
     */
 /*   protected function bindUsingCredentials($username, $password, $suffix = null)
    {
        if (empty($username)) {
            // Allow binding with null username.
            $username = null;
        } else {
            if (is_null($suffix)) {
                // If the suffix is null, we'll retrieve their
                // account suffix from the configuration.
                $suffix = $this->configuration->getAccountSuffix();
            }

            // If the username isn't empty, we'll append the configured
            // account suffix to bind to the LDAP server.
            $username .= $suffix;
        }

        if (empty($password)) {
            // Allow binding with null password.
            $password = null;
        }

        if ($this->connection->bind($username, $password) === false) {
            $errorM = $this->connection->getLastError();
            $errorC = $this->connection->errNo();

            /*if ($this->connection->isUsingSSL() && $this->connection->isUsingTLS() === false) {
                $message = 'Bind to Active Directory failed. Either the LDAPs connection failed or the login credentials are incorrect. AD said: '.$error;
            } else {
                $message = 'Bind to Active Directory failed. Check the login credentials and/or server details. AD said: '.$error;
            }*/
/*
            throw new AdldapException($errorM, $errorC);
        }

        return true;
    }

*/
    /**
     * Return array of folders (CN,OU,builtinDomain) in selected baseDN
     *
     * @param string $path Path to baseDN to receive nonrecursive folders
     * @param bool $checkChild Set if its need to check child folders
     * @return array|bool
     */
    public function getFolders($path = '', $checkChild = false)
    {
        // set baseDN path
        if ($path) {
            core::$adConfig->setBaseDn($path);
        }

        // searching folders in AD (OrganizationalUnit or Container or BuiltinDomain)
        $folders = $this->search()->recursive(false)->
            orWhere("objectcategory", "=", "container")->
            orWhere("objectcategory", "=", "organizationalunit")->
            orWhere("objectcategory", "=", 'builtinDomain')->
            select("name")->
            get()->all();

        // returning if there are childs in this baseDN
        if ($checkChild) {
            return $folders ? true : false;
        }
        $result = array();
        foreach ($folders as $key => $folder) {
            $result[$key]['name'] = $folder->getAttribute('name');
            $result[$key]['dn'] = $folder->getDistinguishedName();
            //$result[$key]['type'] = $folder->getObjectCategory();
            $result[$key]['type'] =   (new \ReflectionClass($folder))->getShortName();
            $result[$key]['hasChilds'] = $this->getFolders($result[$key]['dn'],true);
        }
        sort($result);
        reset($result);
        return $result;
    }

    /**
     * @param string $path
     * @return array|bool
     */
    public function getObjects($path) {
        if ($path) {
            core::$adConfig->setBaseDn($path);
        }
        $objects = $this->search()->recursive(false)->whereHas('name')->
            select("name")->get()->all();
        if (!$objects) return false;
        $result = array();
        foreach ($objects as $key => $object) {
            $result[$key]['name'] = $object->getAttribute('name');
            $result[$key]['dn'] = $object->getDistinguishedName();
            $result[$key]['type'] = (new \ReflectionClass($object))->getShortName();
            $result[$key]['folder'] = $this->isFolder($object);
        }
        sort($result);
        reset($result);
        return $result;
    }


    /**
     * @param Models\Entry $model
     * @return bool
     */
    private function isFolder($model)
    {
        $type = (new \ReflectionClass($model))->getShortName();
        $folders = array('OrganizationalUnit', 'Container');
        return in_array($type, $folders);
    }


}