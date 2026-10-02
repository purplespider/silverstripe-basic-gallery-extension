<?php

namespace PurpleSpider\BasicGalleryExtension;

use PurpleSpider\ElementalBasicGallery\ImageGalleryBlock;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

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
        // Give new images - and images moved between galleries - a SortOrder at the
        // end of *their own* album. The AlbumID/AlbumClass check matters: the bulk
        // uploader writes the record twice before it knows which gallery it's in.
        $needsSortOrder = !$this->SortOrder
            || $this->isChanged('AlbumID', DataObject::CHANGE_VALUE)
            || $this->isChanged('AlbumClass', DataObject::CHANGE_VALUE);

        if ($needsSortOrder && $this->AlbumID && $this->AlbumClass) {
            $max = PhotoGalleryImage::get()
                ->filter([
                    'AlbumID' => $this->AlbumID,
                    'AlbumClass' => $this->AlbumClass,
                ])
                ->exclude('ID', (int) $this->ID)
                ->max('SortOrder');

            $this->SortOrder = ((int) $max) + 1;
        }

        parent::onBeforeWrite();
    }

    #[\Override]
    protected function onAfterWrite()
    {
        parent::onAfterWrite();

        // Only the things that actually feed into the order. This deliberately skips inline
        // caption edits (GridFieldEditableColumns writes several rows in one save) and the
        // SortOrder writes made by drag and drop.
        $affectsOrder = $this->isChanged('AlbumID', DataObject::CHANGE_VALUE)
            || $this->isChanged('AlbumClass', DataObject::CHANGE_VALUE)
            || $this->isChanged('ImageID', DataObject::CHANGE_VALUE);

        if ($affectsOrder) {
            $this->resortAlbum();
        }
    }

    #[\Override]
    protected function onAfterDelete()
    {

  		if ($this->config()->ondelete_delete_image_files) {
  			$this->Image()->deleteIfUnused();
  		}

        // Keep the remaining SortOrder values contiguous
        $this->resortAlbum();

  		parent::onAfterDelete();
  	}

    /**
     * Ask this image's gallery to re-apply its sort order, if it has one.
     */
    protected function resortAlbum()
    {
        // The re-sort writes these rows itself; don't let that trigger another one
        if (PhotoGalleryExtension::isResorting()) {
            return;
        }

        $albumClass = $this->AlbumClass;
        $albumID = (int) $this->AlbumID;

        if (!$albumClass || !$albumID) {
            return;
        }

        if (!class_exists($albumClass) || !is_subclass_of($albumClass, DataObject::class)) {
            return;
        }

        // Look the album up in the draft stage explicitly: during a publish the reading mode
        // is Live, and $this->Album() would hand back an empty singleton.
        $album = Versioned::withVersionedMode(function () use ($albumClass, $albumID) {
            Versioned::set_stage(Versioned::DRAFT);

            return DataObject::get_by_id($albumClass, $albumID);
        });

        if ($album && $album->hasMethod('resortGalleryImages')) {
            $album->resortGalleryImages();
        }
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
