<?php

namespace Codeception\Module;

use Codeception\Module as CodeceptionModule;
use Codeception\Test\Descriptor;
use RemoteWebDriver;
use Codeception\Module\VisualCeption\Utils;

/**
 * Class VisualCeption
 *
 * @copyright Copyright (c) 2014 G+J Digital Products GmbH
 * @license MIT license, http://www.opensource.org/licenses/mit-license.php
 * @package Codeception\Module
 *
 * @author Nils Langner <langner.nils@guj.de>
 * @author Torsten Franz
 * @author Sebastian Neubert
 * @author Ray Romanov
 */
class VisualCeption extends CodeceptionModule
{
    protected $config = [
        'maximumDeviation' => 0,
        'saveCurrentImageIfFailure' => true,
        'referenceImageDir' => 'visualception/reference/[browser]/',
        'currentImageDir' => 'visualception/current/[browser]/',
        'report' => false,
        'module' => 'WebDriver',
        'fullScreenShot' => false
    ];

    protected $saveCurrentImageIfFailure;
    private $referenceImageDir;

    /**
     * This var represents the directory where the taken images are stored
     * @var string
     */
    private $currentImageDir;

    private $maximumDeviation = 0;
    private $operationTimeout = 0.5;

    /**
     * @var RemoteWebDriver
     */
    private $webDriver = null;

    /**
     * @var WebDriver
     */
    private $webDriverModule = null;

    /**
     * @var Utils
     */
    private $utils;

    private $failed = array();
    private $failedTestsMetadata = array();
    private $logFile;

    public function _initialize()
    {
        $this->utils = new Utils();

        $this->maximumDeviation = $this->config["maximumDeviation"];
        $this->saveCurrentImageIfFailure = (boolean)$this->config["saveCurrentImageIfFailure"];
        $this->webDriverModule = $this->getModule($this->config['module']);
        if (false !== strpos($this->config["referenceImageDir"] . $this->config["currentImageDir"] . $this->config['report'], '[browser]')) {
            $browserName = $this->webDriverModule->_getConfig('browser');
            $this->config["referenceImageDir"] = str_replace('[browser]', $browserName, $this->config["referenceImageDir"]);
            $this->config["currentImageDir"] = str_replace('[browser]', $browserName, $this->config["currentImageDir"]);
            $this->config['report'] = str_replace('[browser]', $browserName, $this->config['report']);
        }
        if (file_exists($this->config["referenceImageDir"])) {
            $this->referenceImageDir = $this->config["referenceImageDir"];
        } else {
            $this->debug("Directory does not exist: $this->referenceImageDir");
            $this->referenceImageDir = codecept_data_dir() . $this->config["referenceImageDir"];
            if (!is_dir($this->referenceImageDir)) {
                $this->debug("Creating directory: $this->referenceImageDir");
                @mkdir($this->referenceImageDir, 0777, true);
            }
        }
        $this->currentImageDir = codecept_output_dir() . $this->config["currentImageDir"];
        $this->_initVisualReport();
    }

    public function _afterSuite()
    {
        if (!$this->config['report'] || ($this->config['report'] && !$this->failed)) {
            return;
        }
        $failedTests = $this->failed;
        $failedTestsMetadata = $this->failedTestsMetadata;
        $referenceImageDir = $this->referenceImageDir;
        $i = 0;

        if (!file_exists($this->logFile)) {
            ob_start();
            include_once "report/MainTemplate.php";
            $reportContent = ob_get_contents();
            ob_clean();
            file_put_contents($this->logFile, $reportContent);
            $this->debug("Trying to store file (" . $this->logFile . ")");
        }
        ob_start();
        include_once "report/ItemsTemplate.php";
        $itemsContent = ob_get_contents();
        ob_clean();

        $logFileContent = mb_convert_encoding(file_get_contents($this->logFile), 'UTF-8');
        $logFileContent = str_replace('<!--[END_ITEMS]-->', $itemsContent, $logFileContent);
        file_put_contents($this->logFile, $logFileContent);
    }


    public function _failed(\Codeception\TestInterface $test, $fail)
    {
        if ($fail instanceof ImageDeviationException) {
            $key = $test->getSignature() . '.' . $fail->getIdentifier();
            $this->failed[$key] = $fail;

            $title = empty($test->getFeature()) ? $test->getName() : mb_strstr($test->getFeature() . "|", "|", true);
            if (!is_null($test->getMetadata()->getCurrent('example')) && array_key_exists('wantTo', $test->getMetadata()->getCurrent('example'))) {
                $comment = $test->getMetadata()->getCurrent('example')['wantTo'];
                $title = $title . ' (' . $comment . ')';
            }
            $url = $this->_decodeId($fail->getIdentifier());
            $metadata = [
                'title' => $title,
                'url' => $url,
                'referenceImagePath' => $this->getExpectedScreenshotPath($fail->getIdentifier()),
                'env' => $test->getMetadata()->getEnv(),
                'file' => $test->getMetadata()->getFilename(),
                'error' => $fail->getMessage()
            ];
            $this->failedTestsMetadata[$key] = $metadata;

        }
    }


    /**
     * Event hook before a test starts
     *
     * @param \Codeception\TestInterface $test
     * @throws \Exception
     */
    public function _before(\Codeception\TestInterface $test)
    {
        if (!$this->hasModule($this->config['module'])) {
            throw new \Codeception\Exception\ConfigurationException("VisualCeption uses the WebDriver. Please ensure that this module is activated.");
        }
        if (!class_exists('Imagick')) {
            throw new \Codeception\Exception\ConfigurationException("VisualCeption requires ImageMagick PHP Extension but it was not installed");
        }

        $this->webDriver = $this->webDriverModule->webDriver;

        $this->test = $test;
    }

    /**
     * Get value of the private property $referenceImageDir
     *
     * @return string Path to reference image dir
     */
    public function getReferenceImageDir()
    {
        return $this->referenceImageDir;
    }

    /**
     * Compare the reference image with a current screenshot, identified by their indentifier name
     * and their element ID.
     *
     * @param string $identifier Identifies your test object
     * @param string $elementID DOM ID of the element, which should be screenshotted
     * @param string|array $excludeElements Element name or array of Element names, which should not appear in the screenshot
     * @param float $deviation
     */
    public function seeVisualChanges($identifier, $elementID = null, $excludeElements = array(), array $deleteElements = array(), $deviation = null)
    {
        $this->compareVisualChanges($identifier, $elementID, $excludeElements, $deleteElements, $deviation, true);

        // used for assertion counter in codeception / phpunit
        $this->assertTrue(true);

    }

    /**
     * Compare the reference image with a current screenshot, identified by their indentifier name
     * and their element ID.
     *
     * @param string $identifier identifies your test object
     * @param string $elementID DOM ID of the element, which should be screenshotted
     * @param string|array $excludeElements string of Element name or array of Element names, which should not appear in the screenshot
     * @param float $deviation
     */
    public function dontSeeVisualChanges($identifier, $elementID = null, $excludeElements = array(), array $deleteElements = array(), $deviation = null)
    {
        $this->compareVisualChanges($identifier, $elementID, $excludeElements, $deleteElements, $deviation, false);

        // used for assertion counter in codeception / phpunit
        $this->assertTrue(true);
    }

    private function compareVisualChanges($identifier, $elementID, $excludeElements, $deleteElements, $deviation, $seeChanges)
    {
        $excludeElements = (array)$excludeElements;
        $deleteElements = (array)$deleteElements;

        $maximumDeviation = (!$deviation && !is_numeric($deviation)) ? $this->maximumDeviation : (float)$deviation;

        $deviationResult = $this->getDeviation($identifier, $elementID, $excludeElements, $deleteElements);

        if (is_null($deviationResult["deviationImage"])) {
            return;
        }

        if ($seeChanges && $deviationResult["deviation"] <= $maximumDeviation ||
            !$seeChanges && $deviationResult["deviation"] > $maximumDeviation) {
            $compareScreenshotPath = $this->getDeviationScreenshotPath($identifier);
            $deviationResult["deviationImage"]->writeImage($compareScreenshotPath);

            throw $this->createImageDeviationException($identifier, $compareScreenshotPath, $deviationResult["deviation"], $seeChanges);
        }
    }

    private function createImageDeviationException($identifier, $compareScreenshotPath, $deviation, $seeChanges)
    {
        if ($seeChanges) {
            $message = "The deviation of the taken screenshot is too low";
        } else {
            $message = "The deviation of the taken screenshot is too high";
        }

        $message .= " (" . round($deviation, 2) . "%).\n";

        return new ImageDeviationException(
            $message,
            $identifier,
            $this->getExpectedScreenshotPath($identifier),
            $this->getScreenshotPath($identifier),
            $compareScreenshotPath
        );
    }

    private function setElementsAttribute(array $elementsSelector, $attributeName, $attributeValue)
    {
        foreach ($elementsSelector as $element) {

            $this->webDriver->executeScript('
            var elements = [];
            elements = document.querySelectorAll("' . $element . '");
            if( elements.length > 0 ) {
                for (var i = 0; i < elements.length; i++) {
                    elements[i].style.' . $attributeName . ' = "' . $attributeValue . '";
                }
            }
        ');
        }
    }

    /**
     * Compares the two images and calculate the deviation between expected and actual image
     *
     * @param string $identifier Identifies your test object
     * @param string $elementID DOM ID of the element, which should be screenshotted
     * @param array $excludeElements Element names, which should not appear in the screenshot
     * @return array Includes the calculation of deviation in percent and the diff-image
     */
    private function getDeviation($identifier, $elementID, array $excludeElements = array(), array $deleteElements = array())
    {
        $coords = $this->getCoordinates($elementID);
        $this->createScreenshot($identifier, $coords, $excludeElements, $deleteElements);

        $compareResult = $this->compare($identifier);

        $deviation = $compareResult[1] * 100;

        $this->debug("The deviation between the images is " . $deviation . " percent");

        return array(
            "deviation" => $deviation,
            "deviationImage" => $compareResult[0],
            "currentImage" => $compareResult['currentImage'],
        );
    }

    /**
     * Initialize the module and read the config.
     * Throws a runtime exception, if the
     * reference image dir is not set in the config
     *
     * @throws \RuntimeException
     */

    /**
     * Find the position and proportion of a DOM element, specified by it's ID.
     * Used native JavaScript.
     * @param string $elementId DOM ID of the element, which should be screenshotted
     * @return array coordinates of the element
     */
    private function getCoordinates($elementId)
    {
        if (is_null($elementId)) {
            $elementId = 'body';
        }

        $imageCoords = array();

        $elementExists = (bool)$this->webDriver->executeScript('return document.querySelectorAll( "' . $elementId . '" ).length > 0;');

        if (!$elementExists) {
            throw new \Exception("The element you want to examine ('" . $elementId . "') was not found.");
        }

        $coords = $this->webDriver->executeScript('return document.querySelector( "' . $elementId . '" ).getBoundingClientRect();');

        $imageCoords['offset_x'] = $coords['left'];
        $imageCoords['offset_y'] = $coords['top'];
        $imageCoords['width'] = $coords['width'];
        $imageCoords['height'] = $coords['height'];

        return $imageCoords;
    }

    /**
     * Generates a screenshot image filename
     * it uses the testcase name and the given indentifier to generate a png image name
     *
     * @param string $identifier identifies your test object
     * @return string Name of the image file
     */
    private function getScreenshotName($identifier)
    {
        $identifier = str_replace(array('/', '\\'), array('.', '.'), $identifier);
        return $identifier . '.png';
    }

    /**
     * Returns the temporary path including the filename where a the screenshot should be saved
     * If the path doesn't exist, the method generate it itself
     *
     * @param string $identifier identifies your test object
     * @return string Path an name of the image file
     * @throws \RuntimeException if debug dir could not create
     */
    private function getScreenshotPath($identifier)
    {
        $debugDir = $this->currentImageDir;
        if (!is_dir($debugDir)) {
            $created = @mkdir($debugDir, 0777, true);
            if ($created) {
                $this->debug("Creating directory: $debugDir");
            } else {
                throw new \RuntimeException("Unable to create temporary screenshot dir ($debugDir)");
            }
        }
        return $debugDir . $this->getScreenshotName($identifier);
    }

    /**
     * Returns the reference image path including the filename
     *
     * @param string $identifier identifies your test object
     * @return string Name of the reference image file
     */
    private function getExpectedScreenshotPath($identifier)
    {
        return $this->referenceImageDir . $this->getScreenshotName($identifier);
    }

    /**
     * Generate the screenshot of the dom element
     *
     * @param string $identifier identifies your test object
     * @param array $coords Coordinates where the DOM element is located
     * @param array $excludeElements List of elements, which should not appear in the screenshot
     * @return string Path of the current screenshot image
     */
    private function createScreenshot($identifier, array $coords, array $excludeElements = array(), array $deleteElements = array())
    {
        $screenShotDir = $this->currentImageDir . 'debug/';

        if (!is_dir($screenShotDir)) {
            mkdir($screenShotDir, 0777, true);
        }

        $elementPath = $this->getScreenshotPath($identifier);
        $screenShotImage = new \Imagick();
        $width = 0;
        $height = 0;
        if ($this->config["fullScreenShot"] == "true" || $this->config["fullScreenShot"] == "scroll") {
            $height = $this->webDriver->executeScript("var ele=document.querySelector('html'); return ele.scrollHeight;");
            $viewportHeight = $this->webDriver->executeScript("return window.innerHeight");

            $itr = intval($height / $viewportHeight);

            for ($i = 0; $i < $itr; $i++) {
                $screenshotBinary = $this->webDriver->takeScreenshot();
                $screenShotImage->readimageblob($screenshotBinary);
                $this->webDriver->executeScript("window.scrollBy(0, {$viewportHeight});");
            }

            $screenshotBinary = $this->webDriver->takeScreenshot();
            $screenShotImage->readimageblob($screenshotBinary);
            $heightOffset = $viewportHeight - ($height - ($itr * $viewportHeight));
            $screenShotImage->cropImage(0, 0, 0, $heightOffset * 2);

            $screenShotImage->resetIterator();
            $fullShot = $screenShotImage->appendImages(true);
            $fullShot->writeImage($elementPath);

            return $elementPath;

        } elseif ($this->config["fullScreenShot"] == "resize") {

            $width = (int)$this->webDriver->manage()->window()->getSize()->getWidth();
            $height = (int)$this->webDriver->manage()->window()->getSize()->getHeight();

            $fullHeight = (int)$this->webDriver->executeScript('return Math.max( document.body.scrollHeight, document.body.offsetHeight, document.documentElement.clientHeight, document.documentElement.scrollHeight, document.documentElement.offsetHeight );');
            $this->webDriverModule->resizeWindow($width, $fullHeight);
            $this->debug('Resize browser window from ' . $width . 'x' . $height . ' to ' . $width . 'x' . $fullHeight);
        }
        $this->hideElementsForScreenshot($excludeElements);
        $this->deleteElementsForScreenshot($deleteElements);

        $screenshotBinary = $this->webDriver->takeScreenshot();
        $screenShotImage->readimageblob($screenshotBinary);

        if ($this->config["fullScreenShot"] != "resize") {
            $screenShotImage->cropImage($coords['width'], $coords['height'], $coords['offset_x'], $coords['offset_y']);
        }
        $screenShotImage->writeImage($elementPath);

        $this->resetHideElementsForScreenshot($excludeElements);
        $this->resetDeleteElementsForScreenshot($deleteElements);

        if ($this->config["fullScreenShot"] == "resize") {
            $this->webDriverModule->resizeWindow($width, $height);
            $this->debug('Resize back to ' . $width . 'x' . $height);
        }

        return $elementPath;
    }

    /**
     * Hide the given elements with CSS visibility = hidden. Wait a second after hiding
     *
     * @param array $excludeElements Array of strings, which should be not visible
     */
    private function hideElementsForScreenshot(array $excludeElements)
    {
        if ($excludeElements) {
            $this->setElementsAttribute($excludeElements, 'visibility', 'hidden');
            $this->webDriverModule->waitForElementNotVisible(array_pop($excludeElements));
        }
    }

    /**
     * Reset hiding the given elements with CSS visibility = visible. Wait a second after reset hiding
     *
     * @param array $excludeElements array of strings, which should be visible again
     */
    private function resetHideElementsForScreenshot(array $excludeElements)
    {
        if ($excludeElements) {
            $this->setElementsAttribute($excludeElements, 'visibility', 'visible');
            $this->webDriverModule->wait($this->operationTimeout);
        }
    }

    private function deleteElementsForScreenshot(array $deleteElements)
    {
        if ($deleteElements) {
            $this->setElementsAttribute($deleteElements, 'display', 'none');
            $this->webDriverModule->waitForElementNotVisible(array_pop($deleteElements));
        }
    }

    private function resetDeleteElementsForScreenshot(array $deleteElements)
    {
        if ($deleteElements) {
            $this->setElementsAttribute($deleteElements, 'display', '');
            $this->webDriverModule->wait($this->operationTimeout);
        }
    }

    /**
     * Returns the image path including the filename of a deviation image
     *
     * @param $identifier identifies your test object
     * @return string Path of the deviation image
     */
    private function getDeviationScreenshotPath($identifier, $alternativePrefix = '')
    {
        $debugDir = $this->currentImageDir . 'debug/';
        $prefix = ($alternativePrefix === '') ? 'compare' : $alternativePrefix;
        return $debugDir . $prefix . $this->getScreenshotName($identifier);
    }


    /**
     * Compare two images by its identifiers.
     * If the reference image doesn't exists
     * the image is copied to the reference path.
     *
     * @param $identifier identifies your test object
     * @return array Test result of image comparison
     */
    private function compare($identifier)
    {
        $expectedImagePath = $this->getExpectedScreenshotPath($identifier);
        $currentImagePath = $this->getScreenshotPath($identifier);

        if (!file_exists($expectedImagePath)) {
            $this->debug("Copying image (from $currentImagePath to $expectedImagePath");
            copy($currentImagePath, $expectedImagePath);
            return array(null, 0, 'currentImage' => null);
        } else {
            return $this->compareImages($expectedImagePath, $currentImagePath);
        }
    }

    /**
     * Compares to images by given file path
     *
     * @param $image1 Path to the exprected reference image
     * @param $image2 Path to the current image in the screenshot
     * @return array Result of the comparison
     */
    private function compareImages($image1, $image2)
    {
        $this->debug("Trying to compare $image1 with $image2");

        $imagick1 = new \Imagick($image1);
        $imagick2 = new \Imagick($image2);

        $imagick1Size = $imagick1->getImageGeometry();
        $imagick2Size = $imagick2->getImageGeometry();

        $maxWidth = max($imagick1Size['width'], $imagick2Size['width']);
        $maxHeight = max($imagick1Size['height'], $imagick2Size['height']);

        $imagick1->extentImage($maxWidth, $maxHeight, 0, 0);
        $imagick2->extentImage($maxWidth, $maxHeight, 0, 0);

        try {
            $result = $imagick1->compareImages($imagick2, \Imagick::METRIC_MEANSQUAREERROR);
            $result[0]->setImageFormat('png');
            $result['currentImage'] = clone $imagick2;
            $result['currentImage']->setImageFormat('png');
        } catch (\ImagickException $e) {
            $this->debug("IMagickException! could not campare image1 ($image1) and image2 ($image2).\nExceptionMessage: " . $e->getMessage());
            $this->fail($e->getMessage() . ", image1 $image1 and image2 $image2.");
        }
        return $result;
    }

    protected function _initVisualReport()
    {
        if (!$this->config['report']) {
            return;
        }
        $this->logFile = \Codeception\Configuration::logDir() . '/vcresult.html';
    }

    public function _encodeId($id)
    {
        return strtr(base64_encode(trim($id)), '+=/', '.-~');
    }

    public function _decodeId($id)
    {
        return base64_decode(strtr($id, '.-~', '+=/'));
    }

    public function seeVisualChangesInCurrentPage($excludeElements = array(), $deleteElements = array(), $deviation = null)
    {
        $currentPageUrl = $this->webDriverModule->webDriver->getCurrentURL();
        $identifier = $this->_encodeId($currentPageUrl);
        $this->compareVisualChanges($identifier, null, $excludeElements, $deleteElements, $deviation, true);

        // used for assertion counter in codeception / phpunit
        $this->assertTrue(true);

    }

    public function dontSeeVisualChangesInCurrentPage($excludeElements = array(), $deleteElements = array(), $deviation = null)
    {
        $currentPageUrl = $this->webDriverModule->webDriver->getCurrentURL();
        $identifier = $this->_encodeId($currentPageUrl);
        $this->compareVisualChanges($identifier, null, $excludeElements, $deleteElements, $deviation, false);

        // used for assertion counter in codeception / phpunit
        $this->assertTrue(true);
    }

}