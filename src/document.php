<?php


// Module API

class DocumentList {

    // Public

    function __construct($paths, $config) {
        $this->_documents = [];
        $paths = $paths ? $paths : array_map(function ($document) {return $document['main'];}, $config['documents']);
        $paths = $paths ? $paths : ['README.md'];
        foreach ($paths as $path) {
            $main_path = $path;
            $edit_path = null;
            $sync_path = null;
            foreach ($config['documents'] as $item) {
                if ($path === $item['main']) {
                    $edit_path = $item['edit'] ?? null;
                    $sync_path = $item['sync'] ?? null;
                    break;
                }
            }
            $document = new Document($main_path, $edit_path, $sync_path);
            array_push($this->_documents, $document);
        }
    }

    function edit() {
        foreach ($this->_documents as $document) {
            $document->edit();
        }
    }

    function sync() {
        $success = true;
        foreach ($this->_documents as $document) {
            $valid = $document->test(true);
            $success = $success && $valid;
            if ($valid) {
                $document->sync();
            }
        }
        return $success;
    }

    function test($exit_first=false) {
        $success = true;
        foreach ($this->_documents as $index => $document) {
            $number = $index + 1;
            $valid = $document->test(null, null, $exit_first);
            $success = $success && $valid;
            print_message(null, $number < count($this->_documents) ? 'separator' : 'blank');
        }
        return $success;
    }

}


class Document {

    // Public

    function __construct($main_path, $edit_path, $sync_path) {
        $this->_main_path = $main_path;
        $this->_edit_path = $edit_path;
        $this->_sync_path = $sync_path;
    }

    function edit() {

        // No edit path
        if (!$this->_edit_path) {
            return;
        }

        // Check synced
        if ($this->_main_path !== $this->_edit_path) {
            $main_contents = _load_document($this->_main_path);
            $sync_contents = _load_document($this->_sync_path);
            if ($main_contents !== $sync_contents) {
                throw new Exception("Document '{$this->_edit_path}' is out of sync");
            }
        }

        // Remote document
        if (substr($this->_edit_path, 0, 4) === 'http') {
            system("xdg-open {$this->_edit_path}");

        // Local document
        } else {
            system("editor {$this->_edit_path}");
        }

    }

    function sync() {

        // No sync path
        if (!$this->_sync_path) {
            return;
        }

        // Save remote to local
        $contents = file_get_contents($this->_sync_path);
        file_put_contents($this->_main_path, $contents);

    }

    function test($sync=false, $return_report=false, $exit_first=false) {

        // No test path
        $path = $sync ? $this->_sync_path : $this->_main_path;
        if (!$path) {
            return true;
        }

        // Test document
        $contents = _load_document($path);
        $elements = _parse_document($contents);
        $report = _validate_document($elements, $exit_first);

        return $return_report ? $report : $report['valid'];

    }

}


// Internal

function _load_document($path) {

    // Remote document
    if (substr($path, 0, 4) === 'http') {
        return file_get_contents($path);

    // Local document
    } else {
        return file_get_contents($path);
    }

}


function _parse_document($contents) {
    $elements = [];
    $codeblock = '';
    $capture = false;

    // Parse file lines
    foreach (explode("\n", $contents) as $line) {

        // Heading
        if (substr($line, 0, 1) === '#') {
            $heading = trim($line, " #\n");
            $level = strlen($line) - strlen(trim($line, '#'));
            if ($elements &&
                    $elements[count($elements) - 1]['type'] === 'heading' &&
                    $elements[count($elements) - 1]['level'] === $level) {
                continue;
            }
            array_push($elements, [
                'type' => 'heading',
                'value' => $heading,
                'level' => $level,
            ]);
        }

        // Codeblock
        if (substr($line, 0, 6) === '```php') {
            if (strpos($line, 'goodread') !== false) {
                $capture = true;
            }
            $codeblock = '';
            continue;
        }
        if (substr($line, 0, 3) === '```') {
            if ($capture) {
                array_push($elements, [
                    'type' => 'codeblock',
                    'value' => $codeblock,
                ]);
            }
            $capture = false;
        }
        if ($capture and trim($line)) {
            $codeblock .= "${line}\n";
            continue;
        }

    }

    return $elements;

}


function _validate_document($elements, $exit_first=false) {
    $scope = [];
    $passed = 0;
    $failed = 0;
    $skipped = 0;
    $title = null;
    $exception = null;

    // Test elements
    foreach ($elements as $element) {

        // Heading
        if ($element['type'] === 'heading') {
            print_message($element['value'], 'heading', $element['level']);
            if (!$title) {
                $title = $element['value'];
                print_message(null, 'separator');
            }

        // Codeblock
        } else if ($element['type'] === 'codeblock') {
            [$exception, $exception_line] = run_codeblock($element['value'], $scope);
            $lines = explode("\n", trim($element['value']));
            foreach ($lines as $index => $line) {
                $line_number = $index + 1;
                if ($line_number < $exception_line) {
                    print_message($line, 'success');
                    $passed += 1;
                } else if ($line_number === $exception_line) {
                    print_message($line, 'failure', null, $exception);
                    if ($exit_first) {
                        print_message($scope, 'scope');
                        throw $exception;
                    }
                    $failed += 1;
                } else if ($line_number > $exception_line) {
                    print_message($line, 'skipped');
                    $skipped += 1;
                }
            }
        }

    }

    // Print summary
    if ($title) {
        print_message($title, 'summary', null, null, $passed, $failed, $skipped);
    }

    return [
        'valid' => ($exception === null),
        'passed' => $passed,
        'failed' => $failed,
        'skipped' => $skipped,
    ];
}
