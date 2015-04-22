<?php

/**
 * Description of isapprunning
 *
 * @author Chris Vaughan
 */
class Isapprunning {

    private $testrunningfile = "application_is_running.test";
    private $isalreadyrunning = false;
    private $timediff;
    const STATE_OK = 0;
    const STATE_WASRUNNING = 1;
    const STATE_NEW = 2;
    const STATE_CHANGED = 3;
    const STATE_DELETED = 4;

    function __construct() {
        
    }

    function check() {
        $this->isalreadyrunning = file_exists($this->testrunningfile);
        if (!$this->isalreadyrunning) {
            $scanningFile = fopen($this->testrunningfile, "w") or die("Unable to create " . $testthis->isalreadyrunningfile . " file!");
            fclose($scanningFile);
            return NULL;
        } else {
            $datetext = date("F d Y H:i:s.", filemtime($this->testrunningfile));
            $created = DateTime::createFromFormat("F d Y H:i:s.", $datetext);
            $now = new DateTime(NULL);
            $this->timediff = $created->diff($now);
            return $this->timediff;
        }
    }

    function finish() {
        if (file_exists($this->testrunningfile)) {
            unlink($this->testrunningfile);
        }
    }

}
