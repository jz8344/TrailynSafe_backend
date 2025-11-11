# 🚀 Guía de Deployment en Railway - TrailynSafe Backend

Esta guía te ayudará a deployar tu aplicación Laravel en Railway paso a paso.

## 📋 Prerrequisitos

- Cuenta en [Railway.app](https://railway.app)
- Cuenta en [MongoDB Atlas](https://www.mongodb.com/cloud/atlas) (para la base de datos MongoDB)
- Repositorio Git (GitHub, GitLab, etc.) con tu código
- Variables de entorno configuradas

## 🗂️ Archivos de Configuración Creados

Este proyecto ya incluye los siguientes archivos para Railway:

- ✅ `Procfile` - Define cómo Railway ejecuta la aplicación
- ✅ `nixpacks.toml` - Configuración de build con PHP 8.2 y extensiones necesarias
- ✅ `.railwayignore` - Archivos a excluir del deployment
- ✅ `setup-permissions.sh` - Script para configurar permisos
- ✅ `.env.example` - Plantilla de variables de entorno

## 📝 Pasos para Deployment

### 1. Preparar el Repositorio

Asegúrate de que todos los archivos estén commiteados:

```bash
git add .
git commit -m "Configure for Railway deployment"
git push origin main
```

### 2. Crear Proyecto en Railway

1. Ve a [Railway.app](https://railway.app) e inicia sesión
2. Click en **"New Project"**
3. Selecciona **"Deploy from GitHub repo"**
4. Autoriza Railway para acceder a tu repositorio
5. Selecciona el repositorio de `TrailynSafe_WEB`
6. Railway detectará automáticamente que es un proyecto Laravel

### 3. Agregar Base de Datos PostgreSQL

1. En tu proyecto de Railway, click en **"New"** → **"Database"** → **"Add PostgreSQL"**
2. Railway creará automáticamente la base de datos
3. Las variables `DATABASE_URL`, `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`, `PGDATABASE` se agregarán automáticamente

### 4. Configurar Variables de Entorno

En Railway, ve a tu servicio → **Variables** y agrega las siguientes:

#### Variables Esenciales

```env
# Application
APP_NAME="Trailyn Safe"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:TU_APP_KEY_AQUI
APP_URL=https://tu-app.railway.app

# Database (Railway las configurará automáticamente)
DB_CONNECTION=pgsql
DB_SSLMODE=require

# MongoDB Atlas
MONGO_DSN=mongodb+srv://usuario:password@cluster0.xxxxx.mongodb.net/trailynsafe?retryWrites=true&w=majority&ssl=true
MONGO_DATABASE=trailynsafe
MONGO_AUTH_DB=admin
MONGO_SYNC_DISABLED=false
MONGO_SYNC_QUEUE=false

# Email (Gmail)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password-de-gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-email@gmail.com
MAIL_FROM_NAME="Trailyn Safe"

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Otros
LOG_LEVEL=info
BCRYPT_ROUNDS=12
```

#### ⚠️ Importante: Generar APP_KEY

Si no tienes un `APP_KEY`, genera uno localmente:

```bash
php artisan key:generate --show
```

Copia el valor generado (incluyendo `base64:`) y agrégalo a las variables de Railway.

#### 📧 Configurar Email con Gmail

Para usar Gmail:
1. Ve a tu cuenta de Google → **Seguridad**
2. Activa **Verificación en dos pasos**
3. Ve a **Contraseñas de aplicaciones**
4. Genera una nueva contraseña para "Otra app"
5. Usa esa contraseña en `MAIL_PASSWORD`

### 5. Configurar MongoDB Atlas

1. Ve a [MongoDB Atlas](https://www.mongodb.com/cloud/atlas)
2. Crea un cluster gratuito si no tienes uno
3. Ve a **Database Access** → crea un usuario con permisos de lectura/escritura
4. Ve a **Network Access** → agrega `0.0.0.0/0` (permitir acceso desde cualquier IP)
5. En **Database** → **Connect** → copia la cadena de conexión
6. Reemplaza `<password>` con tu contraseña real
7. Agrega el nombre de la base de datos al final: `/trailynsafe`

### 6. Configurar el Dominio

Railway te asignará un dominio automático como `tu-app.railway.app`:

1. Copia el dominio generado
2. Actualiza la variable `APP_URL` en Railway con ese dominio
3. (Opcional) Puedes agregar un dominio personalizado en **Settings** → **Domains**

### 7. Verificar el Deployment

Railway automáticamente:
- ✅ Instalará las dependencias PHP (`composer install`)
- ✅ Instalará las dependencias Node.js (`npm install`)
- ✅ Compilará los assets (`npm run build`)
- ✅ Ejecutará las migraciones (`php artisan migrate --force`)
- ✅ Optimizará el cache de configuración, rutas y vistas
- ✅ Creará el enlace simbólico de storage

Monitorea el build en la pestaña **Deployments**.

### 8. Verificar que Funciona

Una vez deployado:

1. Visita tu URL: `https://tu-app.railway.app`
2. Prueba los endpoints de la API: `https://tu-app.railway.app/api/...`
3. Revisa los logs en Railway si hay errores: **View Logs**

## 🔧 Comandos Útiles Post-Deployment

Si necesitas ejecutar comandos en Railway:

### Ejecutar Migraciones Manualmente

En tu proyecto local, puedes usar Railway CLI:

```bash
# Instalar Railway CLI
npm i -g @railway/cli

# Login
railway login

# Vincular proyecto
railway link

# Ejecutar comandos
railway run php artisan migrate
railway run php artisan db:seed
railway run php artisan cache:clear
```

### Ver Logs en Tiempo Real

```bash
railway logs
```

## 🐛 Troubleshooting

### Error: "No application encryption key has been specified"

- **Solución**: Genera un `APP_KEY` y agrégalo a las variables de entorno en Railway

### Error: "SQLSTATE[08006] Connection refused"

- **Solución**: Verifica que la base de datos PostgreSQL esté conectada y las variables `DATABASE_URL` estén configuradas

### Error: "Class 'MongoDB\Laravel\MongoDBServiceProvider' not found"

- **Solución**: Asegúrate de que `composer.json` incluya `"mongodb/laravel-mongodb": "^5.4"`

### Los assets no se cargan (CSS/JS)

- **Solución**: 
  1. Verifica que `npm run build` se ejecutó correctamente
  2. Asegúrate de que `APP_URL` está configurado correctamente
  3. Ejecuta `php artisan storage:link`

### Error de permisos en `storage/`

- **Solución**: Railway ya ejecuta `chmod -R 775 storage` automáticamente en el build

## 📊 Monitoreo y Logs

Railway proporciona:
- **Metrics**: CPU, memoria, network
- **Logs**: En tiempo real
- **Deployments**: Historial de deployments

Accede a estos en el dashboard de tu proyecto.

## 🔄 Redeploy

Para hacer un nuevo deploy:

1. Haz tus cambios en el código
2. Commitea y push a tu repositorio:
   ```bash
   git add .
   git commit -m "Update feature X"
   git push origin main
   ```
3. Railway automáticamente detectará el cambio y hará redeploy

## 🎯 Checklist Final

Antes de considerar el deployment completo:

- [ ] Base de datos PostgreSQL configurada en Railway
- [ ] MongoDB Atlas configurado y accesible
- [ ] Variables de entorno configuradas (especialmente `APP_KEY`)
- [ ] Email configurado con Gmail App Password
- [ ] Migraciones ejecutadas correctamente
- [ ] La aplicación responde en el dominio de Railway
- [ ] Los endpoints de la API funcionan
- [ ] Los logs no muestran errores críticos

## 📚 Recursos Adicionales

- [Railway Documentation](https://docs.railway.app)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [MongoDB Atlas Docs](https://docs.atlas.mongodb.com)

## 🆘 Soporte

Si encuentras problemas:
1. Revisa los logs en Railway
2. Verifica las variables de entorno
3. Consulta la documentación de Railway
4. Revisa los issues del repositorio

---

¡Listo! Tu aplicación TrailynSafe debería estar corriendo en Railway 🎉
