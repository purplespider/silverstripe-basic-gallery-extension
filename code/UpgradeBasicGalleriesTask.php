<?php

namespace PurpleSpider\MySite;

use PurpleSpider\BasicGalleryExtension\PhotoGalleryImage;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Command\DbBuild;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Moves gallery images from the separate PhotoGalleryPageID and PhotoGalleryBlockID columns
 * used before v3 to the polymorphic Album relation. Until this has run, upgraded galleries
 * render empty without any error.
 *
 * Only touches images with no album yet, and empties the old column of each one it moves, so
 * it's safe to re-run.
 */
class UpgradeBasicGalleriesTask extends BuildTask {

    /**
     * @config
     */
    protected string $title = 'Upgrade Basic Galleries';

    protected static string $commandName = 'upgrade-basic-galleries';

    /**
     * @config
     */
    protected static string $description = "Moves gallery images from the old PhotoGalleryPageID and PhotoGalleryBlockID columns to the Album relation";

    #[\Override]protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $schema = DB::get_schema();

        if (!$schema->hasTable('PhotoGalleryImage') || !$schema->hasField('PhotoGalleryImage', 'AlbumID')) {
            $output->writeln('The PhotoGalleryImage table is not up to date. Build the database first.');

            return Command::FAILURE;
        }

        // Neither column exists on a site that started on v3 or later
        $hasPageColumn = $schema->hasField('PhotoGalleryImage', 'PhotoGalleryPageID');
        $hasBlockColumn = $schema->hasField('PhotoGalleryImage', 'PhotoGalleryBlockID');

        $moved = 0;
        $skipped = 0;

        if ($hasPageColumn || $hasBlockColumn) {
            $pageClasses = $hasPageColumn ? $this->getPageClasses() : [];

            $sqlQuery = SQLSelect::create()
                ->setFrom('"PhotoGalleryImage"')
                ->selectField('"ID"')
                ->addWhere(['"AlbumID"' => 0]);

            if ($hasPageColumn) {
                $sqlQuery->selectField('"PhotoGalleryPageID"');
            }

            if ($hasBlockColumn) {
                $sqlQuery->selectField('"PhotoGalleryBlockID"');
            }

            foreach ($sqlQuery->execute() as $row) {
                $pageID = (int) ($row['PhotoGalleryPageID'] ?? 0);
                $blockID = (int) ($row['PhotoGalleryBlockID'] ?? 0);

                if (!$pageID && !$blockID) {
                    continue;
                }

                $albumID = $pageID ?: $blockID;
                $albumClass = $pageID
                    ? ($pageClasses[$pageID] ?? null)
                    : 'PurpleSpider\ElementalBasicGallery\ImageGalleryBlock';

                // AlbumClass is an enum of the classes on this site, so a class that's gone
                // (a deleted page, or a site without the Elemental block any more) would be
                // a database error rather than just a broken link. Leave those images as they
                // are: re-running the task picks them up if the class comes back.
                if (!$albumClass || !is_subclass_of($albumClass, DataObject::class)) {
                    $skipped++;
                    continue;
                }

                $update = SQLUpdate::create('"PhotoGalleryImage"')->addWhere(['"ID"' => $row['ID']]);
                $update->assign('"AlbumID"', $albumID);
                $update->assign('"AlbumClass"', $albumClass);

                if ($hasPageColumn) {
                    $update->assign('"PhotoGalleryPageID"', 0);
                }

                if ($hasBlockColumn) {
                    $update->assign('"PhotoGalleryBlockID"', 0);
                }

                $update->execute();
                $moved++;
            }
        }

        $output->writeln(sprintf('Moved %d images to the Album relation.', $moved));

        if ($skipped) {
            $output->writeln(sprintf(
                'Left %d images alone, as the page or block they belong to no longer exists.',
                $skipped
            ));
        }

        $output->writeln(sprintf('Corrected the ClassName of %d images.', $this->updateImageClassNames()));

        return Command::SUCCESS;
    }

    /**
     * The class of every page, by ID, read from the tables directly so it doesn't depend on
     * the reading mode. Draft wins, and the live table catches pages deleted from draft.
     *
     * @return array Page ID => class name
     */
    protected function getPageClasses(): array
    {
        $remapping = DbBuild::config()->get('classname_value_remapping');
        $classes = [];

        foreach (['SiteTree_Live', 'SiteTree'] as $table) {
            $rows = SQLSelect::create(['"ID"', '"ClassName"'], '"' . $table . '"')->execute();

            foreach ($rows as $row) {
                // In case the database hasn't been built since the class names were remapped
                $classes[(int) $row['ID']] = $remapping[$row['ClassName']] ?? $row['ClassName'];
            }
        }

        return $classes;
    }

    /**
     * Same as the classname_value_remapping in basic-galleries, for sites where that hasn't
     * applied, or doesn't exist because the extension is used without that module.
     *
     * @return int Number of images changed
     */
    protected function updateImageClassNames(): int
    {
        SQLUpdate::create('"PhotoGalleryImage"')
            ->assign('"ClassName"', PhotoGalleryImage::class)
            ->addWhere(['"ClassName" IN (?, ?)' => [
                'PhotoGalleryImage',
                'PurpleSpider\BasicGalleries\PhotoGalleryImage',
            ]])
            ->execute();

        return DB::affected_rows();
    }
}
