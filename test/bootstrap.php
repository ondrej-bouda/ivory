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

set_exception_handler(function (Throwable $t) {
    fprintf(STDERR,
        "Uncaught %s: %s\nThrown from %s:%d\n%s\n",
        get_class($t),
        $t->getMessage(),
        $t->getFile(),
        $t->getLine(),
        $t->getTraceAsString()
    );
    exit(2);
});
