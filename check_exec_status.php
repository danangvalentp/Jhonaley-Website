    <?php
    echo "<h1>Status Fungsi PHP exec() dan shell_exec():</h1>";

    echo "<h2>exec():</h2>";
    if (function_exists('exec')) {
        echo "<p style='color: green; font-weight: bold;'>exec() <span style='font-size: 1.2em;'>✅ Aktif</span></p>";
        echo "<p>Mencoba menjalankan perintah sederhana 'whoami':</p>";
        $output = [];
        $return_var = 0;
        exec('whoami', $output, $return_var);
        if ($return_var === 0) {
            echo "<pre>Output: " . htmlspecialchars(implode("\n", $output)) . "</pre>";
        } else {
            echo "<p style='color: orange;'>Gagal menjalankan perintah (return code: {$return_var}). Mungkin ada batasan lain.</p>";
            echo "<p>Error output (jika ada): " . htmlspecialchars(implode("\n", $output)) . "</p>";
        }
    } else {
        echo "<p style='color: red; font-weight: bold;'>exec() <span style='font-size: 1.2em;'>❌ Dinonaktifkan</span></p>";
        echo "<p>Jika dinonaktifkan, skrip PHP tidak dapat menjalankan program eksternal seperti yt-dlp.</p>";
    }

    echo "<h1>Status Fungsi shell_exec():</h1>";
    if (function_exists('shell_exec')) {
        echo "<p style='color: green; font-weight: bold;'>shell_exec() <span style='font-size: 1.2em;'>✅ Aktif</span></p>";
        echo "<p>Mencoba menjalankan perintah sederhana 'pwd':</p>";
        $output = shell_exec('pwd');
        if ($output !== null) {
            echo "<pre>Output: " . htmlspecialchars($output) . "</pre>";
        } else {
            echo "<p style='color: orange;'>Gagal menjalankan perintah.</p>";
        }
    } else {
        echo "<p style='color: red; font-weight: bold;'>shell_exec() <span style='font-size: 1.2em;'>❌ Dinonaktifkan</span></p>";
        echo "<p>Jika dinonaktifkan, skrip PHP tidak dapat menjalankan program eksternal seperti yt-dlp.</p>";
    }
    ?>
    ```
2.  Akses file ini di browser Anda (misalnya `http://namadomainanda.com/check_exec_status.php`).
3.  **Sertakan tangkapan layar atau teks dari output ini.** Ini akan memberi tahu kita apakah `exec()` benar-benar aktif atau tidak. Jika dinonaktifkan, itulah masalahnya dan tidak ada yang bisa kita lakukan dengan PHP selain mengganti hosting atau menggunakan layanan pihak ketiga.

#### **Langkah 2: Tambahkan Logging Detail ke Skrip PHP (`download.php`)**

Ini akan membuat `download.php` mengembalikan pesan error yang lebih spesifik ke frontend jika ada masalah dalam menjalankan `yt-dlp`.

1.  Buka file `download.php` Anda di cPanel (via File Manager).
2.  Ganti seluruh kontennya dengan kode berikut. **Pastikan untuk mengganti `namapenggunaanda` dengan username cPanel Anda yang sebenarnya!**

    
    ```php
    <?php
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Untuk pengembangan

    // Aktifkan pelaporan kesalahan PHP untuk debugging. Nonaktifkan di produksi!
    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    $response = [
        'status' => 'error',
        'message' => 'Permintaan tidak valid atau kesalahan tidak diketahui.',
        'links' => [],
        'debug' => [] // Tambahkan bagian debug untuk informasi lebih lanjut
    ];

    // Cek apakah fungsi exec() diaktifkan
    if (!function_exists('exec')) {
        $response['message'] = 'Fungsi exec() PHP dinonaktifkan di server Anda. Tidak dapat menjalankan yt-dlp.';
        $response['debug']['exec_status'] = 'disabled';
        echo json_encode($response);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
        $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);

        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            // --- SANGAT PENTING: Ganti 'namapenggunaanda' dengan username cPanel Anda! ---
            $yt_dlp_path = '/home/namapenggunaanda/.local/bin/yt-dlp';
            // Pastikan Anda telah mengonfirmasi jalur ini berfungsi di SSH!
            // Coba juga 'which yt-dlp' di SSH untuk memastikan jalurnya benar.

            $response['debug']['yt_dlp_path_used'] = $yt_dlp_path;

            // Periksa apakah executable yt-dlp benar-benar ada dan dapat dieksekusi oleh PHP
            if (!file_exists($yt_dlp_path)) {
                $response['message'] = "Executable yt-dlp tidak ditemukan di jalur: {$yt_dlp_path}.";
                $response['debug']['yt_dlp_exists'] = false;
                echo json_encode($response);
                exit();
            }
            if (!is_executable($yt_dlp_path)) {
                $response['message'] = "Executable yt-dlp di jalur: {$yt_dlp_path} tidak memiliki izin eksekusi.";
                $response['debug']['yt_dlp_executable_permission'] = false;
                echo json_encode($response);
                exit();
            }
            $response['debug']['yt_dlp_exists'] = true;
            $response['debug']['yt_dlp_executable_permission'] = true;


            // Perintah untuk mendapatkan informasi JSON dari video
            // Menambahkan 2>&1 untuk menangkap stderr (output error) juga
            $command = escapeshellarg($yt_dlp_path) . ' --ignore-errors --dump-json ' . escapeshellarg($url) . ' 2>&1';

            $output = [];
            $return_var = 0;

            // Jalankan perintah yt-dlp
            // Menggunakan passthru() jika exec() bermasalah atau untuk output langsung
            // Namun, untuk menangkap output sebagai array, exec() lebih baik.
            // Kita akan tetap menggunakan exec() untuk menangkap output dan error.
            exec($command, $output, $return_var);

            $raw_output = implode("\n", $output);
            $response['debug']['command_executed'] = $command;
            $response['debug']['raw_output'] = $raw_output;
            $response['debug']['return_var'] = $return_var;

            if ($return_var === 0) {
                $video_info = json_decode($raw_output, true);

                if (json_last_error() === JSON_ERROR_NONE && $video_info) {
                    $download_links = [];

                    if (isset($video_info['formats']) && is_array($video_info['formats'])) {
                        foreach ($video_info['formats'] as $format) {
                            if (isset($format['url']) && isset($format['ext'])) {
                                $ext = $format['ext'];
                                $filesize_mb = isset($format['filesize']) ? round($format['filesize'] / (1024 * 1024), 2) : 'N/A';
                                $format_note = isset($format['format_note']) ? " ({$format['format_note']})" : '';
                                $resolution = isset($format['height']) ? "{$format['height']}p" : (isset($format['vcodec']) && $format['vcodec'] !== 'none' ? 'Video' : 'Audio');

                                $label = '';
                                $url_to_download = $format['url'];

                                if ($ext === 'mp4' && $resolution !== 'Audio' && isset($format['height'])) {
                                    $label = "MP4 {$resolution}{$format_note} ({$filesize_mb} MB)";
                                } elseif ($ext === 'webm' && $resolution !== 'Audio' && isset($format['height'])) {
                                    $label = "WEBM {$resolution}{$format_note} ({$filesize_mb} MB)";
                                } elseif (($ext === 'mp3' || $ext === 'm4a' || $ext === 'aac') && $resolution === 'Audio') {
                                    $abr = isset($format['abr']) ? "{$format['abr']}kbps" : '';
                                    $label = strtoupper($ext) . " Audio {$abr}{$format_note} ({$filesize_mb} MB)";
                                } elseif ($ext === 'mp4' && $resolution === 'Audio') {
                                    $abr = isset($format['abr']) ? "{$format['abr']}kbps" : '';
                                    $label = "MP4 Audio {$abr}{$format_note} ({$filesize_mb} MB)";
                                }

                                if (!empty($label)) {
                                    $download_links[] = [
                                        'label' => $label,
                                        'url' => $url_to_download
                                    ];
                                }
                            }
                        }
                    }

                    if (empty($download_links)) {
                        $response['message'] = 'Tidak ada format unduhan yang kompatibel ditemukan untuk URL ini.';
                    } else {
                        $response['status'] = 'success';
                        $response['message'] = 'Link unduh berhasil diambil.';
                        $response['links'] = $download_links;
                    }

                } else {
                    $response['message'] = 'Gagal mem-parsing informasi video dari yt-dlp. Mungkin URL tidak valid, yt-dlp bermasalah, atau outputnya bukan JSON. ' . json_last_error_msg();
                    $response['debug']['json_error'] = json_last_error_msg();
                }
            } else {
                $response['message'] = 'Perintah yt-dlp gagal dijalankan. Return code: ' . $return_var . '. Output: ' . $raw_output;
                // Sangat penting: error_log() akan menulis ke log server PHP
                error_log("yt-dlp command failed. Command: {$command}, Return: {$return_var}, Output: {$raw_output}");
            }
        } else {
            $response['message'] = 'URL yang diberikan tidak valid.';
        }
    }

    echo json_encode($response);
    ?>
    