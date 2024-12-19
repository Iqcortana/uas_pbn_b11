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
        $recommendations = $data['recommendations'] ?? null; // Tangkap rekomendasi jika ada
    } catch (Exception $e) {
        die("Terjadi kesalahan: " . $e->getMessage());
    }
} else {
    $results = [];
    $prioritized_results = [];
    $total_results = 0;
    $page = 1;
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
    <title>Sistem Pencarian Jurnal Sistem Informasi</title>
    <!-- Menambahkan Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
        }
        .header {
            background: linear-gradient(90deg, #007bff, #00c6ff);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5rem;
            margin: 0;
        }
        .header p {
            font-size: 1.25rem;
            margin: 0;
        }
        .search-container {
            margin-top: 30px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .typed-text {
            font-size: 1.2rem;
            font-weight: 400;
            color: #6c757d;
            margin-bottom: 20px;
        }
        .search-container textarea {
            resize: none;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistem Pencarian Jurnal</h1>
        <p>Mudah, cepat, dan sesuai kebutuhan Anda</p>
    </div>

    <div class="container mt-4">
        <div class="search-container">
            <!-- Animasi teks typed -->
            <p class="typed-text" id="typed-text"></p>

            <!-- Formulir untuk memasukkan query pencarian -->
            <form method="POST" action="">
                <div class="mb-3">
                    <textarea class="form-control" name="query" rows="4" placeholder="Contoh: carikan saya jurnal tentang sistem informasi di perusahaan pertamina tahun 2020" required></textarea>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Cari</button>
            </form>
        </div>
    </div>

    <div class="footer">
        &copy; 2024 Sistem Pencarian Jurnal. Dikembangkan untuk memenuhi kebutuhan riset Anda.
    </div>

    <!-- Menambahkan Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script>
        const texts = [
            "Selamat datang! Cari jurnal sesuai kebutuhan Anda.",
            "Deskripsikan jurnal yang ingin Anda cari.",
            "Pencarian jurnal kini lebih mudah dan cepat."
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
