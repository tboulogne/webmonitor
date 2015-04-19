<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of dbconfig
 *
 * @author Chris Vaughan
 */
class Dbconfig {

    public $host;
    public $database;
    public $user;
    public $password;

    function __construct($host, $database, $user, $password) {
        $this->host = $host;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
    }

}
