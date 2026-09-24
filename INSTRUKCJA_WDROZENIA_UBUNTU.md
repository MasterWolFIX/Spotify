# 🚀 Instrukcja wdrożenia Webowego Odtwarzacza Muzyki na Ubuntu Server

Ten dokument zawiera kompletny przewodnik krok po kroku, jak zainstalować, skonfigurować i uruchomić aplikację na czystym serwerze **Ubuntu Server (22.04 LTS / 24.04 LTS)** w sieci lokalnej.

---

## Spis treści
1. [Wymagania wstępne](#1-wymagania-wstępne)
2. [Instalacja Dockera na Ubuntu](#2-instalacja-dockera-na-ubuntu)
3. [Przygotowanie katalogu na muzykę](#3-przygotowanie-katalogu-na-muzykę)
4. [Skopiowanie projektu na serwer](#4-skopiowanie-projektu-na-serwer)
5. [Konfiguracja pliku .env](#5-konfiguracja-pliku-env)
6. [Uruchomienie aplikacji (Docker Compose)](#6-uruchomienie-aplikacji-docker-compose)
7. [Inicjalizacja bazy danych i konto Administratora](#7-inicjalizacja-bazy-danych-i-konto-administratora)
8. [Konfiguracja sieci i Firewalla (UFW)](#8-konfiguracja-sieci-i-firewalla-ufw)
9. [Codzienna obsługa i przydatne komendy](#9-codzienna-obsługa-i-przydatne-komendy)

---

## 1. Wymagania wstępne

- Serwer z zainstalowanym **Ubuntu Server** (np. 22.04 LTS lub 24.04 LTS).
- Dostęp do konta z uprawnieniami `sudo` (np. przez SSH).
- Stały adres IP serwera w sieci lokalnej (np. `192.168.1.150`).

---

## 2. Instalacja Dockera na Ubuntu

Jeśli na serwerze nie ma jeszcze zainstalowanego Dockera, wykonaj poniższe polecenia:

```bash
# 1. Zaktualizuj repozytoria
sudo apt update && sudo apt upgrade -y

# 2. Zainstaluj pakiety pomocnicze
sudo apt install -y ca-certificates curl gnupg lsb-release git

# 3. Dodaj oficjalny klucz GPG Dockera
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# 4. Dodaj repozytorium Dockera
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# 5. Zainstaluj Docker Engine i wtyczkę Docker Compose
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# 6. Dodaj swojego użytkownika do grupy docker (aby nie wpisywać sudo przed docker)
sudo usermod -aG docker $USER
newgrp docker
```

Sprawdź poprawność instalacji:
```bash
docker --version
docker compose version
```

---

## 3. Przygotowanie katalogu na muzykę

Zgodnie ze specyfikacją projektową pliki MP3 będą przechowywane w `/var/lib/music/`:

```bash
# Utwórz katalog na pliki muzyczne
sudo mkdir -p /var/lib/music

# Ustaw uprawnienia zapisu/odczytu dla kontenerów Dockera
sudo chmod -R 775 /var/lib/music
sudo chown -R $USER:www-data /var/lib/music 2>/dev/null || sudo chmod -R 777 /var/lib/music
```

*Możesz od razu wgrać tam swoje foldery z muzyką (np. przez SFTP, SCP lub rsync).*

---

## 4. Skopiowanie projektu na serwer

Przejdź do folderu domowego i skopiuj projekt (np. za pomocą Gita):

```bash
cd /opt  # lub cd ~
sudo git clone <ADRES_TWOJEGO_REPOZYTORIUM> music-player
sudo chown -R $USER:$USER /opt/music-player
cd /opt/music-player
```

*(Jeśli kopiujesz pliki z Windowsa przez SCP/FileZilla, upewnij się, że kopiujesz cały folder projektu).*

---

## 5. Konfiguracja pliku .env

W katalogu projektu otwórz plik `.env` do edycji:

```bash
nano .env
```

Upewnij się, że ustawione są następujące wartości:

```env
APP_ENV=prod
APP_SECRET=wygeneruj_tutaj_dlugi_losowy_ciag_znakow_np_32_znaki

# Katalog z muzyką na serwerze Ubuntu (zgodnie z krokiem 3)
MUSIC_DIR_HOST=/var/lib/music

# Konfiguracja bazy danych MariaDB
DATABASE_URL="mysql://app:silne_haslo_bazy@mariadb:3306/music_player?serverVersion=mariadb-11.0.0&charset=utf8mb4"

MARIADB_DATABASE=music_player
MARIADB_USER=app
MARIADB_PASSWORD=silne_haslo_bazy
MARIADB_ROOT_PASSWORD=silne_haslo_roota

MUSIC_DIR=/var/lib/music
```

Zapisz plik (`Ctrl + O`, `Enter`), wyjdź (`Ctrl + X`).

---

## 6. Uruchomienie aplikacji (Docker Compose)

Uruchom budowanie i start kontenerów w tle:

```bash
docker compose up --build -d
```

Docker pobierze obrazy `mariadb`, `nginx` oraz zbuduje obraz `php` instalując automatycznie wszystkie zależności Composera.

Sprawdź, czy kontenery działają:
```bash
docker compose ps
```
Wszystkie 3 usługi (`music_mariadb`, `music_php`, `music_nginx`) powinny mieć status `Up`.

---

## 7. Inicjalizacja bazy danych i konto Administratora

Wykonaj jednorazowo migracje tabel i załóż konto admina:

### Krok 7.1: Uruchom migracje bazy danych
```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Krok 7.2: Utwórz konto Administratora
```bash
docker compose exec php php bin/console app:create-admin --email=admin@twojadomena.pl --password=twojetajnehaslo
```

---

## 8. Konfiguracja sieci i Firewalla (UFW)

Aby aplikacja była dostępna z innych komputerów i telefonów w Twojej sieci lokalnej, otwórz port 80:

```bash
# Włącz port HTTP (80)
sudo ufw allow 80/tcp

# Upewnij się, że SSH (port 22) jest włączony, aby nie stracić dostępu do serwera
sudo ufw allow 22/tcp

# Włącz firewall (jeśli jeszcze nie był aktywny)
sudo ufw enable
sudo ufw status
```

Sprawdź adres IP swojego serwera w sieci lokalnej:
```bash
hostname -I | awk '{print $1}'
```
*(np. `192.168.1.150`)*

---

## 🎉 Gotowe! Dostęp do aplikacji

Otwórz przeglądarkę na dowolnym urządzeniu w sieci lokalnej (komputer, laptop, telefon) i wpisz:

```text
http://ADRES_IP_SERWERA/
np. http://192.168.1.150/
```

1. Kliknij **Zaloguj się** i wpisz dane administratora z kroku 7.2.
2. W menu bocznym kliknij **Skanuj bibliotekę** — system automatycznie zindeksuje wszystkie pliki z `/var/lib/music/` i odczyta tagi ID3!
3. Możesz też tworzyć playlisty, zmieniać kolejność utworów oraz wgrywać pliki MP3 bezpośrednio przez przeglądarkę.

---

## 9. Codzienna obsługa i przydatne komendy

### Zatrzymanie i ponowne uruchomienie
```bash
# Zatrzymanie
docker compose stop

# Uruchomienie
docker compose start

# Pełny restart
docker compose restart
```

### Podgląd logów
```bash
# Logi Nginxa (ruch HTTP, błędy)
docker compose logs -f nginx

# Logi PHP
docker compose logs -f php
```

### Czyszczenie pamięci podręcznej Symfony
```bash
docker compose exec php php bin/console cache:clear
```

### Ponowne skanowanie biblioteki z poziomu konsoli
*(Alternatywa dla przycisku w przeglądarce)*
```bash
docker compose exec php php bin/console cache:clear
```