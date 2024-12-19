<?php 
// Fungsi untuk menjalankan script Python dan mengambil hasilnya
function run_python_script($query, $start_index = 0) {
    // Encode query untuk menghindari masalah dengan karakter khusus
    $query = escapeshellarg($query); // Lindungi input untuk menghindari injection

    // Cek apakah Python dapat diakses dan modul sudah benar
    $python_path = shell_exec("where python3 2>&1");
    if (!$python_path) {
        die("Error: Python tidak ditemukan di server.");
    }

    // Gunakan path Python yang ditemukan
    $command = escapeshellcmd("python3 search_script.py $query $start_index");

    // Eksekusi perintah dan tangkap output serta error
    $output = shell_exec($command . " 2>&1"); // Tangkap error jika ada

    // Log output untuk debugging jika kosong
    if (!$output) {
        error_log("Python script execution failed or returned empty output.");
        die("Error: Script Python gagal dijalankan atau output kosong.");
    }

    // Cek apakah output JSON valid
    $json_data = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON output: $output");
        die("Error: Output dari Python tidak valid JSON. Output: " . htmlspecialchars($output));
    }

    return $json_data; // Mengonversi JSON ke array PHP
}

// Definisikan variabel query untuk memastikan tidak undefined
$query = '';  // Nilai default untuk query

// Menangani pencarian dari form
if ($_SERVER["REQUEST_METHOD"] == "POST" || isset($_GET['query'])) {
    // Ambil query dari POST atau GET
    $query = $_POST['query'] ?? $_GET['query'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $results_per_page = 10;
    $start_index = ($page - 1) * $results_per_page;

    // Mengambil hasil pencarian dari Python
    try {
        $data = run_python_script($query, $start_index);
        $results = $data['results'] ?? [];
        $prioritized_results = $data['prioritized_results'] ?? [];
        $total_results = $data['total_results'] ?? 0;
        $message = $data['message'] ?? null; // Tangkap pesan jika ada
        $recommendations = $data['recommendations'] ?? null; // Tangkap rekomendasi jika ada
    } catch (Exception $e) {
        die("Terjadi kesalahan: " . $e->getMessage());
    }
} else {
    $results = [];
    $prioritized_results = [];
    $total_results = 0;
    $page = 1;
    $message = null;
    $recommendations = null;
}

// Menentukan jumlah halaman
$total_pages = ceil($total_results / 10);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Deskriptif Jurnal Sistem Informasi</title>
    <!-- Menambahkan Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .typed-text {
            font-size: 1.25rem;
            font-weight: 400;
            color: #6c757d;
        }
        .highlight {
            background-color: #f8f9fa;
            border: 2px solid #007bff;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<!-- Navigasi Atas -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Sistem Pencarian Jurnal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#">Tentang Kami</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Bantuan</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h1 class="mb-4">Pencarian Deskriptif Jurnal Sistem Informasi</h1>

    <!-- Animasi teks typed -->
    <p class="typed-text" id="typed-text"></p>

    <!-- Formulir untuk memasukkan query pencarian -->
    <form method="POST" action="" class="mb-4">
        <div class="mb-3">
            <textarea class="form-control" name="query" rows="3" placeholder="contoh : carikan saya jurnal tentang pengembangan SI/TI di perusahaan pertamina tahun 2015" required><?= htmlspecialchars($query) ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Cari</button>
    </form>

    <?php if ($message): ?>
        <div class="alert alert-warning">
            <strong><?= htmlspecialchars($message) ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($recommendations): ?>
        <div class="alert alert-info">
            <strong>Query Anda tidak sesuai dengan lingkup Sistem Informasi.</strong><br>
            Berikut beberapa rekomendasi lingkup yang dapat dicari:
            <ul>
                <?php foreach ($recommendations as $rec): ?>
                    <li><?= htmlspecialchars($rec) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($prioritized_results)): ?>
        <h2>Hasil Pencarian Prioritas:</h2>
        <?php foreach ($prioritized_results as $result): ?>
            <div class="highlight">
                <strong>Title:</strong> <?= htmlspecialchars($result['title']) ?><br>
                <strong>Author:</strong> <?= is_array($result['author']) ? htmlspecialchars(implode(', ', $result['author'])) : htmlspecialchars($result['author']) ?><br>
                <strong>Year:</strong> <?= htmlspecialchars($result['year']) ?><br>
                <strong>Abstract:</strong> <?= htmlspecialchars($result['abstract']) ?><br>
                <a href="<?= htmlspecialchars($result['url']) ?>" class="btn btn-link" target="_blank">Read More</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <h2>Hasil Pencarian Lainnya:</h2>
        <?php foreach ($results as $result): ?>
            <ul class="list-group">
                <li class="list-group-item">
                    <strong>Title:</strong> <?= htmlspecialchars($result['title']) ?><br>
                    <strong>Author:</strong> <?= is_array($result['author']) ? htmlspecialchars(implode(', ', $result['author'])) : htmlspecialchars($result['author']) ?><br>
                    <strong>Year:</strong> <?= htmlspecialchars($result['year']) ?><br>
                    <strong>Abstract:</strong> <?= htmlspecialchars($result['abstract']) ?><br>
                    <a href="<?= htmlspecialchars($result['url']) ?>" class="btn btn-link" target="_blank">Read More</a>
                </li>
            </ul>
        <?php endforeach; ?>

        <!-- Menampilkan pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <?php
                // Menampilkan tombol "Sebelumnya" dan "Berikutnya"
                if ($page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . '&query=' . urlencode($query) . '">Sebelumnya</a></li>';
                }

                // Menampilkan tombol "Berikutnya"
                if ($page < $total_pages) {
                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . '&query=' . urlencode($query) . '">Berikutnya</a></li>';
                }
                ?>
            </ul>
        </nav>

    <?php endif; ?>
</div>

<!-- Menambahkan Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
    const texts = [
        "Selamat datang, silahkan deskripsikan jurnal di lingkup sistem informasi apa yang ingin anda cari.",
        "Senang bertemu anda, jurnal lingkup sistem informasi apa yang anda butuhkan? silahkan deskripsikan.",
        "Apa yang anda butuhkan? biarkan saya mencarikan jurnal itu untuk anda. silahkan deskripsikan dibawah kotak ini."
    ];

    let index = 0;
    let charIndex = 0;
    const typedText = document.getElementById("typed-text");

    function type() {
        if (charIndex < texts[index].length) {
            typedText.textContent += texts[index][charIndex];
            charIndex++;
            setTimeout(type, 100);
        } else {
            setTimeout(erase, 2000);
        }
    }

    function erase() {
        if (charIndex > 0) {
            typedText.textContent = texts[index].substring(0, charIndex - 1);
            charIndex--;
            setTimeout(erase, 50);
        } else {
            index = (index + 1) % texts.length;
            setTimeout(type, 500);
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        type();
    });
</script>
</body>
</html>
