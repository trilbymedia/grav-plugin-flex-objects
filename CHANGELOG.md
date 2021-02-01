# v1.0.2
## 02/01/2021

1. [](#new)
   * Require **Grav 1.7.4**
1. [](#bugfix)
   * Fixed saving page in expert mode [grav#3174](https://github.com/getgrav/grav/issues/3174)

# v1.0.1
## 01/20/2021

1. [](#bugfix)
   * Fixed 404 when trying to edit a page with accented characters [grav-plugin-admin#2026](https://github.com/getgrav/grav-plugin-admin/issues/2026)

# v1.0.0
## 01/19/2021

1. [](#new)
   * Added `$grav['flex_objects']->getAdminController()` method
1. [](#improved)
   * Added support for relative paths in `getLevelListing` action
1. [](#bugfix)
   * Fixed admin not working with types that do not implement `FlexAuthorizeInterface`
   * Fixed bad redirect when creating new flex object and choosing to create another return to the list
   * Fixed bad redirect when changing parent of new page and saving [grav-plugin-admin#2014](https://github.com/getgrav/grav-plugin-admin/issues/2014)
   * Fixed page forms being empty if multi-language is enabled, but there's just one language [grav#3147](https://github.com/getgrav/grav/issues/3147)
   * Fixed copying a page within a parent with no create permission [grav-plugin-admin#2002](https://github.com/getgrav/grav-plugin-admin/issues/2002)
   
# v1.0.0-rc.20
## 12/15/2020

1. [](#improved)
    * Default cookies usage to SameSite Lax [grav-plugin-admin#1998](https://github.com/getgrav/grav-plugin-admin/issues/1998)
    * Fixed typo [#89](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/89)

# v1.0.0-rc.19
## 12/02/2020

1. [](#improved)
    * Just keeping sync with Grav rc.19

# v1.0.0-rc.18
## 12/02/2020

1. [](#new)
    * Require **PHP 7.3.6**
1. [](#improved)
    * Improved frontend templates
    * Improve blueprint structure
    * Hooked up Duplicate and Move from within Pages list [#81](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/81)
    * Respect CRUD ACL actions for items shortcuts in pages list [#82](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/82)
    * Refresh object on controllers to make sure it is up to date
1. [](#bugfix)
    * Fixed fatal error in admin if list view hasn't been defined
    * Fixed fatal error in admin if directory throws exception
    * Fixed attempts to add an existing page
    * Fixed form loosing its form state if saving fails when using `ObjectController`
    * Fixed missing context when rendering collection in frontend
    * Fixed Flex Admin activating on too old Admin plugin versions
    
# v1.0.0-rc.17
## 10/07/2020

1. [](#bugfix)
    * Fixed media uploads for objects which do not implement `FlexAuthorizeInterface`
    * Fixed file picker field not recognizing `folder: @self` variants

# v1.0.0-rc.16
## 09/01/2020

1. [](#improved)
    * Simplified `Flex Pages` admin not to differentiate between default language file extensions [#47](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/47)
1. [](#bugfix)
    * Fixed extra space in Flex admin pages
    * Fixed folder creation with parent other than root [#66](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/66)
    * Fixed task redirects in sub-folder multi-site environments
    * Fixed typo in default permissions (should have been `admin.flex-objects`) [grav#2915](https://github.com/getgrav/grav/issues/2915)

# v1.0.0-rc.15
## 07/22/2020

1. [](#new)
    * Released with no changes to keep sync with Grav + Admin

# v1.0.0-rc.14
## 07/09/2020

1. [](#new)
    * Released with no changes to keep sync with Grav + Admin

# v1.0.0-rc.13
## 07/01/2020

1. [](#bugfix)
    * Fixed bad link in directory listing template
    * Fixed admin save task displaying error message about non-existing data type
    * Fixed `pagemedia` field not uploading/deleting files right away
    * Fixed `Flex Pages` add, copy and move buttons appearing in edit view when no permissions
    * Fixed `Flex Pages` permission issues
    * Fixed some admin redirect issues

# v1.0.0-rc.12
## 06/08/2020

1. [](#new)
    * Code updates to match Grav 1.7.0-rc.12
1. [](#improved)
    * Changed class `admin-pages` to `admin-{{ target }}` [#59](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/59)   

# v1.0.0-rc.11
## 05/14/2020

1. [](#new)
    * Added integration with Admin's new preset events to style the CSS
1. [](#improved)
    * JS Maitenance    
1. [](#bugfix)
    * Fixed `Accounts` Configuration tab

# v1.0.0-rc.10
## 04/27/2020

1. [](#bugfix)
    * Fixed custom actions not working
    * Fixed custom folder in `mediapicker` field not working
    * Fixed export title when not using CVS [#51](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/51)
    * Fixed preview in Page list view [admin#1845](https://github.com/getgrav/grav-plugin-admin/issues/1845)
    * Fixed `404 Not Found` error after saving a new object

# v1.0.0-rc.9
## 03/20/2020

1. [](#bugfix)
    * Fixed issue with touch devices and scrollbars hidden, preventing native scrolling to work [admin#1857](https://github.com/getgrav/grav-plugin-admin/issues/1857) [#1858](https://github.com/getgrav/grav-plugin-admin/issues/1858)
    
 
# v1.0.0-rc.8
## 03/19/2020

1. [](#new)
    * Added a basic **Convert Data** CLI Command.  Works with `Yaml` <-> `Json`
1. [](#bugfix)
    * Fixed jump of the page when applying filters [grav-admin#1830](https://github.com/getgrav/grav-plugin-admin/issues/1830)
    * Fixed form resetting when validation fails [grav#2764](https://github.com/getgrav/grav/issues/2764)

# v1.0.0-rc.7
## 03/05/2020

1. [](#new)
    * Added option to change perPage amount of items in Flex List. 'All' also available by only at runtime.
1. [](#improved)
    * Page filters now obey admin hide type settings
1. [](#bugfix)
    * Fixed fatal error if there is missing blueprint [grav#2834](https://github.com/getgrav/grav/issues/2834)
    * Fixed redirect when moving a page [grav#2829](https://github.com/getgrav/grav/issues/2829)
    * Fixed no default access set when creating new user from admin [#31](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/31)
    * Flex Pages: Fixed page visibility issues when creating a new page [grav#2823](https://github.com/getgrav/grav/issues/2823)
    * Flex Pages: Fixed translated page having non-translated status with `system.languages.include_default_lang_file_extension: false`
    * Flex Pages: Fixed preview on home page

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
