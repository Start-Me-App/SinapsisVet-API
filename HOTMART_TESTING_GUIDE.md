# Guía Completa: Crear y Probar Pagos con Hotmart

Esta guía te explica paso a paso cómo configurar, crear y probar pagos con Hotmart en tu plataforma.

## Tabla de Contenidos

1. [Cómo Funciona Hotmart](#cómo-funciona-hotmart)
2. [Configuración Inicial](#configuración-inicial)
3. [Crear un Producto en Hotmart](#crear-un-producto-en-hotmart)
4. [Configurar Webhook](#configurar-webhook)
5. [Mapear Productos con Cursos](#mapear-productos-con-cursos)
6. [Probar en Ambiente Sandbox](#probar-en-ambiente-sandbox)
7. [Probar en Producción](#probar-en-producción)
8. [Simular Webhook Localmente](#simular-webhook-localmente)
9. [Troubleshooting](#troubleshooting)

---

## Cómo Funciona Hotmart

Hotmart funciona diferente a MercadoPago o Stripe:

### **Flujo de MercadoPago/Stripe** (tú controlas el checkout):
```
1. Usuario selecciona curso en tu frontend
2. Tu backend crea una "preferencia de pago" con la API
3. Usuario es redirigido a checkout de MercadoPago/Stripe
4. Pagan y retornan a tu sitio
5. Webhook te notifica el pago
```

### **Flujo de Hotmart** (Hotmart controla el checkout):
```
1. Creas un producto en Hotmart (curso, membresía, etc.)
2. Hotmart te da un link de venta: https://pay.hotmart.com/ABC123
3. Usuario hace clic en el link → Va directo a checkout de Hotmart
4. Pagan en Hotmart
5. Webhook te notifica el pago
6. Tu API crea automáticamente la inscripción
```

**📌 Importante:** No puedes crear pagos desde tu backend como con MP/Stripe. Los usuarios compran directamente en Hotmart.

---

## Configuración Inicial

### Paso 1: Ejecutar la Migración

Primero, agrega el campo `hotmart_product_id` a la tabla de cursos:

```bash
cd app
php artisan migrate
```

Esto ejecutará la migración `2026_02_09_200000_add_hotmart_product_id_to_courses_table.php`

### Paso 2: Obtener Credenciales de Hotmart

1. Ve a [Hotmart API Tools](https://app-vlc.hotmart.com/tools/api)
2. Click en **"Crear nueva credencial"**
3. Copia:
   - `CLIENT_ID`
   - `CLIENT_SECRET`

### Paso 3: Generar BASIC_AUTH

Tienes dos opciones:

**Opción A: Usando el endpoint de tu API**

```bash
curl http://localhost:8000/api/hotmart/generate-basic-auth
```

Respuesta:
```json
{
  "basic_auth": "dHVfY2xpZW50X2lkOnR1X2NsaWVudF9zZWNyZXQ=",
  "instruction": "Agrega esta línea a tu .env: HOTMART_BASIC_AUTH=dHVfY2xpZW50X2lkOnR1X2NsaWVudF9zZWNyZXQ="
}
```

**Opción B: En tu terminal**

```bash
echo -n "TU_CLIENT_ID:TU_CLIENT_SECRET" | base64
```

### Paso 4: Configurar `.env`

Agrega las credenciales a tu archivo `.env`:

```env
HOTMART_CLIENT_ID=abc123xyz
HOTMART_CLIENT_SECRET=secret456def
HOTMART_BASIC_AUTH=dHVfY2xpZW50X2lkOnR1X2NsaWVudF9zZWNyZXQ=
HOTMART_HOTTOK=
HOTMART_API_URL=https://developers.hotmart.com
HOTMART_WEBHOOK_PATH=/api/hotmart/webhook
```

### Paso 5: Probar la Conexión

```bash
curl http://localhost:8000/api/hotmart/test
```

✅ **Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Conexión exitosa con Hotmart",
  "data": {
    "connected": true
  }
}
```

❌ **Si falla:**
```bash
# Ver diagnóstico completo
curl http://localhost:8000/api/hotmart/diagnose
```

---

## Crear un Producto en Hotmart

### Paso 1: Crear el Producto

1. Ve a [Hotmart Dashboard](https://app-vlc.hotmart.com/)
2. Click en **"Productos"** → **"Crear nuevo producto"**
3. Completa la información:
   - **Nombre del producto:** "Curso de Veterinaria Avanzada"
   - **Tipo:** Curso en línea
   - **Precio:** Ej. $99 USD
   - **Descripción:** (describe tu curso)
4. Click en **"Guardar"**

### Paso 2: Obtener el Product ID

Después de crear el producto:

1. Ve a la sección **"Configuración"** del producto
2. Encontrarás el **Product ID** (un número o código)
3. **Cópialo** - lo necesitarás para el siguiente paso

Ejemplo: `12345678` o `PROD-ABC-123`

### Paso 3: Obtener el Link de Pago

1. En la página del producto, ve a **"Checkout"**
2. Copia el **Link de Pago**
3. Ejemplo: `https://pay.hotmart.com/A12345678B`

---

## Configurar Webhook

### Paso 1: Exponer tu API Localmente (para testing)

Si estás desarrollando localmente, necesitas exponer tu API:

**Opción A: Usar ngrok**

```bash
# Instalar ngrok
brew install ngrok  # macOS
# o descargar desde https://ngrok.com/

# Exponer tu puerto local
ngrok http 8000
```

Te dará una URL como: `https://abc123.ngrok.io`

**Opción B: Usar tu servidor de staging**

Si tienes un servidor de desarrollo, usa esa URL directamente.

### Paso 2: Configurar el Webhook en Hotmart

1. En Hotmart, ve a tu producto
2. Click en **"Configuración"** → **"Integraciones"** → **"Webhooks"**
3. Click en **"Adicionar webhook"**
4. Completa:
   - **URL:** `https://tu-dominio.com/api/hotmart/webhook`
   - **Versión:** v2 (recomendado)
5. Selecciona los eventos:
   - ✅ `PURCHASE_COMPLETE`
   - ✅ `PURCHASE_APPROVED`
   - ✅ `PURCHASE_CANCELED`
   - ✅ `PURCHASE_REFUNDED`
   - ✅ `SUBSCRIPTION_CANCELLATION`
6. Click en **"Guardar"**

### Paso 3: Probar el Webhook

Hotmart tiene una función de **"Enviar webhook de prueba"**:

1. En la configuración del webhook, click en **"Testar"**
2. Selecciona el evento `PURCHASE_APPROVED`
3. Click en **"Enviar"**

Verifica en tus logs:

```bash
# Ver logs de Laravel
tail -f app/storage/logs/laravel.log
```

Deberías ver:
```
[2026-02-09 20:00:00] local.INFO: Webhook de Hotmart recibido {"event":"PURCHASE_APPROVED"}
```

---

## Mapear Productos con Cursos

### Opción 1: Editar Curso Existente (Recomendado)

Si ya tienes un curso en tu base de datos:

```sql
-- Actualizar curso con hotmart_product_id
UPDATE courses
SET hotmart_product_id = '12345678'
WHERE id = 1;
```

O desde tu admin panel, agrega un campo para editar el `hotmart_product_id`.

### Opción 2: Crear Curso Nuevo con Hotmart Product ID

Al crear un curso nuevo, asegúrate de incluir el campo `hotmart_product_id`:

```json
POST /api/admin/courses
{
  "title": "Curso de Veterinaria",
  "price_usd": 99,
  "hotmart_product_id": "12345678",
  ...
}
```

### Verificar el Mapeo

```sql
-- Ver cursos con Hotmart configurado
SELECT id, title, hotmart_product_id
FROM courses
WHERE hotmart_product_id IS NOT NULL;
```

---

## Probar en Ambiente Sandbox

### Hotmart Sandbox

⚠️ **Nota:** Hotmart **NO** tiene un sandbox oficial como MercadoPago o Stripe.

Para probar sin hacer pagos reales, tienes estas opciones:

### Opción 1: Crear Producto de $0 (Gratis)

1. Crea un producto en Hotmart
2. Configura precio: **$0.00** o **Gratis**
3. Prueba el flujo completo sin pagar

### Opción 2: Hacer Compra Real y Reembolsar

1. Crea un producto con precio bajo ($1 USD)
2. Compra con tu propia tarjeta
3. Verifica que funcione
4. Reembolsa desde el panel de Hotmart

### Opción 3: Simular Webhooks Manualmente

Ver sección [Simular Webhook Localmente](#simular-webhook-localmente)

---

## Probar en Producción

### Paso 1: Compartir el Link de Pago

Comparte el link de pago de Hotmart con tus usuarios:

```
https://pay.hotmart.com/A12345678B
```

Puedes:
- Ponerlo en un botón en tu frontend: "Comprar Curso"
- Enviarlo por email
- Compartirlo en redes sociales

### Paso 2: Monitorear Webhooks

Cuando alguien compre, Hotmart enviará un webhook. Verifica que se procese correctamente:

```bash
# Ver logs en tiempo real
tail -f app/storage/logs/laravel.log | grep Hotmart
```

### Paso 3: Verificar en la Base de Datos

```sql
-- Ver eventos de Hotmart recibidos
SELECT * FROM hotmart_events ORDER BY created_at DESC LIMIT 10;

-- Ver órdenes creadas desde Hotmart
SELECT * FROM orders WHERE payment_method_id = 5 ORDER BY date_created DESC;

-- Ver inscripciones creadas automáticamente
SELECT i.*, u.email, c.title
FROM inscriptions i
JOIN users u ON i.user_id = u.id
JOIN courses c ON i.course_id = c.id
JOIN orders o ON o.user_id = u.id
WHERE o.payment_method_id = 5
ORDER BY i.id DESC;
```

---

## Simular Webhook Localmente

Para probar sin necesidad de crear un producto real o hacer una compra:

### Paso 1: Crear un Script de Prueba

Crea un archivo `test-hotmart-webhook.sh`:

```bash
#!/bin/bash

curl -X POST http://localhost:8000/api/hotmart/webhook \
  -H "Content-Type: application/json" \
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
```

### Paso 2: Ejecutar el Test

```bash
chmod +x test-hotmart-webhook.sh
./test-hotmart-webhook.sh
```

### Paso 3: Verificar Resultado

✅ **Éxito:**
```json
{
  "success": true,
  "message": "Compra aprobada procesada"
}
```

Verifica en la base de datos:
```sql
SELECT * FROM hotmart_events WHERE transaction_id = 'HP12345678901234';
SELECT * FROM orders WHERE external_reference = 'HP12345678901234';
```

---

## Troubleshooting

### ❌ Error: "No se encontró curso para el product_id"

**Causa:** No has mapeado el `hotmart_product_id` con tu curso.

**Solución:**
```sql
UPDATE courses
SET hotmart_product_id = 'TU_PRODUCT_ID_DE_HOTMART'
WHERE id = TU_COURSE_ID;
```

---

### ❌ Error: "Webhook inválido"

**Causa:** El webhook no tiene la estructura esperada.

**Solución:**
1. Verifica que estés usando la versión v2 del webhook de Hotmart
2. Revisa los logs para ver qué está llegando:
```bash
tail -f app/storage/logs/laravel.log | grep "Webhook de Hotmart"
```

---

### ❌ Error: "Error al obtener el access token"

**Causa:** Credenciales incorrectas o `BASIC_AUTH` mal generado.

**Solución:**
```bash
# Regenerar BASIC_AUTH
curl http://localhost:8000/api/hotmart/generate-basic-auth

# Verificar diagnóstico completo
curl http://localhost:8000/api/hotmart/diagnose
```

---

### ❌ Webhook no llega a tu servidor

**Causa:** Firewall, servidor caído, o URL incorrecta.

**Solución:**
1. Verifica que tu servidor esté accesible públicamente:
```bash
curl -I https://tu-dominio.com/api/hotmart/webhook
# Debería retornar 405 Method Not Allowed (porque es POST only)
```

2. Si estás en local, usa ngrok:
```bash
ngrok http 8000
# Usa la URL de ngrok en la configuración del webhook de Hotmart
```

3. Verifica los logs de Hotmart:
   - En el dashboard de Hotmart, ve a "Webhooks"
   - Click en "Ver logs"
   - Verifica si hay errores

---

### ❌ Usuario no se crea automáticamente

**Causa:** El email ya existe pero con diferente información.

**Solución:**
- Los usuarios se crean automáticamente si no existen
- Si el email ya existe, se usa el usuario existente
- Verifica:
```sql
SELECT * FROM users WHERE email = 'test@example.com';
```

---

### ❌ Orden se duplica

**Causa:** Hotmart puede enviar el mismo webhook múltiples veces.

**Solución:**
El código ya maneja esto:
- Verifica por `external_reference` (transaction_id)
- Si ya existe, no crea duplicado

---

## Endpoints Útiles

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/hotmart/webhook` | POST | Recibir webhooks de Hotmart |
| `/api/hotmart/test` | GET | Probar conexión con Hotmart |
| `/api/hotmart/diagnose` | GET | Diagnóstico completo |
| `/api/hotmart/generate-basic-auth` | GET | Generar token BASIC_AUTH |
| `/api/hotmart/subscription/{code}` | GET | Ver detalles de suscripción |
| `/api/hotmart/sales` | GET | Historial de ventas |

---

## Flujo Completo Ejemplo

### Escenario: Usuario compra "Curso de Veterinaria"

1. **Usuario** hace clic en botón "Comprar Curso" en tu frontend
2. **Frontend** redirige a: `https://pay.hotmart.com/A12345678B`
3. **Usuario** completa el pago en Hotmart
4. **Hotmart** procesa el pago
5. **Hotmart** envía webhook a: `https://tu-dominio.com/api/hotmart/webhook`
6. **Tu API** recibe el webhook:
   ```json
   {
     "event": "PURCHASE_APPROVED",
     "data": {
       "product": {"id": "12345678"},
       "buyer": {"email": "juan@example.com"},
       "purchase": {"transaction": "HP999"}
     }
   }
   ```
7. **Tu API** procesa automáticamente:
   - ✅ Busca/crea usuario con email `juan@example.com`
   - ✅ Busca curso con `hotmart_product_id = 12345678`
   - ✅ Crea orden con `external_reference = HP999`
   - ✅ Crea inscripción del usuario al curso
   - ✅ Registra evento en tabla `hotmart_events`
8. **Usuario** puede acceder al curso inmediatamente

---

## Próximos Pasos

1. ✅ Ejecutar migración: `php artisan migrate`
2. ✅ Configurar credenciales en `.env`
3. ✅ Probar conexión: `/api/hotmart/test`
4. ✅ Crear producto en Hotmart
5. ✅ Mapear `hotmart_product_id` en tus cursos
6. ✅ Configurar webhook en Hotmart
7. ✅ Probar webhook con el simulador de Hotmart
8. ✅ Hacer compra real de prueba
9. ✅ Monitorear logs y base de datos
10. ✅ Configurar en producción

---

## Notas Importantes

🔴 **Diferencias con MercadoPago/Stripe:**
- No puedes crear pagos desde tu backend
- Los usuarios SIEMPRE van a checkout de Hotmart
- No hay sandbox oficial
- Hotmart controla todo el flujo de pago

🟢 **Ventajas de Hotmart:**
- No necesitas certificado SSL para el checkout (Hotmart lo maneja)
- Hotmart se encarga de impuestos y compliance en múltiples países
- Gestión de afiliados incluida
- Dashboard de ventas completo

🟡 **Desventajas:**
- Menos control sobre el checkout
- Comisiones más altas (típicamente 9.9% + tarifa de gateway)
- No puedes personalizar tanto la experiencia de pago

---

**Última actualización:** 2026-02-09
