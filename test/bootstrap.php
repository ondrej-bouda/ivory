<?php
// taken from https://gist.github.com/adriengibrat/4761717
spl_autoload_register(function ($class) {
	$file = __DIR__ . '/../src/' . preg_replace('#\\\|_(?!.+\\\)#','/', $class) . '.php';
	if (stream_resolve_include_path($file))
		require $file;
});

require_once __DIR__ . '/../lib/composed/autoload.php';
