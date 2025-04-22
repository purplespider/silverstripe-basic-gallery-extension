<?php

namespace PurpleSpider\BasicGalleryExtension;

use PurpleSpider\ElementalBasicGallery\ImageGalleryBlock;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class PhotoGalleryImage extends DataObject
{

    /**
     * @config
     */
    private static $db = [
        'SortOrder' => 'Int',
        'Title' => 'Varchar(255)'
    ];

    /**
     * @config
     */
    private static $has_one = [
        'Image' => Image::class,
        'Album' => DataObject::class,
    ];

    /**
     * @config
     */
    private static $summary_fields = [
        'Thumbnail',
        'Title',
    ];

    /**
     * @config
     */
    private static $owns = [
      'Image'
    ];

    /**
     * @config
     */
    private static $table_name = 'PhotoGalleryImage';

    /**
     * @config
     */
    private static $default_sort = "SortOrder ASC, Created ASC";

    public function Thumbnail()
    {
      return $this->Image()->Fit(200,200);
    }

    #[\Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeFieldFromTab("Root.Main", "SortOrder");
        $fields->removeFieldFromTab("Root.Main", "PhotoGalleryPageID");

        return $fields;
    }

    #[\Override]
    protected function onBeforeWrite()
    {

  		if (!$this->SortOrder) {
  			$this->SortOrder = PhotoGalleryImage::get()->max('SortOrder') + 1;
  		}

  		parent::onBeforeWrite();
  	}

    #[\Override]
    protected function onAfterDelete()
    {

  		if ($this->config()->ondelete_delete_image_files) {
  			$this->Image()->deleteIfUnused();
  		}

  		parent::onAfterDelete();
  	}

    #[\Override]
    public function fieldLabels($includerelations = true)
    {
        $translatedLabels = [
            'Thumbnail' => _t('PurpleSpider\BasicGalleryExtension\PhotoGalleryImage.Thumbnail', 'Image') //used in summary_fields
        ];

        return array_merge(parent::fieldLabels($includerelations), $translatedLabels);
    }

    public function getParentPhotoGalleryPage()
    {
        if($this->Album()->ClassName === 'PurpleSpider\BasicGalleries\PhotoGalleryPage') {
            return $this->Album();
        }

        return false;
    }

    // To support old custom templates
    public function getPhotoGalleryPage()
    {
        return $this->getParentPhotoGalleryPage() ?: false;
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    #[\Override]
    public function canEdit($member = null)
    {
        return true;
    }

    #[\Override]
    public function canDelete($member = null)
    {
        return true;
    }

    #[\Override]
    public function canView($member = null)
    {
        return true;
    }
}
