<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class XmlContentTest extends TestCase
{
    public function testFromValue()
    {
        $xml = XmlContent::fromValue(
            /** @lang XML */
            '<root><a/><b><c/></b><a/></root>'
        );
        self::assertTrue($xml instanceof XmlDocument);

        $xml = XmlContent::fromValue(
            /** @lang TEXT */
            '<a/><b><c/></b><a/>'
        );
        self::assertFalse($xml instanceof XmlDocument);

        $xml = XmlContent::fromValue(
            /** @lang XML */
            '<?xml version="1.1" encoding="utf-8"?><root><a/><b><c/></b><a/></root>'
        );
        self::assertTrue($xml instanceof XmlDocument);

        $xml = XmlContent::fromValue(
            /** @lang TEXT */
            '<?xml version="1.1" encoding="utf-8"?><a/><b><c/></b><a/>'
        );
        self::assertFalse($xml instanceof XmlDocument);
    }
}
