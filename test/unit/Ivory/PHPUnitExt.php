<?php
namespace Ivory;

trait PHPUnitExt
{
	private $origZendAssertions = null;
	private $origAssertExceptions = null;

	protected function activateAssertionExceptions()
	{
		$iniZendAssertions = ini_get('zend.assertions');
		if ($iniZendAssertions == -1) {
			self::markTestIncomplete('Cannot test assertions, zend.assertions is set to -1');
			return;
		}
		if (!$iniZendAssertions) {
			$this->origZendAssertions = $iniZendAssertions;
			ini_set('zend.assertions', 1);
		}

		$iniAssertExceptions = ini_get('assert.exception');
		if (!$iniAssertExceptions) {
			$this->origAssertExceptions = $iniAssertExceptions;
			ini_set('assert.exception', 1);
		}
	}

	protected function restoreAssertionsConfig()
	{
		if ($this->origZendAssertions !== null) {
			ini_set('zend.assertions', $this->origZendAssertions);
			$this->origZendAssertions = null;
		}

		if ($this->origAssertExceptions !== null) {
			ini_set('assert.exception', $this->origAssertExceptions);
			$this->origAssertExceptions = null;
		}
	}
}
