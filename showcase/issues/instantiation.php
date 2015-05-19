<?php
/**
 * Instantiation of Ivory objects: connection, statements, etc.
 */
namespace Ivory\Showcase\Issues;

// assume that two connections were configured somehow, the default and 'other'


// VERSION 1: Ivory as a singleton register
$stmtOnDefaultConn = \Ivory\Ivory::getConnection()->stmt();
$stmtOnOtherConn = \Ivory\Ivory::getConnection('other')->stmt();


// VERSION 2: Ivory as a singleton connection array object
$stmtOnDefaultConn = \Ivory\Ivory::getInstance()->stmt();
$stmtOnOtherConn = \Ivory\Ivory::getInstance()['other']->stmt();



// ---

// besides the library objects themselves, decide how to instantiate the generated or user relation objects - e.g., what
// to do to get a person relation object of the Person class
