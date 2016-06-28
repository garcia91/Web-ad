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
use LdapTools\Factory\AttributeConverterFactory;


class ldaptools
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


    /**
     * Return array of folders (CN,OU,builtinDomain) in selected baseDN
     *
     * @param string $path Path to baseDN to receive nonrecursive folders
     * @param bool $checkChild Set if its need to check child folders
     * @return array|bool
     */
    public function getFolders($path = null, $checkChild = false)
    {
        if ($checkChild) {
            // returning if there are childs in this baseDN
            $child = $this->search
                ->setBaseDn($path)
                ->getLdapQuery()->getArrayResult();
            return $child ? true : false;
        } else {
            $folders = $this->search
                ->setBaseDn($path)
                ->where($this->search->filter()->bOr(
                    $this->search->filter()->eq("objectcategory", "container"),
                    $this->search->filter()->eq("objectcategory", "organizationalunit"),
                    $this->search->filter()->eq("objectcategory", "builtinDomain")
                ))
                ->orderBy('name')
                ->setScopeOneLevel()
                ->getLdapQuery()->getArrayResult();
        }
        $result = array();
        foreach ($folders as $key => $folder) {
            $result[$key]["lazy"] = $this->getFolders($folder['dn'], true);
            $result[$key]['title'] = $folder['name'];
            $result[$key]['folder'] = "true";
            $result[$key]['key'] = $folder['dn'];
        }
        return json_encode($result);
    }


    /**
     * @param string $path
     * @return array|bool
     */
    public function getObjects($path)
    {
        $allobjects = $this->search
            ->from(LdapObjectType::COMPUTER)
            ->from(LdapObjectType::CONTACT, 'contact')
            ->from(LdapObjectType::CONTAINER)
            ->from(LdapObjectType::USER)
            ->from(LdapObjectType::GROUP)
            ->from(LdapObjectType::OU)
            ->where($this->search->filter()->notPresent('contact.samaccountname'))
            ->select('name')
            ->setBaseDn($path)
            ->orderBy('name')
            ->setScopeOneLevel()
            ->getLdapQuery()->getResult()->toArray();
        if (!count($allobjects)) return false;
        //make two different arrays for folders and other objects
        //  it helps us to sort objects with folders at first
        $objects = array();
        $folders = array();
        foreach ($allobjects as $object) {
            $type = $object->getType();
            if ($this->isFolder($type)) {
                $folders[] = [
                    'data' => ['type' => $type],
                    'folder' => true,
                    'title' => $object->name,
                    'key' => $object->dn
                ];
            } else {
                $objects[] = [
                    'data' => ['type' => $type],
                    'folder' => false,
                    'title' => $object->name,
                    'key' => $object->dn
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
        $folders = array('ou', 'container'); //'builtinDomain',
        return in_array($type, $folders);
    }


    /**
     * Return count of locked users
     *
     * @return int
     */
    public function checkLocked()
    {
        $lockedUsers = $this->search
            ->fromUsers()
            ->select(['lockouttime'])
            ->where(['locked' => true])
            ->getLdapQuery()->getArrayResult();
        if (count($lockedUsers)) {
            $actual_lockouttime = $this->getActualLockouttime();
            $result = array();
            foreach ($lockedUsers as $lockedUser) {
                if ($lockedUser['lockouttime'] > $actual_lockouttime) {
                    $result[] = $lockedUser;
                }
            }
            return count($result);
        } else {
            return 0;
        }
    }


    /**
     * Return array of locked users
     *
     * @return array|bool
     */
    public function getLocked()
    {
        $lockedUsers = $this->search
            ->fromUsers()
            ->select(['samaccountname', 'name', 'lockedDate', 'lockouttime'])
            ->where(['locked' => true])
            ->getLdapQuery()->getArrayResult();
        if (count($lockedUsers)) {
            $result = array();
            $actual_lot = $this->getActualLockouttime();
            foreach ($lockedUsers as $lockedUser) {
                if ($lockedUser['lockouttime'] > $actual_lot) {
                    $result[] = $lockedUser;
                }
            }
            $users = array();
            foreach ($result as $key => $user) {
                $users[$key]['title'] = $user['name'];
                $users[$key]['key'] = $user['samaccountname'];
                $user['lockedDate']->setTimezone(new \DateTimeZone('Europe/Moscow'));
                $users[$key]["data"]['locktime'] = $user['lockedDate']->format('H:i:s');
                $users[$key]["selected"] = true;
            }
            return $users;
        } else {
            return "0";
        }
    }


    /**
     * Returns time since user's lockouttime not expired yet
     *
     * @return int Time in Windows-time format
     */
    private function getActualLockouttime() {
        $duration = $this->newSearch()
            ->select('lockoutduration')
            ->where(['objectclass' => 'domain'])
            ->getLdapQuery()
            ->getSingleScalarOrNullResult();
        $now = new \DateTime();
        $now = AttributeConverterFactory::get('windows_time')->toLdap($now);
        return $duration + $now;
    }


    /**
     * Unlock user
     *
     * @param null|string $user samaccountname
     * @return string
     */
    public function unlockUser($user = null)
    {
        if (!is_null($user)) {
            $user = $this->search
                ->fromUsers()
                ->where(['samaccountname' => $user])
                ->getLdapQuery()->getOneOrNullResult();
            if (!is_null($user)) {
                $user->set('locked', false);
                try {
                    $this->ad->persist($user);
                } catch (\Exception $e) {
                    return "Error updating user! " . $e->getMessage();
                }
                return 'ok';
            } else {
                return "User not found";
            }
        } else {
            return 'User was not sent';
        }
    }


    /**
     * new instance of LdapQueryBuilder
     *
     * @return \LdapTools\Query\LdapQueryBuilder
     */
    private function newSearch()
    {
        return $this->ad->buildLdapQuery();
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
            $user = $this->domain->getUsername();
        }
        return $this->newSearch()
            ->fromUsers()
            ->select('name')
            ->where(['samaccountname' => $user])
            ->getLdapQuery()->getSingleScalarOrNullResult();
    }


}