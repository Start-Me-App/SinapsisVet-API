#!/bin/bash

# Script para probar el webhook de Hotmart localmente
# Uso: ./test-hotmart-webhook.sh [URL]

# URL del webhook (por defecto localhost)
URL="${1:-http://localhost:8000/api/hotmart/webhook}"

echo "=========================================="
echo "Probando Webhook de Hotmart"
echo "URL: $URL"
echo "=========================================="
echo ""

# Test 1: PURCHASE_APPROVED
echo "📦 Test 1: PURCHASE_APPROVED (Compra Aprobada)"
echo ""
curl -X POST "$URL" \
  -H "Content-Type: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n" \
  -d '{
    "event": "PURCHASE_APPROVED",
    "data": {
      "product": {
        "id": "12345678",
        "name": "Curso de Veterinaria Avanzada"
      },
      "buyer": {
        "email": "test@example.com",
        "name": "María García",
        "checkout_phone": "+5491123456789"
      },
      "purchase": {
        "transaction": "HP12345678901234",
        "status": "approved",
        "approved_date": "2026-02-09T20:00:00Z",
        "price": {
          "value": 99.00,
          "currency_code": "USD"
        }
      },
      "commissions": [
        {
          "value": 10.00,
          "currency_code": "USD"
        }
      ]
    }
  }'

echo ""
echo "=========================================="
echo ""

# Test 2: PURCHASE_COMPLETE
echo "✅ Test 2: PURCHASE_COMPLETE (Compra Completa)"
echo ""
curl -X POST "$URL" \
  -H "Content-Type: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n" \
  -d '{
    "event": "PURCHASE_COMPLETE",
    "data": {
      "product": {
        "id": "12345678",
        "name": "Curso de Veterinaria Avanzada"
      },
      "buyer": {
        "email": "juan@example.com",
        "name": "Juan Pérez",
        "checkout_phone": "+5491198765432"
      },
      "purchase": {
        "transaction": "HP98765432109876",
        "status": "complete",
        "approved_date": "2026-02-09T21:00:00Z",
        "price": {
          "value": 150.00,
          "currency_code": "USD"
        }
      },
      "commissions": [
        {
          "value": 15.00,
          "currency_code": "USD"
        }
      ]
    }
  }'

echo ""
echo "=========================================="
echo ""

# Test 3: PURCHASE_CANCELED
echo "❌ Test 3: PURCHASE_CANCELED (Compra Cancelada)"
echo ""
curl -X POST "$URL" \
  -H "Content-Type: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n" \
  -d '{
    "event": "PURCHASE_CANCELED",
    "data": {
      "product": {
        "id": "12345678",
        "name": "Curso de Veterinaria Avanzada"
      },
      "buyer": {
        "email": "test@example.com",
        "name": "María García"
      },
      "purchase": {
        "transaction": "HP11111111111111",
        "status": "canceled"
      }
    }
  }'

echo ""
echo "=========================================="
echo "✨ Tests completados!"
echo ""
echo "Verifica los resultados en:"
echo "- Logs: tail -f app/storage/logs/laravel.log | grep Hotmart"
echo "- Base de datos: SELECT * FROM hotmart_events ORDER BY created_at DESC LIMIT 5;"
echo "=========================================="
