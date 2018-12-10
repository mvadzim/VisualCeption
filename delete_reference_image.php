<?php
/*
 * https://github.com/mvadzim/VisualCeption
 */
$path = '/home/qa/img_storage/[browser]/[environment]/[file]';
$file = trim(urldecode($_GET["file"]), '\/'); // testimage.png
$browser = trim(urldecode($_GET["browser"]), '\/'); // chrome, firefox etc.
$environment = trim(urldecode($_GET["environment"]), '\/'); // prod, dev, stage, qa etc.

if ($file && $browser && $environment) {
    $path = str_replace(['[file]', '[browser]', '[environment]'], [$file, $browser, $environment], $path);
    $path = realpath($path);
    if (!file_exists($path)) {
        die('<b style="color: orange">File not found</b><br/>Path: ' . $path);
    } elseif (!is_writable($path)) {
        die('<b style="color: red">No write permission</b><br/>Path: ' . $path);
    } elseif (unlink($path)) {
        echo '<b style="color: green">File deleted</b><br/>Path: ' . $path;
    } else {
        die('<b style="color: red">Unknown error</b><br/>Path: ' . $path);
    }
} else {
    die('<b style="color: red">No required parameters</b>');
}