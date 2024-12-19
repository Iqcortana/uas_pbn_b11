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
    "sistem informasi",
    "teknologi informasi",
    "pemrograman",
    "data mining",
    "machine learning",
    "big data",
    "pengembangan sistem informasi",
    "pengembangan teknologi informasi",
    "manajemen proyek teknologi informasi",
    "iot",
    "blockchain",
    "keamanan informasi",
    "kecerdasan buatan",
    "text mining",
    "web mining",
    "opini mining"
]

def process_query_tfidf(query):
    """
    Proses input menjadi kata kunci yang dipahami menggunakan TF-IDF.
    """
    query = query.lower()

    # Ekstrak rentang tahun jika ada dalam format YYYY atau YYYY-YYYY
    years = re.findall(r'(\d{4})(?:-(\d{4}))?', query)
    years = [(int(start), int(end) if end else int(start)) for start, end in years]

    # Gabungkan lingkup SI dan query untuk analisis TF-IDF
    corpus = LINGKUP_SI + [query]
    vectorizer = TfidfVectorizer()
    tfidf_matrix = vectorizer.fit_transform(corpus)

    # Ekstrak fitur TF-IDF dari query
    feature_names = vectorizer.get_feature_names_out()
    query_vector = tfidf_matrix[-1].toarray()[0]

    # Pilih kata-kata dengan skor TF-IDF tinggi
    top_keywords = [feature_names[i] for i in query_vector.argsort()[-5:][::-1]]

    return top_keywords, years

def is_year_within_range(year, year_ranges):
    """
    Memeriksa apakah tahun berada dalam rentang yang diminta.
    """
    for start, end in year_ranges:
        if start <= year <= end:
            return True
    return False

def filter_and_prioritize_results(results, year_ranges):
    """
    Memfilter dan memprioritaskan hasil pencarian berdasarkan tahun dan judul yang relevan.
    """
    filtered_results = [
        result for result in results
        if "sistem informasi" in result.get('title', '').lower()
    ]

    prioritized = []
    non_prioritized = []

    for result in filtered_results:
        year = result.get('year', None)

        if year and is_year_within_range(year, year_ranges):
            prioritized.append(result)
        else:
            non_prioritized.append(result)

    return prioritized, non_prioritized

def search_scholar_with_retry(query, start_index=0, max_results=50, retries=3):
    """
    Mencari artikel di Google Scholar dengan retry mechanism.
    """
    try:
        # Proses query untuk mendapatkan kata kunci dan tahun
        keywords, year_ranges = process_query_tfidf(query)
        if not keywords:
            return {
                "error": "Query tidak sesuai dengan lingkup Sistem Informasi."
            }, 0

        search_query = " ".join(keywords)

        search_results = scholarly.search_pubs(search_query)
        results = []

        for _ in range(max_results):
            try:
                result = next(search_results)
                year = result.get('bib', {}).get('pub_year')
                year = int(year) if year else None

                results.append({
                    'title': result.get('bib', {}).get('title', 'No title'),
                    'author': result.get('bib', {}).get('author', 'No author'),
                    'year': year,
                    'abstract': result.get('bib', {}).get('abstract', 'No abstract'),
                    'url': result.get('pub_url', 'No URL')
                })
            except StopIteration:
                break

        # Filter and prioritize results
        prioritized, non_prioritized = filter_and_prioritize_results(results, year_ranges)

        return {
            "prioritized_results": prioritized,
            "results": non_prioritized
        }, len(results)

    except Exception as e:
        return {"error": str(e)}, 0

def main():
    """
    Fungsi utama untuk menjalankan program pencarian Google Scholar.
    """
    try:
        query = sys.argv[1] if len(sys.argv) > 1 else ""
        start_index = int(sys.argv[2]) if len(sys.argv) > 2 else 0

        if not query:
            print(json.dumps({"error": "Query tidak boleh kosong!"}))
            return

        results, total_results = search_scholar_with_retry(query, start_index=start_index, max_results=10)

        print(json.dumps(results, indent=4))

    except Exception as e:
        print(json.dumps({"error": f"Terjadi kesalahan: {str(e)}"}))

if __name__ == "__main__":
    main()
