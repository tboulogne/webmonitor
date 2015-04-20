<?php

class Scan {

    private $db;
    private $report;

    //const SQL_DATE_FORMAT = "%Y-%m-%d %H:%M:%S"; // strftime() format

    function __construct($dbconfig, $domain) {

        $this->db = new Scandatabase($dbconfig);
        $this->domain = $domain;

        $this->report = "";
        Logfile::create("logfile.log");
        Logfile::writeWhen("Logfile created");
        $this->report = "<p>Report of changes to domain <b>" . $this->domain . "</b></p>" . PHP_EOL;
    }

    function __destruct() {
        Logfile::writeWhen("Logfile closed");
        Logfile::close();
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
            if ($isDot) {
                $process = false;
            } else {
                if ($this->skipThisFolder($subpath, $skipFolders)) {
                    $process = false;
                    Logfile::writeWhen("EXCLUDED: " . $filename);
                }
            }

            if ($process) {
                $this->db->process_file($filename);
                $i+=1;
                $no+=1;
                if ($i >= 500) {
                    echo $no . " - files processed<br/>";
                    set_time_limit(20);
                    $i = 0;
                }
            }
            $iter->next();
        }
// done, now set any items that are not changed to Deleted

        $this->db->setDeleted();
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

    Function skipThisFolder($subpath, $skipFolders) {
        if (!isset($subpath)) {
            return false;
        }
        if ($subpath === "") {
            return false;
        }

        if (empty($skipFolders)) {
            return false;
        }
        if (strpos($subpath, '\tmp' . DIRECTORY_SEPARATOR) > 0) {
            $ok = 1;
        }
        foreach ($skipFolders as $value) {
            $ok = $this->isFolderSame($subpath . DIRECTORY_SEPARATOR, $value);
            if ($ok) {
                return true;
            }
        }
        return false;
    }

    function isFolderSame($folder, $skipfolder) {
        $parts = explode(DIRECTORY_SEPARATOR, $folder);
        $skipparts = explode(DIRECTORY_SEPARATOR, $skipfolder);
        unset($parts[count($parts) - 1]);
        unset($skipparts[count($skipparts) - 1]);
        if (count($parts) >= count($skipparts)) {
            foreach ($skipparts as $key => $value) {
                //  echo $value . "  " . $key . "  " . $parts[$key];
                if (!fnmatch($value, $parts[$key])) {
                    return false;
                }
                //  if ($value == "*") {
                //      $value = $parts[$key];
                //  }
                //  If ($value != $parts[$key]) {
                //      return false;
                //  }
            }
            return true;
        } else {
            return false;
        }
    }

    function startsWith($haystack, $needle) {
        return $needle === "" || strpos($haystack, $needle) === 0;
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

    function emailResults($email, $emailinterval, $running) {
        $mailed = false;
        $title = "WebMonitor: ";
        if ($running) {
            $this->report.="<h2>ERROR</h2><p>Last scan failed to complete, displaying results from last scan</p>";
            $title .= "ERROR: ";
        }
        $tested = $this->db->getLastRunDate();
        $this->report .= "<p>Last tested $tested.</p>" . PHP_EOL;
        $lastemailsent = $this->db->getLastEmailSentRunDate();
        $this->report .= "<p>Last email sent $lastemailsent.</p>" . PHP_EOL;

//	E-Mail Results
// 	display discrepancies
        $send = $this->sendEmail($lastemailsent, $emailinterval, $running);
        $this->report.= $this->db->summaryReport();

        echo $this->report;
        if ($send) {
            $witherrors = " ";
            if (Logfile::getNoErrors() > 0) {
                $witherrors = " (with errors) ";
            }
            if ($this->db->getTotals() === 0) {
                $title .= $witherrors . $this->domain . '  Integrity Report v' . VERSION_NUMBER;
            } else {
                $title .= $this->domain . '  Change Report (' . $this->db->getTotals() . ') v' . \VERSION_NUMBER;
            }
            $headers = "From: admin@" . $this->domain . "\r\n";
            $headers .= "Content-type: text/html\r\n";
            $mailed = mail($email, $title, $this->report, $headers);
            if (!$mailed) {
                Logfile::writeError("Email failed to be sent");
            }
        }
        $this->db->recordtestDate($mailed);
    }

    function sendEmail($lastemailsent, $emailinterval, $running) {
        $emailreport = false;
        If ($this->db->getTotals() === 0) {
            $emailreport = $this->sendEmailAnyway($lastemailsent, $emailinterval);
        } else {
            $emailreport = true;
        }
        if (Logfile::getNoErrors() > 0) {
            $emailreport = true;
        }
        if ($running) {
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
