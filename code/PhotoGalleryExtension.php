<?php

namespace PurpleSpider\BasicGalleryExtension;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use Colymba\BulkUpload\BulkUploader;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use PurpleSpider\BasicGalleryExtension\PhotoGalleryImage;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;

class PhotoGalleryExtension extends \SilverStripe\Core\Extension
{

    public $owner;

    // One gallery page has many gallery images
    /**
     * @config
     */
    private static $has_many = ['PhotoGalleryImages' => PhotoGalleryImage::class . '.Album'];

    /**
     * @config
     */
    private static $owns = [
      'PhotoGalleryImages'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeFieldFromTab('Root', 'PhotoGalleryImages');

        if (!$galleryCMSTab = $this->getOwner()->config()->get('gallery-cms-tab')) {
          $galleryCMSTab = "Main";
        }

        $insertGalleryBefore = null;
        if ($galleryCMSTab === "Main") {
          $insertGalleryBefore = "Metadata";
        }

        $gridFieldConfig = GridFieldConfig::create();

        $gridFieldConfig->addComponent(new BulkUploader());

        $bulkUpload = $gridFieldConfig->getComponentByType(BulkUploader::class);
        $bulkUpload->setUfSetup('setFolderName', $this->getBulkUploadFolderName());

        $gridFieldConfig->addComponent(GridFieldOrderableRows::create()->setSortField('SortOrder'));
        $gridFieldConfig->addComponent(GridFieldButtonRow::create('before'));
        $gridFieldConfig->addComponent(GridFieldToolbarHeader::create());
        $gridFieldConfig->addComponent(GridFieldSortableHeader::create());
        $gridFieldConfig->addComponent(GridFieldFilterHeader::create());
        $gridFieldConfig->addComponent(GridFieldEditableColumns::create());
        $gridFieldConfig->addComponent(GridFieldEditButton::create());
        $gridFieldConfig->addComponent(GridFieldDeleteAction::create());
        $gridFieldConfig->addComponent(GridField_ActionMenu::create());
        $gridFieldConfig->addComponent(GridFieldPageCount::create('toolbar-header-right'));
        $gridFieldConfig->addComponent(GridFieldPaginator::create(100));
        $gridFieldConfig->addComponent(GridFieldDetailForm::create());

        $gridfield = GridField::create("PhotoGalleryImages", $this->getGalleryTitle(), $this->getOwner()->PhotoGalleryImages(), $gridFieldConfig);
        $fields->addFieldToTab('Root.'.$galleryCMSTab,
            HeaderField::create('addHeader',
                _t('PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension.AddImages','Add Images')
            ),
            $insertGalleryBefore);
        $fields->addFieldToTab('Root.'.$galleryCMSTab, $gridfield,$insertGalleryBefore);

        return $fields;
    }

    public function GetGalleryImages()
    {
        return $this->getOwner()->PhotoGalleryImages()->sort("SortOrder");
    }

    protected function getBulkUploadFolderName()
    {
        if ($this->getOwner()->hasMethod('getBulkUploadFolderName')) {
            return $this->getOwner()->getBulkUploadFolderName();
        }

        return "Managed/PhotoGalleries/".$this->getOwner()->ID."-".$this->getOwner()->URLSegment;
    }

    /**
     * @return mixed|string
     */
    public function getGalleryTitle()
    {
        if (!$galleryTitle = $this->getOwner()->config()->get('gallery-title')) {
            $galleryTitle = _t('PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension.ImageGallery',
                'Image Gallery');
        }

        $this->getOwner()->extend('updateGalleryTitle', $galleryTitle);

        return $galleryTitle;
    }
}
