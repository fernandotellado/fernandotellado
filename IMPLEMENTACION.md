# Guía de Implementación: Twenty20 XSS Patch Mejorado

## 🚀 Guía Rápida de Implementación

Esta guía te ayudará a implementar la versión mejorada del parche de seguridad para Twenty20.

---

## 📋 Pre-requisitos

Antes de comenzar, verifica que tienes:

- ✅ WordPress 6.0 o superior
- ✅ PHP 7.4 o superior
- ✅ Plugin Twenty20 Image Before-After instalado
- ✅ Acceso FTP/SSH o acceso al panel de administración
- ✅ Permisos de administrador en WordPress

---

## 🎯 Opciones de Implementación

Tienes 3 opciones para implementar el parche:

### Opción 1: Como Plugin Normal (Fácil)
**Nivel:** Principiante
**Ventajas:** Fácil de instalar y desinstalar
**Desventajas:** Puede desactivarse accidentalmente

### Opción 2: Como Must-Use Plugin (Recomendado)
**Nivel:** Intermedio
**Ventajas:** Carga automática, no puede desactivarse
**Desventajas:** Requiere acceso FTP/SSH

### Opción 3: Integración en Tema (Avanzado)
**Nivel:** Avanzado
**Ventajas:** Totalmente integrado
**Desventajas:** Se pierde al cambiar de tema

---

## 📥 Opción 1: Instalación Como Plugin Normal

### Paso 1: Preparar el archivo

1. Crea una carpeta llamada `twenty20-xss-patch`
2. Coloca el archivo `twenty20-xss-patch-mejorado.php` dentro
3. Renombra el archivo a `twenty20-xss-patch.php`

```bash
mkdir twenty20-xss-patch
cd twenty20-xss-patch
# Copiar el archivo mejorado aquí
mv twenty20-xss-patch-mejorado.php twenty20-xss-patch.php
```

### Paso 2: Comprimir en ZIP

```bash
zip -r twenty20-xss-patch.zip twenty20-xss-patch/
```

### Paso 3: Subir a WordPress

1. Ve a **Plugins** > **Añadir nuevo**
2. Haz clic en **Subir plugin**
3. Selecciona el archivo `twenty20-xss-patch.zip`
4. Haz clic en **Instalar ahora**
5. Activa el plugin

### Paso 4: Verificar

Deberías ver una notificación en el panel si Twenty20 ≤ 2.0.4 está instalado.

---

## 🔧 Opción 2: Instalación Como Must-Use Plugin (MU-Plugin)

### ¿Qué es un MU-Plugin?

Los Must-Use Plugins son plugins que:
- Se cargan automáticamente (no necesitan activación)
- No aparecen en la lista de plugins normales
- No pueden ser desactivados desde el admin
- Se cargan ANTES que los plugins normales
- **Ideal para seguridad crítica**

### Paso 1: Acceder al servidor

**Via FTP:**
```
Conéctate a tu servidor FTP
Navega a: /wp-content/
```

**Via SSH:**
```bash
cd /ruta/a/tu/wordpress/wp-content/
```

### Paso 2: Crear carpeta mu-plugins (si no existe)

```bash
# Verificar si existe
ls -la

# Crear si no existe
mkdir -p mu-plugins
chmod 755 mu-plugins
```

### Paso 3: Copiar el archivo

```bash
# Copiar el archivo directamente (sin carpeta contenedora)
cp twenty20-xss-patch-mejorado.php mu-plugins/twenty20-xss-patch.php
```

**IMPORTANTE:** Los MU-Plugins NO usan carpetas. El archivo debe estar directamente en `/wp-content/mu-plugins/`

### Paso 4: Verificar permisos

```bash
chmod 644 mu-plugins/twenty20-xss-patch.php
```

### Paso 5: Comprobar instalación

1. Ve a **Plugins** > **Plugins instalados**
2. Busca la pestaña "**Must-Use**" en la parte superior
3. Deberías ver "Twenty20 XSS Patch - Versión Mejorada"

**Nota:** No tendrá botón de "Desactivar" porque se carga automáticamente.

---

## 🎨 Opción 3: Integración en Tema

### Método A: Via functions.php

**⚠️ No recomendado** - Se pierde al actualizar el tema

1. Abre `wp-content/themes/tu-tema/functions.php`
2. Copia todo el código del parche (excepto las líneas de cabecera del plugin)
3. Pégalo al final de `functions.php`

### Método B: Via Child Theme (Mejor)

1. Crea un child theme si no lo tienes
2. En `wp-content/themes/tu-child-theme/functions.php`:

```php
<?php
// Cargar el parche de seguridad Twenty20
require_once get_stylesheet_directory() . '/inc/twenty20-security-patch.php';
```

3. Crea la carpeta `inc/` y coloca el archivo ahí

---

## 🧪 Verificación de Instalación

### Test 1: Verificar que está cargado

Añade este código temporalmente en cualquier página:

```php
<?php
if ( has_filter( 'shortcode_atts_twenty20' ) ) {
    echo '✅ Parche Twenty20 XSS está activo';
} else {
    echo '❌ Parche Twenty20 XSS NO está activo';
}
?>
```

### Test 2: Probar con un shortcode

Crea una página de prueba con:

```
[twenty20 img1="123" img2="456" before="Texto de prueba" after="Otro texto"]
```

**Resultado esperado:** El slider funciona normalmente.

### Test 3: Revisar notificaciones

Si Twenty20 2.0.4 o anterior está instalado:
- ✅ Deberías ver un aviso amarillo en el panel
- El aviso dice "Twenty20 XSS Patch: El plugin Twenty20..."

### Test 4: Verificar logs (Opcional)

Intenta un ataque simulado:

```
[twenty20 img1="1" img2="2" before="<script>alert('test')</script>Texto"]
```

**Comprueba el log:**

```bash
# Ver últimas líneas del log de errores
tail -50 /ruta/a/wordpress/wp-content/debug.log | grep "Twenty20"
```

**Resultado esperado:**
```
[14-Nov-2025 10:00:00 UTC] [Twenty20 XSS Patch] Posible intento XSS detectado en parámetro "before". Valor sanitizado.
```

---

## 🔍 Troubleshooting

### Problema: El parche no aparece en la lista de plugins

**Solución para Plugin Normal:**
- Verifica que esté en `/wp-content/plugins/twenty20-xss-patch/`
- Verifica permisos: `chmod 755 /carpeta` y `chmod 644 /archivo.php`
- Revisa los logs de errores de PHP

**Solución para MU-Plugin:**
- Verifica que esté DIRECTAMENTE en `/wp-content/mu-plugins/`
- No debe estar en una subcarpeta
- Revisa la pestaña "Must-Use" en Plugins

### Problema: Sale error "Plugin no válido"

**Causa:** Falta la cabecera del plugin

**Solución:** Verifica que el archivo comience con:

```php
<?php
/**
 * Plugin Name: Twenty20 XSS Patch - Versión Mejorada
 * ...
 */
```

### Problema: No veo las notificaciones

**Posibles causas:**

1. Twenty20 no está instalado
2. Twenty20 es versión > 2.0.4
3. Las notificaciones están deshabilitadas en tu configuración

**Verificar:**

```bash
# Ver versión de Twenty20
grep "Version:" wp-content/plugins/twenty20/twenty20.php
```

### Problema: El shortcode no funciona

**Esto NO es causado por el parche.** El parche solo sanitiza, no modifica funcionalidad.

**Verifica:**
1. Twenty20 está activado
2. Los IDs de las imágenes son correctos
3. No hay errores JavaScript en la consola del navegador

---

## 🔐 Configuración de Logs

### Habilitar logging de WordPress

Si quieres ver los registros de intentos XSS:

1. Edita `wp-config.php`
2. Añade antes de `/* That's all, stop editing! */`:

```php
// Habilitar debug
define( 'WP_DEBUG', true );

// Guardar logs en archivo (no mostrar en pantalla)
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

3. Los logs se guardarán en `/wp-content/debug.log`

### Ver logs en tiempo real

```bash
# Linux/Mac
tail -f /ruta/a/wordpress/wp-content/debug.log

# Filtrar solo Twenty20
tail -f /ruta/a/wordpress/wp-content/debug.log | grep "Twenty20"
```

---

## 📊 Monitoreo de Seguridad

### Revisar intentos de XSS semanalmente

```bash
# Contar intentos detectados
grep "Twenty20 XSS Patch" wp-content/debug.log | wc -l

# Ver últimos 10 intentos
grep "Twenty20 XSS Patch" wp-content/debug.log | tail -10
```

### Si detectas muchos intentos:

1. 🚨 Investiga qué usuario/IP está intentando el ataque
2. 🔒 Considera cambiar permisos de edición
3. 🛡️ Implementa un plugin de seguridad adicional (Wordfence, Sucuri)
4. 📧 Notifica al propietario del sitio

---

## 🔄 Actualización desde Versión 1.0.2

### Si ya tienes la versión original instalada:

**Como Plugin Normal:**

1. Desactiva la versión 1.0.2
2. Elimínala
3. Instala la versión 2.0.0 siguiendo los pasos anteriores

**Como MU-Plugin:**

```bash
# Backup del original
cp mu-plugins/twenty20-xss-patch.php mu-plugins/twenty20-xss-patch.php.v1.0.2.bak

# Reemplazar con la versión mejorada
cp twenty20-xss-patch-mejorado.php mu-plugins/twenty20-xss-patch.php
```

**No se requiere ninguna configuración adicional.** La versión 2.0.0 es 100% compatible.

---

## ✅ Checklist Post-Instalación

Después de instalar, verifica:

- [ ] El parche aparece en la lista de plugins (o MU-Plugins)
- [ ] Las notificaciones aparecen si Twenty20 ≤ 2.0.4
- [ ] Los shortcodes existentes siguen funcionando
- [ ] Los logs se están generando (si WP_DEBUG está activo)
- [ ] No hay errores PHP en el log
- [ ] El rendimiento del sitio no se ha visto afectado

---

## 🆘 Soporte

Si encuentras problemas:

1. **Revisar logs:**
   - PHP error log
   - WordPress debug.log
   - Logs del servidor web

2. **Verificar compatibilidad:**
   - Versión de WordPress
   - Versión de PHP
   - Otros plugins de seguridad

3. **Contactar:**
   - Fernando Tellado: https://ayudawp.com
   - GitHub Issues: https://github.com/fernandotellado/twenty20-image-before-after-xss-security-patch

---

## 📚 Recursos Adicionales

- [Análisis completo de seguridad](analisis-twenty20-xss-patch.md)
- [Comparativa de versiones](comparativa-versiones.md)
- [README completo](README-MEJORADO.md)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Security Best Practices](https://wordpress.org/support/article/hardening-wordpress/)

---

## 🎓 Mejores Prácticas

1. **Backups:** Siempre haz backup antes de instalar
2. **Testing:** Prueba en staging antes de producción
3. **Monitoring:** Revisa logs periódicamente
4. **Actualizaciones:** Estate atento a nuevas versiones
5. **Documentación:** Documenta qué cambios hiciste y cuándo

---

**Última actualización:** 2025-11-14
**Versión del documento:** 1.0
**Autor:** Fernando Tellado + Claude AI
