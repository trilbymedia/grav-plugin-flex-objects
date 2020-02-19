# v1.0.0-rc.7
## mm/dd/2020

1. [](#new)
    * Added option to change perPage amount of items in Flex List. 'All' also available by only at runtime.

# v1.0.0-rc.6
## 02/11/2020

1. [](#new)
    * Pass phpstan level 1 tests
    * Removed legacy classes for pages, cleanup deprecated Flex types
1. [](#bugfix)
    * Fixed call to `grav.flex_objects.getObject()` causing fatal error
    * Minor bug fixes

# v1.0.0-rc.5
## 02/03/2020

1. [](#new)
    * No changes, just keeping things in sync with Grav RC version

# v1.0.0-rc.4
## 02/03/2020

1. [](#new)
    * Added support for arbitrary admin menu route for editing a flex type
    * Added support for new improved ACL
    * Added support for custom layouts by adding `/:layout_name` in url
    * Added support for Flex Directory specific Configuration
    * Added support for action aliases (`/accounts/configure` instead of `/accounts/users/:configre`)
    * Added Flex type `Configuration`
    * Enabled `Pages`, `Accounts` and `User Groups` by default
    * Stop using deprecated `onAdminRegisterPermissions` event
    * Renamed directory `grav-pages` to `pages`
    * Renamed directory `grav-accounts` to `user-accounts`
    * Renamed directory `grav-user-groups` to `user-groups`
1. [](#improved)
    * Flex caching settings were moved into Grav core
    * Flex Objects plugin now better integrates to Grav core
1. [](#bugfix)
    * Fixed empty directory entries in plugin configuration
    * Fixed plugin configuration displaying directories outside of the plugin
    * Fixed broken blueprints if there's folder with the name of the blueprint file
    * Fixed visible save button when in 404 page
    * Fixed missing save location when file does not exist
    * Fixed multiple ACL related issues (no access, bad links, information leaks)
    * Fixed Admin Panel Page list buttons not appearing in Flex Pages

# v1.0.0-rc.3
## 01/02/2020

1. [](#new)
    * Added root page support for `Flex Pages`
1. [](#bugfix)   
    * Fixed after save: Edit
    * Fixed JS failing on initial filters setup due to no fallback implemented [#2724](https://github.com/getgrav/grav/issues/2724)

# v1.0.0-rc.2
## 12/04/2019

1. [](#new)
    * Admin: Added support for editing `User Groups`
    * Admin: `Flex Pages` now support **searching** and **filtering**
1. [](#bugfix)     
    * Hide hidden/system types (pages, accounts, user groups) from Flex Objects page type [#38](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/38)

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
