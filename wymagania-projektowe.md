# Webowy odtwarzacz muzyki — Dokumentacja projektowa

## 1. Opis projektu

Projekt zakłada stworzenie aplikacji internetowej umożliwiającej odtwarzanie muzyki zapisanej lokalnie na serwerze Ubuntu. Aplikacja będzie dostępna z poziomu przeglądarki internetowej w sieci lokalnej.

Użytkownik będzie mógł przeglądać bibliotekę muzyczną, odtwarzać utwory, tworzyć playlisty oraz zarządzać znajdującymi się na serwerze plikami MP3.

## 2. Główne funkcje

Aplikacja będzie umożliwiała:

- wyświetlanie listy utworów,
- odtwarzanie plików MP3,
- zatrzymywanie i wznawianie odtwarzania,
- przewijanie utworu,
- regulację głośności,
- przechodzenie do poprzedniego i następnego utworu,
- tryb losowego odtwarzania (shuffle),
- powtarzanie utworu lub playlisty,
- tworzenie i usuwanie playlist,
- dodawanie i usuwanie utworów z playlist,
- zmianę kolejności utworów,
- wyszukiwanie utworów,
- dodawanie plików MP3 do biblioteki,
- usuwanie utworów z biblioteki,
- automatyczne skanowanie wskazanego folderu,
- utworzenie playlisty na podstawie zawartości folderu.

## 3. Technologie

### Backend
- PHP
- Symfony
- Doctrine ORM

### Frontend
- Twig
- HTML5
- CSS3
- JavaScript

### Baza danych
- MariaDB

### Serwer
- Ubuntu Server
- Nginx (jako reverse proxy)
- Docker / Docker Compose (konteneryzacja aplikacji)

### Przechowywanie muzyki

Pliki muzyczne będą przechowywane bezpośrednio na dysku serwera, np.:

```text
/var/lib/music/
├── Rock/
│   ├── utwor1.mp3
│   └── utwor2.mp3
├── Pop/
│   ├── utwor3.mp3
│   └── utwor4.mp3
```

## 4. Architektura aplikacji

Aplikacja będzie wykorzystywać architekturę MVC dostępną w Symfony.

```text
Użytkownik
    │
    ▼
Przeglądarka
    │
    ▼
Symfony Controller
    │
    ├── Twig / JavaScript
    │
    ├── Doctrine ORM
    │       │
    │       ▼
    │    MariaDB
    │
    └── System plików
            │
            ▼
          Pliki MP3
```

## 5. Baza danych

Podstawowymi encjami będą:

### Song
Przechowuje informacje o utworze:
- ID
- tytuł
- wykonawca
- album
- czas trwania
- ścieżka do pliku
- data dodania

### Playlist
Przechowuje informacje o playlistach:
- ID
- nazwa
- data utworzenia

### PlaylistSong
Tabela pośrednicząca pomiędzy playlistami i utworami:
- ID playlisty
- ID utworu
- pozycja utworu na playliście

> W przyszłości można również dodać encję `User`, umożliwiającą tworzenie osobnych playlist dla różnych użytkowników.

## 6. Odtwarzacz

Odtwarzanie muzyki będzie realizowane po stronie przeglądarki przy wykorzystaniu elementu HTML5 `<audio>` oraz JavaScript.

Przeglądarka pobierze utwór z serwera poprzez odpowiedni endpoint Symfony.

Przykładowo:

```text
GET /music/15/stream
```

Backend odnajdzie odpowiedni plik MP3 i przekaże go do przeglądarki.

Odtwarzacz będzie obsługiwał:
- Play/Pause,
- Previous/Next,
- pasek postępu,
- przewijanie,
- głośność,
- Shuffle,
- Repeat.

## 7. Dodawanie muzyki

Muzyka będzie mogła być dodawana poprzez:

1. przesłanie plików MP3 przez aplikację,
2. skopiowanie plików bezpośrednio do katalogu muzyki na serwerze.

Aplikacja będzie posiadała funkcję skanowania biblioteki. Podczas skanowania system wyszuka pliki MP3 i doda je do bazy danych.

Przykład:

```text
/music/Metallica/
├── song1.mp3
├── song2.mp3
└── song3.mp3
```

Po wykonaniu skanowania utwory pojawią się w bibliotece aplikacji.

## 8. Tworzenie playlisty z folderu

Jedną z funkcji aplikacji będzie możliwość automatycznego utworzenia playlisty na podstawie folderu.

Przykład:

```text
/music/Workout/
├── song1.mp3
├── song2.mp3
├── song3.mp3
└── song4.mp3
```

Po wybraniu opcji „Utwórz playlistę z folderu" aplikacja:

1. odczyta zawartość folderu,
2. znajdzie pliki MP3,
3. doda nieznane utwory do biblioteki,
4. utworzy playlistę,
5. doda do niej znalezione utwory.

Dzięki temu większą liczbę utworów można dodać do playlisty bez ręcznego wybierania każdego pliku.

## 9. Odczytywanie informacji z MP3

Podczas dodawania utworu aplikacja może odczytywać metadane ID3 znajdujące się w pliku MP3.

Przykładowe dane:

```text
Tytuł: Song Name
Wykonawca: Artist
Album: Album Name
Rok: 2025
Gatunek: Rock
```

Pozwoli to automatycznie uporządkować bibliotekę muzyczną.

## 10. Przykładowe endpointy

```text
GET    /songs
GET    /songs/{id}
GET    /songs/{id}/stream

POST   /songs
DELETE /songs/{id}

GET    /playlists
POST   /playlists
DELETE /playlists/{id}

POST   /playlists/{id}/songs
DELETE /playlists/{id}/songs/{songId}

POST   /library/scan
```

## 11. Bezpieczeństwo

Dostęp do aplikacji może zostać ograniczony do sieci lokalnej.

Dodatkowo można zastosować:
- system logowania użytkowników,
- kontrolę uprawnień,
- ochronę formularzy CSRF,
- walidację przesyłanych plików,
- ograniczenie dozwolonych formatów do MP3,
- zabezpieczenie przed przesyłaniem wykonywalnych plików,
- ograniczenie dostępu do katalogu z muzyką.

## 12. Możliwy rozwój projektu

W przyszłości aplikacja może zostać rozszerzona o:
- konta użytkowników,
- okładki albumów,
- automatyczne pobieranie metadanych,
- historię odtwarzania,
- ulubione utwory,
- system ocen,
- statystyki odtwarzania,
- tryb ciemny,
- responsywny interfejs dla telefonu,
- import całych folderów,
- obsługę innych formatów audio,
- dostęp przez Internet z wykorzystaniem HTTPS.

## 13. Konteneryzacja (Docker)

Aplikacja zostanie uruchomiona na serwerze Ubuntu z wykorzystaniem Dockera i Docker Compose, co ułatwi wdrożenie, izolację usług oraz przyszłą migrację środowiska.

### Planowane kontenery

- **php** — kontener z PHP-FPM i aplikacją Symfony (własny `Dockerfile`, z wymaganymi rozszerzeniami PHP oraz biblioteką do odczytu metadanych ID3),
- **nginx** — serwer webowy, obsługuje ruch HTTP i przekazuje zapytania do PHP-FPM,
- **mariadb** — baza danych aplikacji,
- **adminer** *(opcjonalnie, środowisko developerskie)* — podgląd i zarządzanie bazą danych przez przeglądarkę.

### Wolumeny i przechowywanie danych

- katalog z biblioteką muzyczną (`/var/lib/music/`) będzie zamontowany jako **bind mount** z hosta do kontenera `php` (a w razie potrzeby również do `nginx`, jeśli pliki mają być serwowane bezpośrednio z pominięciem PHP),
- dane bazy MariaDB będą przechowywane w nazwanym wolumenie Dockera, aby przetrwały restart kontenerów,
- pliki aplikacji Symfony (kod źródłowy) będą montowane jako wolumen w trakcie developmentu lub kopiowane do obrazu w wersji produkcyjnej.

### Przykładowa struktura `docker-compose.yml`

```text
services:
  php:
    build: ./docker/php
    volumes:
      - ./:/var/www/app
      - /var/lib/music:/var/lib/music:ro
    depends_on:
      - mariadb

  nginx:
    image: nginx:stable
    ports:
      - "80:80"
    volumes:
      - ./:/var/www/app
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php

  mariadb:
    image: mariadb:11
    environment:
      MARIADB_DATABASE: music_player
      MARIADB_USER: app
      MARIADB_PASSWORD: app
      MARIADB_ROOT_PASSWORD: root
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

### Uwagi

- katalog z muzyką montowany jest w trybie tylko do odczytu (`:ro`) z poziomu kontenera aplikacji — pliki nadal są zarządzane bezpośrednio na hoście lub przez funkcję uploadu w aplikacji, zapisującą pliki na hoście poza kontenerem,
- w wersji produkcyjnej warto rozdzielić plik `docker-compose.yml` na wersję deweloperską i produkcyjną (np. `docker-compose.prod.yml`) oraz usunąć z produkcji kontener `adminer`,
- dostęp do aplikacji ograniczony do sieci lokalnej może zostać zrealizowany na poziomie konfiguracji `nginx` lub reguł firewalla na hoście (np. `ufw`).

## 14. Design / UI

Interfejs aplikacji zostanie utrzymany w spójnej, ciemnej stylistyce.

Kierunek wizualny:

- **ciemny, fioletowy motyw kolorystyczny** — jako baza dla tła i głównych elementów interfejsu,
- **brak emotek** w interfejsie (etykiety, przyciski, komunikaty) — komunikacja czysto tekstowa/ikonograficzna,
- **zaokrąglony przycisk odtwarzania** — główny przycisk Play/Pause/Stop ma być okrągły; pozostałe elementy interfejsu niekoniecznie muszą być zaokrąglone — nie chodzi o to, by zaokrąglać wszystko na siłę.

Styl ten dotyczy zarówno widoku biblioteki utworów, listy playlist, jak i głównego paska odtwarzacza. Interfejs ma mieć własny, odróżniający się charakter — celem nie jest odwzorowanie wyglądu znanych serwisów streamingowych (np. Spotify), a stworzenie oryginalnej stylistyki w oparciu o powyższe założenia.

## 15. Cel projektu

Celem projektu jest stworzenie własnego, lokalnego systemu do zarządzania i odtwarzania kolekcji muzycznej poprzez przeglądarkę internetową. Projekt pozwoli wykorzystać praktyczną wiedzę z zakresu PHP, Symfony, baz danych, ORM, JavaScript, HTML, CSS oraz administracji serwerem Linux.