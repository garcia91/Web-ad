<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:51
 */

namespace webad;

use LdapTools\Configuration;
use LdapTools\DomainConfiguration;
use LdapTools\LdapManager;
use LdapTools\Object\LdapObjectType;


class adldap
{


    /**
     * @var Configuration
     */
    public $config;

    /**
     * @var DomainConfiguration
     */
    public $domain;


    /**
     * @var string
     */
    private $domainName;


    /**
     * @var LdapManager
     */
    public $ad;


    /**
     * @var \LdapTools\Query\LdapQueryBuilder
     */
    public $search;



    /**
     * ad constructor.
     *
     * @param string $username
     * @param string $userpass
     * @param string $dc
     */
    public function __construct($username, $userpass, $dc)
    {
        $this->setDomainName($dc);
        $baseDN = $this->getBaseDN();

        $this->domain = (new DomainConfiguration($this->domainName))
            ->setBaseDn($baseDN)
            ->setUsername($username)
            ->setPassword($userpass)
            ->setServers([$dc]);
        $this->config = new Configuration($this->domain);
        $this->ad = new LdapManager($this->config);
        $this->search = $this->ad->buildLdapQuery();


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
     * @param $dc string
     * @return string
     */
    private function getBaseDN($dc = null)
    {
        if (is_null($dc)) {
            $dn = $this->getDomainName();
        } else {
            $dn = substr(strstr($dc, "."), 1);
        }
        return "DC=" . implode(",DC=", explode(".", $dn));
    }

    /**
     * @param $dc string
     * @return bool|string
     */
    private function getDomainName()
    {
        return $this->domainName;
    }

    /**
     * @param $dc string
     * @return bool|string
     */
    private function setDomainName($dc) {
        if ($dc) {
            $this->domainName = substr(strstr($dc, "."), 1);
        }
    }




    /**
     * Return array of folders (CN,OU,builtinDomain) in selected baseDN
     *
     * @param string $path Path to baseDN to receive nonrecursive folders
     * @param bool $checkChild Set if its need to check child folders
     * @return array|bool
     */
    public function getFolders($path = null, $checkChild = false)
    {
        // returning if there are childs in this baseDN
        if ($checkChild) {
            $child = $this->search
                ->setBaseDn($path)
                ->from(LdapObjectType::OU)
                ->from(LdapObjectType::CONTAINER)
                ->setScopeOneLevel()
                ->getLdapQuery()->getArrayResult();
            return $child ? true : false;
        } else {
            $folders = $this->search
                //->select(['name','dn'])
                ->setBaseDn($path)
                ->from(LdapObjectType::OU)
                ->from(LdapObjectType::CONTAINER)
                ->orderBy('name')
                ->setScopeOneLevel()
                ->getLdapQuery()->getArrayResult();
        }

        foreach ($folders as $key => $folder) {
            $folders[$key]['hasChilds'] = $this->getFolders($folder['dn'], true);
        }

        return $folders;
    }

    /**
     * @param string $path
     * @return array|bool
     */
    public function getObjects($path) {

        $objects = $this->search
            ->where($this->search->filter()->present('name'))
            ->select(['name','dn'])
            ->setBaseDn($path)
            ->setScopeOneLevel()
            ->getLdapQuery()->getResult();

        //$objects = $this->search()->recursive(false)->whereHas('name')->select("name")->get()->all();
        if (!$objects) return false;
        foreach ($objects as $key => $object) {
            //$result[$key]['type'] = $object->getObjectClass()->getName();
            $result[$key]['type'] = (new \ReflectionClass($object))->getShortName();
            $result[$key]['folder'] = $this->isFolder($result[$key]['type']);
        }
        sort($result);
        reset($result);
        return $result;
    }


    /**
     * @param string $type
     * @return bool
     */
    private function isFolder($type)
    {
        //$type = $model->getObjectCategory();
        $folders = array('OrganizationalUnit', 'Builtin-Domain', 'Container');
        return in_array($type, $folders);
    }


    /**
     * Return array (or count) of locked users
     *
     * @param bool $checkCount
     * @return array|bool
     */
    public function getLocked($checkCount = false){
        //Lets find all users who was locked ever
        $lockedUsers = $this->search()->users()->rawFilter("(lockouttime>=1)")->select("lockouttime", "samaccountname", "name")->get()->all();
        if (count($lockedUsers)) {
            // get durations of time of lock from ad settings
            $d_filter = '(objectClass=domain)';
            $duration = $this->search()->rawFilter($d_filter)->select("lockoutduration")->get()->all();
            $duration = $duration[0]->lockoutduration;
            $duration = $duration[0]/-10000000;
            $time = time(); //time on web-server and dc must be synchronized
            $result = array();
            // and check if the lock time of every user isn't expired
            foreach ($lockedUsers as $lockedUser) {
                $locktime = round($lockedUser->getLockoutTime() / (10 * 1000 * 1000)) - 11644473600;
                if ($locktime+$duration > $time) {
                    $lockedUser->lockouttime = array(0 => date("H:i:s", $locktime));
                    $result[] = $lockedUser;
                }
            }
            if ($checkCount) {
                return count($result);
            }
            if (count($result)) {
                $users = array();
                foreach ($result as $key => $user) {
                    $users[$key]['title'] = $user->getName();
                    $users[$key]['key'] = $user->getAccountName();
                    $users[$key]["data"]['locktime'] = $user->getLockoutTime();
                    $users[$key]["selected"] = true;
                }
                return $users;
            } else {
                return false;
            }
        } else {
            return "0";
        }
    }


    /**
     * Unlock user
     *
     * @param null|string $user samaccountname
     * @return string
     */
    public function unlockUser($user = null) {
        if (!is_null($user)) {
            $user = $this->search()->users()->select('lockouttime')->findBy('samaccountname', $user);
            if ($user) {
                $user->setAttribute("lockouttime","0");
                if ($user->update()) {
                    return "ok";
                } else {
                    return "update failed for ".$user;
                }
            } else {
                return "user ".$user." not found";
            }
        }
    }


}