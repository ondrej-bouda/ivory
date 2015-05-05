<?php
namespace Ppg\Sandpit;

class Foo { }

echo gettype(Foo::class); // poor PHP, it is a mere string so, if given as an argument, it cannot be told from strings
echo "\n";



echo "$* $1";
echo "\n";
