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
    DB_USERNAME=root
    DB_PASSWORD=
    ```

4. 🗄️ Crear la base de datos y correr migraciones
    ```bash
    mysql -u root -e "CREATE DATABASE sit_marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    php artisan migrate --seed
    ```

---

### ▶️ Iniciar servidor en desarrollo

Ejecuta:

```bash
php artisan serve
```

Tu API estará disponible en:

👉 http://127.0.0.1:8000

---

### 🚀 Despliegue en producción

Pendiente

---

MIT
Free Software, software to learn!
