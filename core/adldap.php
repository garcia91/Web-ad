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
            $result[$key]['name'] = $folder->getName();
            $result[$key]['dn'] = $folder->getDistinguishedName();
            $result[$key]['type'] = $folder->getObjectCategory();
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
            $result[$key]['name'] = $object->getName();
            $result[$key]['dn'] = $object->getDistinguishedName();
            $result[$key]['type'] = $object->getObjectCategory();
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
        $type = $model->getObjectCategory();
        $folders = array('Organizational-Unit', 'Builtin-Domain', 'Container');
        return in_array($type, $folders);
    }


}