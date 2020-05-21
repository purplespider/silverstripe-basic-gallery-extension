# Basic Image Gallery Extension

## Introduction

Add this extension to any page type, to get the following batch image upload interface in the CMS:

![Screenshot](screenshot.png)

It allows images to be bulk uploaded, drag and drop reordering and inline caption adding.

Or use the following modules:
* [Basic Image Gallery Page](https://github.com/purplespider/silverstripe-basic-galleries) - Uses this extension to provide Image Gallery Page and Image Gallery Holder page types.
* [Basic Image Gallery Elemental Block](https://github.com/purplespider/silverstripe-elemental-basic-gallery) - Uses this extension to provide an Image Gallery Elemental block.

## Maintainer Contact ##
 * James Cocker (ssmodulesgithub@pswd.biz)
 
## Requirements
 * Silverstripe 4.1+
 
## Installation Instructions

````
composer require purplespider/silverstripe-basic-gallery-extension ^1
````

## Config

The Extension can be applied to any page type to enable the gallery functionality.

You can also customise the CMS tab that the gallery appears on, as well as the title of the gallery displayed in the CMS, and rename the main Content tab:

````
HomePage:
  extensions:
    - PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension
  gallery-title: Image Gallery
  gallery-cms-tab: Main
  content-cms-tab: Top Content
````
