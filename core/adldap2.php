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
use Adldap\Exceptions\Auth\BindException;
use Adldap\Models;
use Adldap\Adldap;
use Adldap\Search\Factory;


class adldap2
{

    /**
     * @var Adldap Adldap instance
     */
    private $adInstance;

    private $provider;

    /**
     * @var Configuration
     */
    public $config;


    /**
     * @var string
     */
    private $domainName;


    /**
     * @param string $username
     * @param string $userpass
     * @param string $dc
     */
    public function __construct($username, $userpass, $dc)
    {
        $this->setDomainName($dc);
        $baseDN = $this->getBaseDN();

        $this->config = new Configuration();
        $this->config->setDomainControllers([$dc]);
        $this->config->setAdminUsername($username);
        $this->config->setAdminPassword($userpass);
        $this->config->setBaseDn($baseDN);
        $this->config->setAccountSuffix($this->getAccountSuffix());

        $this->adInstance = new Adldap();
        $this->provider = new Provider($this->config);

        $this->adInstance->addProvider('default', $this->provider);
        try {
            $this->adInstance->connect('default');
        } catch (BindException $e) {
            echo $e->getMessage();
            exit;
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
     * @return bool|string
     */
    private function getDomainName()
    {
        return $this->domainName;
    }

    /**
     * @param $dc string FQDN of domain controller
     * @return bool|string
     */
    private function setDomainName($dc)
    {
        if ($dc) {
            $this->domainName = substr(strstr($dc, "."), 1);
        }
    }


    private function getAccountSuffix($domainNane = null)
    {
        if (is_null($domainNane)) {
            $domainNane = $this->domainName;
        }
        return '@' . $domainNane;
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
        // returning if there are childs in this baseDN
        if ($checkChild) {
            $filter = "(|(objectcategory=container)(objectcategory=organizationalunit))";
            $child = $this->sf($path)
                ->recursive(false)
                ->rawFilter($filter)
                ->select('')
                ->first();
            return $child ? true : false;
        } else {
            $filter = "(|(objectcategory=container)(objectcategory=organizationalunit)(objectcategory=builtinDomain))";
            // searching folders in AD (OrganizationalUnit or Container or BuiltinDomain)
            $folders = $this->sf($path)
                ->recursive(false)
                ->rawFilter($filter)
                ->select("name")
                ->get()->all();
        }
        $result = array();
        foreach ($folders as $key => $folder) {
            $result[$key]['title'] = $folder->getName();
            $result[$key]['key'] = $folder->getDistinguishedName();
            $result[$key]['folder'] = "true";
            $result[$key]['lazy'] = $this->getFolders($result[$key]['key'],true);
        }
        sort($result);
        reset($result);
        return json_encode($result);
    }

    /**
     * @param string $path
     * @return array|bool
     */
    public function getObjects($path) {
        if ($path) {
            $this->config->setBaseDn($path);
        }
        $allobjects = $this->search()->recursive(false)->whereHas('name')->
        select("name")->get()->all();
        if (!$allobjects) return false;

        $objects = array();
        $folders = array();
        foreach ($allobjects as $object) {
            $type = strtolower((new \ReflectionClass($object))->getShortName());
            if ($this->isFolder($type)) {
                $folders[] = [
                    'data' => ['type' => $type],
                    'folder' => true,
                    'title' => $object->getName(),
                    'key' => $object->getDistinguishedName()
                ];
            } else {
                $objects[] = [
                    'data' => ['type' => $type],
                    'folder' => false,
                    'title' => $object->getName(),
                    'key' => $object->getDistinguishedName()
                ];
            }
        }
        return json_encode(array_merge($folders, $objects));
    }


    /**
     * @param string $type
     * @return bool
     */
    private function isFolder($type)
    {
        $folders = array('organizationalunit', 'builtin-domain', 'container');
        return in_array($type, $folders);
    }


    public function checkLocked(){
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
            return count($result);
        } else {
            return "0";
        }
    }



    /**
     * Return array (or count) of locked users
     *
     * @return array|bool
     */
    public function getLocked(){
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
                return "0";
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


    /**
     * @return Factory
     */
    private function search() {
        return $this->provider->search();
    }


    /**
     * @param $path string
     * @return Factory
     */
    private function sf($path) {
        if ((is_null($path)) or ($path=='')) $path=$this->config->getBaseDn();
        return new Factory($this->provider->getConnection(), $this->provider->getSchema(), $path);
    }

    /**
     * Return $user's fullname
     * If $user not set it returns fullname of user setted in domain configuration
     *
     * @param string $user
     * @return string
     */
    public function getUserFullName($user = null)
    {
        if (is_null($user)) {
            $user = $this->config->getAdminUsername();
        }
        return $this->search()
            ->users()
            ->findBy("samaccountname", $user, ['cn','displayName'])
            ->getCommonName();
    }


}