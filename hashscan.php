<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
// Code to scan a web site's account for changes in any of the files.
// The code is adapted from Eyesite and another article on the web.
// Author Chris Vaughan Derby & South Derbyshire Ramblers
//                      chris@cevsystems.co.uk
//                      copyright 2014
//
// User options - all user options are contained in the config.php file
//  THIS VERSION WORKES WITH VeRSION 1.01 of the CONFIG file

define("VERSION_NUMBER", "1.02");
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


error_reporting(E_ALL);
ini_set('display_errors', 1);
// 	initialize
require('config.php');
if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
    // echo 'I am at least PHP version 5.3.0, my version: ' . PHP_VERSION . "\n";
} else {
    $text= 'You MUST be running on PHP version 5.3.0 or higher, running version: ' . PHP_VERSION . "\n";
  	$mailed = mail($email, "WebMonitor: ERROR SCANNING " . $domain, $text); 
	die();
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

If (isset($skipFolders)) {
    for ($i = 0; $i < count($skipFolders); ++$i) {
        $skipFolders[$i] = str_replace("/", DIRECTORY_SEPARATOR, $skipFolders[$i]);
    }
}
$path = str_replace("/", DIRECTORY_SEPARATOR, $path);

$scan = new scan($host, $database, $user, $password, $domain);
if ($scan->Connect()) {

//	Last Hash Scan
    $scan->scanFiles($path, $skipFolders, $processExtensions);
    $scan->emailResults($email, $emailinterval);
    $scan->deleteOldTestedRecords();
    $scan = NULL;
} else {
    $text = "Error in running hashscan.php for this domain, consult logfile";
    $mailed = mail($email, "WebMonitor: ERROR SCANNING " . $domain, $text);
}

class scan {

    var $host, $database, $user, $password;
    var $mysqi;
    var $report;
    var $logfile;
    var $total = 0;
    var $new = 0;
    var $changed = 0;
    var $deleted = 0;

    const STATE_OK = 0;
    const STATE_RUNNING = 1;
    const STATE_NEW = 2;
    const STATE_CHANGED = 3;
    const STATE_DELETED = 4;

    //const SQL_DATE_FORMAT = "%Y-%m-%d %H:%M:%S"; // strftime() format

    function __construct($host, $database, $user, $password, $domain) {
        $this->host = $host;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->domain = $domain;
        $this->report = "";
        $this->logfile = fopen("logfile.log", "w") or die("Unable to open file!");
        $this->Msg("Logfile created");
        $this->report = "<p>Report of changes to domain <b>" . $this->domain . "</b></p>" . PHP_EOL;
    }

    function __destruct() {

        $this->removeDeleted();
        $this->mysqli->close();
        $this->Msg("Logfile closed");
        fclose($this->logfile);
    }

    function Connect() {
// open database
        $this->mysqli = new mysqli($this->host, $this->user, $this->password, $this->database);
        if ($this->mysqli->connect_error) {
            $err = 'Error connecting to database (' . $this->mysqli->connect_errno . ') '
                    . $this->mysqli->connect_error;
            $this->ErrorMsg($err);
            $this->Msg($err);
            return false;
        } else {
            $this->Msg("Database opened");
            $this->checkTablesExist();
            return true;
        }
    }

    function checkTablesExist() {
        if (!$this->TableExists('baseline')) {
            $text = "CREATE TABLE IF NOT EXISTS `baseline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filepath` varchar(255) NOT NULL,
  `hash` varchar(32) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_checked` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Filepath` (`filepath`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8154";
            $res = $this->mysqli->Query($text);
            if ($res) {
                $this->Msg("Table 'baseline' created");
            } else {
                $this->ErrorMsg("Table creation 'baseline' FAILED");
            }
        }
        if (!$this->TableExists('tested')) {
            $text = "CREATE TABLE `tested` (
  `tested` datetime NOT NULL,
  `total` int(11),
  `new` int(11),
  `changed` int(11),
  `deleted` int(11),
  `emailsent` tinyint(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
            $res = $this->mysqli->Query($text);
            if ($res) {
                $this->Msg("Table 'tested' created");
            } else {
                $this->ErrorMsg("Table creation 'tested' FAILED");
            }
        }
    }

    function TableExists($table) {
        $res = $this->mysqli->Query("SHOW TABLES LIKE '" . $table . "'");
        return $res->num_rows > 0;
    }

    function scanFiles($path, $skipFolders, $processExtensions) {
        $this->report.=$this->displayOptions($path, $skipFolders, $processExtensions);
        $ok = $this->setRunning();
        If ($ok == false) {
            return;
        }
        $i = 0;
        $no = 0;

        $dir = new RecursiveDirectoryIterator($path);
        $iter = new RecursiveIteratorIterator($dir);
        while ($iter->valid()) {
            $process = true;
            $subpath = $iter->getSubPath();
            $isDot = $iter->isDot();
            $filename = $iter->key();
            $extension = $iter->getExtension();
            if ($this->skipThisFile($extension, $processExtensions)) {
                $process = false;
            }
            if ($this->skipThisFolder($subpath, $isDot, $skipFolders)) {
                $process = false;
            }
            if ($process) {
                $this->process_file($filename);
                $i+=1;
                $no+=1;
                if ($i >= 500) {
                    echo $no . " - files processed<br/>";
                    $i = 0;
                }
            }
            $iter->next();
        }
// done, now set any items that are not changed to Deleted

        $this->setDeleted();
        $this->report.="Total number of files scanned " . $no;
    }

    function skipThisFile($extension, $processExtensions) {
        $extlower = strtolower($extension);
        if (empty($processExtensions)) {
            return false;
        }
        if (in_array($extlower, $processExtensions)) {
            return false;
        }
        return true;
    }

    Function skipThisFolder($subpath, $isDot, $skipFolders) {
        if ($isDot) {
            return true;
        }
        if (!isset($subpath)) {
            return false;
        }
        if ($subpath == "") {
            return false;
        }

        if (empty($skipFolders)) {
            return false;
        }
        foreach ($skipFolders as $value) {
            if ($this->startsWith($subpath . DIRECTORY_SEPARATOR, $value) == true) {
                return true;
            }
        }
        return false;
    }

    function startsWith($haystack, $needle) {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }

//-------------------------------------------------------------------------------
// Process one file
// Returns 0 if no errors, 1 for a file error, or 2 for a database error
//
    function process_file($filepath) {
        $utf8_filepath = utf8_encode($filepath);

        if (!is_readable($filepath)) {
            $this->ErrorMsg("Unreadable file: $filepath");
            return 1;
        }

        $hash = hash_file("md5", $filepath);
        if (!$hash) {
            $this->ErrorMsg("Unable to calculate hash for: $filepath");
            return 1;
        }

        $query = "Select hash, state From baseline Where filepath = '" . addslashes($filepath) . "'";
        $res = $this->mysqli->query($query);
        if ($res === false) {
            $this->Msg("Select from baseline failed for file: " . $filepath);
            return 2;
        }

// if the file is not registered in the database, insert it in state NEW
        $norows = $res->num_rows;
        if ($norows == 0) {
            $query = "Insert into baseline (filepath, hash, state,  date_added, date_checked)
			values ('" . addslashes($filepath) . "', '$hash', " . self::STATE_NEW . ", NOW(), 0)";
            $result = $this->mysqli->query($query);
            if ($result === false) {
                $this->ErrorMsg("Unable to add NEW entry for " . $filepath);
                return 2;
            }
            $this->Msg("NEW: " . $filepath);
            return 0; // we're done for this file
        }

// here when the file is in the database
// if it already has a problem flagged, leave it alone
        $datarow = $res->fetch_array();
        if ($datarow["state"] != self::STATE_RUNNING) {
            $this->Msg("File already has state set: " . $filepath);
            return 0;
        }

// if the hash value matches, set the state to OK
        $oldhash = $datarow["hash"];
        if ($oldhash == $hash) {    // compare file hash with database hash
            $query = "Update baseline Set state = " . self::STATE_OK . ", date_checked = NOW() Where filepath = '" . addslashes($filepath) . "'";
            $result = $this->mysqli->query($query);
            if ($result === false) {
                $this->ErrorMsg("Unable to reset entry, to OK, for " . $filepath);
                return 2;
            }
            $this->Msg("OK: " . $filepath);
            return 0; // we're done for this file
        }

// the hash value doesn't match, so set the state to CHANGED

        $query = "UPDATE `baseline` SET `state`=" . self::STATE_CHANGED . ",`hash` = '" . $hash . "' WHERE `filepath` = '" . addslashes($filepath) . "'";
        $result = $this->mysqli->query($query);
        if ($result === false) {
            $this->ErrorMsg("Unable to add CHANGED entry for " . $filepath);
            return 2;
        }
        $this->Msg("CHANGED: " . $filepath);
        return 0;
    }

    function setRunning() {
        $query = "Update baseline Set state = " . self::STATE_RUNNING;
        $result = $this->mysqli->query($query);
        if ($result === false) {
            $this->ErrorMsg("Unable to set Running tags");
            return false;
        }
        return true;
    }

    function setDeleted() {
        $query = "Select filepath From baseline Where state = '" . self::STATE_RUNNING . "'";
        $result1 = $this->mysqli->query($query);
        if ($result1) {
// Cycle through results
            while ($obj = mysqli_fetch_object($result1)) {
                $this->Msg("DELETED: " . $obj->filepath);
            }
            /* free result set */
            mysqli_free_result($result1);
        }
        $query = "Update baseline Set state = " . self::STATE_DELETED . " Where state=" . self::STATE_RUNNING;
        $result2 = $this->mysqli->query($query);
        if ($result2 === false) {
            $this->ErrorMsg("Unable to set Deleted state");
            return false;
        }
        return true;
    }

    function removeDeleted() {
        $query = "DELETE FROM baseline WHERE state=" . self::STATE_DELETED;
        $result = $this->mysqli->query($query);
        if ($result === false) {
            $this->ErrorMsg("Unable to remove Deleted records");
            return false;
        }
        return true;
    }

    function listFilesInState($state) {
        $text = "";
        $result = $this->mysqli->query("SELECT filepath FROM baseline WHERE state='" . $state . "'");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $text .= "    <li>" . $row[0] . "</li>" . PHP_EOL;
            }

            /* free result set */
            $result->close();
        }
        return $text;
    }

    function displayOptions($path, $skipFolders, $processExtensions) {
        $text = "";
        $text .= "<p>Scanning directory - " . $path . " and its sub directories</p>" . PHP_EOL;
        if (!$skipFolders == NULL) {
            $text .= "<p>Excluding the following directories</p><ul>" . PHP_EOL;
            foreach ($skipFolders as $value) {
                $text .= "<li>" . $value . "</li>" . PHP_EOL;
            }
            $text .= "</ul>" . PHP_EOL;
        }
        if (!$processExtensions == NULL) {

            $text .= "<p>File types being scanned</p><ul><li>" . PHP_EOL;
            $first = true;
            foreach ($processExtensions as $value) {
                if (!$first) {
                    $text.=", ";
                } else {
                    $first = false;
                }
                $text .= $value;
            }
            $text .= "</li></ul>" . PHP_EOL;
        }
        return $text;
    }

    function emailResults($email, $emailinterval) {
        $tested = $this->getLastRunDate();
        $this->report .= "<p>Last tested $tested.</p>" . PHP_EOL;

        $lastemailsent = $this->getLastEmailSentRunDate();
        $this->report .= "<p>Last email sent $lastemailsent.</p>" . PHP_EOL;

//	E-Mail Results
// 	display discrepancies

        $this->report.= $this->summaryReport();
        $send = $this->sendEmail($lastemailsent, $emailinterval);
        if ($this->total == 0) {
            $title = "WebMonitor: " . $this->domain . '  Integrity Report v' . VERSION_NUMBER;
        } else {
            $title = "WebMonitor: " . $this->domain . '  Change Report (' . $this->total . ') v' . VERSION_NUMBER;
        }
        $headers = "From: admin@" . $this->domain . "\r\n";
        $headers .= "Content-type: text/html\r\n";
        $mailed = false;
        echo $this->report;
        if ($send) {
            $mailed = mail($email, $title, $this->report, $headers);
        }
        $this->recordtestDate($mailed);
    }

    function getLastRunDate() {
        $tested = "";
        $result = $this->mysqli->query("SELECT tested FROM tested ORDER BY tested DESC LIMIT 1");
        if ($result) {
            $row = $result->fetch_row();
            $tested = $row[0];
        } else {
            $this->ErrorMsg('Unable to retrieve last test date(' . $this->mysqli->errno . ') '
                    . $this->mysqli->error);
        }
        $this->Msg("Last scan date " . $tested);
        return $tested;
    }

    function getLastEmailSentRunDate() {
        $lastemailsent = '';
        $result = $this->mysqli->query("SELECT tested FROM tested WHERE emailsent=true ORDER BY tested DESC LIMIT 1");
        if ($result) {
            $row = $result->fetch_row();
            $lastemailsent = $row[0];
        }
        return $lastemailsent;
    }

    function sendEmailAnyway($lastemailsent, $emailinterval) {
        // return boolean if we last sent email outside interval
        $emailreport = false;
        $today = new DateTime(NULL);
        $date = new DateTime($lastemailsent);
        $interval = new DateInterval($emailinterval);
        $date->add($interval);
        if ($date < $today) {
            $emailreport = true;
        }
        return $emailreport;
    }

    function summaryReport() {
        $text = "";
        $this->new = $this->GetStateCount(self::STATE_NEW);
        $this->changed = $this->GetStateCount(self::STATE_CHANGED);
        $this->deleted = $this->GetStateCount(self::STATE_DELETED);
        $this->total = $this->new + $this->changed + $this->deleted;
        If ($this->total == 0) {
            $text .= "<p>File structure has NOT changed.</p>" . PHP_EOL;
        } else {
            $text .= "<p>File structure has changed:-</p><ul>" . PHP_EOL;
            $text .= "<li>     NEW files:" . $this->new . "</li>" . PHP_EOL;
            $text .= "<li>     CHANGED files:" . $this->changed . "</li>" . PHP_EOL;
            $text .= "<li>     DELETED files:" . $this->deleted . "</li></ul>" . PHP_EOL;
            $text .= "<p> </p>" . PHP_EOL;
            $text .= "<p>PLEASE review the changes, if you expected these changes then ignore this email</p>" . PHP_EOL;
            $text .= "<p>This email should also go to Ramblers-webs administrators who will also review the changes</p>" . PHP_EOL;
            $text .= "<p>If you have any concerns that your site has been hacked then please raise a Support Issue</p>" . PHP_EOL;
            $text .= "<p> </p>" . PHP_EOL;
            if ($this->new > 0) {
                $text.="<p>     NEW files</p>" . PHP_EOL;
                $text.="<ul>" . PHP_EOL;
                $text.=$this->listFilesInState(self::STATE_NEW);
                $text.="</ul>" . PHP_EOL;
            }
            if ($this->changed > 0) {
                $text.="<p>     CHANGED files</p>" . PHP_EOL;
                $text.="<ul>" . PHP_EOL;
                $text.=$this->listFilesInState(self::STATE_CHANGED);
                $text.="</ul>" . PHP_EOL;
            }
            if ($this->deleted > 0) {
                $text.="<p>     DELETED files</p>" . PHP_EOL;
                $text.="<ul>" . PHP_EOL;
                $text.= $this->listFilesInState(self::STATE_DELETED);
                $text.="</ul>" . PHP_EOL;
            }
        }
        $text .= "<p> </p>" . PHP_EOL;
        $text .= "<p> </p>" . PHP_EOL;
        $text .= "<p>PHP version: " . PHP_VERSION . "  Monitor system version: " . VERSION_NUMBER . "</p>" . PHP_EOL;
        $text .= "<p>---------------------------------</p>" . PHP_EOL;
        return $text;
    }

    function GetStateCount($state) {
        $result = $this->mysqli->query("SELECT COUNT(*) FROM `baseline` WHERE state=" . $state);
        $row = $result->fetch_row();
        return intval($row[0]);
    }

    function sendEmail($lastemailsent, $emailinterval) {
        $emailreport = false;
        If ($this->total == 0) {
            $emailreport = $this->sendEmailAnyway($lastemailsent, $emailinterval);
        } else {
            $emailreport = true;
        }
        return $emailreport;
    }

    function recordTestDate($mailed) {
        $today = new DateTime(NULL);
        $when = $today->format('Y-m-d H:i:s');
        $result = $this->mysqli->query("INSERT INTO `tested` (`tested`, `emailsent`"
                . ",`total`,`new`,`changed`,`deleted`) VALUES ('$when', '$mailed',"
                . "'$this->total','$this->new','$this->changed','$this->deleted')");
    }

    function deleteOldTestedRecords() {
        // delete records older then a year
        $today = new DateTime(NULL);
        $date = $today;
        $date->sub(new DateInterval('P365D'));
        $formatdate = $date->format('Y-m-d');
        $result = $this->mysqli->query("DELETE FROM tested WHERE tested < '$formatdate'");
        if (!$result) {
            $this->ErrorMsg('Unable to delete old records in tested table(' . $this->mysqli->errno . ') '
                    . $this->mysqli->error);
        }
    }

    function ErrorMsg($text) {
        $today = new DateTime(NULL);
        $when = $today->format('Y-m-d H:i:s');
        fwrite($this->logfile, $when . "  ERROR: " . $text . "\n");
        echo "<p>" . $when . "  ERROR: " . $text . "</p>" . PHP_EOL;
    }

    function Msg($text) {
        $today = new DateTime(NULL);
        $when = $today->format('Y-m-d H:i:s');
        fwrite($this->logfile, $when . "  " . $text . "\n");
// echo $text . "<br/>" . PHP_EOL;
    }

}

?>

