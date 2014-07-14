<?php

// start of config  version v1.00
// $email - email address to send results
// $domain - domain/account being scanned
//
// $host, $database, $user, $password 
//      Set up a mysql database and record its details in this section
//      For ramblers webs accounts set $host to "localhost";
//          set the $database and $user to the name of the database 
//          
// $path - top level to search, hashscan will monitor this folder and all subfolders
//          except for those folders specified in the $skipFolders field
//          
// $skipFolders - skip the following folders, these should be defined relative to the $path value
// if you are using Joomla (in folder www0x then it is recommended that you exclude these subfolders
//     "public_html/www0X/tmp/","public_html/www0X/log/","public_html/www0X/cache/","public_html/www0X/administrator/cache/"
// NOTE: end all subfolders with a / otherwise folders with similar names will also be excluded
// If you are using a another CMS then you should consider which folders to exclude
// 
// $processExtensions - specify the file extensions that you wish to be checked, supply them in lower case.
//      extensions are not treated as case sensitive so jpg will scan for both JPG and jpg files
//      for Ramblers-webs it is recommended to monitor the following file types
//      "txt", "php", "jpg", "htm", "html", "cgi", "pdf", "ini", "htaccess"
//      
// $emailinterval - If no changes are found then hashscan will send an email if this time period has elapsed.
//      This is so you get a regular email and know that the scheduled task is still running
//      Interval between emails if no changes P10D - ten days
//      For Ramblers-webs sites it is recommended to use a value of P30D - thirty days 


$email = "chris@somewhere.co.uk";
$domain = "mywebsite.org.uk";


$host = ini_get("mysqli.default_host");
$database = "monitorv01";
$user = "monitor";
$password = "password";

$path = " " . $domain;
$path = "D:Data/UniServerZ/www/";
$skipFolders = Array(""); // scan all folders
$skipFolders = Array("monitor/Detect Hacked Files via CRON_PHP_files/", "Joomla3/");
$processExtensions = NULL; // process all file types
$processExtensions = array("txt", "php", "jpg", "htm", "html", "cgi", "pdf", "ini", "htaccess");

$emailinterval = "P2D";
// end of config

