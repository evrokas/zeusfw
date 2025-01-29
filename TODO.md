[29-01-25] user roles are fixed. webforms is fixed. security is updated and
improved. maintenance functions are added.

[13-01-25] user roles is not working, must correct the Security::require() function to test for current user role, the current user role is not defined

[31-12-24] Webforms are 90% complete. 
 - Form arrays are generated
 - Form html is generated
 What remains is integration with the database
 
[17-11-24] Webform development. This is important because it will allow the creation of the administration module and the use of a simple user interface for alterning configuration variables by the web application. This will allow the `zpms/operations` functionality be implemented more easily, because there many variables to be configured on the web app, that should not be implemented on hardcoded configuration files. Also, should this be implemented, the `zpms/patient` and `zpms/appointment` pages, can be implemented as webforms with the same or improved functionality. Also, in the `zweb/blog`, `zweb/contact` and other pages webforms will be very easy to implement.

* update the createDBFieldDefinition() function for changes in the sync SQL table with YAML function so field definition are created in on point for clarity, 
*  add '@delete' shortcut for database fields, 
* update necessery .yaml files for form fields, 
* ?Shall we use the elements: field(?) as proposed in the ChatGPT document, 
* should form definitions be stored in the database (like the content functionality does), or be stored in the file system and retrieved by the framework on the fly when the form is requested? 
* Prepare the funcionality to create the form HTML incorporating the ZETEM template system.
* add the functionality for form processing functions
* save the results in the database?, the filesystem?
* create PDF forms?
* alternative templates for various forms?
* implement administration module [28-11-24]
* add ajax support for forms and separate fields (!?!)
* add reCaptcha support for form submission

* implement backup system
* implement development <--> stage <--> production enviroments configuration and user data tranfer


[28-11-24] add administration module
[28-11-24] add oauth2-like authorization system/module

[17-11-24] add firewall module
[17-11-24] add user login protection (count login tries,expire password etc)

[10-10-24] make modules self contained with their own .yaml file etc
* this is somewhat implemented with '@app' and '@core' directives in the module .yaml configuration files, these directives can be replaced by a more general ('@'?) so that the module directory in used(?)

[10-10-24] make login safer with challenge question
[10-10-24] add Google ReCaptcha v3

[13-11-24] Implement themes in the framework

[13-11-24] Make Kernel class static

----
[10-10-24] [completed] implement remember-me functionality using cookies
[13-11-24] [completed] Split kernel.php to kernel.php and utils.php with helper functions