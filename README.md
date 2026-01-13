# UTM Web - Portal Académico

Sistema de gestión académica para la Universidad Técnica Metropolitana desarrollado por **Angel Noh** y **Mauricio Chale** del 4-E.

## 🚀 Tecnologías

- **Backend:** Laravel 12
- **Frontend:** Vue 3 + Inertia.js
- **Styling:** Tailwind CSS
- **Base de Datos:** SQLite (configurable a MySQL/MariaDB)
- **Servidor Local:** Laragon

## 📋 Características

### 👨‍🎓 Portal Estudiantil
- Dashboard con sidebar lateral moderno
- Consulta de horarios de clases
- Historial académico y calificaciones
- Gestión de cursos internos y externos
- Procesos administrativos
- Modo oscuro
- Sistema de perfil completo con foto

### 👨‍🏫 Portal Docente
- Dashboard de gestión de clases
- Control de asistencia
- Lista de alumnos por grupo
- Gestión de cursos internos y externos
- Calificaciones

### 🔧 Portal Administrativo
- **Gestión de Estudiantes:** ✅ FUNCIONAL
  - ✅ Listado completo de todos los estudiantes
  - ✅ Dar de alta estudiantes con formulario completo
  - ✅ Editar información de estudiantes existentes
  - ✅ Eliminar estudiantes
  - ✅ Búsqueda en tiempo real
  - ✅ Creación automática de correos institucionales
  - ✅ Asignación de grupos (A, B, C, D, E)
  - ✅ Información académica completa (matrícula, carrera, grado, grupo)
  - ✅ Información personal opcional (teléfono, dirección, fecha de nacimiento)
  
- **Gestión de Maestros:**
  - Dar de alta maestros
  - Creación de cuentas institucionales
  
- **Gestión de Horarios:**
  - Creación de horarios por carrera y grupo
  - Horarios de maestros generados automáticamente
  - Soporte para teoría, laboratorio y práctica
  
- **Gestión de Documentos:**
  - Verificar solicitudes de trámites
  - Marcar documentos como listos
  - Control de entregas
  - Cancelación de trámites

## 🔑 Credenciales de Acceso

### Administrador
- **Email:** admini@admin.utmetropolitana.edu.mx
- **Contraseña:** 12345

### Estudiante 1
- **Email:** 24090564@alumno.utmetropolitana.edu.mx
- **Contraseña:** password

### Estudiante 2
- **Email:** 24090565@alumno.utmetropolitana.edu.mx
- **Contraseña:** password

### Maestro 1
- **Email:** jesus.pech@utmetropolitana.edu.mx
- **Contraseña:** password

### Maestro 2
- **Email:** maria.gonzalez@utmetropolitana.edu.mx
- **Contraseña:** password

## 🛠️ Instalación

### Requisitos
- PHP 8.2+
- Composer
- Node.js 18+
- SQLite o MySQL/MariaDB

### Pasos

1. **Clonar el repositorio**
```bash
cd C:\laragon\www\UTM-Web
```

2. **Instalar dependencias de PHP**
```bash
composer install
```

3. **Instalar dependencias de Node.js**
```bash
npm install
```

4. **Configurar el entorno**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Ejecutar migraciones**
```bash
php artisan migrate
```

6. **Poblar la base de datos**
```bash
php artisan db:seed
```

7. **Compilar assets**
```bash
npm run build
```

8. **Iniciar servidores**
```bash
# Terminal 1 - Servidor PHP
php artisan serve

# Terminal 2 - Servidor Vite (desarrollo)
npm run dev
```

## 📁 Estructura del Proyecto

```
UTM-Web/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Controladores de administración
│   │   │   │   ├── StudentController.php
│   │   │   │   ├── TeacherController.php
│   │   │   │   ├── ScheduleController.php
│   │   │   │   └── DocumentController.php
│   │   │   └── Auth/           # Controladores de autenticación
│   │   └── Middleware/
│   │       └── CheckUserRole.php
│   └── Models/
│       ├── User.php            # Modelo de usuarios
│       ├── StudentDetail.php   # Detalles de estudiantes
│       ├── Schedule.php        # Horarios
│       └── Document.php        # Documentos
├── database/
│   ├── migrations/             # Migraciones de base de datos
│   └── seeders/                # Seeders para datos de prueba
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Admin/          # Páginas de administración
│   │   │   ├── Dashboard.vue   # Dashboard de estudiantes
│   │   │   ├── DashboardMaestro.vue
│   │   │   ├── ConsultarHorario.vue
│   │   │   └── ...
│   │   ├── Layouts/
│   │   │   └── AuthenticatedLayout.vue
│   │   └── Components/
│   └── css/
│       └── app.css
└── routes/
    ├── web.php                 # Rutas web
    └── auth.php                # Rutas de autenticación
```

## 🔒 Roles del Sistema

El sistema identifica automáticamente el rol del usuario basándose en su correo electrónico:

- **@admin.utmetropolitana.edu.mx** - Administradores
- **@alumno.utmetropolitana.edu.mx** - Estudiantes
- **@utmetropolitana.edu.mx** - Maestros

## 📚 APIs Disponibles

### Administradores

#### Estudiantes
- `GET /admin/students` - Listar estudiantes
- `POST /admin/students` - Crear estudiante
- `GET /admin/students/{id}` - Ver estudiante
- `PUT /admin/students/{id}` - Actualizar estudiante
- `DELETE /admin/students/{id}` - Eliminar estudiante

#### Maestros
- `GET /admin/teachers` - Listar maestros
- `POST /admin/teachers` - Crear maestro
- `GET /admin/teachers/{id}` - Ver maestro
- `PUT /admin/teachers/{id}` - Actualizar maestro
- `DELETE /admin/teachers/{id}` - Eliminar maestro

#### Horarios
- `GET /admin/schedules` - Listar horarios
- `POST /admin/schedules` - Crear horario
- `POST /admin/schedules/batch` - Crear múltiples horarios
- `PUT /admin/schedules/{id}` - Actualizar horario
- `DELETE /admin/schedules/{id}` - Eliminar horario

#### Documentos
- `GET /admin/documents` - Listar documentos
- `POST /admin/documents/{id}/ready` - Marcar como listo
- `POST /admin/documents/{id}/delivered` - Marcar como entregado
- `POST /admin/documents/{id}/cancel` - Cancelar documento

## 🎨 Características de UI/UX

- Diseño moderno y responsivo
- Modo oscuro disponible
- Sidebar lateral para estudiantes
- Transiciones suaves
- Cards con hover effects
- Sistema de iconos SVG
- Color scheme UTM (Verde, Azul, Naranja)

## 🧪 Testing

```bash
php artisan test
```

## 📝 Desarrollo

Para el desarrollo con hot reload:

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3 (opcional - logs)
php artisan pail
```

## 🤝 Contribuidores

- **Angel Noh** - 24090564@alumno.utmetropolitana.edu.mx
- **Mauricio Chale** - 24090565@alumno.utmetropolitana.edu.mx

## 📄 Licencia

MIT License

## 📞 Contacto

Universidad Técnica Metropolitana

---

Desarrollado con ❤️ por estudiantes del 4-E
