Expected PhpStorm inspection results
====================================

`Ivory src` inspection on _Project Production Files_
----------------------------------------------------
* Code Smell:
  * Inconsistent return points:
    * `SqlPattern.php` 1 warning:
      * Missing yield statement (PhpStorm bug WI-34792; cannot be suppressed - bug WI-39411)
    * `ConnConfig.php` 1 warning:
      * Missing return statement (PhpStorm bug WI-34792; cannot be suppressed - bug WI-39411)

`Ivory test` inspection on _Project Test Files_
-----------------------------------------------
* Undefined:
  * Undefined field: 121 warnings

