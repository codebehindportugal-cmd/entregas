<?php

return [
    'max_upload_size'      => env('INVOICE_MAX_UPLOAD_SIZE', 10240),
    'storage_disk'         => env('INVOICE_STORAGE_DISK', 'local'),
    'storage_path'         => env('INVOICE_STORAGE_PATH', 'private/invoices'),
    'tesseract_language'   => env('INVOICE_TESSERACT_LANGUAGE', 'por+eng'),
    'min_pdf_text_length'  => env('INVOICE_MIN_PDF_TEXT_LENGTH', 100),
    'ocrmypdf_binary'      => env('OCRMYPDF_BINARY', 'ocrmypdf'),
    'pdftotext_binary'     => env('PDFTOTEXT_BINARY', 'pdftotext'),
    'tesseract_binary'     => env('TESSERACT_BINARY', 'tesseract'),
    'imagemagick_binary'   => env('IMAGEMAGICK_BINARY', 'convert'),
];
