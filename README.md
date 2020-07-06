# Media Upload

## INTRODUCTION

This module adds third-party settings for each media type in your system.
Go to /admin/structure/media/manage/{type} and use the 'Media upload configuration' to enable bulk-uploading and
set the 'Upload target field' to a file or image type field from your type and save the type.
The go to /media/bulk-upload or /media/bulk-upload/{type} where you can upload your files.


## REQUIREMENTS

This module requires the following modules:

 * Drupal 9.0 or later
 * Media module from Drupal Core
 * DropzoneJS (https://drupal.org/project/dropzonejs)

Note, that DropzoneJS requires the Dropzone library. The easiest way to install it is to have project based on `drupal-composer/drupal-project` or `drupal/recommended-project`, and follow these steps:

* Set up asset-packagist:
```json
    "repositories": {
        "drupal": {
            "type": "composer",
            "url": "https://packages.drupal.org/8"
        },
        "asset-packagist": {
            "type": "composer",
            "url": "https://asset-packagist.org"
        }
    }
```
* Add `"installer-types": ["bower-asset", "npm-asset"]` to the `extra` key
* Extend `installer-paths` under `extra` with
```json
    "web/libraries/{$name}": [
        "type:drupal-library",
        "type:bower-asset",
        "type:npm-asset"
    ],
```
* Require a helper package and the dropzone package: `composer require oomphinc/composer-installers-extender:^1.1 npm-asset/dropzone:^5.7`

That's it! The project templates above are already set up for handling "drupal-library" typed packages. Otherwise, `composer/installers` can be used.

## INSTALLATION

 * Install as you would normally install a contributed Drupal module. Visit:
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

## CONFIGURATION

 * Configure user permissions in Administration » People » Permissions:

   - Dropzone upload files

     Media Upload uses DropzoneJS. This permission must match the permission
     "Upload media" of Media Upload, otherwise a user allowed to access
     Media Upload may not be able to actually use it.

   - Upload media

     Users in roles with the "Upload media" permission will see the button
     "Upload media in bulk" next to "Add media" under
     Administration » Content » Media.

 NOTE:
 * Max total size parameter defines the maximum total size for a complete
   bulk upload.

 * Max single file size defines the maximum size of each file dropped into
   the file dropzone.

 NOTE:
   This value only acts as an input filter, each uploaded file is actually
   compared to the max size value defined by the source field of the media
   type you chose.

## TROUBLESHOOTING

 * Section waiting for its first problems to solve.

## FAQ

 * Section waiting for its first questions to answer.

## MAINTAINERS

Current maintainers:
 * Tanguy Reuliaux (treuliaux) - https://www.drupal.org/u/treuliaux

This project has been sponsored by:
 * KLEE INTERACTIVE
   KLEE INTERACTIVE creates the websites, collaborative tools and digital
   solutions that offer the most attractive web experience to your users.
   Our expertise includes usability, accessibility, communication, web and
   mobile marketing as well as editorial advice and graphic design.
   KLEE INTERACTIVE is a KLEE GROUP agency.
   Visit http://www.kleeinteractive.com/ for more information.

## SPECIAL THANKS
 * Shawn Duncan (FatherShawn) - https://www.drupal.org/u/fathershawn
 * Antonio Savorelli (antiorario) - https://www.drupal.org/u/antiorario