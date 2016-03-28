<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:51
 */

namespace webad;

use Adldap\Connections\Configuration;
use Adldap\Models;
use Adldap\Exceptions\AdldapException;
use Adldap\Schemas\ActiveDirectory;

class ad extends \Adldap\Adldap
{


    public function __construct($configuration, $connection = null, $autoConnect = true)
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
    protected function bindUsingCredentials($username, $password, $suffix = null)
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

            throw new AdldapException($errorM, $errorC);
        }

        return true;
    }


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
        if (strlen($path)) {
            core::$adConfig->setBaseDn($path);
        } else {
            $path = $this->getBaseDN(core::$adConfig);
        }
        $result = array();
        // searching folders in AD (OrganizationalUnit or Container or BuiltinDomain)
        $folders = $this->search()->recursive(false)->
        orWhereEquals(ActiveDirectory::OBJECT_CATEGORY, ActiveDirectory::OBJECT_CATEGORY_CONTAINER)->
        orWhereEquals(ActiveDirectory::OBJECT_CATEGORY, ActiveDirectory::ORGANIZATIONAL_UNIT_LONG)->
        orWhereEquals(ActiveDirectory::OBJECT_CATEGORY, 'builtinDomain')->
        get()->getValues();
        // returning if there are childs in this baseDN
        if ($checkChild) {
            return count($folders) ? true : false;
        }

        foreach ($folders as $key => $folder) {
            // get current type of folder to make new path
            if ($folder instanceof Models\OrganizationalUnit) {
                $ct = 'OU=';
            } else {
                $ct = 'CN=';
            }
            $result[$key]['name'] = $folder->getName();
            $result[$key]['type'] = $folder->getObjectCategory();
            $result[$key]['hasChilds'] = $this->getFolders($ct.$folder->getName().','.$path,true);
        }
        sort($result);
        reset($result);
        return $result;
    }


}