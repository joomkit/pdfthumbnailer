<?php
/**
 * pdfthumbnailer plugin for Craft CMS 3.x
 *
 * Create thumbnail images from pdf docs
 *
 * @link      https://www.joomkit.com
 * @copyright Copyright (c) 2018 Alan
 */

namespace joomkit\pdfthumbnailer\services;

use craft\elements\Asset;
use joomkit\pdfthumbnailer\Pssdfthumbnailer;
use joomkit\pdfthumbnailer\models\Settings;


use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\helpers\FileHelper;

use yii\base\ErrorException;

/**
 * SaveEntry Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Alan
 * @package   Pdfthumbnailer
 * @since     1.0.0
 */
class SaveEntry extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     Pdfthumbnailer::$plugin->saveEntry->exampleService()
     *
     * @return mixed
     */
    public function exampleService()
    {
        $result = 'something';
        // Check our Plugin's settings for `someAttribute`
        if (Pdfthumbnailer::$plugin->getSettings()->someAttribute) {
        }

        return $result;
    }

    public function setThumb($entry)
    {

        if($entry->pdfDocument) {
            //check if PDF asset exists for entry
            $asset = $entry->pdfDocument->one();

            //$this->prettyPrint($entry); die();

            if (!is_null($asset)) {
                //craft()->userSession->setNotice('Making pdf thumbnail.....');
                $this->makeThumb($entry);

            }else{

            }


        }

    }

//

    public function makeThumb($entry)
    {
        //if thumb exists dont bother making another one

        //if thumb doesnt exist lets make one

        //str_replace('{basePath}', $basePATH,
        $asset = $entry->pdfDocument->one();

        $pdfAsset = $this->getPDFAsset($entry);

        $im = new \Imagick();


        $im->setResolution(150,150); // dubious medium choice
        $im->readimage($pdfAsset . '[0]'); // reads first page '[0]' of pdf and takes this as thumbnail

        $cropWidth = $im->getImageWidth();
        $cropHeight = $im->getImageHeight();

        $im->setImageFormat('jpg');
        // Remove transparency, fill transparent areas with white rather than black.
        $im->setImageBackgroundColor("white");
        $im->borderImage("white", 0,0);
        // $im->setImageAlphaChannel($im->ALPHACHANNEL_REMOVE); // Imagick::ALPHACHANNEL_REMOVE
        $im->mergeImageLayers($im->LAYERMETHOD_FLATTEN);
        //$im->transformImageColorspace($im->COLORSPACE_sRGB);
        //$im->scaleImage(595, 842, true, false);
        $im->resizeImage( 595, 842, $im->FILTER_LANCZOS, 1 );


        $im->trimImage(0);
        //store image

        $tmpImagePath =  getenv('CRAFTENV_BASE_PATH').'storage/runtime/';
        //$tmpImagePath = sys_get_temp_dir();

        $im->writeImage($tmpImagePath . $entry->slug . '.jpg');

//        $tmpAsset =(object) array(
//            "title" => $entry->slug,
//            "tempFilePath" => $tmpImagePath,
//            "volumeId" => "9",
//            "filename" => $entry->slug . '.jpg',
//            "newFolderId" => "0",
//            "avoidFilenameConflicts" => true,
//        );

        $assetId = $this->savePDFThumbImageAsAsset($tmpImagePath,$entry);

        Craft::info(
            Craft::t(
                'pdfthumbnailer',
                'foo '.$assetId
            ),
            __METHOD__
        );

        $result = $this->SaveAssetToEntry($assetId, $entry);
        if ($result === false) {
            throw new Exception('Error');
        }
    }

    public function savePDFThumbImageAsAsset($tmpImagePath, $entry)
    {
        $assets = Craft::$app->getAssets();
        $folderId = 16; //<- insert your folder id

        /** @var \craft\models\VolumeFolder $folder */
        $folder = $assets->findFolder(['id' => $folderId]);

        $asset = new Asset();
        $asset->tempFilePath = $tmpImagePath . $entry->slug . '.jpg';
        $asset->filename = $entry->slug . '.jpg';
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_DEFAULT);

        $result = Craft::$app->getElements()->saveElement($asset);

//        // In case of error, let user know about it.
//        if ($result === false) {
//            throw new Exception('Error while upload asset');
//        }



        return $asset->id;
    }

    public function SaveAssetToEntry($assetId, $entry){

        // Set the custom field values (including Matrix)
        $fieldValues = ['pdfThumbnailImage' => [$assetId]];

//        $element->setFieldValue('fieldHandle', $ids);

        $entry->setFieldValue('pdfThumbnailImage', [$assetId]);
        $entry->title = "Fooobarmutherfucker";

        //Craft::$app->elements->saveElement($entry);
        //return $assetId;
    }

//    public function savePDFAssetThumb(){
//        $asset = new Asset();
//        $asset->title = $url;
//        $asset->tempFilePath = $tmpPath; // temp path for image in /storage/runtime/temp/
//        $asset->volumeId = $volumeId; // ID of my Asset Volume
//        $asset->filename = $filename; // filename like youtube_<youTubeKey>.jpg
//        $asset->newFolderId = $folder->id; // root folder id of the volume
//        $asset->avoidFilenameConflicts = true;
//        $asset->setScenario(Asset::SCENARIO_CREATE);
//        clearstatcache();
//        list ($w, $h) = Image::imageSize($tmpPath);
//        $asset->setWidth($w);
//        $asset->setHeight($h);
//        $result = Craft::$app->getElements()->saveElement($asset);
//    }

    public function savePDFThumbnailAsCMSAsset($pdfAsset, $entry){

        $filename = $entry->slug. '.jpg' ;
        $localFilePath = $pdfAsset->tempFileLocation . $filename;

        //asset folder $folder->id = '9';

        //get asset folder info - ideally from config
        // Find the target folder
        $folder = craft()->assets->findFolder(array(
            'sourceId' => $this->getSettings()->pdfAssetFolderId  //$this->settings->getAttribute('pdfAssetFolderId')
        ));
        $folder->id = $folder->getAttribute('id');

        //conflict res can be Replace or KeepBoth

        $response = craft()->assets->insertFileByLocalPath(
            $localFilePath,
            $filename,
            $folder->id,
            AssetConflictResolution::Replace
        );

        // Get id of pdf (newly created Asset)
        $pdfId = craft()->assets->findFile(array(
            'filename' => $filename
        ))->id;

        // Add PDF image to entry // field must be called pdfThumbnailImage
        $entry->setContentFromPost(array(
            'pdfThumbnailImage' => array($pdfId)
        ));
        //saves without triggering itself
        craft()->elements->saveElement($entry);
//        if (craft()->entries->saveEntry($entry)) {
//            PdfThumbnailerPlugin::log("saved " . $pdfId);
//        }
        // $this->entryPrettyPrint($response); die();
        return $response;
    }



    public function checkThumbExists($entry)
    {
        //$asset = $entry->pdfDocument->first();
        //var_dump($asset); die();
//        $source = $asset->getSource();
//        return IOHelper::fileExists($path);
    }

    public function prettyPrint($arg){
        echo '<pre>';
        print_r($arg);
        echo '</pre>';

    }

    public function fileExists($entry)
    {
        $asset = $entry->pdfDocument->first();
        //var_dump($asset); die();
//        $source = $asset->getSource();
//        return IOHelper::fileExists($path);
    }



    /*
     *
     */
    public function getPDFAsset($entry)
    {

        $asset = $entry->pdfDocument->one();
        //get basepath env variable from config and replace stirng with real path if setup uses basePath
       // $basePATH = craft()->config->get('environmentVariables')['basePath'];


        $volumePath = $asset->getVolume()->settings['path'];
        $folderPath = $asset->getFolder()->path."/";
        $assetFilePath = Craft::getAlias($volumePath) . $folderPath . $asset->filename;


        return $assetFilePath;
    }

    public function entryPrettyPrint($arg){
        echo '<pre>';
        echo print_r($arg) . '</pre>';
    }

}
