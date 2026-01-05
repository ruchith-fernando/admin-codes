<?php
// /public_html/pages/pdf_diag.php
error_reporting(E_ALL);
ini_set('display_errors','1');
header('Content-Type: text/plain');

$BASE = __DIR__;
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__,1), '/');

echo "=== ENV ===\n";
printf("PHP %s\n", PHP_VERSION);
printf("memory_limit: %s\n", ini_get('memory_limit'));
printf("DOCUMENT_ROOT: %s\n", $DOCROOT);
printf("__DIR__: %s\n\n", $BASE);

$autoload = $BASE . '/dompdf/autoload.inc.php';
echo "=== CHECKS ===\n";
echo "[1] dompdf autoloader: $autoload ... ";
echo is_file($autoload) ? "OK\n" : "MISSING\n";

$tmp = $BASE.'/tmp';  $out = $BASE.'/output';
foreach ([$tmp,$out] as $d) {
  echo "[2] dir $d ... ";
  if (!is_dir($d)) @mkdir($d,0755,true);
  echo (is_dir($d) ? "exists" : "missing") . " | ";
  echo (is_writable($d) ? "writable\n" : "NOT WRITABLE\n");
}

echo "[3] cURL: "; echo function_exists('curl_init') ? "OK\n" : "NOT AVAILABLE\n";

if (!is_file($autoload)) { exit("\nFix autoloader path and re-run.\n"); }

require_once $autoload;
use Dompdf\Dompdf; use Dompdf\Options;

try {
  echo "\n=== DOMPDF SAMPLE RENDER ===\n";
  $opts = new Options();
  $opts->set('isRemoteEnabled', true);
  $opts->set('isHtml5ParserEnabled', true);
  $opts->set('chroot', $DOCROOT);
  $opts->set('dpi', 72);
  $opts->set('tempDir', $tmp);
  $opts->set('fontCache', $tmp.'/font-cache');

  $dompdf = new Dompdf($opts);
  $html = '<!doctype html><meta charset="utf-8"><style>@page{margin:0}body{margin:0;font:16px Arial}div{padding:40px}</style><div>Sample OK — dompdf can render here.</div>';
  $dompdf->loadHtml($html,'UTF-8');
  $dompdf->setPaper([0,0,595,20000]); // one tall page
  $dompdf->render();
  $pdf = $dompdf->output();

  $file = $out.'/__diag-sample.pdf';
  if (file_put_contents($file, $pdf) === false) {
    echo "Write FAILED: $file\n"; 
  } else {
    echo "Rendered & wrote: $file\nOpen: /pages/output/__diag-sample.pdf\n";
  }
} catch (Throwable $e) {
  echo "EXCEPTION: ".$e->getMessage()."\n";
  echo $e->getFile().":".$e->getLine()."\n";
}
