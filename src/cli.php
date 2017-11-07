<?php
require_once('vendor/autoload.php');
require_once(dirname(__FILE__) . '/document.php');
require_once(dirname(__FILE__) . '/helpers.php');


// Main program

// Parse
$paths = [];
$edit = false;
$sync = false;
$exit_first = false;
foreach (array_slice($argv, 1) as $arg) {
    if (in_array($arg, ['-e', '--edit'])) {
        $edit = true;
    } else if (in_array($arg, ['-s', '--sync'])) {
        $sync = true;
    } else if (in_array($arg, ['-x', '--exit-first'])) {
        $exit_first = true;
    } else {
        array_push($paths, $arg);
    }
}

// Prepare
$config = read_config();
$documents = new DocumentList($paths, $config);

// Edit
if ($edit) {
    $documents->edit();

// Sync
} else if ($sync) {
  $documents->sync();

// Test
} else {
  $success = $documents->test($exit_first);
  if (!$success) {
    exit(1);
  }
}
