<?php
declare(strict_types=1);

namespace Ivory\Data\Map;

class ArrayRelationMap implements \IteratorAggregate, IRelationMap
{
    use ArrayMapMacros;

    //region ArrayMapMacros

    protected function isNestedMap($entry): bool
    {
        return ($entry instanceof IRelationMap);
    }

    //endregion
}
