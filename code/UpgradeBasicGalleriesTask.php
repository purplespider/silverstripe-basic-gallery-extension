<?php

namespace PurpleSpider\MySite;

use SilverStripe\Dev\BuildTask;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;


class UpgradeBasicGalleriesTask extends BuildTask {

    /**
     * @config
     */
    protected string $title = 'Upgrade Basic Galleries';

    /**
     * @config
     */
    private static $segment = 'upgrade-basic-galleries';

    /**
     * @config
     */
    protected static string $description = "Applies database updates for Basic Galleries polymorphic update";

    #[\Override]protected function execute(InputInterface $input, PolyOutput $output): int
    {

        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('PhotoGalleryImage');
        $sqlQuery->selectField('PhotoGalleryPageID');

        $result = $sqlQuery->execute();

        $updatecount = 0;

        foreach($result as $row) {
            if(isset($row['PhotoGalleryPageID']) && $row['PhotoGalleryPageID']) {
                $page = SiteTree::get()->byID($row['PhotoGalleryPageID']);
                if($page) {
                    $pageClassname = $page->ClassName;
                    $update = SQLUpdate::create('"PhotoGalleryImage"')->addWhere(['ID' => $row['ID']]);
                    $update->assign('"AlbumID"', $row['PhotoGalleryPageID']);
                    $update->assign('"AlbumClass"', $pageClassname);
                    $update->assign('"PhotoGalleryPageID"', 0);
                    $update->execute();
                    $updatecount++;
                }
            }

            if(isset($row['PhotoGalleryBlockID']) && $row['PhotoGalleryBlockID']) {
                $update = SQLUpdate::create('"PhotoGalleryImage"')->addWhere(['ID' => $row['ID']]);
                $update->assign('"AlbumID"', $row['PhotoGalleryBlockID']);
                $update->assign('"AlbumClass"', \PurpleSpider\ElementalBasicGallery\ImageGalleryBlock::class);
                $update->assign('"PhotoGalleryBlockID"', 0);
                $update->execute();
                $updatecount++;
            }
        }

        $output->writeln(sprintf('Updated %d records.', $updatecount));

        return Command::SUCCESS;

    }
}
