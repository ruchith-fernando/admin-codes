<?php
// convert.php

// Autoload for PDFParser (manual)
spl_autoload_register(function ($class) {
    $prefix = 'Smalot\\PdfParser\\';
    $base_dir = __DIR__ . '/vendor/pdfparser/src/Smalot/PdfParser/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use Smalot\PdfParser\Parser;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];
    $outputFormat = $_POST['output_format'];
    $uploadPath = 'uploads/' . basename($file['name']);

    if (!file_exists('uploads')) mkdir('uploads');
    if (!file_exists('output')) mkdir('output');

    move_uploaded_file($file['tmp_name'], $uploadPath);

    // Parse PDF
    $parser = new Parser();
    $pdf = $parser->parseFile($uploadPath);
    $text = $pdf->getText();
    $lines = explode("\n", $text);

    // Save to Excel
    if ($outputFormat === 'excel') {
        require_once __DIR__ . '/vendor/PhpSpreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
        require_once __DIR__ . '/vendor/PhpSpreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';
        require_once __DIR__ . '/vendor/PhpSpreadsheet/src/PhpSpreadsheet/IOFactory.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($lines as $row => $line) {
            $sheet->setCellValue('A' . ($row + 1), $line);
        }

        $filename = 'output/converted_' . time() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filename);

    } elseif ($outputFormat === 'word') {
        require_once __DIR__ . '/vendor/PhpWord/src/PhpWord/PhpWord.php';
        require_once __DIR__ . '/vendor/PhpWord/src/PhpWord/Writer/Word2007.php';

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();

        foreach ($lines as $line) {
            $section->addText($line);
        }

        $filename = 'output/converted_' . time() . '.docx';
        $writer = new \PhpOffice\PhpWord\Writer\Word2007($phpWord);
        $writer->save($filename);
    }

    echo "<div class='p-4'>
        <h4>Conversion Complete</h4>
        <a href='$filename' class='btn btn-success'>Download Output</a><br><br>
        <a href='index.php' class='btn btn-secondary'>Back</a>
    </div>";
} else {
    echo "No PDF uploaded.";
}
?>
