<?php
// Aktifkan pelaporan error untuk debugging (Hapus di lingkungan produksi!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header untuk mengizinkan permintaan lintas domain (CORS)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Telegram Bot API Credentials
// PENTING: Ganti dengan token bot dan chat ID Anda yang sebenarnya
define('TELEGRAM_BOT_TOKEN', '8182712453:AAF-w9Ben5ye7hX9UHBl-P6XMub4F6zi51k'); // Your bot token
define('TELEGRAM_CHAT_ID', '7296711578'); // Your target chat ID

// Ambil input JSON dari permintaan frontend
$input = json_decode(file_get_contents('php://input'), true);

$downloadedFilenameBase = $input['filename'] ?? null;

if (empty($downloadedFilenameBase)) {
    error_log("send_telegram_video.php: Error - Filename not provided.");
    echo json_encode(['status' => 'error', 'message' => 'Nama file tidak diberikan.']);
    exit;
}

// Tambahkan penundaan di sini agar pengguna sempat mengunduh
// Misalnya, 5 detik. Sesuaikan sesuai kebutuhan.
sleep(90); 

// Buat URL yang menunjuk ke file yang diunduh agar dapat diakses oleh Telegram
// Ini HARUS berupa URL yang dapat diakses publik dari luar server Anda
$videoUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/tiktok/downloads/' . urlencode($downloadedFilenameBase);
$full_file_path = __DIR__ . '/tiktok/downloads/' . $downloadedFilenameBase; // Jalur fisik ke file

// Pastikan file benar-benar ada sebelum mencoba mengirim
if (!file_exists($full_file_path)) {
    error_log("send_telegram_video.php: Error - File not found at path: " . $full_file_path);
    echo json_encode(['status' => 'error', 'message' => 'File tidak ditemukan di server.']);
    exit;
}

// --- Send video to Telegram Bot ---
$telegramApiUrl = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendVideo';

$postFields = [
    'chat_id' => TELEGRAM_CHAT_ID,
    'video' => $videoUrl, // URL video yang dapat diakses publik
    'caption' => "Video TikTok Anda sudah siap! Dari: " . $videoUrl // Pesan keterangan
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tambahkan timeout untuk pengiriman video (mungkin butuh lebih lama)
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$telegramResponse = curl_exec($ch);
$telegramHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$telegramError = curl_error($ch);
curl_close($ch);

// Logging untuk debugging Telegram
error_log("send_telegram_video.php: Telegram API Response for " . $downloadedFilenameBase . ": " . ($telegramResponse ?: 'No response') . ", HTTP Code: " . $telegramHttpCode . ", cURL Error: " . ($telegramError ?: 'None'));

$telegramResult = json_decode($telegramResponse, true);

if ($telegramHttpCode === 200 && ($telegramResult['ok'] ?? false) === true) {
    // --- NEW: Delete file after successful Telegram send ---
    if (file_exists($full_file_path)) {
        if (unlink($full_file_path)) {
            error_log("send_telegram_video.php: Successfully deleted file: " . $full_file_path);
            echo json_encode(['status' => 'success', 'message' => 'Video berhasil dikirim ke Telegram dan dihapus dari server.']);
        } else {
            error_log("send_telegram_video.php: Failed to delete file: " . $full_file_path . ". Check permissions.");
            echo json_encode(['status' => 'success', 'message' => 'Video berhasil dikirim ke Telegram, tetapi gagal dihapus dari server.', 'delete_error' => 'Gagal menghapus file. Periksa izin.']);
        }
    } else {
        error_log("send_telegram_video.php: File not found for deletion (already gone?): " . $full_file_path);
        echo json_encode(['status' => 'success', 'message' => 'Video berhasil dikirim ke Telegram. File sudah tidak ada di server.']);
    }
} else {
    // Pengiriman Telegram gagal
    $errorMessage = 'Gagal mengirim video ke Telegram.';
    if (isset($telegramResult['description'])) {
        $errorMessage .= ' Pesan Telegram: ' . $telegramResult['description'];
    } else if ($telegramError) {
        $errorMessage .= ' cURL Error: ' . $telegramError;
    }
    error_log("send_telegram_video.php: Telegram send failed for " . $downloadedFilenameBase . ": " . $errorMessage);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'telegram_response_raw' => $telegramResponse,
        'telegram_result_decoded' => $telegramResult,
        'telegram_http_code' => $telegramHttpCode,
        'telegram_cURL_error' => $telegramError
    ]);
}
?>
