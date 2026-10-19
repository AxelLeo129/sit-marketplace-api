# SIT Marketplace API

API para la gestión de recetas médicas y la venta y entrega de medicamentos.

**Flujo principal:** el médico emite una receta → el call-center llama al paciente y crea el pedido → facturación lo factura → el despachador asigna un mensajero → el mensajero lo entrega. Cada línea vendida genera comisiones para el médico, el visitador, el call-center y el mensajero.

| Rol (`App\Enums\Role`) | Qué hace |
|---|---|
| `admin` | Administra usuarios, catálogo y reportes |
| `doctor` | Emite recetas a sus pacientes |
| `sales_rep` (visitador) | Da seguimiento a sus médicos asignados |
| `call_center` | Convierte recetas en pedidos y reagenda llamadas |
| `billing` | Factura los pedidos |
| `dispatcher` | Asigna los pedidos facturados a mensajeros |
| `courier` | Entrega los pedidos |

Las tasas de IVA, tarjeta y comisiones están en `config/marketplace.php`. El cálculo se hace solo en el backend (`App\Services\SaleCalculator`).

#### 💰 Pedidos y comisiones

Al convertir una receta en pedido (`App\Services\OrderPlacement`):

- Se **reservan las existencias** y se devuelven si el pedido se cancela.
- El servidor decide el **origen** de cada producto: *recetado* si estaba en la receta, *agregado por el call-center* si no.
- Cada línea guarda su desglose: total, neto, IVA, cargo de tarjeta, costo y margen bruto.
- Comisiones sobre el margen bruto de cada línea:

| Beneficiario | Tasa |
|---|---|
| Médico | 20 % en la 1.ª compra, 15 % en la 2.ª, 10 % en la 3.ª, 5 % en la 4.ª y 0 % después. Solo en productos que él recetó |
| Visitador del médico | 2 % |
| Call-center | 0 % en la 1.ª compra, 1 % en las recompras |
| Mensajero | 0.5 % (se le asigna al despachar) |

Si el margen es cero o negativo, nadie cobra comisión. El número de compra cuenta los pedidos anteriores, no cancelados, del mismo producto, cliente y médico.

Quién ve los montos: el administrador ve costo, margen y comisiones; facturación ve neto e IVA; el resto solo precios y totales. Nunca se guarda el número completo ni el vencimiento de la tarjeta.

#### 🚚 Facturación, despacho y entrega

Cada paso lo hace un rol distinto y solo desde el estado anterior (`App\Services\OrderFulfillment`):

| Paso | Quién | Estado |
|---|---|---|
| Registrar la factura (número; NIT y fecha opcionales) | Facturación | Pendiente de factura → Facturado |
| Asignar o reasignar mensajero | Despachador | Facturado / Asignado / No entregado → Asignado |
| Salir a ruta | Mensajero asignado | Asignado → En ruta |
| Entregar (nombre de quien recibe) | Mensajero asignado | En ruta → Entregado |
| Reportar que no se pudo entregar (motivo) | Mensajero asignado | En ruta → No entregado |

La comisión de mensajero la cobra quien tenga el pedido asignado. Un pedido no entregado se puede reasignar o cancelar (las existencias regresan; si ya estaba facturado, la factura se anula fuera del sistema). Cada pedido indica en `can` qué acciones puede hacer el usuario, para que el frontend muestre solo los botones válidos.

No hay registro público: los usuarios los crea un administrador.

---

### 👨‍💻 Colaboradores

<table>
  <tr>
    <td align="center"><a style="color: black" href="https://github.com/AxelLeo129"><img src="https://github.com/AxelLeo129.png" width="100" height="100" alt="Axel Leonardo"><br>Axel Leonardo</a></td>
  </tr>
</table>

---

### 🧰 Tecnologías utilizadas

- PHP **8.3**
- Laravel **13**
- Laravel Sanctum (autenticación por tokens)
- Eloquent ORM
- MySQL
- Firebase Storage (imágenes)
- Scramble (documentación OpenAPI / Swagger generada desde el código)

---

### 🔧 Requisitos previos

Antes de comenzar, asegúrate de tener instalado:

- PHP **8.3** o superior
- Composer
- MySQL

---

### 📦 Instalación

Sigue estos pasos para configurar el proyecto localmente:

1. 📥 Clonar repositorio
    ```bash
    git clone https://github.com/AxelLeo129/sit-marketplace-api
    cd sit-marketplace-api
    ```

2. 📥 Instalar dependencias
    ```bash
    composer install
    ```

3. ⚙️ Variables de entorno
    Copia el archivo de ejemplo y ajusta los valores:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    Edita el archivo `.env` con tu configuración:
    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=sit_marketplace
    ADMIN_EMAIL=tu-correo@ejemplo.com
    ADMIN_PASSWORD=una-contraseña-segura
    DB_USERNAME=root
    DB_PASSWORD=
    ```

4. 🗄️ Crear la base de datos y correr migraciones
    ```bash
    mysql -u root -e "CREATE DATABASE sit_marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    php artisan migrate --seed
    php artisan storage:link
    ```
    `storage:link` publica las imágenes subidas en local (`MEDIA_DISK=public`).

    En entorno local también se cargan datos demo: un usuario por rol (`<rol>@demo.test`, contraseña `password`), 20 productos, 5 recetas del médico demo y un cliente (teléfono `55550000`) con sus direcciones.

    Para correr los tests:
    ```bash
    php artisan test
    ```

---

### ▶️ Iniciar servidor en desarrollo

Ejecuta:

```bash
php artisan serve
```

Tu API estará disponible en:

👉 http://127.0.0.1:8000

Con Laragon no hace falta `artisan serve`: la API queda en 👉 http://sit-marketplace-api.test

---

### 📖 Documentación interactiva (Swagger / OpenAPI)

La documentación se genera automáticamente desde el código (rutas, validaciones, respuestas y permisos) con [Scramble](https://scramble.dedoc.co), así que siempre está al día.

| URL | Contenido |
|---|---|
| http://sit-marketplace-api.test/docs/api | Interfaz para explorar y **probar** los endpoints |
| http://sit-marketplace-api.test/docs/api.json | Especificación OpenAPI 3.1 (importable en Postman o Insomnia) |

Para probar endpoints protegidos: obtén un token con `POST /api/login` y pégalo en el botón de autenticación (Bearer) de la interfaz.

Por seguridad, la documentación **solo está disponible con `APP_ENV=local`**; en producción responde 403. Para exportar la especificación a un archivo: `php artisan scramble:export`.

---

### 🔌 Endpoints

Todas las rutas llevan el prefijo `/api`. Salvo las públicas, requieren el header `Authorization: Bearer <token>`. El detalle de cada campo está en la documentación interactiva.

| Método | Ruta | Quién | Descripción |
|---|---|---|---|
| POST | `/login` | Público | Inicia sesión con correo o número de colegiado |
| POST | `/forgot-password` | Público | Envía el enlace para restablecer la contraseña |
| POST | `/reset-password` | Público | Restablece la contraseña con el token del enlace |
| POST | `/logout` | Autenticado | Cierra la sesión actual |
| GET, PATCH | `/me` | Autenticado | Consulta o edita el propio perfil (médico: clínica y especialidades) |
| PUT | `/me/password` | Autenticado | Cambia la contraseña (requiere la actual) |
| POST | `/me/avatar` | Autenticado | Sube la foto de perfil |
| POST | `/me/clinic-logo` | Médico | Sube el logo de la clínica |
| CRUD | `/users` | Admin, visitador | El visitador solo ve y crea sus médicos |
| GET | `/prescriptions` | Admin, médico, call-center | Recetas visibles para el rol. Filtros: `status`, `search`, `issued_from`, `issued_to` |
| POST | `/prescriptions` | Médico | Emite una receta (fecha de hoy, hora de Guatemala) |
| GET | `/prescriptions/{id}` | Admin, médico autor, call-center | Diagnóstico y síntomas solo los ve el médico autor |
| PUT | `/prescriptions/{id}` | Médico autor | Edita mientras siga abierta (nueva o reagendada) |
| POST | `/prescriptions/{id}/reschedule` | Call-center, admin | Agenda otra llamada (`callback_at`) |
| POST | `/prescriptions/{id}/cancel` | Call-center, admin | Cancela la receta |
| POST | `/prescriptions/{id}/send` | Médico autor | Envía la receta al correo del paciente |
| GET, POST | `/customers` | Call-center, admin | Busca (`search`: teléfono, nombre o NIT) o registra clientes |
| GET, PUT | `/customers/{id}` | Call-center, admin | Consulta o edita un cliente con sus direcciones |
| POST, PUT, DELETE | `/customers/{id}/addresses/{addressId}` | Call-center, admin | Direcciones de facturación (`billing`) o entrega (`shipping`) |
| GET | `/orders` | Admin, call-center, facturación, despachador, mensajero | Pedidos visibles para el rol. Filtros: `status`, `search`, `created_from`, `created_to` |
| POST | `/orders` | Call-center, admin | Convierte una receta abierta en pedido |
| GET | `/orders/{id}` | Según rol | El desglose financiero depende del rol |
| POST | `/orders/{id}/cancel` | Call-center, admin | Cancela si aún no tiene mensajero o si la entrega falló; devuelve existencias |
| POST | `/orders/{id}/invoice` | Facturación, admin | Registra la factura (`invoice_number`; opcionales `invoice_tax_id`, `invoiced_at`) |
| GET | `/couriers` | Despachador, admin | Mensajeros activos con sus entregas pendientes |
| POST | `/orders/{id}/assign` | Despachador, admin | Asigna o reasigna el mensajero (`courier_id`) |
| POST | `/orders/{id}/start-delivery` | Mensajero asignado | Sale a ruta |
| POST | `/orders/{id}/deliver` | Mensajero asignado | Confirma la entrega (`received_by`, `notes` opcional) |
| POST | `/orders/{id}/fail-delivery` | Mensajero asignado | Reporta que no pudo entregar (`notes` con el motivo) |
| GET | `/categories`, `/labs`, `/presentations`, `/products` | Autenticado | Consulta del catálogo |
| POST, PUT, DELETE | `/categories`, `/labs`, `/presentations`, `/products` | Admin | Administración del catálogo |
| POST, DELETE | `/products/{id}/image` | Admin | Sube o quita la imagen del producto |

`GET /products` acepta los filtros `search`, `category_id`, `lab_id`, `per_page` e `include_inactive` (solo admin).

---

### 🚀 Despliegue en producción

Pendiente

---

MIT
Free Software, software to learn!
