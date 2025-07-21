<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

// Create a new Dompdf instance
$dompdf = new Dompdf();

// Your HTML content
$html = '
    <h1>Hello from Dompdf</h1>
    <p>This PDF was generated using <strong>Dompdf</strong>.</p>
';

// Load HTML into Dompdf
$dompdf->loadHtml($html);

// Set paper size and orientation (optional)
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to the browser
$dompdf->stream("example.pdf", ["Attachment" => false]); // Set to true to force download
