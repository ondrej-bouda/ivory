<?php
declare(strict_types=1);
namespace Ivory\Documentation;

class Ltree
{
    private $labels;

    public static function fromArray(array $labels)
    {
        foreach ($labels as $label) {
            self::checkLabel($label);
        }
        return new Ltree($labels);
    }

    protected static function checkLabel(string $label): void
    {
        if (!preg_match('~ ^ [[:alnum:]_]+ (?: \. [[:alnum:]_]+ )* ~ux', $label)) {
            throw new \InvalidArgumentException('Invalid label used for a label path');
        }
    }

    protected function __construct(array $labels)
    {
        $this->labels = $labels;
    }

    public function toArray(): array
    {
        return $this->labels;
    }

    public function join(Ltree $other): Ltree
    {
        return new Ltree(array_merge($this->labels, $other->labels));
    }
}
