# v1.0.0-rc.3
## mm/dd/2019

1. [](#new)
    * Added root page support for `Flex Pages`
1. [](#bugfix)   
    * Fixed after save: Edit

# v1.0.0-rc.2
## 12/04/2019

1. [](#new)
    * Admin: Added support for editing `User Groups`
    * Admin: `Flex Pages` now support **searching** and **filtering**
1. [](#bugfix)     
    * Hide hidden/system types (pages, accounts, user groups) from Flex Objects page type [#38](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/38)
    * Fixed JS failing on initial filters setup due to no fallback implemented [#2724](https://github.com/getgrav/grav/issues/2724).

# v1.0.0-rc.1
## 11/06/2019

1. [](#new)
    * Added directory configuration option for custom admin templates
    * Added `Flex Accounts (Admin)` type to administer user accounts in Flex independently from Grav system setting
    * Added `Flex Pages (Admin)` type to administer pages in Flex independently from Grav system setting
    * Added blueprint option to hide directory from Flex Objects types page in frontend 
    * Deprecated all `Flex Page` classes and traits in favor of the new classes in Grav core
    * Moved flex object/collection templates to `templates/flex/{TYPE}` which is easier to remember
    * Admin: Added support customizable preview and export
1. [](#improved)
    * Admin: Allow custom title template when editing object
    * Translations: rename MODULAR to MODULE everywhere
1. [](#bugfix) 
    * Flex Pages: Fixed default language not being translated in both `translatedLanguages()` and `untranslatedLanguages()` results
    * Flex Pages: Language interface compatibility fixes
    * Flex Pages: Fixed frontend issues with plugin events [#5](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/5)
    * Flex Pages: Fixed `filePathClean()` and `filePathClean()` not returning file for folder
    * Flex Pages: Fixed multiple multi-language related issues in admin [#10](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/10)
    * Flex Pages: Fixed raw edit mode
    * File upload is broken for nested fields [#34](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/34)

# v1.0.0-beta.10
## 10/03/2019

1. [](#bugfix)
    * Flex Pages: Fixed moving visible page in admin causing ordering issues [#6](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/6)
    * Flex Pages List: Fixed issue where auto-hiding scrollbars in macOS would throw off the dropdown position [#20](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/20)
    * Flex Pages: Fixed prev/next page missing pages if pagination was turned on in page header

# v1.0.0-beta.9
## 09/26/2019

1. [](#improved)
    * Show/hide dropdown menu as needed when scrolling the page columns container left and right
1. [](#bugfix)
    * PHP 7.1: Fixed error when activating `Flex Pages` in Plugin parameters [#13](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/13)
    * Flex Pages: Fixed page template cannot be changed [#4](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/4)
    * Flex Pages: Fixed new pages being created with wrong template [#22](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/22)
    * Flex Pages: Fixed `Preview` not working [#17](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/17)
    * Fixed error caused by automatic path selection from cookie when destination not available [#23](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/23)
    * Fixed breadcrumb issue in Flex Pages List [#19](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/19)
    * Flex Pages: Fixed unable to change page template [#4](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/4)
    * Fixed `Error 404` when adding new contact [#14](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/14)
    * Flex Pages: Non-visible items appear in Nav menu [#24](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/24)
    * Disabling plugin breaks saving plugin configuration [#11](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/11)

# v1.0.0-beta.8
## 09/19/2019

1. [](#new)
    * Initial public release (all previous versions were in a private repo)
