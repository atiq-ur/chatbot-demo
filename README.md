# 🧠 Nexus AI — Personal AI Assistant for Developers

> A full-stack, multi-provider AI chat assistant built for software engineers. Supports real-time streaming, image & PDF uploads, code highlighting, and seamless provider switching across OpenAI, Claude, Gemini, and Ollama.

---

## 📁 Project Structure

```
chatbot/
├── chat-app/              # Next.js 14 frontend
└── chatobot-backend/      # Laravel 12 backend (API)
```

---

## ⚙️ Tech Stack

| Layer     | Technology                                      |
|-----------|-------------------------------------------------|
| Frontend  | Next.js 14, TypeScript, Tailwind CSS, shadcn/ui |
| Backend   | Laravel 12, PHP 8.4, MySQL                      |
| Streaming | Server-Sent Events (SSE)                        |
| AI        | OpenAI, Anthropic Claude, Gemini, Ollama        |

---

## 🚀 Prerequisites

Before starting, make sure the following are installed:

- **PHP 8.4+** with extensions: `json`, `pdo`, `pdo_mysql`, `fileinfo`, `zlib`
- **Composer**
- **Node.js 20+** and **npm**
- **MySQL 8+**
- **Git**

---

## 🦙 Ollama Setup (Local AI)

Ollama lets you run powerful AI models entirely on your own machine — no API key required.

### 1. Install Ollama

**Linux / macOS:**
```bash
curl -fsSL https://ollama.com/install.sh | sh
```

**Windows:** Download the installer from [https://ollama.com/download](https://ollama.com/download)

### 2. Start the Ollama Service

```bash
ollama serve
```

Ollama will start listening at `http://localhost:11434` by default.

### 3. Pull a Model

```bash
# Pull DeepSeek (default for Nexus AI)
ollama pull deepseek-r1:7b

# Or try other models
ollama pull llama3.2
ollama pull mistral
ollama pull phi4-mini
```

> 💡 **Tip:** For vision support (image uploads), use a model that supports it, e.g. `llava` or `moondream`.

### 4. Verify Ollama is Running

```bash
curl http://localhost:11434/api/tags
```

You should see a JSON list of your installed models.

---

## 🛠️ Backend Setup (Laravel)

### 1. Clone and Navigate

```bash
cd chatobot-backend
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your settings:

```ini
# Application
APP_NAME="Nexus AI"
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nexus_ai
DB_USERNAME=root
DB_PASSWORD=your_password

# --- AI Provider Keys ---

# OpenAI (https://platform.openai.com/api-keys)
OPENAI_API_KEY=sk-...

# Anthropic Claude (https://console.anthropic.com/)
ANTHROPIC_API_KEY=sk-ant-...

# Google Gemini (https://aistudio.google.com/app/apikey)
GEMINI_API_KEY=AIza...

# Ollama (local — no key needed)
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=deepseek-r1:7b
```

> You only need to fill in keys for the providers you intend to use. Ollama requires no key.

### 4. Create the Database

```sql
CREATE DATABASE nexus_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Create Storage Symlink (for file uploads)

```bash
php artisan storage:link
# If artisan doesn't work due to PHP version:
ln -s ../storage/app/public public/storage
```

### 7. Start the Backend Server

```bash
php artisan serve --port=8000
```

The API will be available at `http://localhost:8000/api`.

---

## 🖥️ Frontend Setup (Next.js)

### 1. Navigate to the Frontend

```bash
cd chat-app
```

### 2. Install Node Dependencies

```bash
npm install
```

### 3. Configure Environment

```bash
cp .env.local.example .env.local
```

> If `.env.local.example` doesn't exist, create `.env.local` manually:

```ini
NEXT_PUBLIC_API_BASE=http://localhost:8000/api
```

> **Note:** The API base URL is currently hardcoded in `app/page.tsx` as `http://localhost:8000/api`. Update it there if your backend runs on a different port.

### 4. Start the Development Server

```bash
npm run dev
```

The app will be available at `http://localhost:3000`.

---

## 🔑 API Keys Guide

| Provider | Where to Get | Notes |
|----------|-------------|-------|
| **OpenAI** | [platform.openai.com](https://platform.openai.com/api-keys) | GPT-3.5 Turbo (free tier). Auto-upgrades to GPT-4o-mini for image uploads |
| **Claude** | [console.anthropic.com](https://console.anthropic.com/) | Claude 3.5 Sonnet. Best for long documents and code |
| **Gemini** | [aistudio.google.com](https://aistudio.google.com/app/apikey) | Gemini 1.5 Pro. Free tier available |
| **Ollama** | No key needed | Fully local and private. Requires Ollama installed |

---

## ✨ Features

- 🔄 **Multi-provider switching** — Switch between OpenAI, Claude, Gemini, and Ollama from the header
- ⚡ **Real-time streaming** — SSE-based responses stream token by token
- 🖼️ **Image uploads** — Attach images for vision-capable models to analyze
- 📄 **PDF uploads** — Upload PDFs; text is extracted and injected as AI context
- 🧠 **Persistent chat history** — All conversations saved to MySQL
- 🗑️ **Delete chats** — With shadcn AlertDialog confirmation + Sonner toast feedback
- 💡 **Provider badge** — See which model generated each response
- 🎨 **Code highlighting** — Markdown rendering with VS Code Dark+ syntax theme
- 📋 **Copy & Retry** — Copy any response or retry with a single click
- 🔁 **Ollama fallback** — Automatically falls back to OpenAI if Ollama is unavailable
- 🪟 **Context window** — Sliding 20-message window with system prompt for better memory

---

## 🏗️ Architecture Overview

```
Browser (Next.js)
    │
    │  FormData (text + images + PDFs)
    ▼
Laravel API (SSE Stream)
    │
    ├── Saves user message to MySQL (original text only)
    ├── Extracts PDF text (PHP native, no library)
    ├── Builds AI context (system prompt + sliding window)
    │
    ├── AIFactory::make($provider)
    │       ├── OpenAIService    → api.openai.com
    │       ├── ClaudeService    → api.anthropic.com
    │       ├── GeminiService    → api.openai.com (Gemini endpoint)
    │       └── OllamaService    → localhost:11434 (local)
    │
    ├── Streams chunks → SSE → browser
    └── Saves assistant reply to MySQL (after stream ends)
```

---

## 🧪 Running Both Services

Open two terminal windows:

**Terminal 1 — Backend:**
```bash
cd chatobot-backend
php artisan serve --port=8000
```

**Terminal 2 — Frontend:**
```bash
cd chat-app
npm run dev
```

Then open [http://localhost:3000](http://localhost:3000).

---

## 🐛 Troubleshooting

### Streaming not working
- Ensure your web server / proxy does not buffer SSE responses.
- Add `X-Accel-Buffering: no` header (already set in `MessageController`).
- For nginx, add `proxy_buffering off;` to your location block.

### Ollama not responding
- Make sure `ollama serve` is running in a terminal.
- Verify the model is pulled: `ollama list`
- Test directly: `curl http://localhost:11434/api/tags`
- Check `OLLAMA_URL` and `OLLAMA_MODEL` in `.env`

### Image/PDF uploads failing
- Check that `public/storage` symlink exists: `ls -la public/storage`
- Ensure `storage/app/public/attachments` directory is writable: `chmod -R 775 storage`

### MySQL connection errors
- Verify credentials in `.env`
- Ensure the database exists: `CREATE DATABASE nexus_ai;`
- Run migrations: `php artisan migrate`

### PHP version errors
- This project requires **PHP 8.4+**
- Check with: `php --version`
- You may need to use `php8.4 artisan ...` explicitly on systems with multiple PHP versions

---

## 📦 Adding a New AI Provider

1. Create `app/Services/AI/YourProviderService.php` implementing `AIServiceInterface`
2. Add the provider to `AIFactory::make()` in `AIFactory.php`
3. Add the `SelectItem` in the frontend provider dropdown (`app/page.tsx`)
4. Add the API key to `.env` and `.env.example`

---

## 📄 License

MIT — free to use, modify, and distribute.
