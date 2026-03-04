# 📱 Documentación API Móvil - UTM Web

## 🔐 Autenticación

### POST `/api/login`

Inicia sesión y obtiene un token de acceso para la aplicación móvil.

**URL:** `https://tu-dominio.com/api/login`

**Método:** `POST`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
    "email": "alumno@alumno.utmetropolitana.edu.mx",
    "password": "tu_contraseña"
}
```

**Respuesta Exitosa (200):**
```json
{
    "message": "Login exitoso",
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "user": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "alumno@alumno.utmetropolitana.edu.mx",
        "matricula": "2024001",
        "carrera": "Ingeniería en Sistemas",
        "grado": 1,
        "grupo": "A"
    }
}
```

**Errores Posibles:**

- **422 - Error de Validación:**
```json
{
    "message": "Error de validación",
    "errors": {
        "email": ["El correo debe terminar con uno de los siguientes: @alumno.utmetropolitana.edu.mx, @admin.utmetropolitana.edu.mx, @utmetropolitana.edu.mx."]
    }
}
```

- **401 - Credenciales Incorrectas:**
```json
{
    "message": "Credenciales incorrectas",
    "errors": {
        "email": ["Las credenciales proporcionadas no son correctas."]
    }
}
```

- **403 - Acceso Denegado (No es alumno):**
```json
{
    "message": "Acceso denegado",
    "error": "Esta aplicación móvil es exclusiva para alumnos. Los maestros y administradores deben usar el portal web."
}
```

---

### POST `/api/logout`

Cierra sesión y revoca el token de acceso.

**URL:** `https://tu-dominio.com/api/logout`

**Método:** `POST`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Respuesta Exitosa (200):**
```json
{
    "message": "Sesión cerrada exitosamente"
}
```

---

### GET `/api/user`

Obtiene los datos del usuario autenticado.

**URL:** `https://tu-dominio.com/api/user`

**Método:** `GET`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Respuesta Exitosa (200):**
```json
{
    "id": 1,
    "name": "Juan Pérez",
    "email": "alumno@alumno.utmetropolitana.edu.mx",
    "rol": "alumno",
    "student_detail": {
        "matricula": "2024001",
        "carrera": "Ingeniería en Sistemas",
        "grado": 1,
        "grupo": "A"
    }
}
```

---

## 🔑 Uso del Token

Después de hacer login exitoso, guarda el `token` recibido y úsalo en todas las peticiones protegidas:

```
Authorization: Bearer {token}
```

**Ejemplo en JavaScript/React Native:**
```javascript
const token = response.data.token; // Guardar después del login
axios.get('https://tu-dominio.com/api/user', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
    }
});
```

---

## 📋 Notas Importantes

1. **Solo alumnos pueden usar la app móvil**: Los maestros y administradores deben usar el portal web.

2. **El token expira**: Si recibes un error 401, el token puede haber expirado. Vuelve a hacer login.

3. **Dominio**: Reemplaza `https://tu-dominio.com` con tu dominio real (ej: `https://plataforma-utm.onrender.com`).

4. **HTTPS**: Asegúrate de usar HTTPS en producción.

---

## 🧪 Pruebas con cURL

### Login:
```bash
curl -X POST https://tu-dominio.com/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "alumno@alumno.utmetropolitana.edu.mx",
    "password": "tu_contraseña"
  }'
```

### Obtener Usuario:
```bash
curl -X GET https://tu-dominio.com/api/user \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Logout:
```bash
curl -X POST https://tu-dominio.com/api/logout \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```
