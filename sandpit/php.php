<?php
namespace Ivory\Sandpit;

class Foo { }

echo gettype(Foo::class); // poor PHP, it is a mere string so, if given as an argument, it cannot be told from strings
echo "\n";



echo "$* $1";
echo "\n";



$obj = new \stdClass();
$obj->{'edoo.user'} = 4;
var_dump((array)$obj);



class Bar {
    const ARR = [1,2,3];
}

//var_dump(isset(Bar::ARR[1])); // isset() cannot be used on expressions
