<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('assert.warning', 1);

set_time_limit(30);
// Code to scan a web site's account for changes in any of the files.
// The code is adapted from Eyesite and another article on the web.
// Author Chris Vaughan Derby & South Derbyshire Ramblers
//                      chris@cevsystems.co.uk
//                      copyright 2014
//
// User options - all user options are contained in the config.php file
//  THIS VERSION WORKES WITH VERSION 1.01 of the CONFIG file

define("VERSION_NUMBER", "2.03");
//  version 2.03
//             change of order to statements when closing application
//  version 2.02
//             recode directory iteration to make it quicker
//  version 2.01
//             check if already running, check filename length, bug fixes
//  version 2.00 
//             change to coding to be use classes and wild cards corrently
//  version 1.04
//             Addition of use of wild char * in excluded folders
//  version 1.03
//              Remove second error reporting code
//              Add running time of 60 seconds
//  version 1.02
//              Correction to error email
//  version 1.01
//              Change to add in Joomla subfolders that should be ignored
//  version 1.00
//              Change to email title to make it easier to recognise , 
//              if you have a few emails you will be able to sort them by domain
// version 0.99
//              change to how extensions are displayed to reduce length of output email
//              change to how folders are specified in config file with / rather than \\
//              change to skipFolders so that folders with simialar names are handled correctly
// version 0.98
//              check that code is running on php version 5.3 or higher
// version 0.97
//              correction to record test date, no errors whether email sent of not
// version 0.96
//              added email in the case of a serious error
// version 0.95
//              change to check upper and lower case file extensions
// version 0.94
//              Change files processed msgs to echo
// version 0.93
//              Add version number to email title
// version 0.92
//              fix to only delete from tested table entries over a year old
// version 0.91
//              fix to get the email anyway option working correctly
// version 0.9 - 23 June 2014
//              First release
// 	initialize


if (file_exists('config.php')) {
    require('config.php');
} else {
    require('config_master.php');
}

require('classes/autoload.php');
Logfile::create("logfile.log");
Logfile::writeWhen("Logfile created");

if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
// echo 'I am at least PHP version 5.4.0, my version: ' . PHP_VERSION . "\n";
} else {
    $text = 'You MUST be running on PHP version 5.4.0 or higher, running version: ' . PHP_VERSION . "\n";
    $mailed = mail($email, "WebMonitor: ERROR SCANNING " . $domain, $text);
    die();
}
$appStatus = new Isapprunning();
$status = $appStatus->check();

$alreadyRunning = false;
if ($status === NULL) {
    $alreadyRunning = false;
} else {
    $timeperiod = new DateInterval("PT10M");
    if ($status > $timeperiod) {
        $alreadyRunning = true;
    } else {
        // too close to last run about
        die("Application execution is too soon after previous run");
    }
}

if (isset($joomlaFolders)) {
    foreach ($joomlaFolders as $value) {
        if (!isset($skipFolders)) {
            $skipFolders = Array($value . "/tmp/");
        } else {
            array_push($skipFolders, $value . "/tmp/");
        }
        array_push($skipFolders, $value . "/log/");
        array_push($skipFolders, $value . "/cache/");
        array_push($skipFolders, $value . "/administrator/cache/");
    }
}

$dbconfig = new Dbconfig($host, $database, $user, $password);
$scan = new Scan($dbconfig, $domain);

if ($scan->Connect()) {
    // check to see if last scan completed correctly
    if ($alreadyRunning === false) {
        $scan->scanFiles($path, $skipFolders, $processExtensions);
    }
    set_time_limit(30);
    $scan->emailResults($email, $emailinterval, $alreadyRunning);
    $scan->deleteOldTestedRecords();
    $scan = NULL;
} else {
    $text = "Error in running hashscan.php for this domain, consult logfile";
    $mailed = mail($email, "WebMonitor: ERROR SCANNING " . $domain, $text);
}
$appStatus->finish();
Logfile::writeWhen("Logfile closed");
Logfile::close();

