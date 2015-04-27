<?php

class Scan {

    private $db;
    private $report;

    //const SQL_DATE_FORMAT = "%Y-%m-%d %H:%M:%S"; // strftime() format

    function __construct($dbconfig, $domain) {

        $this->db = new ScanDatabase($dbconfig);
        $this->domain = $domain;

        $this->report = "";
        $this->report = "<p>Report of changes to domain <b>" . $this->domain . "</b></p>" . PHP_EOL;
    }

    function __destruct() {
        
    }

    function Connect() {
// open database
        if ($this->db->connect()) {
            $this->db->checkTables();
            return true;
        } else {
            return false;
        }
    }

    function scanFiles($path, $skipFolders, $processExtensions) {
        $this->report.=$this->displayOptions($path, $skipFolders, $processExtensions);
        $ok = $this->db->setRunning();
        If ($ok == false) {
            return;
        }
        $iter = new ScanIterator($path, 1000);
        $iter->addExtensions($processExtensions);
        $iter->addFolders($skipFolders);
        $iter->process($this, "processFile");
        $this->db->setDeleted();
        $this->report.="Total number of files scanned " . $iter->getNoProcessed();
    }

    function processFile($basepath,$filename) {
        //echo $filename . "<br/>";
        $this->db->process_file($basepath,$filename);
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

    function emailResults($email, $emailinterval, $alreadyRunning) {
        $mailed = false;
        $title = "WebMonitor: ";
        if ($alreadyRunning) {
            $this->report.="<h2>ERROR</h2><p>Last scan failed to complete, displaying results from last scan</p>";
            $title .= "ERROR: ";
        }
        $witherrors = " ";
        if (Logfile::getNoErrors() > 0) {
            $witherrors = " (with errors) ";
        }
        if ($this->db->getTotals() === 0) {
            $title .= $witherrors . $this->domain . '  Integrity Report v' . VERSION_NUMBER;
        } else {
            $title .= $this->domain . '  Change Report (' . $this->db->getTotals() . ') v' . \VERSION_NUMBER;
        }
        echo $title;
        $tested = $this->db->getLastRunDate();
        $this->report .= "<p>Last tested $tested.</p>" . PHP_EOL;
        $lastemailsent = $this->db->getLastEmailSentRunDate();
        $this->report .= "<p>Last email sent $lastemailsent.</p>" . PHP_EOL;

//	E-Mail Results
// 	display discrepancies
        $send = $this->sendEmail($lastemailsent, $emailinterval, $alreadyRunning);

        $this->report.= $this->db->summaryReport();

        echo $this->report;
        if ($send) {
            $headers = "From: admin@" . $this->domain . "\r\n";
            $headers .= "Content-type: text/html\r\n";
            $mailed = mail($email, $title, $this->report, $headers);
            if (!$mailed) {
                Logfile::writeError("Email failed to be sent");
            } else {
                Logfile::writeWhen("Email sent");
            }
        } else {
            Logfile::writeWhen("Email not required");
        }
        $this->db->recordtestDate($mailed);
    }

    function sendEmail($lastemailsent, $emailinterval, $alreadyRunning) {
        $emailreport = false;
        If ($this->db->getTotals() === 0) {
            $emailreport = $this->sendEmailAnyway($lastemailsent, $emailinterval);
        } else {
            $emailreport = true;
        }
        if (Logfile::getNoErrors() > 0) {
            $emailreport = true;
        }
        if ($alreadyRunning) {
            $emailreport = true;
        }
        return $emailreport;
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

    function deleteOldTestedRecords() {
        $this->db->removeDeleted();
    }

}
