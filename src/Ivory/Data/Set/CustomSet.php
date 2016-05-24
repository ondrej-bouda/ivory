<?php
namespace Ivory\Data\Set;

use Ivory\Relation\Alg\CallbackValueHasher;
use Ivory\Relation\Alg\IValueHasher;

/**
 * {@inheritdoc}
 *
 * This implementation employs a custom function converting the input values to dictionary keys.
 */
class CustomSet extends DictionarySet
{
    private $converter;

    /**
     * @param IValueHasher|callable $converter given a value to store in the set, expected to return either an int or a
     *                                           string
     */
    public function __construct($converter)
    {
        if ($converter instanceof IValueHasher) {
            $this->converter = $converter;
        }
        else {
            $this->converter = new CallbackValueHasher($converter);
        }
    }

    protected function computeKey($value)
    {
        return $this->converter->hash($value);
    }
}