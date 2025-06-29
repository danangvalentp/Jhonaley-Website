<?php
// Set header untuk mengizinkan permintaan lintas domain (CORS)
header('Access-Control-Allow-Origin: *');

// Direktori tempat file yang diunduh disimpan
// GUNAKAN JALUR ABSOLUT UNTUK KONSISTENSI
$downloads_directory = __DIR__ . '/downloads/';

// Pastikan parameter 'file' ada di URL
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400); // Bad Request
    echo "Nama file tidak diberikan.";
    exit;
}

$filename = basename($_GET['file']); // Gunakan basename untuk mencegah serangan directory traversal
$filepath = $downloads_directory . $filename;

// Pastikan file ada dan dapat dibaca
if (!file_exists($filepath) || !is_readable($filepath)) {
    http_response_code(404); // Not Found
    echo "File tidak ditemukan atau tidak dapat diakses.";
    exit;
}

// Tentukan tipe konten (MIME type)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Jika tipe MIME tidak dapat ditentukan, set default
if ($mime_type === false) {
    $mime_type = 'application/octet-stream'; // Default untuk unduhan file biner
}

// Set header untuk memaksa unduhan
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $filename . '"'); // Ini yang memaksa unduhan
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Hapus output buffering untuk memastikan file langsung di-stream

// Stream file ke browser
readfile($filepath);

exit;
?>
