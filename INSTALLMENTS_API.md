# API de Gestión de Cuotas (Installments)

Documentación de endpoints relacionados con la gestión de cuotas de pago.

## Tabla de Contenidos

1. [Modelos](#modelos)
2. [Endpoints Existentes](#endpoints-existentes)
3. [Nuevo Endpoint: Actualizar Fecha de Vencimiento](#actualizar-fecha-de-vencimiento)
4. [Ejemplos de Uso](#ejemplos-de-uso)

---

## Modelos

### Installments (Cabecera de Cuotas)

Representa el plan de cuotas de una orden.

**Tabla:** `installments`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | ID del plan de cuotas |
| `order_id` | bigint | ID de la orden |
| `due_date` | date | Fecha de vencimiento final |
| `status` | varchar | Estado del plan (pending, paid) |
| `amount` | integer | Cantidad de cuotas |
| `date_created` | timestamp | Fecha de creación |
| `date_last_updated` | timestamp | Fecha de última actualización |

### InstallmentDetail (Detalle de Cuota Individual)

Representa cada cuota individual dentro del plan.

**Tabla:** `installment_detail`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | ID de la cuota |
| `installment_id` | bigint | ID del plan de cuotas |
| `installment_number` | integer | Número de cuota (1, 2, 3...) |
| `due_date` | date | **Fecha de vencimiento** |
| `paid_date` | timestamp | Fecha de pago (null si no pagada) |
| `url_payment` | varchar | URL del comprobante de pago |
| `paid` | boolean | Si está pagada (0 o 1) |
| `movement_id` | bigint | ID del movimiento asociado |

---

## Endpoints Existentes

### 1. Obtener Todas las Cuotas

```http
GET /api/admin/orders/installments/all?status=pending
```

**Query Parameters:**
- `status` (opcional): Filtrar por estado (`pending`, `paid`)

**Respuesta:**
```json
{
  "data": [
    {
      "id": 5,
      "order_id": 123,
      "amount": 6,
      "status": "pending",
      "next_due_date": "2026-03-15",
      "next_due_url_payment": "https://...",
      "order": {
        "user": {
          "name": "María García",
          "email": "maria@example.com"
        }
      },
      "installmentDetails": [...]
    }
  ]
}
```

---

### 2. Obtener Cuotas de una Orden

```http
GET /api/admin/orders/{order_id}/installments
```

**Respuesta:**
```json
{
  "data": [
    {
      "id": 5,
      "order_id": 123,
      "installmentDetails": [
        {
          "id": 45,
          "installment_number": 1,
          "due_date": "2026-02-15",
          "paid": true,
          "paid_date": "2026-02-14 10:30:00"
        },
        {
          "id": 46,
          "installment_number": 2,
          "due_date": "2026-03-15",
          "paid": false,
          "paid_date": null
        }
      ]
    }
  ]
}
```

---

### 3. Actualizar Cuota (Completo)

Actualiza múltiples campos de una cuota: URL de pago, estado de pago, **y opcionalmente la fecha**.

```http
PATCH /api/admin/orders/{installment_id}/update
```

**Body (JSON):**
```json
{
  "account_id": 1,
  "commission_percentage": 10,
  "url_payment": "https://example.com/comprobante.pdf",
  "paid": true,
  "due_date": "2026-03-20"
}
```

**Campos:**
- `account_id` (requerido): ID de la cuenta donde se registra el pago
- `commission_percentage` (opcional): Porcentaje de comisión
- `url_payment` (opcional): URL del comprobante
- `paid` (requerido): Estado de pago (true/false)
- `due_date` (opcional): **Nueva fecha de vencimiento**

**Comportamiento:**
- Si `paid = true`: Crea movimientos contables automáticamente
- Si `paid = false`: Elimina movimientos previos
- Si se proporciona `due_date`: Actualiza la fecha de vencimiento

**Respuesta:**
```json
{
  "data": {
    "id": 46,
    "installment_id": 5,
    "installment_number": 2,
    "due_date": "2026-03-20",
    "paid": true,
    "paid_date": "2026-02-09 15:45:00",
    "url_payment": "https://example.com/comprobante.pdf",
    "movement_id": 234
  }
}
```

---

## Actualizar Fecha de Vencimiento

### Nuevo Endpoint: Actualizar Solo la Fecha

Endpoint específico y simple para actualizar únicamente la fecha de vencimiento de una cuota.

```http
PATCH /api/admin/installments/{installment_id}/due-date
```

**Parámetros de URL:**
- `installment_id` (integer, requerido): ID de la cuota (`installment_detail.id`)

**Body (JSON):**
```json
{
  "due_date": "2026-04-15"
}
```

**Validaciones:**
- `due_date`: requerido, debe ser una fecha válida en formato `YYYY-MM-DD`

**Respuesta exitosa (200):**
```json
{
  "message": "Fecha de vencimiento actualizada correctamente",
  "data": {
    "installment_detail_id": 46,
    "installment_number": 2,
    "old_due_date": "2026-03-15",
    "new_due_date": "2026-04-15",
    "paid": false,
    "updated_at": "2026-02-09 16:00:00"
  }
}
```

**Errores:**
- `404`: Cuota no encontrada
- `422`: Validación fallida (fecha inválida o faltante)

---

## Ejemplos de Uso

### Ejemplo 1: Cambiar fecha de vencimiento de una cuota

Un cliente solicita extender el plazo de pago de la cuota 2.

**Paso 1: Consultar las cuotas de la orden**
```bash
curl -X GET "https://tu-dominio.com/api/admin/orders/123/installments" \
  -H "Authorization: Bearer tu_token_admin"
```

**Respuesta:**
```json
{
  "data": [
    {
      "installmentDetails": [
        {
          "id": 45,
          "installment_number": 1,
          "due_date": "2026-02-15",
          "paid": true
        },
        {
          "id": 46,
          "installment_number": 2,
          "due_date": "2026-03-15",
          "paid": false
        }
      ]
    }
  ]
}
```

**Paso 2: Actualizar la fecha de vencimiento**
```bash
curl -X PATCH "https://tu-dominio.com/api/admin/installments/46/due-date" \
  -H "Authorization: Bearer tu_token_admin" \
  -H "Content-Type: application/json" \
  -d '{"due_date": "2026-04-15"}'
```

**Respuesta:**
```json
{
  "message": "Fecha de vencimiento actualizada correctamente",
  "data": {
    "installment_detail_id": 46,
    "installment_number": 2,
    "old_due_date": "2026-03-15",
    "new_due_date": "2026-04-15",
    "paid": false,
    "updated_at": "2026-02-09 16:00:00"
  }
}
```

---

### Ejemplo 2: Cambiar fecha y marcar como pagada en una sola operación

Si necesitas actualizar la fecha Y marcar como pagada, usa el endpoint completo:

```bash
curl -X PATCH "https://tu-dominio.com/api/admin/orders/46/update" \
  -H "Authorization: Bearer tu_token_admin" \
  -H "Content-Type: application/json" \
  -d '{
    "account_id": 1,
    "commission_percentage": 10,
    "url_payment": "https://example.com/comprobante.pdf",
    "paid": true,
    "due_date": "2026-04-15"
  }'
```

---

### Ejemplo 3: Exportar cuotas a Excel

```bash
curl -X GET "https://tu-dominio.com/api/admin/installments/export?status=pending&date_from=2026-01-01&date_to=2026-12-31" \
  -H "Authorization: Bearer tu_token_admin" \
  --output cuotas.xlsx
```

**Query Parameters:**
- `status` (opcional): `pending`, `paid`
- `date_from` (opcional): Fecha desde (YYYY-MM-DD)
- `date_to` (opcional): Fecha hasta (YYYY-MM-DD)

---

## Diferencias entre los Endpoints

### `/admin/orders/{installment_id}/update` (Completo)
**Cuándo usar:**
- Necesitas marcar una cuota como pagada
- Quieres actualizar URL de pago
- Necesitas actualizar múltiples campos a la vez
- Opcionalmente, también cambiar la fecha

**Requiere:**
- `account_id` (obligatorio)
- `paid` (obligatorio)

**Efecto secundario:**
- Crea movimientos contables si `paid = true`

---

### `/admin/installments/{installment_id}/due-date` (Solo Fecha)
**Cuándo usar:**
- Solo necesitas cambiar la fecha de vencimiento
- No quieres tocar el estado de pago ni movimientos
- Quieres una operación simple y rápida

**Requiere:**
- Solo `due_date`

**Efecto secundario:**
- Ninguno, solo actualiza la fecha

---

## Implementación en Frontend

### Componente para Cambiar Fecha de Vencimiento

```typescript
async function updateInstallmentDueDate(
  installmentId: number,
  newDueDate: string
) {
  const response = await fetch(
    `/api/admin/installments/${installmentId}/due-date`,
    {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ due_date: newDueDate })
    }
  );

  const data = await response.json();

  if (response.ok) {
    console.log('Fecha actualizada:', data.data);
    alert(`Cuota ${data.data.installment_number}: ${data.data.old_due_date} → ${data.data.new_due_date}`);
  } else {
    console.error('Error:', data.error);
  }
}

// Uso
updateInstallmentDueDate(46, '2026-04-15');
```

### Componente de Gestión de Cuotas

```typescript
// 1. Obtener cuotas de una orden
const cuotas = await fetch(`/api/admin/orders/${orderId}/installments`);

// 2. Mostrar lista editable
cuotas.data[0].installmentDetails.forEach(cuota => {
  console.log(`
    Cuota ${cuota.installment_number}
    Vence: ${cuota.due_date}
    Estado: ${cuota.paid ? 'Pagada' : 'Pendiente'}
    [Cambiar fecha] [Marcar como pagada]
  `);
});

// 3. Al hacer clic en "Cambiar fecha"
const nuevaFecha = prompt('Nueva fecha (YYYY-MM-DD):');
await updateInstallmentDueDate(cuota.id, nuevaFecha);

// 4. Recargar cuotas
// ... recargar la lista
```

---

## Notas Importantes

1. **IDs Correctos**:
   - `/admin/orders/{installment_id}/update` usa el ID del **detalle** (`installment_detail.id`)
   - `/admin/installments/{installment_id}/due-date` usa el ID del **detalle** (`installment_detail.id`)
   - No confundir con `installments.id` (plan de cuotas)

2. **Formato de Fecha**:
   - Siempre usar formato ISO: `YYYY-MM-DD`
   - Ejemplo válido: `2026-04-15`
   - Ejemplo inválido: `15/04/2026` o `04-15-2026`

3. **Timestamp Automático**:
   - El campo `date_last_updated` del plan de cuotas se actualiza automáticamente

4. **Permisos**:
   - Todos estos endpoints requieren rol de administrador

5. **Cuotas Pagadas**:
   - Puedes cambiar la fecha de cuotas ya pagadas
   - No afecta los movimientos contables ya creados

6. **Validación**:
   - El endpoint valida que la cuota exista
   - Valida que la fecha sea válida
   - No valida si la fecha es pasada (puedes poner fechas pasadas si es necesario)

---

## Casos de Uso Comunes

### Caso 1: Cliente solicita prórroga
**Problema:** El cliente no puede pagar en la fecha original.
**Solución:** Usar `/admin/installments/{id}/due-date` para extender 30 días.

### Caso 2: Error al crear las cuotas
**Problema:** Las fechas se calcularon mal al crear la orden.
**Solución:** Actualizar cada cuota individualmente con el endpoint de fecha.

### Caso 3: Cambio de acuerdo de pago
**Problema:** Se renegociaron las fechas con el cliente.
**Solución:** Actualizar todas las cuotas pendientes con las nuevas fechas.

### Caso 4: Pago adelantado
**Problema:** El cliente pagó antes de la fecha de vencimiento.
**Solución:** Usar `/admin/orders/{id}/update` con `paid: true` (no hace falta cambiar fecha).

---

**Última actualización:** 2026-02-09
