#!/bin/bash

# ---DYNAMIC INPUT WITH DEFAULT ---
# $1 is the first argument (Model Name)
# $2 is the second argument (Path Modelfile)
MODEL_NAME=${1:-"default-chat"}
MODELFILE_PATH=${2:-"./asisten-pribadi/Modelfile-Default"}

echo "------------------------------------------------"
echo "🚀 PROSES UPDATE & WARM-UP"
echo "------------------------------------------------"
echo "🔹 Nama Model : $MODEL_NAME"
echo "🔹 Modelfile  : $MODELFILE_PATH"

# Check whether the Modelfile file exists
if [ ! -f "$MODELFILE_PATH" ]; then
    echo "❌ ERROR: File '$MODELFILE_PATH' tidak ditemukan!"
    exit 1
fi

#1. Update Ollama's Modelfile
echo "📦 Mengupdate model \"$MODEL_NAME\" di Ollama..."
if ollama create "$MODEL_NAME" -f "$MODELFILE_PATH"; then
    echo "✅ Create Model Berhasil."
else
    echo "❌ Gagal mengupdate Modelfile."
    exit 1
fi

# 2. Clean RAM & Reload (Crucial for 3GB RAM)
echo "🧹 Membersihkan RAM & Restarting Ollama..."
sudo systemctl daemon-reload
sudo systemctl restart ollama

# 3. Pause i5 CPU Stabilization
echo "⏳ Menunggu sistem stabil (5 detik)..."
sleep 5

# 4. WARM-UP PROCESS (Warming Up)
echo "🔥 Melakukan Warm-up ke RAM..."
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST http://localhost:11434/api/generate -d "{
  \"model\": \"$MODEL_NAME\",
  \"prompt\": \"halo\",
  \"stream\": false
}")

HTTP_STATUS=$(echo "$RESPONSE" | tail -n 1)

if [ "$HTTP_STATUS" -eq 200 ]; then
    echo "✨ Model '$MODEL_NAME' sekarang sudah hangat di RAM!"
    echo "------------------------------------------------"
    echo "✅ SIAP DIGUNAKAN DI DASHBOARD"
else
    echo "⚠️ Warm-up mungkin gagal (HTTP $HTTP_STATUS). Cek 'ollama serve'."
fi