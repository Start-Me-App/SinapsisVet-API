# API de Gestión de Asistencias a Lecciones

Esta documentación describe los endpoints disponibles para gestionar asistencias (view_lesson) a las lecciones.

## Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Modelos](#modelos)
3. [Endpoints Públicos](#endpoints-públicos)
4. [Endpoints de Administración](#endpoints-de-administración)
5. [Ejemplos de Uso](#ejemplos-de-uso)

## Descripción General

El sistema de asistencias permite:

- **Estudiantes**: Marcar su propia asistencia al ver una lección
- **Administradores**: Ver quién asistió, tomar lista manualmente, y gestionar asistencias

La tabla `view_lesson` almacena los registros de asistencia con:
- `id`: ID del registro
- `user_id`: ID del usuario
- `lesson_id`: ID de la lección
- `created_at`: Fecha y hora en que se marcó la asistencia
- `updated_at`: Fecha de última actualización

## Modelos

### Relaciones Agregadas

#### ViewLesson
```php
// Relación con la lección
public function lesson()

// Relación con el usuario
public function user()
```

#### Lessons
```php
// Relación con asistencias
public function attendances()

// Relación muchos a muchos con usuarios que asistieron
public function attendees()
```

---

## Endpoints Públicos

Estos endpoints están disponibles para todos los usuarios autenticados.

### 1. Ver Asistencias de una Lección

Obtiene la lista de todos los usuarios que marcaron asistencia en una lección específica.

**Endpoint:**
```
GET /api/lessons/{lesson_id}/attendances
```

**Parámetros de URL:**
- `lesson_id` (integer, requerido): ID de la lección

**Respuesta exitosa (200):**
```json
{
  "lesson_id": 15,
  "lesson_name": "Introducción a la Cardiología Veterinaria",
  "total_attendances": 23,
  "attendances": [
    {
      "id": 145,
      "user_id": 42,
      "user_name": "María García",
      "user_email": "maria@example.com",
      "user_phone": "+5491112345678",
      "marked_at": "2026-02-09T14:30:00.000000Z"
    },
    {
      "id": 144,
      "user_id": 38,
      "user_name": "Carlos López",
      "user_email": "carlos@example.com",
      "user_phone": "+5491187654321",
      "marked_at": "2026-02-09T14:28:15.000000Z"
    }
  ]
}
```

**Errores:**
- `404`: Lección no encontrada

**Ejemplo de uso:**
```bash
curl -X GET "https://tu-dominio.com/api/lessons/15/attendances" \
  -H "Authorization: Bearer tu_token_aqui"
```

---

## Endpoints de Administración

Estos endpoints requieren permisos de administrador.

### 2. Obtener Estudiantes Elegibles

Obtiene la lista completa de estudiantes inscritos en el curso, indicando quiénes ya marcaron asistencia.

**Endpoint:**
```
GET /api/admin/lessons/{lesson_id}/eligible-students
```

**Parámetros de URL:**
- `lesson_id` (integer, requerido): ID de la lección

**Respuesta exitosa (200):**
```json
{
  "lesson_id": 15,
  "lesson_name": "Introducción a la Cardiología Veterinaria",
  "course_id": 3,
  "course_name": "Cardiología Veterinaria Avanzada",
  "statistics": {
    "total_students": 30,
    "attended": 23,
    "missing": 7,
    "attendance_percentage": 76.67
  },
  "students": [
    {
      "user_id": 42,
      "name": "María García",
      "email": "maria@example.com",
      "phone": "+5491112345678",
      "has_attendance": true,
      "attendance_id": 145,
      "attended_at": "2026-02-09T14:30:00.000000Z"
    },
    {
      "user_id": 55,
      "name": "Juan Pérez",
      "email": "juan@example.com",
      "phone": "+5491198765432",
      "has_attendance": false,
      "attendance_id": null,
      "attended_at": null
    }
  ]
}
```

**Descripción de campos:**
- `has_attendance`: `true` si el usuario ya marcó asistencia, `false` si no
- `attendance_id`: ID del registro de asistencia (null si no asistió)
- `attended_at`: Fecha/hora de la asistencia (null si no asistió)

**Errores:**
- `404`: Lección no encontrada
- `404`: Curso no encontrado para esta lección

**Ejemplo de uso:**
```bash
curl -X GET "https://tu-dominio.com/api/admin/lessons/15/eligible-students" \
  -H "Authorization: Bearer tu_token_admin"
```

**Caso de uso:**
Este endpoint es ideal para mostrar una interfaz de "tomar lista" donde el admin puede ver:
- Quiénes ya están presentes (marcados en verde)
- Quiénes faltan (marcados en rojo)
- Porcentaje de asistencia

---

### 3. Marcar Asistencia Individual

Marca la asistencia de un usuario específico a una lección.

**Endpoint:**
```
POST /api/admin/lessons/{lesson_id}/attendance
```

**Parámetros de URL:**
- `lesson_id` (integer, requerido): ID de la lección

**Parámetros del Body (JSON):**
```json
{
  "user_id": 42
}
```

**Validaciones:**
- `user_id`: requerido, debe ser un entero y existir en la tabla users
- El usuario debe estar inscrito en el curso de la lección

**Respuesta exitosa (201):**
```json
{
  "message": "Asistencia marcada correctamente",
  "data": {
    "id": 146,
    "user_id": 42,
    "user_name": "María García",
    "user_email": "maria@example.com",
    "lesson_id": 15,
    "lesson_name": "Introducción a la Cardiología Veterinaria",
    "marked_at": "2026-02-09T15:45:00.000000Z"
  }
}
```

**Si la asistencia ya existe (200):**
```json
{
  "message": "La asistencia ya fue marcada previamente",
  "data": {
    "id": 145,
    "user_id": 42,
    "lesson_id": 15,
    "marked_at": "2026-02-09T14:30:00.000000Z"
  }
}
```

**Errores:**
- `404`: Lección no encontrada
- `403`: El usuario no está inscrito en este curso
- `422`: Validación fallida (user_id inválido)

**Ejemplo de uso:**
```bash
curl -X POST "https://tu-dominio.com/api/admin/lessons/15/attendance" \
  -H "Authorization: Bearer tu_token_admin" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 42}'
```

---

### 4. Marcar Asistencia Múltiple

Marca la asistencia de varios usuarios a la vez (útil para tomar lista completa).

**Endpoint:**
```
POST /api/admin/lessons/{lesson_id}/attendance/multiple
```

**Parámetros de URL:**
- `lesson_id` (integer, requerido): ID de la lección

**Parámetros del Body (JSON):**
```json
{
  "user_ids": [42, 38, 55, 61, 73]
}
```

**Validaciones:**
- `user_ids`: requerido, debe ser un array
- Cada elemento del array debe ser un entero y existir en la tabla users

**Respuesta exitosa (200):**
```json
{
  "message": "Proceso de asistencias completado",
  "lesson_id": 15,
  "lesson_name": "Introducción a la Cardiología Veterinaria",
  "results": {
    "created": [
      {
        "user_id": 55,
        "attendance_id": 147
      },
      {
        "user_id": 61,
        "attendance_id": 148
      }
    ],
    "already_exists": [
      {
        "user_id": 42,
        "attendance_id": 145
      },
      {
        "user_id": 38,
        "attendance_id": 144
      }
    ],
    "not_enrolled": [
      {
        "user_id": 73,
        "user_name": "Pedro Martínez"
      }
    ]
  },
  "summary": {
    "created": 2,
    "already_exists": 2,
    "not_enrolled": 1,
    "total_processed": 5
  }
}
```

**Descripción de resultados:**
- `created`: Asistencias creadas exitosamente
- `already_exists`: Usuarios que ya tenían asistencia marcada
- `not_enrolled`: Usuarios que no están inscritos en el curso

**Errores:**
- `404`: Lección no encontrada
- `422`: Validación fallida

**Ejemplo de uso:**
```bash
curl -X POST "https://tu-dominio.com/api/admin/lessons/15/attendance/multiple" \
  -H "Authorization: Bearer tu_token_admin" \
  -H "Content-Type: application/json" \
  -d '{"user_ids": [42, 38, 55, 61, 73]}'
```

**Caso de uso:**
Útil para marcar asistencia de todos los presentes después de tomar lista manualmente:
1. El admin consulta `/eligible-students`
2. Selecciona a todos los presentes en la UI
3. Envía la lista completa con `/attendance/multiple`

---

### 5. Eliminar Asistencia

Elimina el registro de asistencia de un usuario (desmarca asistencia).

**Endpoint:**
```
DELETE /api/admin/lessons/{lesson_id}/attendance/{user_id}
```

**Parámetros de URL:**
- `lesson_id` (integer, requerido): ID de la lección
- `user_id` (integer, requerido): ID del usuario

**Respuesta exitosa (200):**
```json
{
  "message": "Asistencia eliminada correctamente",
  "data": {
    "id": 145,
    "user_id": 42,
    "lesson_id": 15,
    "marked_at": "2026-02-09T14:30:00.000000Z"
  }
}
```

**Errores:**
- `404`: Lección no encontrada
- `404`: No se encontró asistencia para este usuario en esta lección

**Ejemplo de uso:**
```bash
curl -X DELETE "https://tu-dominio.com/api/admin/lessons/15/attendance/42" \
  -H "Authorization: Bearer tu_token_admin"
```

**Caso de uso:**
- Corregir errores al marcar asistencia por error
- Eliminar asistencia de alguien que no estuvo realmente presente

---

## Ejemplos de Uso

### Escenario 1: Estudiante marca su propia asistencia

El estudiante accede a la lección y automáticamente se marca su asistencia:

```bash
# El frontend llama a este endpoint cuando el estudiante abre la lección
curl -X POST "https://tu-dominio.com/api/lessons/15/view" \
  -H "Authorization: Bearer token_del_estudiante"
```

### Escenario 2: Admin toma lista manualmente

**Paso 1:** Obtener lista de estudiantes elegibles

```bash
curl -X GET "https://tu-dominio.com/api/admin/lessons/15/eligible-students" \
  -H "Authorization: Bearer token_admin"
```

**Paso 2:** Marcar asistencia de los presentes

Opción A - Individual (uno por uno):
```bash
curl -X POST "https://tu-dominio.com/api/admin/lessons/15/attendance" \
  -H "Authorization: Bearer token_admin" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 42}'
```

Opción B - Múltiple (todos a la vez):
```bash
curl -X POST "https://tu-dominio.com/api/admin/lessons/15/attendance/multiple" \
  -H "Authorization: Bearer token_admin" \
  -H "Content-Type: application/json" \
  -d '{"user_ids": [42, 38, 55, 61, 67]}'
```

### Escenario 3: Consultar asistencias de una lección

Cualquier usuario puede ver quiénes asistieron:

```bash
curl -X GET "https://tu-dominio.com/api/lessons/15/attendances" \
  -H "Authorization: Bearer tu_token"
```

### Escenario 4: Corregir error en asistencia

El admin marcó por error a alguien que no estuvo presente:

```bash
curl -X DELETE "https://tu-dominio.com/api/admin/lessons/15/attendance/42" \
  -H "Authorization: Bearer token_admin"
```

---

## Implementación en el Frontend

### Componente de Tomar Lista (Admin)

```typescript
// 1. Obtener estudiantes elegibles
const response = await fetch(`/api/admin/lessons/${lessonId}/eligible-students`, {
  headers: { 'Authorization': `Bearer ${token}` }
});
const data = await response.json();

// 2. Mostrar lista con checkboxes
data.students.forEach(student => {
  console.log(`
    ${student.has_attendance ? '✓' : '☐'}
    ${student.name} - ${student.email}
  `);
});

// 3. Marcar asistencias seleccionadas
const selectedUserIds = [42, 38, 55]; // IDs de los checkboxes marcados
await fetch(`/api/admin/lessons/${lessonId}/attendance/multiple`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ user_ids: selectedUserIds })
});
```

### Componente de Ver Asistencias

```typescript
// Obtener lista de asistencias
const response = await fetch(`/api/lessons/${lessonId}/attendances`, {
  headers: { 'Authorization': `Bearer ${token}` }
});
const data = await response.json();

console.log(`Asistencia: ${data.total_attendances} estudiantes`);
data.attendances.forEach(attendance => {
  console.log(`- ${attendance.user_name} (${attendance.marked_at})`);
});
```

---

## Notas Importantes

1. **Inscripción Requerida**: Solo se puede marcar asistencia para usuarios inscritos en el curso de la lección.

2. **Idempotencia**: Marcar asistencia múltiples veces para el mismo usuario no crea duplicados.

3. **Timestamps**: La tabla `view_lesson` tiene `created_at` y `updated_at` automáticos.

4. **Permisos**:
   - Endpoints en `/api/lessons/*` son públicos (requieren autenticación)
   - Endpoints en `/api/admin/lessons/*` requieren rol de administrador

5. **Estadísticas en Tiempo Real**: El endpoint `eligible-students` siempre devuelve estadísticas actualizadas.

6. **Escalabilidad**: Para lecciones con muchos estudiantes (>100), considera agregar paginación.

---

## Mejoras Futuras Sugeridas

1. **Exportar Asistencias**: Endpoint para exportar a Excel/PDF
2. **Reportes**: Estadísticas de asistencia por curso, por alumno, etc.
3. **Notificaciones**: Alertas cuando la asistencia es baja
4. **Confirmación**: Que los estudiantes confirmen manualmente su asistencia en lugar de automático
5. **Geolocalización**: Verificar ubicación al marcar asistencia
6. **Tiempo Mínimo**: Marcar asistencia solo si el usuario estuvo X minutos en la lección

---

**Última actualización:** 2026-02-09
