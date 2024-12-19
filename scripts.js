const texts = [
    "Silahkan deskripsikan jurnal di lingkup sistem informasi apa yang ingin anda cari.",
    "Apa yang anda butuhkan? Biarkan saya mencarikan jurnal itu untuk anda."
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

// Fungsi untuk mereset form
function resetForm() {
    document.getElementById('searchForm').reset();
}