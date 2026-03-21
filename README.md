
# 🛒 Task&Taste

**Task&Taste** to lekka, nowoczesna aplikacja webowa pełniąca rolę osobistego centrum dowodzenia. 
Łączy w sobie zarządzanie listami zakupów, bazę własnych przepisów kulinarnych oraz system zadań.
Całość ubrana jest w minimalistyczny interfejs inspirowany Google Material Design 3.

---

## ✨ Główne funkcje

* 🛍️ **Lista Zakupów:** Dodawanie sklepów, kategoryzowanie produktów i błyskawiczne odhaczanie 
kupionych rzeczy bez przeładowywania strony.
* 🍳 **Książka Przepisów:** Twoja prywatna baza kulinarna z podziałem na posiłki, instrukcjami
 przygotowania i zdjęciami.
* ✅ **Zadania (To-Do):** Zarządzanie obowiązkami, grupowanie zadań (np. "Do szkoły", "Do pracy"),
 ustawianie terminów i priorytetów (oznaczanych kolorami).
* 🌍 **Wielojęzyczność:** Wbudowany, lekki system tłumaczeń i18n oparty na plikach JSON
 (obecnie wspiera język polski i angielski).
* ⚡ **Błyskawiczne działanie:** Dzięki wykorzystaniu HTMX aplikacja działa jak nowoczesne SPA 
(Single Page Application), ale bez ciężkiego frameworka JavaScript.

---

## 🛠️ Stos technologiczny

* **Backend:** PHP 8.2+, Slim Framework 4
* **Baza danych:** SQLite (baza generuje się automatycznie w jednym pliku)
* **Frontend:** Twig (szablony), Tailwind CSS (stylowanie), HTMX (dynamiczne interakcje)
* **Infrastruktura:** Gotowe do konteneryzacji (wbudowany `Dockerfile`),
 idealne pod hostingi takie jak Northflank.

---

## 🚀 Uruchomienie lokalne (np. XAMPP)

1. Sklonuj repozytorium do folderu serwera (np. `htdocs` w XAMPP):
   ```bash
   git clone https://github.com/Twiggiermaen21/Task-Taste.git
   cd Task-Taste
   ```

2. Zainstaluj zależności za pomocą Composera:
   ```bash
   composer install
   ```

3. Upewnij się, że masz włączony moduł Apache. Aplikacja automatycznie utworzy folder `data` i wygeneruje w nim plik bazy danych `baza.sqlite` przy pierwszym uruchomieniu.

4. Wejdź w przeglądarce pod adres przypisany do Twojego lokalnego serwera (np. `http://localhost/task-and-taste/`).

---

## 🐳 Uruchomienie w Dockerze (np. Northflank)

Projekt zawiera gotowy plik `Dockerfile`.
* Baza danych jest przechowywana w katalogu `/var/www/html/data`.
* **Ważne:** Konfigurując środowisko w chmurze (np. Northflank), pamiętaj o podpięciu trwałego dysku (Volume) pod ścieżkę `/var/www/html/data`, aby Twoje zadania i przepisy nie zniknęły po restarcie kontenera.

---

## 📁 Struktura plików

* `/public/` - Główny punkt wejścia aplikacji (zawiera `index.php` z routingiem).
* `/templates/` - Widoki HTML oparte na silniku Twig.
* `/lang/` - Pliki tłumaczeń JSON (pl.json, en.json).
* `/data/` - Folder z plikiem bazy danych SQLite (ignorowany w repozytorium).
* `/vendor/` - Zależności pobrane przez Composera.

---

*Stworzone z pasją do lepszej organizacji czasu i smaczniejszego jedzenia.* 🥗📋
