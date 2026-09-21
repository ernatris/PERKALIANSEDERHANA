<?php
// API game Kali & Bagi — kompatibel PHP 7.4+ dan MySQL/MariaDB
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const POIN = ['mudah' => 10, 'sedang' => 15, 'sulit' => 20];
const MODE_OK = ['kali', 'bagi', 'campur'];

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail($msg, $code = 400) {
    out(['ok' => false, 'error' => $msg], $code);
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

function bersih($s, $max) {
    $s = is_string($s) ? $s : '';
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));
    return mb_substr($s, 0, $max);
}

function buatToken() {
    $t = time();
    $nonce = bin2hex(random_bytes(8));
    $sig = hash_hmac('sha256', $t . '.' . $nonce, APP_SECRET);
    return $t . '.' . $nonce . '.' . $sig;
}

// Mengembalikan [waktuMulai, nonce] jika token valid, selain itu null
function cekToken($token) {
    if (!is_string($token)) return null;
    $p = explode('.', $token);
    if (count($p) !== 3) return null;
    list($t, $nonce, $sig) = $p;
    if (!ctype_digit($t) || !preg_match('/^[a-f0-9]{16}$/', $nonce)) return null;
    $cek = hash_hmac('sha256', $t . '.' . $nonce, APP_SECRET);
    if (!hash_equals($cek, $sig)) return null;
    return [(int)$t, $nonce];
}

// Peringkat pemain = jumlah pemain dengan skor terbaik lebih tinggi + 1
function hitungPeringkat($nama, $sekolah, $permainan = null) {
    $w = $permainan ? 'WHERE permainan = :m' : '';
    $pm = $permainan ? [':m' => $permainan] : [];

    $q = db()->prepare("SELECT MAX(skor) FROM skor $w " . ($w ? 'AND' : 'WHERE') . ' nama = :n AND sekolah = :s');
    $q->execute($pm + [':n' => $nama, ':s' => $sekolah]);
    $best = (int)$q->fetchColumn();

    $q = db()->prepare("SELECT COUNT(*) + 1 FROM (SELECT MAX(skor) AS m FROM skor $w GROUP BY nama, sekolah) t WHERE m > :b");
    $q->execute($pm + [':b' => $best]);
    $rank = (int)$q->fetchColumn();

    $q = db()->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM skor $w GROUP BY nama, sekolah) t");
    $q->execute($pm);
    $total = (int)$q->fetchColumn();

    return [$rank, $total, $best];
}

$aksi = $_GET['action'] ?? '';

try {
    if ($aksi === 'start') {
        out(['ok' => true, 'token' => buatToken()]);
    }

    if ($aksi === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode harus POST', 405);
        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) fail('Data tidak valid');

        $nama = bersih($in['nama'] ?? '', 40);
        $sekolah = bersih($in['sekolah'] ?? '', 60);
        $mode = $in['mode'] ?? '';
        $level = $in['level'] ?? '';
        $benar = (int)($in['benar'] ?? -1);
        $salah = (int)($in['salah'] ?? -1);
        $streak = (int)($in['streak_max'] ?? 0);

        if (mb_strlen($nama) < 2) fail('Nama minimal 2 huruf');
        if (mb_strlen($sekolah) < 3) fail('Nama sekolah minimal 3 huruf');
        if (!in_array($mode, MODE_OK, true)) fail('Permainan tidak valid');
        if (!isset(POIN[$level])) fail('Tingkat tidak valid');
        if ($benar < 0 || $benar > 300 || $salah < 0 || $salah > 300) fail('Angka tidak valid');
        if ($streak < 0 || $streak > $benar) fail('Angka tidak valid');

        $tk = cekToken($in['token'] ?? null);
        if (!$tk) fail('Token tidak valid. Mainkan lagi ya.', 403);
        list($mulai, $nonce) = $tk;

        $lama = time() - $mulai;
        $bonusMaks = intdiv($benar, 3) * 10;
        if ($lama < 55) fail('Permainan belum selesai', 403);
        if ($lama > 60 + $bonusMaks + 300) fail('Token kedaluwarsa. Mainkan lagi ya.', 403);
        if ($benar > ($lama + 5) / 0.5) fail('Data tidak wajar', 403);

        // Skor selalu dihitung ulang di server
        $skor = $benar * POIN[$level];
        $tokenId = substr($nonce, 0, 16);

        try {
            $q = db()->prepare(
                'INSERT INTO skor (nama, sekolah, permainan, tingkat, skor, benar, salah, streak_max, token_id)
                 VALUES (:n, :s, :m, :l, :k, :b, :x, :r, :t)'
            );
            $q->execute([
                ':n' => $nama, ':s' => $sekolah, ':m' => $mode, ':l' => $level,
                ':k' => $skor, ':b' => $benar, ':x' => $salah, ':r' => $streak, ':t' => $tokenId,
            ]);
        } catch (PDOException $e) {
            // Token yang sama dikirim dua kali (mis. sinyal putus): anggap sudah tersimpan
            if (!(isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062)) throw $e;
        }

        list($rAll, $totAll) = hitungPeringkat($nama, $sekolah);
        list($rMode, $totMode) = hitungPeringkat($nama, $sekolah, $mode);

        out([
            'ok' => true,
            'skor' => $skor,
            'rank_all' => $rAll, 'total_all' => $totAll,
            'rank_mode' => $rMode, 'total_mode' => $totMode,
        ]);
    }

    if ($aksi === 'rank') {
        $mode = $_GET['mode'] ?? 'all';
        $batas = max(1, min(50, (int)($_GET['limit'] ?? 20)));
        $w = '';
        $pm = [];
        if (in_array($mode, MODE_OK, true)) {
            $w = 'WHERE permainan = :m';
            $pm[':m'] = $mode;
        }
        $q = db()->prepare(
            "SELECT nama, sekolah, MAX(skor) AS skor, MAX(benar) AS benar
             FROM skor $w
             GROUP BY nama, sekolah
             ORDER BY skor DESC, benar DESC, MIN(dibuat) ASC
             LIMIT $batas"
        );
        $q->execute($pm);
        $baris = $q->fetchAll();
        foreach ($baris as &$b) {
            $b['skor'] = (int)$b['skor'];
            $b['benar'] = (int)$b['benar'];
        }
        out(['ok' => true, 'mode' => $mode, 'data' => $baris]);
    }

    fail('Aksi tidak dikenal', 404);
} catch (Throwable $e) {
    error_log('[game-kali-bagi] ' . $e->getMessage());
    fail('Server sedang bermasalah. Coba lagi nanti.', 500);
}
