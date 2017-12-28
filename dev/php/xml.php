<?php
namespace Ivory\Dev\Php;

use DOMDocument;
use DOMImplementation;
use XMLReader;

function first() {
    $doc = new DOMDocument();
    $doc->loadXML("<root/>");
    $node = $doc->createTextNode('wheee');
    $doc->documentElement->appendChild($node);
    echo __FUNCTION__ . ":\n";
    echo $doc->saveXML($node);
    echo "\n";
}
first();

function second() {
    $doc = new DOMDocument();
    $doc->loadXML("<root/>");
    $f = $doc->createDocumentFragment();
    $f->appendXML("<foo>text</foo><bar>text2</bar>");
    $doc->documentElement->appendChild($f);
    echo __FUNCTION__ . ":\n";
    echo $doc->saveXML($f); // although a DOMNode subclass, DOMDocumentFragment does not get saved :(
    echo "\n";
}
second();

function third() {
    $domImpl = new DOMImplementation();
    $doc = $domImpl->createDocument(null, 'root');
    $doc->xmlVersion = '1.1';
    $doc->encoding = 'US-ASCII';
    echo __FUNCTION__ . ":\n";
    echo $doc->saveXML();
    echo "\n";
}
third();

function fourth() {
    $doc = new DOMDocument();
    $doc->loadXML('<root><a><b>bagr</b></a><b>veslo</b><b>jerab</b></root>');
    $doc->xmlVersion = '1.1';
    echo __FUNCTION__ . ":\n";
    echo $doc->saveXML();
    echo "\n";
    $nodeList = $doc->getElementsByTagName('b');
    echo get_class($nodeList) . "\n";
    foreach ($nodeList as $el) {
        echo $el->ownerDocument->saveXML($el) . "\n";
    }
}
fourth();

function fifth() {
    echo "\n\n\n";
    echo __FUNCTION__ . ":\n";

    $xmls = [
        '<foo>text</foo><bar>text2</bar>',
        '<root><a><b>bagr</b></a><b>veslo</b><b>jerab</b></root>',
        sprintf('<root>%s</root>', str_repeat('<a b="3"/>', 100000)),
        sprintf('<root>%s</root><b>', str_repeat('<a b="3"/>', 100000)),
    ];

    $validators = [
        function ($xml) {
            $reader = new XMLReader();
            $reader->xml($xml);
            if (!@$reader->read()) {
                return false;
            }
            while (@$reader->read()) {}
            return ($reader->depth == 0);
        },

//        function ($xml) {
//            $reader = new XMLReader();
//            $reader->xml($xml);
//
//            printf("Read node type %s at depth %d\n", $reader->nodeType, $reader->depth);
//            echo "Started\n";
//            while ($reader->read()) {
//                printf("Read node type %s at depth %d\n", $reader->nodeType, $reader->depth);
//            }
//            echo "Stopped\n";
//            printf("Read node type %s at depth %d\n", $reader->nodeType, $reader->depth);
//
//            return true;
//        },
    ];

    foreach ($validators as $v => $validator) {
        echo "VALIDATOR $v:\n";
        foreach ($xmls as $i => $xml) {
            echo "$i: ";
            var_dump($validator($xml));
        }
        echo "\n\n";
    }


//    $reader = new XMLReader();
//    $opened = $reader->xml('<foo>text</foo><bar>text2</bar>');
//    var_dump($opened);
//    var_dump($reader->isValid());
//    echo "\n";
//    $reader = new XMLReader();
//    $opened = $reader->xml('<foo>text</foo><bar>text2</bar>');
//    var_dump($opened);
//    $reader->setParserProperty(XMLReader::VALIDATE, true);
//    var_dump($reader->isValid());
//    echo "\n";
//    $reader = new XMLReader();
//    $opened = $reader->xml('<foo>text</foo><bar>text2</bar>');
//    var_dump($opened);
//    while (@$reader->next()) {}
//    var_dump(($reader->depth == 0));
//    echo "\n";
//    $reader = new XMLReader();
//    $opened = $reader->xml('<foo>text</foo><bar>text2</bar>');
//    var_dump($opened);
//
//    while ($reader->next() && $reader->isValid() && $reader->depth > 0) {
//        printf("read `%s` at depth %d\n", $reader->readString(), $reader->depth);
//    }
//    var_dump(($reader->depth == 0));

//    echo "\n";
//    echo "\n";
//
//    $reader = new XMLReader();
//    $opened = $reader->xml('<root><a><b>bagr</b></a><b>veslo</b><b>jerab</b></root>');
//    var_dump($opened);
//    var_dump($reader->isValid());
//    echo "\n";
//    $reader = new XMLReader();
//    $opened = $reader->xml('<root><a><b>bagr</b></a><b>veslo</b><b>jerab</b></root>');
//    var_dump($opened);
//    $reader->setParserProperty(XMLReader::VALIDATE, true);
//    var_dump($reader->isValid());
//    echo "\n";
//    $reader = new XMLReader();
//    $opened = $reader->xml('<root><a><b>bagr</b></a><b>veslo</b><b>jerab</b></root>');
//    var_dump($opened);
//    while ($reader->next()) {}
//    var_dump(($reader->depth == 0));
//    echo "\n";
//    $reader = new XMLReader();
//    $opened = $reader->xml('<root><a><b>bagr</b></a><b>veslo</b><b>jerab</b></root>');
//    $reader->setParserProperty(XMLReader::VALIDATE, true);
//    var_dump($opened);
//    while (@$reader->next() && $reader->isValid() && $reader->depth > 0) {}
//    var_dump(($reader->depth == 0));

    echo "\n\n\n";
}
fifth();

