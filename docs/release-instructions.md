# Internal Instructions

## Development
* Mark each change appropriately in the [CHANGELOG.md](../CHANGELOG.md)
* Before commit:
  * run all tests -- all should pass
  * run the _Ivory Inspections_ inspection profile on _Inspection Scope_, including test sources -- no inspection
    violations should be reported
* Commit:
  * new features to the `master` branch
  * fixes to all supported branches, to the releasing branch (if any), and to the `master` branch

## Preparing for a Major/Minor Release
* Create a new branch `X.Y` from `master`
* Update [CHANGELOG.md](../CHANGELOG.md):
  * change the label of _Unreleased_ section to _Unreleased `X.Y`_
  * add a new _Unreleased_ section, linking to the diff between the new version number and the `master` `HEAD`

## Making a Release
* Update `Ivory::VERSION`
* Tag the version in the Git branch `X.Y`
* Update [CHANGELOG.md](../CHANGELOG.md):
  * major/minor release: change the label of _Unreleased `X.Y`_ section to `X.Y.0` and set the current date
  * patch release: change the label of the patched _Unreleased `X.Y`_ section to `X.Y.Z` and set the current date
* Update [docs/index.md](index.md):
  * at the bottom of the page, update the latest version info

