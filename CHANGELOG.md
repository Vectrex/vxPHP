## v1.6.4
- ImageCache filter allows to display a fallback image by replacing the src attribute if the image file is not found
- RadioElement no longer relies on FormElement::getForm() to generate its id attribute 
- Required PHP version bumped to 7.1

## v1.6.5
- Bugfixes
- CSRF token handling improved

## v1.6.6
- URL validator fixed

## v1.6.7
- minor improvements to mimetype handling with FilesystemFiles

## v1.6.8
- DatabaseInterface::deleteRecord allows arrays as values when key-value pairs are passed to the method; in this case the resulting WHERE clause will use IN()

## v1.6.9
- bugfix: in form templates element arrays expected the form element to appear after its label