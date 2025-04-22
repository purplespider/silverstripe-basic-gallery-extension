<?php

namespace PurpleSpider\BasicGalleryExtension;

use SilverStripe\Lumberjack\Model\Lumberjack;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\Tab;
use SilverStripe\Dev\Debug;
use SilverStripe\CMS\Model\SiteTree;

class CustomLumberjack extends Lumberjack
{

	/**
	 * This is responsible for adding the child pages tab and gridfield.
	 * CUSTOM: Customised to make the GridField tab first.
	 *
	 * @param FieldList $fields
	 */
	#[\Override]
 protected function updateCMSFields(FieldList $fields)
	{
		$excluded = $this->getOwner()->getExcludedSiteTreeClassNames();
		if (!empty($excluded)) {
			$pages = $this->getLumberjackPagesForGridfield($excluded);
			$gridField = GridField::create("ChildPages", $this->getLumberjackTitle(), $pages, $this->getLumberjackGridFieldConfig());

			$tab = Tab::create('ChildPages', $this->getLumberjackTitle(), $gridField);

			// BEGIN CUSTOMISATION

			// $fields->insertAfter($tab, 'Main');

			if (SiteTree::get()->filter('ParentID', $this->getOwner()->ID)->count()) {
				$fields->insertBefore('Main', $tab);
			} else {
				$fields->insertAfter('Main', $tab);
			}

			// END CUSTOMISATION

		}
	}
}
