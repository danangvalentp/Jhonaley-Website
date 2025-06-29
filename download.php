<?php
// Aktifkan pelaporan error untuk debugging (Hapus di lingkungan produksi!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header untuk mengizinkan permintaan lintas domain (CORS)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Mengambil input JSON dari permintaan frontend
$input = json_decode(file_get_contents('php://input'), true);

$url = $input['url'] ?? '';
$format = $input['format'] ?? 'video'; // Default ke 'video' jika tidak ditentukan

// Validasi input
if (empty($url)) {
    echo json_encode(['status' => 'error', 'message' => 'URL tidak boleh kosong.']);
    exit;
}

// Direktori tempat file yang diunduh akan disimpan
$outputDir = __DIR__ . '/tiktok/downloads/'; 

// Pastikan direktori downloads ada dan dapat ditulisi
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori unduhan. Periksa izin.']);
        exit;
    }
}

$command = '';
$output_array = [];
$return_var = 0;
$downloadedFilenameBase = ''; 

// Tentukan executable yt-dlp. Ganti ini dengan jalur lengkap Anda jika diperlukan.
$yt_dlp_executable = '/home/jhon3647/yt-dlp-wrapper.sh'; 

// Format output untuk yt-dlp agar mendapatkan nama file yang konsisten (ID video/audio + ekstensi asli)
$yt_dlp_output_template = '%(id)s.%(ext)s'; 

if ($format === 'video') {
    // Menambahkan opsi untuk memastikan output .mp4
    $command = "{$yt_dlp_executable} --output \"{$outputDir}{$yt_dlp_output_template}\" -f \"bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best\" --recode-video mp4 " . escapeshellarg($url);
} elseif ($format === 'audio') {
    $command = "{$yt_dlp_executable} --output \"{$outputDir}%(id)s.%(ext)s\" -x --audio-format mp3 " . escapeshellarg($url);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Format tidak valid. Pilih "video" atau "audio".']);
    exit;
}

// Jalankan perintah yt-dlp dan tangkap output serta kode keluar
exec($command . ' 2>&1', $output_array, $return_var);
$full_output = implode("\n", $output_array);

if ($return_var === 0) {
    // Ambil nama file yang seharusnya dihasilkan oleh yt-dlp
    $get_filename_command = "{$yt_dlp_executable} --get-filename --output \"{$yt_dlp_output_template}\" " . escapeshellarg($url);
    if ($format === 'audio') {
        $get_filename_command = "{$yt_dlp_executable} --get-filename --output \"%(id)s.%(ext)s\" -x --audio-format mp3 " . escapeshellarg($url);
    }
    $downloadedFilenameBase = trim(shell_exec($get_filename_command . ' 2>&1'));

    if (!empty($downloadedFilenameBase)) {
        // Buat URL yang menunjuk ke skrip PHP baru untuk melayani unduhan
        $downloadUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/tiktok/serve_download.php?file=' . urlencode($downloadedFilenameBase);
        
        // Pastikan file tersebut benar-benar ada di direktori downloads
        $full_file_path = $outputDir . $downloadedFilenameBase;
        if (!file_exists($full_file_path)) {
            echo json_encode(['status' => 'error', 'message' => 'File berhasil diunduh oleh yt-dlp tetapi tidak ditemukan di direktori target.', 'yt_dlp_raw_output' => $full_output, 'expected_filename' => $downloadedFilenameBase]);
            exit;
        }

        // Mengembalikan status sukses dengan downloadUrl dan downloadedFilenameBase
        // Frontend akan memicu unduhan otomatis DAN kemudian mengirim ke Telegram
        echo json_encode(['status' => 'success', 'message' => 'File berhasil diunduh.', 'downloadUrl' => $downloadUrl, 'downloaded_filename_base' => $downloadedFilenameBase]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'File berhasil diunduh tetapi gagal mendapatkan nama file yang tepat.', 'yt_dlp_raw_output' => $full_output]);
    }

} else {
    // Gagal mengunduh
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengunduh file.', 'yt_dlp_raw_output' => $full_output, 'return_code' => $return_var]);
}
?>
