craftimport
============

Craft CMS plugin to import entries from XML.

### Credits

Initial starting point courtesy [Roi Kingon](https://plus.google.com/112173526450245116573/posts).

## v0.2

#### Re-import now supported

Re-importing entries is now supported. However, *slug* was not loading from the import in v0.1. It was being generated by Craft based on the title of the new entry. (I was setting it as a content field rather than a direct attribute. It wasn't being captured at all.)

If your slug (or URL Title) fields in your XML did not match what Craft generated, the v0.2 re-import update will not work for those entries. It will keep creating new entries for each imported entry where *slug* was not matched on.

#### Importing tags now supported

In my source XML, I'm outputting ExpressionEngine categories into child nodes: `entry/categories/category`.

You'll find the loop for those on `Lines 78-104`. You can disable those on `Line 18` by setting `$importTags=false`.

Note that you need to look up and provide your `tagSetId` and your `tagFieldId` to properly load & assign tags.

## v0.1

### Install

* Drop the craftimport directory into your craft/plugins directory.
* Navigate to Settings -> Plugins and click "Install" to the right of Craft Import.

This will create a "Craft Import" tab at the top of your admin. It's safe to click that without running an import.

### Import

Click the "Load Entries" button to run your import. **Recommended: Backup your database before running an import.**

### Images & Assets

The previous blog (in my example) stored images locally and went through an upgrade, so Wygwam fields contained "/images/uploads" and "http://www.DOMAIN.com/images/uploads" references.

In Craft, I'm using Amazon S3 for Assets. I added an image to a blog post to test the syntax, and then added `Lines 35-40` to update those.

Be sure to comment out these lines if you don't need them, as they will replace any instances of "/images/uploads".

### Configuration

All the magic happens in services/CraftImportService.php

* Update the URL on `Line 17` to point to well-formed XML. I created an XML template in an ExpressionEngine site as my source.
* Update `Lines 64-73` to match your Craft configuration and source nodes. Reference inline comments.
* **Note that** the importer does not have a limit on the number of entries and may time out.

### Customization

Customize the plugin's landing page by editing templates/index.html. Add a "**do not use!**" warning!

### Export

The included `eecms-export-template.html` template is a reference for the XML structure the importer is looking for.
