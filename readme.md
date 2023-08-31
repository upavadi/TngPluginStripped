# **TNG / Wordpress integration** # 
# **Warning:** Please note that this Plugin works on WordPress 6.2.2 BUT does NOT work with WordPress 6.3 - gives a 500 error and a blank page.
Thread:
https://tng.community/index.php?/forums/topic/17704-tng-wordpress-plugin-and-wordpress-63/#comment-96390

## **Files**
- **readme.txt, tng.txt** - Original
- **changes-mu.txt** - Log of changes in line numbers
- **ref_files/tng-original.php** - Version: 10.1.1 Updated by: Darrin Lythgoe and Roger Moffat
- **ref_files/tng-commented.php** Version: 11.0.0 redundant lines commented out by Maehsh
- **ref_files/tng_widgets.php** Repository for the widgets removed. Not sure whether these would be resurrected.
- **tng.php** Stripped Down Working Version: 11.0.1. 
-----
## **Description**
Description: Integrates TNG (The Next Generation of Genealogy) with Wordpress. TNG v12-13 compatibility
- Author: Mark Barnes with additions by Darrin Lythgoe and Roger Moffat 
- Updated by: Darrin Lythgoe and Roger Moffat, 2011-2016
- CHANGES FOR COMPACT VERSION BY Mahesh Upadhyaya @mahesh

---
## **License**
The code is licenced under the [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.en.html)

------------

## **Compatibility**
The plugin tested with
- Wordpress 6.2.2
- TNG V12 and TNG V13.0.2
- PHP 7.4 and 8.0.1 
- [Wordpress-TNG Login Widget Version 3.1.3.beta](https://github.com/upavadi/tng-wp-login/archive/refs/tags/3.1.3.beta.zip) 
---------------------
## **Changes**
- **Version Change** - 11.0.1
- **Plugin Directory** - SW always looked for *tng-wordpress-plugin* as directory name. Replace calls to "*tng-wordpress-plugin*" with $current_dir.
- Remove login/out
- Remove Registrations
- Remove Widgets. Moved all the widgets to *widgets.php* for future use, if required
- Add link to integrated login - Wordpress-TNG Login Widget (https://github.com/upavadi/tng-wp-login/)
- Removed from TNG Page but left in WP_options for backward compatibility
  - Integrate TNG/Wordpress logins
  - Redirect successful login to referrer page
  - Replace TNG homepage with Wordpress page
  ----



 
