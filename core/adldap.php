<?php
/**
 * Created by PhpStorm.
 * User: veron
 * Date: 13.03.16
 * Time: 13:51
 */

namespace webad;

use \Adldap\Connections\Configuration;

class ad extends \Adldap\Adldap
{
    /**
     * @var Configuration
     */
    private $config;


    public function __construct($configuration, $connection, $autoConnect)
    {

        parent::__construct($this->config, $connection, $autoConnect);
    }

}