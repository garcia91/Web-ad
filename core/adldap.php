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
     * @param $dc string
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
                //->select('')
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

        foreach ($folders as $key => $folder) {
            $folders[$key]['hasChilds'] = $this->getFolders($folder['dn'], true);
        }

        return $folders;
    }

    /**
     * @param string $path
     * @return array|bool
     */
    public function getObjects($path)
    {
        $objects = $this->search
            ->from(LdapObjectType::COMPUTER)
            ->from(LdapObjectType::CONTACT, 'contact')
            ->from(LdapObjectType::CONTAINER)
            ->from(LdapObjectType::USER)
            ->from(LdapObjectType::GROUP)
            ->from(LdapObjectType::OU)
            ->where($this->search->filter()->present('name'))
            ->where($this->search->filter()->notPresent('contact.samaccountname'))
            ->select(['name', 'dn'])
            ->setBaseDn($path)
            ->orderBy('name')
            ->setScopeOneLevel()
            ->getLdapQuery()->getResult()->toArray();
        if (!count($objects)) return false;

        //make two different arrays for folders and other objects
        //  it helps us to sort objects with folders at first
        $result = array();
        $folders = array();
        foreach ($objects as $object) {
            $type = $object->getType();
            if ($this->isFolder($type)) {
                $folders[] = [
                    'type' => $type,
                    'folder' => true,
                    'name' => $object->name,
                    'dn' => $object->dn
                ];
            } else {
                $result[] = [
                    'type' => $type,
                    'folder' => false,
                    'name' => $object->name,
                    'dn' => $object->dn
                ];
            }
        }
        return array_merge($folders, $result);
    }


    /**
     * @param string $type
     * @return bool
     */
    private function isFolder($type)
    {
        $folders = array('ou', 'builtinDomain', 'container');
        return in_array($type, $folders);
    }


    /**
     * Return array (or count) of locked users
     *
     * @param bool $checkCount
     * @return array|bool
     */
    public function getLocked($checkCount = false)
    {
        $lockedUsers = $this->search
            ->fromUsers()
            //->select('*')
            ->select(['samaccountname', 'name', 'lockedDate', 'lockouttime'])
            ->where(['locked' => true])
            ->getLdapQuery()->getArrayResult();
        if (count($lockedUsers)) {
            $duration = $this->newSearch()
                ->select('lockoutduration')
                ->where(['objectclass' => 'domain'])
                ->getLdapQuery()
                ->getSingleScalarOrNullResult();
            $now = new \DateTime();
            $now = AttributeConverterFactory::get('windows_time')->toLdap($now);
            $actual_lockouttime = $duration + $now;
            $result = array();
            foreach ($lockedUsers as $lockedUser) {
                if ($lockedUser['lockouttime'] > $actual_lockouttime) {
                    $result[] = $lockedUser;
                }
            }
            if ($checkCount) {
                return count($result);
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
     * Unlock user
     *
     * @param null|string $user samaccountname
     * @return string
     */
    public function unlockUser($user = null)
    {
        if (!is_null($user)) {
            $user = $this->search()->users()->select('lockouttime')->findBy('samaccountname', $user);
            if ($user) {
                $user->setAttribute("lockouttime", "0");
                if ($user->update()) {
                    return "ok";
                } else {
                    return "update failed for " . $user;
                }
            } else {
                return "user " . $user . " not found";
            }
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