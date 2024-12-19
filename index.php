<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pencarian Jurnal</title>
    <!-- Menambahkan Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Navigasi Atas -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Sistem Pencarian Jurnal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>

<div class="container mt-5">
    <h1 class="mb-4">Pencarian Deskriptif Jurnal Sistem Informasi</h1>

    <!-- Animasi teks typed -->
    <p class="typed-text" id="typed-text"></p>

    <!-- Formulir untuk memasukkan query pencarian -->
    <div class="row">
        <form id="searchForm" method="POST" action="" class="mb-4">
            <div class="mb-3">
                <textarea class="form-control" name="query" id="query" rows="3" placeholder="contoh: carikan saya jurnal tentang pengembangan SI/TI di perusahaan Pertamina tahun 2015" required><?php echo isset($_POST['query']) ? htmlspecialchars($_POST['query']) : ''; ?></textarea>
            </div>
            <div class="d-flex">
                <button class="btn btn-primary w-100 me-2" type="submit">Cari</button>
                <button class="btn btn-secondary w-100" type="button" onclick="resetForm()">Reset</button>
            </div>
        </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['query'])) {
        $query = htmlspecialchars($_POST['query']);
        $url = 'http://127.0.0.1:5000/search'; // Endpoint Flask
        $data = json_encode(['query' => $query]);

        // Menggunakan cURL untuk terhubung ke Flask
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $results = json_decode($response, true);

            echo '<div class="row mt-4">';

            //  Kolom hasil database 
            echo '<div class="col-md-6">';
            echo '<h2>Hasil dari Jurnal ITK</h2>';
            if (!empty($results['local_results'])) {
                echo '<ul class="list-group">';
                foreach ($results['local_results'] as $result) {
                    echo '<li class="list-group-item">';
                    echo '<strong>Judul:</strong> ' . htmlspecialchars($result['judul']) . '<br>';
                    echo '<strong>Penulis:</strong> ' . htmlspecialchars($result['penulis']) . '<br>';
                    echo '<strong>Tahun:</strong> ' . htmlspecialchars($result['tahun']) . '<br>';
                    echo '<strong>Abstrak:</strong> ' . htmlspecialchars($result['abstrak']) . '<br>';
                    echo '<strong>Similarity Score:</strong> ' . number_format($result['similarity'] * 100, 2) . '%<br>';
                    if (!empty($result['url'])) {
                        echo '<a href="' . htmlspecialchars($result['url']) . '" target="_blank" class="btn btn-link">Lihat Jurnal</a>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p><i>Tidak ada hasil ditemukan di database lokal.</i></p>';
            }
            echo '</div>';
            
            // Kolom hasil Google Scholar
            echo '<div class="col-md-6">';
            echo '<h2>Hasil dari Google Scholar</h2>';
            if (!empty($results['scholar_results'])) {
                echo '<ul class="list-group">';
                foreach ($results['scholar_results'] as $result) {
                    echo '<li class="list-group-item">';
                    echo '<strong>Judul:</strong> ' . htmlspecialchars($result['judul']) . '<br>';
                    echo '<strong>Penulis:</strong> ' . htmlspecialchars($result['penulis']) . '<br>';
                    echo '<strong>Tahun:</strong> ' . htmlspecialchars($result['tahun']) . '<br>';
                    if (!empty($result['abstract'])) {
                        echo '<strong>Abstrak:</strong> ' . htmlspecialchars($result['abstract']) . '<br>';
                    }
                    if (!empty($result['url'])) {
                        echo '<a href="' . htmlspecialchars($result['url']) . '" target="_blank" class="btn btn-link">Lihat Jurnal</a>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p><i>Tidak ada hasil ditemukan di Google Scholar.</i></p>';
            }
            echo '</div>';
            

            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">Terjadi kesalahan saat mengambil data. Silakan coba lagi.</div>';
        }
    }
    ?>
</div>

<!-- Menambahkan Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script src="scripts.js"></script>
<script>
    // Fungsi untuk mereset form
    function resetForm() {
        document.getElementById('searchForm').reset();
    }
</script>

</body>
</html>
