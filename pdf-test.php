<?php
require 'dompdf/autoload.inc.php'; // change if you kept a different folder name

use Dompdf\Dompdf;

$dompdf = new Dompdf(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);
$dompdf->loadHtml('<h1>Hello from Dompdf 3.1.0</h1>');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('test.pdf', ['Attachment' => false]);
