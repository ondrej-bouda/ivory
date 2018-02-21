<?php
declare(strict_types=1);
namespace Ivory\Documentation;

/**
 * @method static SmsProtocol SMPP()
 * @method static SmsProtocol REST()
 * @method static SmsProtocol Jupiter()
 */
class SmsProtocol extends \Ivory\Value\StrictEnum
{
    protected static function getValues(): array
    {
        return [
            'SMPP',
            'REST',
            'Jupiter',
        ];
    }
}
