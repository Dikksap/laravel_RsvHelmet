#!/bin/bash
set -e

# ==========================================================
# Deployment Script for RSV Helmet Laravel Production
# ==========================================================

COMPOSE_FILE="docker-compose.prod.yml"

echo "=========================================="
echo " Starting RSV Helmet Deployment..."
echo "=========================================="

# Check if .env exists
if [ ! -f .env ]; then
    echo "ERROR: File .env belum dibuat! Salin .env.example atau buat .env terlebih dahulu."
    exit 1
fi

echo "1. Pulling latest image from GHCR..."
docker compose -f $COMPOSE_FILE pull app

echo "2. Starting / updating containers..."
docker compose -f $COMPOSE_FILE up -d --remove-orphans

echo "3. Waiting for container to be ready..."
sleep 5

echo "4. Running post-deployment optimizations..."
docker compose -f $COMPOSE_FILE exec -T app php artisan config:cache
docker compose -f $COMPOSE_FILE exec -T app php artisan route:cache
docker compose -f $COMPOSE_FILE exec -T app php artisan view:cache
docker compose -f $COMPOSE_FILE exec -T app php artisan storage:link || true

echo "=========================================="
echo " Deployment Successfully Finished!"
echo " URL: http://192.168.1.168:8001"
echo "=========================================="
