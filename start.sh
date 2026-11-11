#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/logs storage/backups storage/exports storage/uploads/{receipts,employees,documents,logos}

# Stop existing instances if running
./stop.sh >/dev/null 2>&1 || true

# Start Python FastAPI Microservice on 127.0.0.1:8001
if [ -f "analytics_service/.venv/bin/uvicorn" ]; then
    echo "Starting Python FastAPI Analytics Service on http://127.0.0.1:8001 ..."
    nohup analytics_service/.venv/bin/uvicorn app.main:app \
        --app-dir analytics_service \
        --host 127.0.0.1 --port 8001 \
        </dev/null >storage/logs/fastapi.log 2>&1 &
    echo $! > storage/logs/fastapi.pid
fi

# Start Native PHP Server on 127.0.0.1:8080
echo "Starting Native PHP Web Server on http://127.0.0.1:8080 ..."
nohup php -S 127.0.0.1:8080 -t public public/router.php \
    </dev/null >storage/logs/php.log 2>&1 &
echo $! > storage/logs/php.pid

sleep 2
echo "SmartCMS is running!"
echo "  Web App:  http://127.0.0.1:8080"
echo "  FastAPI:  http://127.0.0.1:8001/docs"
