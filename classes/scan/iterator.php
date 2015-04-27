<?php

/**
 * Description of scanfolders
 *
 * @author Chris Vaughan
 */
class ScanIterator {

    private $no = 0;
    private $resetNo = 0;
    private $resetTimeCount;
    private $extensions;
    private $folders;
    private $basePath;
    private $pathLen;

    function __construct($path, $resetCount) {
        $path = str_replace("/", DIRECTORY_SEPARATOR, $path);
        $this->basePath = $path;
        $this->pathLen = strlen($path);
        $this->resetTimeCount = $resetCount;
        $this->folders = [];
        $this->extensions = [];
    }

    function addExtensions($some) {
        If (isset($some)) {
            foreach ($some as $ext) {
                $ext = str_replace("/", DIRECTORY_SEPARATOR, $ext);
                $this->extensions[] = $ext;
            }
        }
    }

    function addFolders($some) {
        If (isset($some)) {
            foreach ($some as $folder) {
                $folder = str_replace("/", DIRECTORY_SEPARATOR, $folder);
                $this->folders[] = $folder;
            }
        }
    }

    function process($class, $func) {
        // $this->func = $func;
        // $class->$func($path);
        $this->getDirContents($this->basePath, $class, $func);
        return;
    }

    private function getDirContents($dir, $class, $func) {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $extension = self::getExtension($path);
                if ($this->processFile($extension)) {
                    if ($this->resetNo > 1000) {
                        set_time_limit(30);
                        $this->resetNo = 0;
                    }
                    $this->resetNo += 1;
                    $this->no += 1;
                    $subpath = substr($path, $this->pathLen+1);
                    $class->$func($this->basePath, $subpath);
                }
            } else if (is_dir($path) && $value != "." && $value != "..") {
                if ($this->processFolder($path)) {
                    $this->getDirContents($path, $class, $func);
                } else {
                    Logfile::writeWhen("Folder excluded: " . $path);
                }
            }
        }
    }

    function getNoProcessed() {
        return $this->no;
    }

    static function getExtension($path) {
        $parts = explode(".", $path);
        if (count($parts) == 1) {
            return null;
        }
        return $parts[count($parts) - 1];
    }

    private function processFile($extension) {
        if (empty($this->extensions)) {
            return true;
        }
        $extlower = strtolower($extension);
        if (in_array($extlower, $this->extensions)) {
            return true;
        }
        return false;
    }

    private Function processFolder($path) {
        $subpath = substr($path, $this->pathLen + 1);
        if (!isset($subpath)) {
            return true;
        }
        if ($subpath === "") {
            return true;
        }

        if (empty($this->folders)) {
            return true;
        }
        //   if (strpos($subpath, '\tmp' . DIRECTORY_SEPARATOR) > 0) {
        //       $ok = 1;
        //   }
        foreach ($this->folders as $value) {
            $ok = $this->isFolderSame($subpath . DIRECTORY_SEPARATOR, $value);
            if ($ok) {
                return false;
            }
        }
        return true;
    }

    private function isFolderSame($folder, $skipfolder) {
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
            }
            return true;
        } else {
            return false;
        }
    }

}
