<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Value\StrictEnum;

/**
 * @method static ChocolateBar Mars()
 * @method static ChocolateBar Snickers()
 * @method static ChocolateBar Twix()
 */
class ChocolateBar extends StrictEnum
{
    protected static function getValues(): array
    {
        return [
            'Mars',
            'Snickers',
            'Twix',
        ];
    }
}
