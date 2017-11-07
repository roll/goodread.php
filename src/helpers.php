<?php
use Colors\Color;
use Spatie\Emoji\Emoji;
use Symfony\Component\Yaml\Yaml;
$state = ['last_message_type' => null];


// Module API

function read_config() {
    $config = ['documents' => ['README.md']];
    if (is_file('goodread.yml')) {
        $config = Yaml::parse(file_get_contents('goodread.yml'));
    }
    foreach ($config['documents'] as $index => $document) {
        if (is_array($document)) {
            if (!array_key_exists('main', $document)) {
                throw new Exception('Document requires "main" property');
            }
        }
        if (is_string($document)) {
            $config['documents'][$index] = ['main' => $document];
        }
    }
    return $config;
}


function run_codeblock($codeblock) {
    $lines = [];
    foreach (explode("\n", trim($codeblock)) as $line) {
        if (strpos($line, ' // ') !== false) {
            [$left, $right] = explode(' // ', $line);
            $left = trim($left);
            $right = trim($right);
            if ($left && $right) {
                $message = $left . ' != ' . $right;
                $line = "if({$left} != {$right}) {throw new Exception('{$message}');}";
            }
        }
        array_push($lines, $line);
    }
    $exception_line = 1000; // infinity
    $exception = null;
    try {
        // TODO: implement scope
        eval(join("\n", $lines));
    } catch (Exception $exc) {
        $exception = $exc;
        $exception_line = $exc->getLine();
    }
    return [$exception, $exception_line];
}


function print_message($message, $type, $level=null, $exception=null, $passed=null, $failed=null, $skipped=null) {
    global $state;
    $text = '';
    $colorize = new Color();
    if ($type === 'blank') {
        return print('');
    } else if ($type === 'separator') {
        $text = str_repeat(Emoji::heavyMinusSign(), 3);
    } else if ($type === 'heading') {
        $text = ' ' . str_repeat('#', $level) . '  ';
        $text .= $colorize($message)->bold();
    } else if ($type === 'success') {
        $text = $colorize(' ' . Emoji::heavyCheckMark() . '  ')->green();
        $text .= $message;
    } else if ($type === 'failure') {
        $text = $colorize(' ' . Emoji::crossMark() . '  ')->red();
        $text .= $message;
        $text .= $colorize("\nException: {$exception->getMessage()}")->bold()->red();
    } else if ($type === 'scope') {
        $scope = join(", ", $message);
        $text .= "---\n\n";
        $text .= "Scope (current execution scope):\n";
        $text .= "[{$scope}]\n";
        $text .= "\n---\n";
    } else if ($type === 'skipped') {
        $text = $colorize(' ' . Emoji::heavyMinusSign() . '  ')->yellow();
        $text .= $message;
    } else if ($type === 'summary') {
        $color = 'green';
        $text = $colorize(' ' . Emoji::heavyCheckMark() . '  ')->bold()->green() . '';
        if (($failed + $skipped) > 0) {
            $color = 'red';
            $text = $colorize(' ' . Emoji::crossMark() . '  ')->bold()->red() . '';
        }
        $count = $passed + $failed + $skipped;
        $text .= $colorize("{$message}: {$passed}/{$count}\n")->bold()->fg($color);
    }
    if (in_array($type, ['success', 'failure', 'skipped'])) {
        $type = 'test';
    }
    if ($text) {
        if ($state['last_message_type'] !== $type) {
            $text = "\n" . $text;
        }
        print("${text}\n");
    }
    $state['last_message_type'] = $type;
}
