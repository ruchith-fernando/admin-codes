<?php
require __DIR__ . '/vendor-composer/vendor/autoload.php';  // <-- important

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

$code = $_GET['code'] ?? 'TEST123';
$fmt  = strtolower($_GET['fmt'] ?? 'svg');

if ($fmt === 'png' && extension_loaded('gd')) {
    $gen = new BarcodeGeneratorPNG();
    header('Content-Type: image/png');
    echo $gen->getBarcode($code, $gen::TYPE_CODE_128, 2, 60);
} else {
    $gen = new BarcodeGeneratorSVG();
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo $gen->getBarcode($code, $gen::TYPE_CODE_128);
}
