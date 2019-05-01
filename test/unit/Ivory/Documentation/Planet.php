<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Value\StrictEnum;

/**
 * @method static Planet Mercury()
 * @method static Planet Venus()
 * @method static Planet Earth()
 * @method static Planet Mars()
 * @method static Planet Jupiter()
 * @method static Planet Saturn()
 * @method static Planet Uranus()
 * @method static Planet Neptune()
 */
class Planet extends StrictEnum
{
    protected static function getValues(): array
    {
        return [
            'Mercury',
            'Venus',
            'Earth',
            'Mars',
            'Jupiter',
            'Saturn',
            'Uranus',
            'Neptune',
        ];
    }
}
