#!/usr/bin/env bash
# rebuild.sh — Rebuild and restart the Nexus AI frontend
# Usage: ./rebuild.sh

set -e

FRONTEND_DIR="$(cd "$(dirname "$0")/chat-app" && pwd)"

NPM=/home/atiq/.nvm/versions/node/v24.11.0/bin/npm

echo "🔨 Building Next.js frontend..."
cd "$FRONTEND_DIR"
$NPM run build

echo "♻️  Restarting nexusai-frontend service..."
systemctl --user restart nexusai-frontend

echo "✅ Done! Frontend is live at http://nexusai.test"
