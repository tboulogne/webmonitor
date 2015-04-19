<?php

/**
 * Description of scandatabase
 *
 * @author Chris Vaughan
 */
class Scandatabase extends Database {

    const STATE_OK = 0;
    const STATE_RUNNING = 1;
    const STATE_NEW = 2;
    const STATE_CHANGED = 3;
    const STATE_DELETED = 4;

    private $total = 0;
    private $new = 0;
    private $changed = 0;
    private $deleted = 0;
    private $calcTotals = true;

    //put your code here

    function checkTables() {
        if (!parent::tableExists('baseline')) {
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
            $ok = parent::runQuery($text);
            if ($ok) {
                Logfile::writeWhen("Table 'baseline' created");
            } else {
                Logfile::errorMsg("Table creation 'baseline' FAILED");
            }
        }
        if (!parent::tableExists('tested')) {
            $text = "CREATE TABLE `tested` (
  `tested` datetime NOT NULL,
  `total` int(11),
  `new` int(11),
  `changed` int(11),
  `deleted` int(11),
  `emailsent` tinyint(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
            $ok = parent::runQuery($text);
            if ($ok) {
                Logfile::writeWhen("Table 'tested' created");
            } else {
                Logfile::errorMsg("Table creation 'tested' FAILED");
            }
        }
    }

//-------------------------------------------------------------------------------
// Process one file
// Returns 0 if no errors, 1 for a file error, or 2 for a database error
//

    function process_file($filepath) {
        $utf8_filepath = utf8_encode($filepath);

        if (!is_readable($filepath)) {
            Logfile::errorMsg("Unreadable file: $filepath");
            return 1;
        }

        $hash = hash_file("md5", $filepath);
        if (!$hash) {
            Logfile::errorMsg("Unable to calculate hash for: $filepath");
            return 1;
        }

        $query = "Select hash, state From baseline Where filepath = '" . addslashes($filepath) . "'";
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::writeWhen("Select from baseline failed for file: " . $filepath);
            return 2;
        }

// if the file is not registered in the database, insert it in state NEW
        $res = parent::getResult();
        $norows = $res->num_rows;
        if ($norows == 0) {
            $query = "Insert into baseline (filepath, hash, state,  date_added, date_checked)
			values ('" . addslashes($filepath) . "', '$hash', " . self ::STATE_NEW . ", NOW(), 0)";
            $ok = parent::runQuery($query);
            if (!$ok) {
                Logfile::errorMsg("Unable to add NEW entry for " . $filepath);
                return 2;
            }
            Logfile::writeWhen("NEW: " . $filepath);
            return 0; // we're done for this file
        }

// here when the file is in the database
        // if it already has a problem flagged, leave it alone
        $datarow = $res->fetch_array();
        if ($datarow["state"] != self::STATE_RUNNING) {
            Logfile::writeWhen("File already has state set: " . $filepath);
            return 0;
        }

// if the hash value matches, set the state to OK
        $oldhash = $datarow["hash"];
        if ($oldhash == $hash) {    // compare file hash with database hash
            $query = "Update baseline Set state = " . self::STATE_OK . ", date_checked = NOW() Where filepath = '" . addslashes($filepath) . "'";
            $ok = parent::runQuery($query);
            if (!$ok) {
                Logfile::errorMsg("Unable to reset entry, to OK, for " . $filepath);
                return 2;
            }
            Logfile::writeWhen("OK: " . $filepath);
            return 0; // we're done for this file
        }

// the hash value doesn't match, so set the state to CHANGED

        $query = "UPDATE `baseline` SET `state`=" . self::STATE_CHANGED . ",`hash` = '" . $hash . "' WHERE `filepath` = '" . addslashes($filepath) . "'";
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::errorMsg("Unable to add CHANGED entry for " . $filepath);
            return 2;
        }
        Logfile::writeWhen("CHANGED: " . $filepath);
        return 0;
    }

    function setRunning() {
        $query = "Update baseline Set state = " . self::STATE_RUNNING;
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::errorMsg("Unable to set Running tags");
            return false;
        }
        return true;
    }

    function setDeleted() {
        $query = "Select filepath From baseline Where state = '" . self::STATE_RUNNING . "'";
        $ok = parent::runQuery($query);
        if ($ok) {
// Cycle through results
            $result = parent::getResult();
            while ($obj = mysqli_fetch_object($result)) {
                Logfile::writeWhen("DELETED: " . $obj->filepath);
            }
            /* free result set */
            mysqli_free_result($result);
        } else {
            Logfile::writeWhen("EREOR getting deleted file name: " . parent::error());
        }
        $query = "Update baseline Set state = " . self::STATE_DELETED . " Where state=" . self::STATE_RUNNING;
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::errorMsg("Unable to set Deleted state");
            return false;
        }
        return true;
    }

    function removeDeleted() {
        $query = "DELETE FROM baseline WHERE state=" . self::STATE_DELETED;
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::errorMsg("Unable to remove Deleted records");
            return false;
        }
        return true;
    }

    function listFilesInState($state) {
        $text = "";
        $ok = parent::runQuery("SELECT filepath FROM baseline WHERE state='" . $state . "'");
        if ($ok) {
            $result = parent::getResult();
            while ($row = $result->fetch_row()) {
                $text .= "    <li>" . $row[0] . "</li>" . PHP_EOL;
            }

            /* free result set */
            $result->close();
        }
        return $text;
    }

    function recordTestDate($mailed) {
        $this->total = $this->getTotals();
        $today = new DateTime(NULL);
        $when = $today->format('Y-m-d H:i:s');
        $ok = parent::runQuery("INSERT INTO `tested` (`tested`, `emailsent`"
                        . ",`total`,`new`,`changed`,`deleted`) VALUES ('$when', '$mailed',"
                        . "'$this->total','$this->new','$this->changed','$this->deleted')");

        if ($ok) {
            //  $result=  parent::getResult();
        }
    }

    function summaryReport() {
        $text = "";
        If ($this->total === 0) {
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
                $text .= $this->listFilesInState(self::STATE_CHANGED);
                $text.="</ul>" . PHP_EOL;
            }
            if ($this->deleted > 0) {
                $text.="<p>     DELETED files</p>" . PHP_EOL;
                $text .= "<ul>" . PHP_EOL;
                $text.= $this->listFilesInState(self::STATE_DELETED);
                $text .= "</ul>" . PHP_EOL;
            }
        }
        $text .= "<p> </p>" . PHP_EOL;
        $text .= "<p> </p>" . PHP_EOL;
        $text .= "<p>PHP version: " . PHP_VERSION . "  Monitor system version: " . VERSION_NUMBER . "</p>" . PHP_EOL;
        $text .= "<p>---------------------------------</p>" . PHP_EOL;
        return $text;
    }

    function deleteOldTestedRecords() {
        // delete records older then a year
        $today = new DateTime(NULL);
        $date = $today;
        $date->sub(new DateInterval('P365D'));
        $formatdate = $date->format('Y-m-d');
        $ok = parent::runQuery("DELETE FROM tested WHERE tested < '$formatdate'");
        if (!$ok) {
            Logfile::errorMsg('Unable to delete old records in tested table(' . parent::error());
        }
    }

    function getLastRunDate() {
        $tested = "";
        $ok = parent::runQuery("SELECT tested FROM tested ORDER BY tested DESC LIMIT 1");
        if ($ok) {
            $result = parent::getResult();
            $row = $result->fetch_row();
            $tested = $row[0];
        } else {
            Logfile::errorMsg('Unable to retrieve last test date(' . parent::error());
        }
        Logfile::writeWhen("Last scan date " . $tested);
        return $tested;
    }

    function getLastEmailSentRunDate() {
        $lastemailsent = '';
        $ok = parent::runQuery("SELECT tested FROM tested WHERE emailsent=true ORDER BY tested DESC LIMIT 1");
        if ($ok) {
            $result = parent::getResult();
            $row = $result->fetch_row();
            $lastemailsent = $row[0];
        }
        return $lastemailsent;
    }

    function GetStateCount($state) {
        $ok = parent::runQuery("SELECT COUNT(*) FROM `baseline` WHERE state=" . $state);
        $result = parent::getResult();
        $row = $result->fetch_row();
        return intval($row[0]);
    }

    function getTotals() {
        if ($this->calcTotals) {
            $this->new = $this->GetStateCount(self::STATE_NEW);
            $this->changed = $this->GetStateCount(self::STATE_CHANGED);
            $this->deleted = $this->GetStateCount(self::STATE_DELETED);
            $this->total = $this->new + $this->changed + $this->deleted;
            $this->calcTotals = false;
        }
        return $this->total;
    }

}
