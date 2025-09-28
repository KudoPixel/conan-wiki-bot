# Detective Conan Wiki Bot (Gemini AI + Telegram)
This is a professional, modular PHP Telegram bot designed to serve as a highly knowledgeable Detective Conan Wiki resource. It uses the Google Gemini API with integrated Google Search grounding to provide accurate, up-to-date, and authoritative information about the manga and anime series.

The bot is designed with a clean, object-oriented structure (Clients, Services, Models) and uses Composer for dependency management.

## 🚀 Features
* Modular Architecture: Clean separation of concerns (Telegram Client, Gemini Client, Bot Handler).

* **Gemini Search Grounding:** Uses Google Search to ensure answers are based on the latest information from the web (e.g., official wiki sources).

* **Custom Persona:** Configured with a system instruction to act as a "Mysterious and highly knowledgeable detective."

* **Robust Logging:** Uses Monolog to log all events and errors, including sending critical failures to a dedicated Telegram channel (TELEGRAM_ERROR_CHAT_ID).

* **Docker Ready:** Includes a Dockerfile for easy deployment on cloud platforms like Render.

🛠️ Project Setup (Local Development)
### 1. Requirements
* PHP (>= 8.1)

* Composer

* cURL extension enabled

* Docker (for containerized deployment)

### 2. Installation
* Clone the repository:


```
git clone your-repo-name detective-conan-bot
cd detective-conan-bot
```

**Install dependencies:**

```composer install```

**Generate Autoload map: (Crucial for loading App classes)**

```composer dump-autoload```

### 3. Configuration (.env)
* Copy the example file and fill in your secrets:

* cp .env.example .env

* Edit the .env file with your actual keys:

```
TELEGRAM_BOT_TOKEN="YOUR_TELEGRAM_BOT_TOKEN_HERE"
GEMINI_API_KEY="YOUR_GEMINI_API_KEY_HERE"
TELEGRAM_ERROR_CHAT_ID="YOUR_ERROR_CHANNEL_ID"
```

## ⚙️ Running the Bot
Since Telegram requires a public HTTPS endpoint, you must use a tunneling service (like ngrok or Cloudflare Tunnel) or deploy to a public server.

1. Set up the local server
Run the built-in PHP server (only for receiving the webhook requests via tunnel):

```php -S 0.0.0.0:7878 webhook.php```

2. Set the Telegram Webhook
Using your tunneling URL (e.g., https://abcd.ngrok-free.app/webhook.php), set the webhook via your browser:

[https://api.telegram.org/bot](https://api.telegram.org/bot)<YOUR_BOT_TOKEN>/setWebhook?url=[https://abcd.ngrok-free.app/webhook.php](https://abcd.ngrok-free.app/webhook.php)

## 🐳 Deployment with Docker (e.g., Render)
The provided Dockerfile is optimized for cloud environments using php-fpm.

Build the Docker Image:

docker build -t conan-bot .

Run the container:

```docker run -d -p 9000:9000 conan-bot```

Render Deployment: Configure your Render Web Service to use the provided Dockerfile and ensure the TELEGRAM_BOT_TOKEN and GEMINI_API_KEY are set as environment variables. (Remember to expose port 9000 if needed).

Webhook: Update the Telegram Webhook URL to point to your Render public URL.

## 📁 Project Structure
```
.
├── src/
│   ├── Clients/
│   │   ├── GeminiClient.php
│   │   └── TelegramClient.php
│   ├── Models/
│   │   └── TelegramUpdate.php
│   ├── Services/
│   │   └── BotHandler.php
│   └── Utils/
│       └── Logger.php
├── .env.example
├── composer.json
├── Config.php
├── Dockerfile
├── GeminiConfig.json
├── README.md
└── webhook.php
```