import time
import re
import json
from scholarly import scholarly
import nltk
from sklearn.feature_extraction.text import TfidfVectorizer
import sys

# Unduh komponen NLP yang diperlukan jika belum ada
nltk.download('punkt', quiet=True)

# Definisi lingkup Sistem Informasi berdasarkan topik yang relevan
LINGKUP_SI = [
    "dasar sistem informasi",
    "teknologi informasi",
    "pemrograman",
    "analisis sistem",
    "desain sistem",
    "manajemen data",
    "big data",
    "sistem informasi bisnis",
    "keamanan informasi",
    "kecerdasan buatan",
    "blockchain",
    "iot",
    "manajemen proyek ti",
    "e-commerce",
    "praktikum dan magang",
    "pemrograman bahasa natural",
    "data mining",
    "text mining",
    "web mining",
    "opini mining",
    "machine learning",
    "deep learning",
    "pengembangan sistem informasi",
    "pengembangan teknologi informasi",
    "bisnis intelijen",
    "regresi linear berganda",
    "regresi linier sederhana",
    "analisis kuantitatif",
    "klasterisasi",
    "klasifikasi",
    "decision tree",
    "geographically weighted regression",
    "multivariate adapted regression splines",
    "mobile development"
]

REKOMENDASI_LINGKUP = [
    "IoT dalam bisnis",
    "Blockchain untuk keamanan data",
    "Sistem informasi berbasis AI",
    "Big Data untuk manajemen bisnis",
    "Manajemen proyek teknologi informasi",
    "Pemrograman bahasa natural dalam text mining",
    "Data mining untuk analisis data",
    "Machine learning dalam pengolahan data",
    "Opini mining untuk sentimen analisis",
    "Web mining untuk ekstraksi data"
]

def process_query_tfidf(query):
    """
    Proses input menjadi kata kunci yang dipahami menggunakan TF-IDF.
    """
    if not query or not isinstance(query, str):
        return [], [], []

    query = query.lower()

    # Ekstrak rentang tahun jika ada dalam format YYYY-YYYY atau tahun tunggal
    years = re.findall(r'(\d{4})(?:-(\d{4}))?', query)
    years = [(int(start), int(end) if end else int(start)) for start, end in years]

    # Gabungkan lingkup SI dan query untuk analisis TF-IDF
    corpus = LINGKUP_SI + [query]
    vectorizer = TfidfVectorizer()
    tfidf_matrix = vectorizer.fit_transform(corpus)

    # Ekstrak fitur TF-IDF dari query
    feature_names = vectorizer.get_feature_names_out()
    query_vector = tfidf_matrix[-1].toarray()[0]  # Ambil vektor query

    # Pilih kata-kata dengan skor TF-IDF tinggi
    top_keywords = [feature_names[i] for i in query_vector.argsort()[-5:][::-1]]

    # Filter topik menggunakan lingkup SI
    filtered_topics = [keyword for keyword in top_keywords if keyword in LINGKUP_SI]

    return filtered_topics, years

def is_year_within_range(year, year_ranges):
    """
    Memeriksa apakah tahun berada dalam rentang yang diminta.
    """
    for start, end in year_ranges:
        if start <= year <= end:
            return True
    return False

def prioritize_results(results, institutions, year_ranges):
    """
    Memprioritaskan hasil pencarian berdasarkan institusi dan tahun.
    """
    prioritized = []
    non_prioritized = []

    for result in results:
        title = result.get('title', '').lower()
        abstract = result.get('abstract', '').lower()
        year = result.get('year', None)

        if any(inst.lower() in title or inst.lower() in abstract for inst in institutions):
            if year and is_year_within_range(year, year_ranges):
                prioritized.append(result)
            else:
                non_prioritized.append(result)
        else:
            non_prioritized.append(result)

    return prioritized + non_prioritized

def search_scholar_with_retry(query, start_index=0, max_results=50, retries=3):
    """
    Mencari artikel di Google Scholar dengan retry mechanism.
    """
    try:
        # Proses query untuk mendapatkan kata kunci dan penulis
        filtered_topics, year_ranges = process_query_tfidf(query)
        if not filtered_topics:
            return {
                "error": "Query tidak sesuai dengan lingkup Sistem Informasi.",
                "recommendations": REKOMENDASI_LINGKUP
            }, 0

        search_query = " ".join(filtered_topics)

        search_results = scholarly.search_pubs(search_query)

        results = []
        attempts = 0
        count = 0

        while attempts < retries and len(results) < max_results:
            try:
                result = next(search_results)
                year = result.get('bib', {}).get('pub_year')
                if year:
                    year = int(year)
                else:
                    year = None

                results.append({
                    'title': result.get('bib', {}).get('title', 'No title'),
                    'author': result.get('bib', {}).get('author', 'No author'),
                    'year': year,
                    'abstract': result.get('bib', {}).get('abstract', 'No abstract'),
                    'url': result.get('pub_url', 'No URL')
                })
                count += 1
            except StopIteration:
                break
            except Exception as e:
                attempts += 1
                time.sleep(2)  # Tunggu sebelum retry
                if attempts >= retries:
                    raise Exception(f"Gagal mengambil data setelah {retries} percobaan. Error: {e}")

        # Prioritize results based on institutions and year ranges
        prioritized_results = prioritize_results(results, [], year_ranges)

        return {
            "results": prioritized_results
        }, count

    except Exception as e:
        return {"error": str(e)}, 0  # Kembalikan error jika terjadi

def main():
    """
    Fungsi utama untuk menjalankan program pencarian Google Scholar.
    """
    try:
        query = sys.argv[1] if len(sys.argv) > 1 else ""  # Ambil query dari argumen command line
        start_index = int(sys.argv[2]) if len(sys.argv) > 2 else 0  # Start index dari argumen

        if not query:
            # Jika query kosong, kembalikan JSON error
            print(json.dumps({"error": "Query tidak boleh kosong!"}))
            return

        # Panggil fungsi pencarian
        results, total_results = search_scholar_with_retry(query, start_index=start_index, max_results=10)

        # Format hasil dalam JSON
        if isinstance(results, dict) and "recommendations" in results:
            print(json.dumps(results, indent=4))
        elif isinstance(results, dict) and "message" in results:
            print(json.dumps(results, indent=4))
        elif not results:
            print(json.dumps({"error": "Tidak ada hasil ditemukan."}))
        else:
            # Output hasil dalam format JSON yang valid
            response = {
                "results": results,
                "total_results": total_results  # Total hasil untuk menghitung pagination
            }
            print(json.dumps(response, indent=4))

    except Exception as e:
        print(json.dumps({"error": f"Terjadi kesalahan: {str(e)}"}))

if __name__ == "__main__":
    main()
