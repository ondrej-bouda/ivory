<?php
/**
 * Instantiation of Ppg objects: connection, statements, etc.
 */
namespace Ppg\Showcase\Issues;

// assume that two connections were configured somehow, the default and 'other'


// VERSION 1: Ppg as a singleton register
$stmtOnDefaultConn = \Ppg\Ppg::getConnection()->stmt();
$stmtOnOtherConn = \Ppg\Ppg::getConnection('other')->stmt();


// VERSION 2: Ppg as a singleton connection array object
$stmtOnDefaultConn = \Ppg\Ppg::getInstance()->stmt();
$stmtOnOtherConn = \Ppg\Ppg::getInstance()['other']->stmt();



// ---

// besides the library objects themselves, decide how to instantiate the generated or user relation objects - e.g., what
// to do to get a person relation object of the Person class
