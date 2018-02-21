<?php
declare(strict_types=1);
namespace Ivory\Documentation;

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
class Planet extends \Ivory\Value\StrictEnum
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
