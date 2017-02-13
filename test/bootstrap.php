<?php
// adapted from https://gist.github.com/adriengibrat/4761717
spl_autoload_register(function ($class) {
    $roots = [
        __DIR__ . '/../src/',
        __DIR__ . '/unit/',
    ];
    $filename = preg_replace('#\\\|_(?!.+\\\)#', '/', $class) . '.php';
    foreach ($roots as $root) {
        $path = $root . $filename;
        if (stream_resolve_include_path($path)) {
            require $path;
            return;
        }
    }
});

require_once __DIR__ . '/../lib/composed/autoload.php';
