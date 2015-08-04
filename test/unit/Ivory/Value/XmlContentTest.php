<?php
namespace Ivory\Value;

class XmlContentTest extends \PHPUnit_Framework_TestCase
{
    public function testFromValue()
    {
        $xml = XmlContent::fromValue('<root><a/><b><c/></b><a/></root>');
        $this->assertTrue($xml instanceof XmlDocument);

        $xml = XmlContent::fromValue('<a/><b><c/></b><a/>');
        $this->assertFalse($xml instanceof XmlDocument);

        $xml = XmlContent::fromValue('<?xml version="1.1" encoding="utf-8"?><root><a/><b><c/></b><a/></root>');
        $this->assertTrue($xml instanceof XmlDocument);

        $xml = XmlContent::fromValue('<?xml version="1.1" encoding="utf-8"?><a/><b><c/></b><a/>');
        $this->assertFalse($xml instanceof XmlDocument);
    }
}
