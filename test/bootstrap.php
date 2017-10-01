<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

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
