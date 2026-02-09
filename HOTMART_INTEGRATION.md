# Integración con Hotmart API

Esta documentación describe cómo funciona la integración con Hotmart en la API de SinapsisVet.

## Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Configuración](#configuración)
3. [Arquitectura](#arquitectura)
4. [Webhooks](#webhooks)
5. [Endpoints de la API](#endpoints-de-la-api)
6. [Base de Datos](#base-de-datos)
7. [Flujo de Compra](#flujo-de-compra)
8. [Mapeo de Productos](#mapeo-de-productos)
9. [Pruebas](#pruebas)
10. [Solución de Problemas](#solución-de-problemas)

## Descripción General

La integración con Hotmart permite:

- Recibir notificaciones automáticas de compras, cancelaciones y reembolsos
- Crear órdenes automáticamente cuando se aprueba una compra
- Inscribir usuarios en cursos automáticamente
- Registrar y auditar todos los eventos de Hotmart
- Consultar información de suscripciones y ventas

## Configuración

### 1. Obtener Credenciales

Accede a [Hotmart Developer Portal](https://app-vlc.hotmart.com/tools/api) y crea tus credenciales:

1. Ve a **Herramientas** → **API**
2. Haz clic en **Nueva credencial**
3. Guarda los siguientes datos:
   - **Client ID**
   - **Client Secret**
   - **Basic Auth** (código base64 para autenticación)
   - **Hottok** (token para validar webhooks)

### 2. Configurar Variables de Entorno

Agrega estas variables a tu archivo `.env`:

```env
# Hotmart API Configuration
HOTMART_CLIENT_ID=tu_client_id_aqui
HOTMART_CLIENT_SECRET=tu_client_secret_aqui
HOTMART_BASIC_AUTH=tu_basic_auth_aqui
HOTMART_HOTTOK=tu_hottok_aqui
HOTMART_API_URL=https://developers.hotmart.com
HOTMART_WEBHOOK_PATH=/api/hotmart/webhook
```

### 3. Configurar Webhook en Hotmart

1. Ve a **Herramientas** → **Webhook**
2. Haz clic en **+ Registrar Webhook**
3. Configura:
   - **URL de entrega**: `https://tu-dominio.com/api/hotmart/webhook`
   - **Eventos**: Selecciona los eventos que quieres recibir:
     - `PURCHASE_COMPLETE`
     - `PURCHASE_APPROVED`
     - `PURCHASE_CANCELED`
     - `PURCHASE_REFUNDED`
     - `PURCHASE_CHARGEBACK`
     - `SUBSCRIPTION_CANCELLATION`
     - `SUBSCRIPTION_REACTIVATION`
4. Guarda la configuración

### 4. Ejecutar Migración

```bash
php artisan migrate
```

Esto creará la tabla `hotmart_events` para registrar todos los eventos.

## Arquitectura

### Archivos Principales

```
app/
├── Support/
│   └── Hotmart.php                    # Clase principal de integración
├── Http/Controllers/
│   └── HotmartController.php          # Controlador de endpoints
├── Models/
│   └── HotmartEvent.php               # Modelo para eventos
├── database/migrations/
│   └── 2026_02_09_000000_create_hotmart_events_table.php
└── routes/
    └── api.php                        # Rutas API
```

### Clase `Hotmart` ([app/Support/Hotmart.php](app/app/Support/Hotmart.php))

Responsabilidades:
- Autenticación OAuth 2.0
- Validación de webhooks
- Consultas a la API de Hotmart
- Procesamiento de eventos
- Extracción de datos de compras

### Controlador `HotmartController` ([app/Http/Controllers/HotmartController.php](app/Http/Controllers/HotmartController.php))

Responsabilidades:
- Recibir webhooks
- Crear órdenes e inscripciones
- Exponer endpoints REST
- Registrar eventos en la base de datos

## Webhooks

### Endpoint del Webhook

```
POST /api/hotmart/webhook
```

**Headers requeridos:**
- `X-Hotmart-Hottok`: Token de autenticación enviado por Hotmart

### Eventos Soportados

| Evento | Descripción | Acción Automática |
|--------|-------------|-------------------|
| `PURCHASE_COMPLETE` | Compra completada | Crear orden + inscripción |
| `PURCHASE_APPROVED` | Compra aprobada | Crear orden + inscripción |
| `PURCHASE_CANCELED` | Compra cancelada | Registrar evento |
| `PURCHASE_REFUNDED` | Reembolso procesado | Registrar evento |
| `PURCHASE_CHARGEBACK` | Contracargo | Registrar evento |
| `SUBSCRIPTION_CANCELLATION` | Cancelación de suscripción | Registrar evento |
| `SUBSCRIPTION_REACTIVATION` | Reactivación de suscripción | Registrar evento |

### Ejemplo de Payload del Webhook

```json
{
  "event": "PURCHASE_APPROVED",
  "data": {
    "buyer": {
      "email": "comprador@example.com",
      "name": "Juan Pérez",
      "checkout_phone": "+5491112345678"
    },
    "product": {
      "id": "12345",
      "name": "Curso de Veterinaria Avanzada"
    },
    "purchase": {
      "transaction": "HP12345678901234",
      "status": "APPROVED",
      "approved_date": "2026-02-09T10:30:00Z",
      "price": {
        "value": 99.99,
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
}
```

## Endpoints de la API

### 1. Webhook (Recibir Notificaciones)

```http
POST /api/hotmart/webhook
```

**Uso:** Hotmart envía notificaciones automáticamente a este endpoint.

---

### 2. Probar Conexión

```http
GET /api/hotmart/test
```

**Descripción:** Verifica que las credenciales sean correctas y que la API pueda conectarse a Hotmart.

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Conexión exitosa con Hotmart",
  "data": {
    "connected": true
  }
}
```

---

### 3. Obtener Suscripción

```http
GET /api/hotmart/subscription/{subscriber_code}
```

**Parámetros:**
- `subscriber_code`: Código del suscriptor en Hotmart

**Respuesta:**
```json
{
  "success": true,
  "message": "Suscripción obtenida con éxito",
  "data": {
    "subscriber_code": "SUB123456",
    "status": "ACTIVE",
    "product_id": "12345",
    ...
  }
}
```

---

### 4. Obtener Historial de Ventas

```http
GET /api/hotmart/sales?start_date=2026-01-01&end_date=2026-02-09
```

**Parámetros de Query:**
- `transaction_status`: Estado de la transacción (APPROVED, CANCELED, etc.)
- `start_date`: Fecha de inicio (formato: YYYY-MM-DD)
- `end_date`: Fecha de fin (formato: YYYY-MM-DD)
- `product_id`: ID del producto
- `buyer_email`: Email del comprador
- `max_results`: Cantidad máxima de resultados
- `page_token`: Token de paginación

**Respuesta:**
```json
{
  "success": true,
  "message": "Historial de ventas obtenido con éxito",
  "data": {
    "items": [...],
    "page_info": {...}
  }
}
```

## Base de Datos

### Tabla `hotmart_events`

Registra todos los eventos recibidos de Hotmart para auditoría y debugging.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | ID autoincremental |
| `event_type` | varchar(100) | Tipo de evento (PURCHASE_APPROVED, etc.) |
| `transaction_id` | varchar(255) | ID de transacción de Hotmart |
| `product_id` | varchar(255) | ID del producto en Hotmart |
| `product_name` | varchar(500) | Nombre del producto |
| `buyer_email` | varchar(255) | Email del comprador |
| `buyer_name` | varchar(255) | Nombre del comprador |
| `status` | varchar(50) | Estado de la transacción |
| `price_value` | decimal(10,2) | Valor de la compra |
| `price_currency` | varchar(10) | Moneda (USD, BRL, etc.) |
| `commission_value` | decimal(10,2) | Valor de la comisión |
| `approved_date` | timestamp | Fecha de aprobación |
| `raw_data` | json | Payload completo del webhook |
| `order_id` | bigint | ID de la orden creada (nullable) |
| `processed` | boolean | Si el evento fue procesado |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp | Fecha de actualización |

### Relación con Órdenes

- Una orden puede tener un evento de Hotmart asociado
- `order.payment_method_id = 5` indica que es un pago de Hotmart
- `order.external_reference` almacena el `transaction_id` de Hotmart

## Flujo de Compra

### 1. Cliente compra en Hotmart

El cliente realiza la compra en la plataforma de Hotmart.

### 2. Hotmart procesa el pago

Hotmart procesa el pago y aprueba/rechaza la transacción.

### 3. Webhook enviado

Hotmart envía un webhook `PURCHASE_APPROVED` a tu API.

### 4. Validación del webhook

```php
// Validar hottok
$hottok = $request->header('X-Hotmart-Hottok');
if (!Hotmart::validateWebhookToken($hottok)) {
    return response()->json(['error' => 'Token inválido'], 401);
}
```

### 5. Registro del evento

Se crea un registro en `hotmart_events` con toda la información del webhook.

### 6. Procesamiento automático

Si el evento es `PURCHASE_APPROVED` o `PURCHASE_COMPLETE`:

1. **Buscar o crear usuario**
   ```php
   $user = User::where('email', $orderData['buyer_email'])->first();
   if (!$user) {
       // Crear usuario nuevo
   }
   ```

2. **Verificar duplicados**
   ```php
   $existingOrder = Order::where('external_reference', $transaction_id)->first();
   ```

3. **Crear orden**
   ```php
   $order = new Order();
   $order->user_id = $user->id;
   $order->status = 'paid';
   $order->payment_method_id = 5; // Hotmart
   $order->external_reference = $transaction_id;
   // ...
   $order->save();
   ```

4. **Crear detalle de orden**
   ```php
   $orderDetail = new OrderDetail();
   $orderDetail->order_id = $order->id;
   $orderDetail->course_id = $course->id;
   $orderDetail->price = $price;
   $orderDetail->save();
   ```

5. **Crear inscripción**
   ```php
   $inscription = new Inscriptions();
   $inscription->user_id = $user->id;
   $inscription->course_id = $course->id;
   $inscription->save();
   ```

6. **Marcar evento como procesado**
   ```php
   $hotmartEvent->order_id = $order->id;
   $hotmartEvent->processed = true;
   $hotmartEvent->save();
   ```

## Mapeo de Productos

**IMPORTANTE:** Debes configurar el mapeo entre los `product_id` de Hotmart y los `course_id` de tu sistema.

### Opción 1: Agregar campo a la tabla `courses`

```sql
ALTER TABLE courses ADD COLUMN hotmart_product_id VARCHAR(255) NULL;
```

Luego modifica el método `findCourseByHotmartProductId`:

```php
private function findCourseByHotmartProductId(?string $hotmartProductId): ?Courses
{
    if (!$hotmartProductId) {
        return null;
    }

    return Courses::where('hotmart_product_id', $hotmartProductId)->first();
}
```

### Opción 2: Configuración en código

Edita el método `findCourseByHotmartProductId` en [HotmartController.php](app/app/Http/Controllers/HotmartController.php):

```php
private function findCourseByHotmartProductId(?string $hotmartProductId): ?Courses
{
    $productMapping = [
        '12345' => 1,  // Hotmart product 12345 → Course ID 1
        '67890' => 2,  // Hotmart product 67890 → Course ID 2
        // ... agregar más mapeos
    ];

    if (isset($productMapping[$hotmartProductId])) {
        return Courses::find($productMapping[$hotmartProductId]);
    }

    return null;
}
```

### Opción 3: Tabla de mapeo dedicada

Crea una tabla `hotmart_product_mappings`:

```sql
CREATE TABLE hotmart_product_mappings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    hotmart_product_id VARCHAR(255) NOT NULL,
    course_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);
```

## Pruebas

### 1. Probar Conexión

```bash
curl -X GET https://tu-dominio.com/api/hotmart/test
```

### 2. Simular Webhook Local

```bash
curl -X POST https://tu-dominio.com/api/hotmart/webhook \
  -H "Content-Type: application/json" \
  -H "X-Hotmart-Hottok: tu_hottok_aqui" \
  -d '{
    "event": "PURCHASE_APPROVED",
    "data": {
      "buyer": {
        "email": "test@example.com",
        "name": "Test User"
      },
      "product": {
        "id": "12345",
        "name": "Test Course"
      },
      "purchase": {
        "transaction": "TEST123",
        "status": "APPROVED",
        "price": {
          "value": 99.99,
          "currency_code": "USD"
        }
      }
    }
  }'
```

### 3. Verificar Logs

Revisa los logs de Laravel para ver el procesamiento:

```bash
tail -f storage/logs/laravel.log
```

## Solución de Problemas

### Error: "Token de autenticación inválido"

**Causa:** El `HOTMART_HOTTOK` en `.env` no coincide con el enviado por Hotmart.

**Solución:** Verifica que el valor en `.env` sea exactamente el mismo que aparece en el panel de Hotmart.

---

### Error: "No se pudieron extraer datos de la compra"

**Causa:** El formato del webhook de Hotmart cambió o está incompleto.

**Solución:** Revisa el campo `raw_data` en la tabla `hotmart_events` para ver el payload completo y ajusta el método `extractOrderData()`.

---

### No se crea la orden automáticamente

**Causa:** El mapeo de productos no está configurado.

**Solución:** Configura el mapeo entre `product_id` de Hotmart y `course_id` usando una de las opciones descritas en [Mapeo de Productos](#mapeo-de-productos).

---

### Webhook recibido pero no procesado

**Causa:** El evento quedó registrado pero `processed = false`.

**Solución:**
1. Revisa los logs: `tail -f storage/logs/laravel.log`
2. Verifica que el usuario se creó correctamente
3. Verifica que el mapeo de productos esté configurado
4. Procesa manualmente si es necesario

---

### Error: "Call to unknown function: str_random"

**Causa:** Función obsoleta en Laravel 8+.

**Solución:** Ya corregido en el código. Usa `\Illuminate\Support\Str::random()` en su lugar.

---

## Recursos Adicionales

- [Documentación oficial de Hotmart API](https://developers.hotmart.com/docs/es/)
- [Panel de desarrolladores de Hotmart](https://app-vlc.hotmart.com/tools/api)
- [Webhook de Hotmart - Guía](https://hotmart.com/en/blog/hotconnect-and-webhook)

## Método de Pago

Agrega Hotmart como método de pago en tu base de datos:

```sql
INSERT INTO payment_methods (id, name) VALUES (5, 'Hotmart');
```

## Notas Importantes

1. **Seguridad:** El `hottok` es crítico para la seguridad. Manténlo secreto y no lo compartas.

2. **Idempotencia:** Los webhooks pueden enviarse múltiples veces. El sistema verifica duplicados usando `external_reference`.

3. **Monedas:** Hotmart soporta múltiples monedas (USD, BRL, EUR, etc.). Asegúrate de manejarlas correctamente.

4. **Usuarios:** Si un usuario con el email ya existe, se usa. Si no, se crea automáticamente con una contraseña temporal.

5. **Auditoría:** Todos los eventos se registran en `hotmart_events` para auditoría, incluso si fallan.

6. **Logs:** Usa `storage/logs/laravel.log` para debugging y monitoreo de eventos.

---

**Última actualización:** 2026-02-09
