## 3.0.2
- Fix: Eliminate some deprectation warnings

## 3.0.1
- Fix: Access to typed property before initialization when accessing a parent folder of a FilesystemFolder instance
- Refactor: Use PHP8.3 language features throughout; ensure PHP8.4 compatibility

## 3.0.0
- Updated to PHP8.3+ compatibility 

## 2.9.3
- DotEnvReader received a getKeysInFile() method which lists all keys in the parsed env file

## 2.9.2
- Fix: Form elements with a default value of null return null as their value instead of an empty string 

## 2.9.1
- added a AbstractPdoAdapter::getColumnNames() method to retrieve all column names of a given table

## 2.9.0
- environment variables can now be accessed in the config XML with a `{$env(<varname>)}` expression
- deprecated configuration parsers (db, pages) have been removed
- Binaries configuration parser no longer observes context attribute

## 2.8.3
- fix: Email::getMailer() can return null

## 2.8.2
- quickfix: added missing defaults in typed properties in abstract PDO adapter

## 2.8.1
- Abstract controller drops it rather pointless execute() method
- fix: deleteRecord() with an array as key identifier failed

## 2.8.0
- feat: added a dotenv reader
- Session handler no longer enforces session handling via cookies
- Controller classes no longer need to implement an execute method

## 2.7.7
- bug fix: Template\Filter::anchorHref() failed when a shortcut could not be matched with a menu entry
- bug fix: Database\Adapter\MySQL ignored the keep_key_case option
- minor fixes and type declarations added

## 2.7.6
- bug fix: Database\Util::unformatDecimal() did not parse strings correctly

## 2.7.5
- bug fix: HttpException no longer passes NULL messages to \Exception

## 2.7.4
- quick fix to avoid deprecation warnings when running on PHP 8.x while maintaining PHP 7.4 compatibility

## 2.7.3
- minor fixes and cleanups to avoid PHP8.x deprecation warnings

## 2.7.2
- bug fix: ExceptionHandler tried to access obsolete path config attribute

## 2.7.1
- bug fix: ErrorHandler/ExceptionHandler was called with wrong number of arguments
- some minor refactoring
 
## 2.7.0
- refactored classes to use PHP 7.4+ language features
- paths of menu entries can now contain slashes

## 2.6.10
- refactored and simplified menu renderer; menus are no longer automatically wrapped with divs and get specific id attributes
- the SimpleList menu renderer now accepts additional aClass, spanClass, activeClass parameters to allow for easier styling (particularly with utility-first CSS frameworks)
- PHP7.4 is now the minimum supported version

## 2.6.9
- bug fix: SmtpMailer did not encode subject with non-ASCII characters

## 2.6.8
- bug fix: DatabaseInterface::insertRecords() failed
- bug fix: DatabaseInterfaceFactory failed to initialize an interface

## 2.6.7
- improved DSN parsing for PDO connections

## 2.6.6
- bug fix: checking for a table column in a database failed if the table structure cache had not been filled by a previous database operation

## 2.6.5
- bug fix: form element factory cast wrong error message type

## 2.6.4
- bug fix: image modifier resizing of images with *max* limits was broken

## 2.6.3
- web image related functionality (ImageCache) now handles (lossy) WebP images

## 2.6.2
- bug fix: SimpleTemplate::insertTemplateAt() left angle bracket artifacts
- fixes to form element classes hierarchy

## 2.6.1
- bug fixes for menu authentication and route parameter encoding

## 2.6.0
- SimpleTemplate allows several marked blocks in a child template which are inserted in a single parent template
- redirects can now be configured both on route level
- display of single menu entries can be configured with a display attribute set to "none"; menu entries expose setDisplay() and getDisplay() methods
- bug fixes

## 2.5.0
- path parameters can now have validators: route declarations can now contain placeholder elements with name, match and default attributes; matches are regular expressions applied to the placeholder when matching routes
- the Config constructor drops the "section" argument and replaces it with a generic "options" array; default config parsers are the parsers found in the Application\Config\Parser\Xml namespace
- additional config parsers can be passed to the Config constructor in the options array under the "parser" key

## 2.4.0
- additional attributes for menus allowed; the SimpleListRenderer observes a display attribute
- added type hints to menu classes; improved code consistency, potentially breaking existing applications
- SmtpMailer provides authentication via OAuth
- removed MetaInformation class (for being useful this would require both a database connection and a database structure)

## 2.3.0
- removed obsolete JSMin and accompanying exception class
- introduced Text class to pool text utility functions
- ImageCache filter now observes srcset attribute
- FilesystemFolder received a move() method
- minor bugfixes and refactoring 

## 2.2.6
- bugfixes: mailer configuration, form checkbox validation, evaluation of conditions in form templates
- refactored Email class

## 2.2.5
- bugfixes: queries to obtain table metadata in the PostgreSQL adapter were incompatible with recent Postgres versions
- refactored both the MySQL and Postgres adapters

## 2.2.4
- bugfix: menu entries did not observer relative paths to script files
- refactored menu entries to use a fluent interface

## 2.2.3
- fixes to avoid deprecation notices in PasswordGenerator; removed obsolete code

## 2.2.2
- bugfix: spaceless filter didn't work over multiple lines

## 2.2.1
- spaceless filter for templates will only work within marked blocks; added as a default filter

## 2.2.0
- parsing of configuration files improved:
  - order of top-level elements is no longer relevant
  - parsers moved to separate classes which should allow more flexibility with other config file formats

## 2.1.1
- bugfixes
- code of template filters cleaned up

## 2.1.0
- updated Controller class: removed obsolete code and several dependencies; Controller::createControllerFromRoute() now expects additional parameters 

## 2.0.0
- updated Http/* classes based on Symfony's HttpFoundation updated to versions of Symfony 4.4
- routing improved; removed some dependencies
- several bugfixes

## 1.6.13
- bugfixes to routing

## 1.6.12
- added an interface for FilesystemFile

## v1.6.11
- bugfixes to plugin initialization
- some code clean-up

## v1.6.10
- FilesystemFolder provides a rename method

## v1.6.9
- bugfix: in form templates element arrays expected the form element to appear after its label

## v1.6.8
- DatabaseInterface::deleteRecord allows arrays as values when key-value pairs are passed to the method; in this case the resulting WHERE clause will use IN()

## v1.6.7
- minor improvements to mimetype handling with FilesystemFiles

## v1.6.6
- URL validator fixed

## v1.6.5
- Bugfixes
- CSRF token handling improved

## v1.6.4
- ImageCache filter allows to display a fallback image by replacing the src attribute if the image file is not found
- RadioElement no longer relies on FormElement::getForm() to generate its id attribute 
- Required PHP version bumped to 7.1
