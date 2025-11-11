# ✅ Checklist Pre-Deployment Railway

Usa este checklist antes de hacer push al repositorio y deployar en Railway.

## 📋 Antes de Commitear

- [ ] Todos los archivos de configuración están creados:
  - [ ] `Procfile`
  - [ ] `nixpacks.toml`
  - [ ] `.railwayignore`
  - [ ] `.env.example` actualizado
  - [ ] `RAILWAY_DEPLOYMENT.md`

- [ ] El archivo `.env` local NO está en el repositorio (debe estar en `.gitignore`)

- [ ] `composer.json` incluye todas las dependencias:
  - [ ] `mongodb/laravel-mongodb`
  - [ ] `laravel/sanctum`
  - [ ] Scripts de deploy configurados

- [ ] Las migraciones están creadas y funcionan localmente:
  ```bash
  php artisan migrate:fresh
  ```

- [ ] El código está funcionando localmente sin errores

## 🔧 En Railway

- [ ] Proyecto creado en Railway
- [ ] Base de datos PostgreSQL agregada al proyecto
- [ ] Variables de entorno configuradas:
  - [ ] `APP_NAME`
  - [ ] `APP_ENV=production`
  - [ ] `APP_KEY` (generado con `php artisan key:generate --show`)
  - [ ] `APP_URL` (tu dominio de Railway)
  - [ ] `APP_DEBUG=false`
  - [ ] Variables de PostgreSQL (automáticas)
  - [ ] `MONGO_DSN` (de MongoDB Atlas)
  - [ ] `MONGO_DATABASE`
  - [ ] Variables de email (Gmail)

## 🗄️ MongoDB Atlas

- [ ] Cluster creado en MongoDB Atlas
- [ ] Usuario de base de datos creado con permisos
- [ ] Network Access configurado (`0.0.0.0/0` permitido)
- [ ] Cadena de conexión copiada y agregada a Railway (`MONGO_DSN`)

## 📧 Email (Gmail)

- [ ] Verificación en dos pasos activada en Google
- [ ] Contraseña de aplicación generada
- [ ] Variables de email configuradas en Railway:
  - [ ] `MAIL_USERNAME`
  - [ ] `MAIL_PASSWORD`
  - [ ] `MAIL_FROM_ADDRESS`

## 🚀 Deployment

- [ ] Código pusheado a GitHub/GitLab:
  ```bash
  git add .
  git commit -m "Configure for Railway deployment"
  git push origin main
  ```

- [ ] Railway está conectado al repositorio
- [ ] Build completado sin errores (revisar logs)
- [ ] Migraciones ejecutadas correctamente
- [ ] Aplicación accesible en el dominio de Railway

## ✅ Post-Deployment

- [ ] La aplicación responde en `https://tu-app.railway.app`
- [ ] Los endpoints de API funcionan correctamente
- [ ] Las migraciones se ejecutaron (verificar en la BD)
- [ ] Los emails se envían correctamente
- [ ] La sincronización con MongoDB funciona
- [ ] No hay errores en los logs de Railway

## 🔍 Verificación de Endpoints

Prueba estos endpoints (reemplaza con tu dominio):

```bash
# Health check
curl https://tu-app.railway.app/api/health

# Test de autenticación (si existe)
curl https://tu-app.railway.app/api/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

## 🆘 Si Algo Falla

1. **Revisa los logs en Railway:**
   - Ve a tu servicio → **View Logs**
   
2. **Verifica las variables de entorno:**
   - Asegúrate de que `APP_KEY` esté configurado
   - Verifica que `DATABASE_URL` esté presente
   
3. **Ejecuta comandos manualmente:**
   ```bash
   railway login
   railway link
   railway run php artisan migrate
   railway run php artisan config:clear
   ```

4. **Redeploy manualmente:**
   - En Railway → Settings → Redeploy

## 📝 Notas

- Railway puede tardar 2-5 minutos en el primer deployment
- Los logs en tiempo real te mostrarán el progreso del build
- Después del primer deployment, los siguientes serán más rápidos
- Railway automáticamente hace redeploy cuando haces push al repositorio

---

**¿Todo listo?** Procede con el deployment siguiendo la [Guía Completa](RAILWAY_DEPLOYMENT.md)
