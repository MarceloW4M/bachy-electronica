# Documentación: tablet-alta.html

Resumen
- Archivo: `public/tablet-alta.html`
- Propósito: interfaz optimizada para tablets donde un cliente puede registrarse (o buscar por DNI/CUIT), registrar un dispositivo y crear una orden de reparación en un flujo sencillo.

Flujo de usuario
1. Buscar por DNI/CUIT en la primera pantalla.
   - Si existe el cliente, se precargan sus datos y se avanza al formulario de detalles.
   - Si no existe, el operador completa los datos del cliente y avanza.
2. Completar datos del dispositivo (Marca → Modelo → Falla / Observaciones).
3. Pulsar `Guardar` para:
   - Crear/actualizar el cliente vía `public/api/customers.php` (POST/PUT según corresponda).
   - Crear el device via `public/api/devices.php` (si se completó al menos 'falla' o marca/modelo).
   - Crear la reparación (`status = 'Pendiente'`) via `public/api/repairs.php`.
4. Mostrar modal con `order_number` y `order_date`. La UI refresca `/admin/repairs.html`.

Endpoints usados (resumen)
- `public/api/customers.php` — crear/actualizar clientes.
- `public/api/brands.php` — lista de marcas (precarga al iniciar la página).
- `public/api/models.php?brand_id=<id>` — lista de modelos para una marca.
- `public/api/provinces.php` — lista de provincias.
- `public/api/cities.php?province_id=<id>` — ciudades por provincia.
- `public/api/devices.php` — crear device (POST).
- `public/api/repairs.php` — crear reparación (POST). Respuesta esperada: JSON con `id`, `order_number`, `order_date`.

Campos principales en la UI
- Cliente: `dni`/`cuit`, `name`, `phone`, `address`, `province_id`, `city_id`, `postal_code` (hidden en el form pero se envía).
- Dispositivo: `device_brand_id`, `device_model_id`, `device_fault`, `device_observations`.

Validaciones
- DNI/CUIT aceptado: 8–11 dígitos (ver `handleLookup()` en el JS).
- Marca precargada en `init()`; modelos cargados al seleccionar marca.

Pruebas E2E recomendadas
1. Abrir `http://localhost:8185/tablet-alta.html` en una tablet o navegador con tamaño responsive.
2. Probar búsqueda con DNI existente: comprobar que se precargan datos.
3. Probar alta de cliente nuevo: completar dirección y provincia/ciudad, guardar.
4. Crear device + reparación: completar marca/modelo o solo falla, pulsar `Guardar`.
5. Verificar modal muestra `order_number` y `order_date` y que `/admin/repairs.html` se actualiza.
6. Verificar en BD que `repairs` contiene `customer_id` y (si hubo device) `device_id`.

Errores parciales y recuperación
- La UI muestra un indicador `#creationStatus` si la creación de device/repair falla parcialmente.
- En caso de fallo en creación de device, el código aún intentará crear la reparación (sin `device_id`), pero se debe revisar el log del servidor y la respuesta de la API.

Notas de mantenimiento
- Si cambian los nombres de campos de las APIs, actualizar `getPayload()` en el JS de `tablet-alta.html`.
- Si se requiere autenticación adicional o CSRF, adaptar las llamadas `fetch()` para incluir encabezados/credenciales.

Mejoras sugeridas (opcional)
- Reemplazar el modal por uno con CTA: `Ir a reparaciones` y `Nueva alta`.
- Añadir reintento para creación de device/repair y mostrar errores detallados.
- Añadir pruebas automatizadas (Selenium/Playwright) que ejecuten el flujo completo.

Contacto
- Autor: Equipo de desarrollo (repositorio local `bachy-tw`).
