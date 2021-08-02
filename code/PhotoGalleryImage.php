<?php

namespace PurpleSpider\BasicGalleryExtension;

use PurpleSpider\ElementalBasicGallery\ImageGalleryBlock;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class PhotoGalleryImage extends DataObject
{

    private static $db = [
        'SortOrder' => 'Int',
        'Title' => 'Varchar(255)'
    ];

    private static $has_one = [
        'Image' => Image::class,
        'Album' => DataObject::class,
    ];

    private static $summary_fields = [
        'Thumbnail',
        'Title',
    ];

    private static $owns = [
      'Image'
    ];

    private static $table_name = 'PhotoGalleryImage';
    private static $default_sort = "SortOrder ASC, Created ASC";
    public function Thumbnail()
    {
      return $this->Image()->Fit(200,200);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeFieldFromTab("Root.Main", "SortOrder");
        $fields->removeFieldFromTab("Root.Main", "PhotoGalleryPageID");

        return $fields;
    }

    protected function onBeforeWrite()
    {

  		if (!$this->SortOrder) {
  			$this->SortOrder = PhotoGalleryImage::get()->max('SortOrder') + 1;
  		}

  		parent::onBeforeWrite();
  	}

    protected function onBeforeDelete()
    {

  		if ($this->config()->ondelete_delete_image_files) {
  			$this->Image()->delete();
  		}

  		parent::onBeforeDelete();
  	}

    public function fieldLabels($includerelations = true)
    {
        $translatedLabels = [
            'Thumbnail' => _t('PurpleSpider\BasicGalleryExtension\PhotoGalleryImage.Thumbnail', 'Image') //used in summary_fields
        ];
        
        return array_merge(parent::fieldLabels($includerelations), $translatedLabels);
    }

    public function getParentPhotoGalleryPage()
    {
        if($this->Album()->ClassName == 'PurpleSpider\BasicGalleries\PhotoGalleryPage') {
            return $this->Album();
        }

        return false;
    }
    
    // To support old custom templates
    public function getPhotoGalleryPage()
    {
        return $this->getParentPhotoGalleryPage() ? $this->getParentPhotoGalleryPage() : false;
    }

    public function canCreate($member = null, $context = array())
    {
        return true;
    }

    public function canEdit($members = null)
    {
        return true;
    }

    public function canDelete($members = null)
    {
        return true;
    }

    public function canView($members = null)
    {
        return true;
    }
}
