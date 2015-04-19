<?php

/**
 * Description of logfile
 *
 * @author Chris Vaughan
 */
class Logfile {

    private static $logfile;

    static function create($name) {
        self::$logfile = fopen($name, "w") or die("Unable to open logfile file!");
    }

    static function write($text) {
        if (isset(self::$logfile)) {
            fwrite(self::$logfile, $text . "\n");
        }
    }

    static function writeWhen($text) {
        $today = new DateTime(NULL);
        $when = $today->format('Y-m-d H:i:s');
        self::write($when . ": " . $text);
    }

    static function writeError($text) {
        self::writeWhen(" ERROR: " . $text);
    }
     function errorMsg($text) {
        self::writeWhen("  ERROR: " . $text . "\n");
        $today = new DateTime(NULL);
        $when = $today->format('Y-m-d H:i:s');
        echo "<p>" . $when . "  ERROR: " . $text . "</p>" . PHP_EOL;
    }

    static function close() {
        if (isset(self::$logfile)) {
            fclose(self::$logfile);
        }
    }

}
