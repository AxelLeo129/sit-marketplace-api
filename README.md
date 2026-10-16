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

    En entorno local también se cargan datos demo: un usuario por rol (`<rol>@demo.test`, contraseña `password`).

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

### 🔌 Endpoints

Todas las rutas llevan el prefijo `/api`. Salvo las públicas, requieren el header `Authorization: Bearer <token>`.

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
