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
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use Colymba\BulkUpload\BulkUploader;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Versioned\Versioned;
use PurpleSpider\BasicGalleryExtension\PhotoGalleryImage;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;

class PhotoGalleryExtension extends \SilverStripe\Core\Extension
{

    public $owner;

    /**
     * Images are ordered by hand, by dragging rows in the CMS.
     */
    const SORT_CUSTOM = 'Custom';

    const SORT_FILENAME_ASC = 'FilenameAsc';

    const SORT_FILENAME_DESC = 'FilenameDesc';

    const SORT_CREATED_ASC = 'CreatedAsc';

    const SORT_CREATED_DESC = 'CreatedDesc';

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

    /**
     * Deliberately a Varchar rather than an Enum, so that a site (or another module) can
     * offer extra sort modes without an ALTER TABLE on every gallery-owning class.
     * Legacy rows are NULL, which normalises to SORT_CUSTOM.
     *
     * @config
     */
    private static $db = [
        'GallerySortMode' => 'Varchar(32)'
    ];

    /**
     * The sort modes offered in the CMS dropdown, in the order they are shown.
     *
     * @config
     */
    private static $gallery_sort_modes = [
        self::SORT_CUSTOM,
        self::SORT_FILENAME_ASC,
        self::SORT_FILENAME_DESC,
        self::SORT_CREATED_ASC,
        self::SORT_CREATED_DESC,
    ];

    /**
     * Used for galleries with no sort mode stored yet. Unlike $defaults this can be set
     * per site or per page class in YAML, and applies to existing galleries too.
     *
     * @config
     */
    private static $default_gallery_sort_mode = self::SORT_CUSTOM;

    /**
     * Guards against the writes made by resortGalleryImages() triggering another re-sort.
     *
     * @var bool
     */
    protected static $isResorting = false;

    /**
     * @return bool
     */
    public static function isResorting()
    {
        return self::$isResorting;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeFieldFromTab('Root', 'PhotoGalleryImages');

        // Owners that scaffold their own fields - an Elemental block, for instance - would
        // otherwise render a stray TextField for the sort mode.
        $fields->removeByName('GallerySortMode');

        if (!$galleryCMSTab = $this->getOwner()->config()->get('gallery-cms-tab')) {
          $galleryCMSTab = "Main";
        }

        $insertGalleryBefore = null;
        if ($galleryCMSTab === "Main") {
          $insertGalleryBefore = "Metadata";
        }

        $sortMode = $this->getOwner()->getGallerySortMode();

        $gridFieldConfig = GridFieldConfig::create();

        $gridFieldConfig->addComponent(new BulkUploader());

        $bulkUpload = $gridFieldConfig->getComponentByType(BulkUploader::class);
        $bulkUpload->setUfSetup('setFolderName', $this->getBulkUploadFolderName());

        // Only offer drag and drop when the editor is actually in charge of the order - in
        // an automatic mode the next save would silently undo any drag.
        if ($sortMode === self::SORT_CUSTOM) {
            $gridFieldConfig->addComponent(GridFieldOrderableRows::create()->setSortField('SortOrder'));
        }

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

        $gridfield = GridField::create(
            "PhotoGalleryImages",
            $this->getGalleryTitle(),
            $this->getOwner()->PhotoGalleryImages(),
            $gridFieldConfig
        );

        $sortModeField = DropdownField::create(
            'GallerySortMode',
            _t('PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension.GallerySortMode', 'Image order'),
            $this->getOwner()->getGallerySortModes()
        )->setDescription(
            _t(
                'PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension.GallerySortModeDescription',
                'Images are re-ordered when you save. Choose "Custom" to arrange them yourself by '
                    . 'dragging and dropping.'
            )
        );

        // All three inserts share the same anchor, so call order is display order
        $fields->addFieldToTab('Root.'.$galleryCMSTab,
            HeaderField::create('addHeader',
                _t('PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension.AddImages','Add Images')
            ),
            $insertGalleryBefore);
        $fields->addFieldToTab('Root.'.$galleryCMSTab, $gridfield,$insertGalleryBefore);
        $fields->addFieldToTab('Root.'.$galleryCMSTab, $sortModeField, $insertGalleryBefore);

        return $fields;
    }

    public function GetGalleryImages()
    {
        return $this->getOwner()->PhotoGalleryImages()->sort(['SortOrder' => 'ASC', 'ID' => 'ASC']);
    }

    /**
     * The available sort modes, as a value => translated label map suitable for a dropdown.
     *
     * @return array
     */
    public function getGallerySortModes()
    {
        $defaultLabels = [
            self::SORT_CUSTOM => 'Custom (drag and drop)',
            self::SORT_FILENAME_ASC => 'Filename (A-Z)',
            self::SORT_FILENAME_DESC => 'Filename (Z-A)',
            self::SORT_CREATED_ASC => 'Date added (oldest first)',
            self::SORT_CREATED_DESC => 'Date added (newest first)',
        ];

        $modes = [];

        foreach ((array) $this->getOwner()->config()->get('gallery_sort_modes') as $mode) {
            $modes[$mode] = _t(
                'PurpleSpider\BasicGalleryExtension\PhotoGalleryExtension.SortMode' . $mode,
                isset($defaultLabels[$mode]) ? $defaultLabels[$mode] : $mode
            );
        }

        $this->getOwner()->extend('updateGallerySortModes', $modes);

        return $modes;
    }

    /**
     * The gallery's sort mode, always one of the modes on offer.
     *
     * Note this deliberately shadows the GallerySortMode database field: ViewableData::__get()
     * prefers a get<Field>() method, which is what makes Form::loadDataFrom() pre-select the
     * configured default for legacy galleries that have no value stored yet. Anything that
     * needs the raw stored value must use getField('GallerySortMode').
     *
     * @return string
     */
    public function getGallerySortMode()
    {
        $modes = $this->getOwner()->getGallerySortModes();
        $mode = $this->getOwner()->getField('GallerySortMode');

        if ($mode && isset($modes[$mode])) {
            return $mode;
        }

        $default = $this->getOwner()->config()->get('default_gallery_sort_mode');

        return ($default && isset($modes[$default])) ? $default : self::SORT_CUSTOM;
    }

    /**
     * Rewrite the SortOrder column so that it matches the selected sort mode.
     *
     * The order is applied by physically renumbering the rows rather than by sorting at
     * query time, so that every existing template - all of which loop PhotoGalleryImages
     * and rely on its $default_sort - honours it without any change.
     *
     * @return int Number of images whose SortOrder actually changed
     */
    public function resortGalleryImages()
    {
        $owner = $this->getOwner();

        if (self::$isResorting || !$owner->isInDB()) {
            return 0;
        }

        $mode = $owner->getGallerySortMode();

        // Custom means the editor owns the order, so there is nothing to do - and no query
        // to run, which keeps existing galleries free of any overhead.
        if ($mode === self::SORT_CUSTOM) {
            return 0;
        }

        self::$isResorting = true;

        try {
            $images = $owner->PhotoGalleryImages()->sort('ID ASC')->toArray();

            if (!$images) {
                return 0;
            }

            $sorted = false;

            switch ($mode) {
                case self::SORT_FILENAME_ASC:
                case self::SORT_FILENAME_DESC:
                    $this->sortImagesByFilename($images, $mode === self::SORT_FILENAME_DESC);
                    $sorted = true;
                    break;

                case self::SORT_CREATED_ASC:
                case self::SORT_CREATED_DESC:
                    $this->sortImagesByCreated($images, $mode === self::SORT_CREATED_DESC);
                    $sorted = true;
                    break;
            }

            // Both a chance to adjust the order above, and how a site or another module can
            // implement a mode it added to $gallery_sort_modes: reorder $images and set
            // $sorted to true. Anything else is left alone rather than silently renumbered.
            $owner->extend('updateSortedGalleryImages', $images, $mode, $sorted);

            if (!$sorted) {
                return 0;
            }

            $changed = 0;
            // 1-based: GridFieldOrderableRows treats a SortOrder of 0 as "not yet sorted"
            // and would rewrite it to MAX+1 on the first drag.
            $position = 1;

            foreach ($images as $image) {
                if ((int) $image->SortOrder !== $position) {
                    $image->SortOrder = $position;
                    $image->write();
                    $changed++;
                }

                $position++;
            }

            return $changed;
        } finally {
            self::$isResorting = false;
        }
    }

    /**
     * Sorted in PHP rather than SQL: sorting on the filename through the ORM would need a
     * join against File and would give collation order, where strnatcasecmp() puts
     * "DSC_2.jpg" before "DSC_10.jpg" as an editor would expect.
     *
     * @param PhotoGalleryImage[] $images Sorted in place
     * @param bool $descending
     */
    protected function sortImagesByFilename(array &$images, $descending)
    {
        $names = $this->getImageFileNames($images);

        usort($images, function ($a, $b) use ($names, $descending) {
            $nameA = isset($names[$a->ID]) ? $names[$a->ID] : null;
            $nameB = isset($names[$b->ID]) ? $names[$b->ID] : null;

            // Images whose file has gone missing sort last, in both directions
            if ($nameA === null || $nameB === null) {
                if ($nameA === $nameB) {
                    return $a->ID - $b->ID;
                }

                return $nameA === null ? 1 : -1;
            }

            $result = strnatcasecmp($nameA, $nameB);

            // ID is only a stable tie-break here, not part of the order, so it isn't reversed
            if ($result === 0) {
                return $a->ID - $b->ID;
            }

            return $descending ? -$result : $result;
        });
    }

    /**
     * @param PhotoGalleryImage[] $images Sorted in place
     * @param bool $descending
     */
    protected function sortImagesByCreated(array &$images, $descending)
    {
        usort($images, function ($a, $b) use ($descending) {
            // Created is stored as 'Y-m-d H:i:s', which compares correctly as a string
            $result = strcmp((string) $a->Created, (string) $b->Created);

            // A bulk upload shares a timestamp to the second, so fall back to ID - which is
            // itself a proxy for "added later", and so does get reversed
            if ($result === 0) {
                $result = $a->ID - $b->ID;
            }

            return $descending ? -$result : $result;
        });
    }

    /**
     * Look up the filename of each image in one query.
     *
     * Uses Name, not Filename - the latter is a subfield of the DBFile composite rather than
     * a column. Images with no usable file are simply absent from the returned map.
     *
     * @param PhotoGalleryImage[] $images
     * @return array PhotoGalleryImage ID => filename
     */
    protected function getImageFileNames(array $images)
    {
        $fileIDs = [];

        foreach ($images as $image) {
            if ($image->ImageID) {
                $fileIDs[] = (int) $image->ImageID;
            }
        }

        if (!$fileIDs) {
            return [];
        }

        // Read from the draft stage explicitly: this also runs while publishing, when an
        // unpublished image would have no row in File_Live and so no name to sort on.
        $files = Versioned::withVersionedMode(function () use ($fileIDs) {
            Versioned::set_stage(Versioned::DRAFT);

            $names = [];

            foreach (File::get()->filter('ID', $fileIDs)->map('ID', 'Name')->toArray() as $id => $name) {
                $names[(int) $id] = $name;
            }

            return $names;
        });

        $names = [];

        foreach ($images as $image) {
            $fileID = (int) $image->ImageID;

            if ($fileID && !empty($files[$fileID])) {
                $names[$image->ID] = $files[$fileID];
            }
        }

        return $names;
    }

    public function onAfterWrite()
    {
        // writeToStage() does a forceChange() + write() in the Live stage on every publish.
        // Re-sorting there would run a second time over the same rows, and would read
        // filenames from the live tables.
        if (Versioned::get_stage() === Versioned::LIVE) {
            return;
        }

        // Not gated on the sort mode having changed: re-running on every draft save costs a
        // single query and makes the gallery self-healing after a file is renamed in assets.
        $this->getOwner()->resortGalleryImages();
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
