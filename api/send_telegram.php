<?php
header('Content-Type: application/json');

// Token bot Telegram Anda
define('TELEGRAM_BOT_TOKEN', '7919852370:AAHt-sCv7-CF0hXIuMw1ts-O2VI5-k3GbIw');
// ID chat Telegram tempat Anda ingin menerima notifikasi admin
define('TELEGRAM_CHAT_ID', '7296711578');

// Fonnte API Key Anda
define('FONNTE_API_KEY', 'NLiW8A3ZEHq5HUxMKKyW');

$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari POST request
    $buyerWhatsapp = $_POST['itemName'] ?? ''; // Menggunakan itemName sebagai WA Pembeli
    $country = $_POST['country'] ?? 'Tidak Disebutkan';
    $platform = $_POST['platform'] ?? 'Tidak Disebutkan';
    $quantity = $_POST['quantity'] ?? 'Tidak Disebutkan';
    $totalPrice = $_POST['totalPrice'] ?? 'Rp 0';
    $paymentMethod = $_POST['paymentMethod'] ?? 'Tidak Disebutkan';

    $proofPhoto = $_FILES['proofPhoto'] ?? null;

    // --- Format nomor WhatsApp pembeli untuk Fonnte dan WA Link ---
    $formattedBuyerWhatsapp = trim($buyerWhatsapp); // Hapus spasi di awal/akhir
    $formattedBuyerWhatsapp = preg_replace('/[^0-9]/', '', $formattedBuyerWhatsapp); // Hapus semua karakter non-digit

    // Jika dimulai dengan '0', ganti dengan '62' (PHP < 8.0 compatible)
    if (substr($formattedBuyerWhatsapp, 0, 1) === '0') {
        $formattedBuyerWhatsapp = '62' . substr($formattedBuyerWhatsapp, 1);
    }
    // Jika belum dimulai dengan '62' (dan tidak kosong), tambahkan '62' (PHP < 8.0 compatible)
    if (!empty($formattedBuyerWhatsapp) && substr($formattedBuyerWhatsapp, 0, 2) !== '62') {
        $formattedBuyerWhatsapp = '62' . $formattedBuyerWhatsapp;
    }


    // --- Pesan Invoice untuk Pembeli ---
    $invoiceMessageForBuyer = "Halo! Terima kasih atas pembelian Nokos Anda.\n\n" .
                              "Berikut detail pesanan Anda:\n" .
                              "🌎 Negara: {$country}\n" .
                              "📱 Platform: {$platform}\n" .
                              "🔢 Jumlah: {$quantity}\n" .
                              "💰 Total Harga: {$totalPrice}\n" .
                              "💳 Metode Pembayaran: {$paymentMethod}\n\n" .
                              "Kami akan segera memproses pesanan Anda. Jika ada pertanyaan, balas pesan ini.";

    // --- Kirim Pesan ke Pembeli via Fonnte ---
    $fonnteSendStatus = false;
    $fonnteMessage = '';

    if (!empty($formattedBuyerWhatsapp)) {
        $chFonnte = curl_init();
        curl_setopt($chFonnte, CURLOPT_URL, 'https://api.fonnte.com/send');
        curl_setopt($chFonnte, CURLOPT_POST, 1);
        curl_setopt($chFonnte, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chFonnte, CURLOPT_POSTFIELDS, [
            'target' => $formattedBuyerWhatsapp,
            'message' => $invoiceMessageForBuyer,
        ]);
        curl_setopt($chFonnte, CURLOPT_HTTPHEADER, ['Authorization: ' . FONNTE_API_KEY]);

        $fonnteResponse = curl_exec($chFonnte);
        $fonnteHttpStatus = curl_getinfo($chFonnte, CURLINFO_HTTP_CODE);
        $fonnteError = curl_error($chFonnte);
        curl_close($chFonnte);

        if ($fonnteResponse === false) {
            $fonnteMessage = "Gagal mengirim invoice via Fonnte. cURL Error: " . $fonnteError;
        } else {
            $fonnteResponseDecoded = json_decode($fonnteResponse, true);
            // Asumsi Fonnte mengembalikan 'true' untuk sukses di bidang 'status' atau ada kunci 'id' yang berarti pesan terkirim
            if (isset($fonnteResponseDecoded['status']) && $fonnteResponseDecoded['status'] === true || isset($fonnteResponseDecoded['id'])) {
                $fonnteSendStatus = true;
                $fonnteMessage = "Invoice berhasil dikirim ke pembeli via WhatsApp.";
            } else {
                $fonnteMessage = "Gagal mengirim invoice via Fonnte. Response: " . ($fonnteResponseDecoded['reason'] ?? ($fonnteResponseDecoded['detail'] ?? 'Unknown error'));
            }
        }
    } else {
        $fonnteMessage = "Nomor WhatsApp pembeli tidak valid atau kosong, invoice tidak dikirim via Fonnte.";
    }


    // --- Kirim Notifikasi ke Admin via Telegram ---
    $telegramApiUrl = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/';
    $telegramMessageText = "⭐ *Pemesanan Nokos Baru!* ⭐\n\n" .
                           "📞 *WA Pembeli:* `{$buyerWhatsapp}`\n" . // Nomor asli dari input
                           "📞 *WA Fonnte Format:* `{$formattedBuyerWhatsapp}`\n" . // Nomor yang diformat
                           "🌎 *Negara:* `{$country}`\n" .
                           "📱 *Platform:* `{$platform}`\n" .
                           "🔢 *Jumlah:* `{$quantity}`\n" .
                           "💰 *Total Harga:* `{$totalPrice}`\n" .
                           "💳 *Metode Pembayaran:* `{$paymentMethod}`\n\n" .
                           "Status pengiriman invoice via Fonnte: " . ($fonnteSendStatus ? "BERHASIL" : "GAGAL") . "\n" .
                           "Detail Fonnte: {$fonnteMessage}\n\n" .
                           "Harap segera proses pesanan ini.";

    // Kirim foto bukti pembayaran ke Telegram jika ada
    if ($proofPhoto && $proofPhoto['error'] === UPLOAD_ERR_OK) {
        $photoPath = $proofPhoto['tmp_name'];
        $photoMimeType = mime_content_type($photoPath);

        if (strpos($photoMimeType, 'image/') === 0) {
            $chTelegram = curl_init();
            curl_setopt($chTelegram, CURLOPT_URL, $telegramApiUrl . 'sendPhoto');
            curl_setopt($chTelegram, CURLOPT_POST, 1);
            curl_setopt($chTelegram, CURLOPT_POSTFIELDS, [
                'chat_id' => TELEGRAM_CHAT_ID,
                'caption' => $telegramMessageText,
                'parse_mode' => 'Markdown',
                'photo' => new CURLFile($photoPath, $photoMimeType, $proofPhoto['name'])
            ]);
            curl_setopt($chTelegram, CURLOPT_RETURNTRANSFER, true);
            $telegramResponse = curl_exec($chTelegram);
            $telegramError = curl_error($chTelegram);
            curl_close($chTelegram);

            if ($telegramResponse === false) {
                $response['message'] = "Gagal mengirim notifikasi foto ke Telegram. cURL Error: " . $telegramError . " (Fonnte status: " . $fonnteMessage . ")";
            } else {
                $decodedTelegramResponse = json_decode($telegramResponse, true);
                if ($decodedTelegramResponse['ok']) {
                    $response = ['status' => 'success', 'message' => 'Bukti pembayaran berhasil dikirim. Notifikasi admin dan invoice pembeli telah dikirim.'];
                } else {
                    $response['message'] = "Gagal mengirim notifikasi foto ke Telegram. Response: " . ($decodedTelegramResponse['description'] ?? 'Unknown error') . " (Fonnte status: " . $fonnteMessage . ")";
                }
            }
        } else {
            $response['message'] = "File yang diunggah bukan gambar yang valid. (Fonnte status: " . $fonnteMessage . ")";
        }
    } else {
        // Jika tidak ada foto, kirim pesan teks saja ke Telegram
        $chTelegram = curl_init();
        curl_setopt($chTelegram, CURLOPT_URL, $telegramApiUrl . 'sendMessage');
        curl_setopt($chTelegram, CURLOPT_POST, 1);
        curl_setopt($chTelegram, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $telegramMessageText,
            'parse_mode' => 'Markdown'
        ]));
        curl_setopt($chTelegram, CURLOPT_RETURNTRANSFER, true);
        $telegramResponse = curl_exec($chTelegram);
        $telegramError = curl_error($chTelegram);
        curl_close($chTelegram);

        if ($telegramResponse === false) {
            $response['message'] = "Gagal mengirim notifikasi teks ke Telegram. cURL Error: " . $telegramError . " (Fonnte status: " . $fonnteMessage . ")";
        } else {
            $decodedTelegramResponse = json_decode($telegramResponse, true);
            if ($decodedTelegramResponse['ok']) {
                $response = ['status' => 'success', 'message' => 'Bukti pembayaran berhasil dikirim. Notifikasi admin dan invoice pembeli telah dikirim.'];
            } else {
                $response['message'] = "Gagal mengirim notifikasi teks ke Telegram. Response: " . ($decodedTelegramResponse['description'] ?? 'Unknown error') . " (Fonnte status: " . $fonnteMessage . ")";
            }
        }
    }
}

echo json_encode($response);
?>
