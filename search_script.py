from flask import Flask, request, jsonify
import re
import spacy
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from scholarly import scholarly

app = Flask(__name__)

# Load Spacy model
nlp = spacy.load("en_core_web_sm")

# Load data dari Excel
file_path = 'Final Project PBN - B11.xlsx'
data_jurnal_df = pd.ExcelFile(file_path).parse('Sheet1')
data_jurnal_df = data_jurnal_df.rename(columns={"Judul Penelitian": "judul", "Penulis": "penulis", "Tahun": "tahun", "Abstrak": "abstrak"})

# Preprocessing teks
def preprocess_text(text):
    if pd.isnull(text):
        return ""
    text = text.lower()
    text = re.sub(r'[^a-zA-Z0-9\s]', '', text)
    return text

# Ekstraksi Entitas (NER)
def extract_entities(input_text):
    doc = nlp(input_text)
    journal_title_words = []
    author_name = None
    year = None

    for token in doc:
        if token.pos_ in ["NOUN", "PROPN", "ADJ"]:
            journal_title_words.append(token.text)

    for ent in doc.ents:
        if ent.label_ == "PERSON":
            author_name = ent.text
        elif ent.label_ == "DATE":
            year_match = re.search(r'\d{4}', ent.text)
            if year_match:
                year = year_match.group()

    return {
        "judul": " ".join(journal_title_words),
        "penulis": author_name,
        "tahun": year
    }

# Fungsi pencarian jurnal di database lokal
def search_local_journal(input_text, data_jurnal):
    input_text = preprocess_text(input_text)
    entities = extract_entities(input_text)

    query = "{} {} {}".format(
        entities.get("judul", ""),
        entities.get("penulis", ""),
        entities.get("tahun", "")
    ).strip()

    data_jurnal["combined"] = data_jurnal.apply(
        lambda row: preprocess_text(f"{row['judul']} {row['penulis']} {row['tahun']} {row['abstrak']}"), axis=1
    )

    vectorizer = TfidfVectorizer()
    vectors = vectorizer.fit_transform([query] + data_jurnal["combined"].tolist())
    similarity_scores = cosine_similarity(vectors[0:1], vectors[1:]).flatten()

    data_jurnal["similarity"] = similarity_scores
    results = data_jurnal.sort_values(by="similarity", ascending=False)
    return results[results["similarity"] > 0].head(10).to_dict(orient="records")

# Fungsi pencarian jurnal di Google Scholar
def search_google_scholar(input_text):
    search_query = scholarly.search_pubs(input_text)
    results = []

    try:
        for _ in range(10):  # Ambil maksimal 10 hasil
            pub = next(search_query)
            results.append({
                "judul": pub.get("bib", {}).get("title", ""),  # Judul jurnal
                "penulis": ", ".join(pub.get("bib", {}).get("author", [])),  # Penulis
                "tahun": pub.get("bib", {}).get("pub_year", ""),  # Tahun publikasi
                "abstrak": pub.get("bib", {}).get("abstract", "Abstrak tidak tersedia"),  # Abstrak
                "url": pub.get("pub_url", "URL tidak tersedia")  # URL publikasi
            })
    except StopIteration:
        pass
    except Exception as e:
        print(f"Error while fetching Google Scholar results: {e}")

    return results


@app.route('/search', methods=['POST'])
def search():
    data = request.get_json()
    query = data.get("query", "")

    # Pencarian lokal
    local_results = search_local_journal(query, data_jurnal_df)

    # Pencarian Google Scholar
    scholar_results = search_google_scholar(query)

    # Mengembalikan hasil
    return jsonify({
        "local_results": local_results,
        "scholar_results": scholar_results
    })

if __name__ == '__main__':
    app.run(debug=True)
