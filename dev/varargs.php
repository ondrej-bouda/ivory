<?php
function foo($lead1, $lead2, ...$varArgs) // non-variable trailing arguments would lead to a parse error
{
	var_export($varArgs);
	echo "\n";
}

foo(...range(1, 5)); // prints [3, 4, 5]
foo(...[1 => 'a', 3 => 'b']); // prints []
foo(...[-2 => 'a', -1 => 'b', 0 => 'c', 1 => 'd']); // prints ['c', 'd']
