<?php

// auto load where Capital letters imply folder
// e.g. TestClassTest is stored in test/class/test.php

function __autoload($class_name) {
    $lowerclass = strtolower($class_name);
    $file = "classes";
    $len = strlen($class_name);

    for ($i = 0; $i < $len; $i++) {
        $a = substr($lowerclass, $i, 1);
        $b = substr($class_name, $i, 1);
        if ($a == $b) {
            $file .=$a;
        } else {
            $file .="/" . $a;
        }
    }
    $file .= ".php";
   // echo $file."<br />";
    if (file_exists($file)) {
        require_once( $file );
    } else {
        echo "Unable to find class file: " . $file;
    }
}
