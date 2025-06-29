<?php
// Izinkan akses dari domain mana pun jika diperlukan (untuk development, tidak direkomendasikan untuk produksi)
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if (isset($_GET['url'])) {
    $imageUrl = filter_var(urldecode($_GET['url']), FILTER_SANITIZE_URL);
    $filename = basename($imageUrl); // Ambil nama file dari URL
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);

    // Default filename jika tidak ada atau tidak valid
    if (empty($fileExtension) || !in_array($fileExtension, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
        $filename = 'payment.png';
    } else {
        $filename = 'payment.png' . $fileExtension; // Beri nama yang lebih konsisten
    }

    if (!empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        // Ambil konten gambar dari URL
        $imageData = @file_get_contents($imageUrl);

        if ($imageData === FALSE) {
            http_response_code(404);
            echo "Error: Could not retrieve image from URL.";
            exit();
        }

        // Set header untuk memaksa download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($imageData));

        // Output gambar
        echo $imageData;
        exit();
    } else {
        http_response_code(400);
        echo "Error: Invalid image URL provided.";
        exit();
    }
} else {
    http_response_code(400);
    echo "Error: No image URL provided.";
    exit();
}
?>