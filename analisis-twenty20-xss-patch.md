# Análisis de Seguridad: Twenty20 XSS Patch

## 📍 Ubicación del Código
**Repositorio:** https://github.com/fernandotellado/twenty20-image-before-after-xss-security-patch
**Archivo principal:** `twenty20-xss-patch.php`

---

## 🔍 Análisis del Código de Protección XSS

### Código Implementado

```php
add_filter( 'shortcode_atts_twenty20', function( $out, $pairs, $atts ) {

    // img1 / img2 pueden ser IDs o URLs
    foreach ( ['img1','img2'] as $key ) {
        if ( isset( $out[$key] ) ) {
            if ( is_numeric( $out[$key] ) ) {
                $out[$key] = intval( $out[$key] );
            } else {
                $out[$key] = esc_url_raw( $out[$key] );
            }
        }
    }

    if ( isset( $out['offset'] ) ) {
        $out['offset'] = floatval( $out['offset'] );
    }
    if ( isset( $out['orientation'] ) && ! in_array( $out['orientation'], array( 'horizontal','vertical' ), true ) ) {
        $out['orientation'] = 'horizontal';
    }
    if ( isset( $out['align'] ) ) {
        $out['align'] = sanitize_html_class( $out['align'] );
    }

    foreach ( array( 'move_slider_on_hover', 'move_with_handle_only', 'click_to_move' ) as $flag ) {
        if ( isset( $out[ $flag ] ) ) {
            $out[ $flag ] = ( $out[ $flag ] === 'true' ) ? 'true' : 'false';
        }
    }

    // before, after, width → se dejarán escapar por el plugin original
    return $out;

}, 10, 3 );
```

---

## ✅ Protecciones Implementadas

### 1. **Parámetro `img1` y `img2`**
- **Sanitización:**
  - Si es numérico: `intval()` - convierte a entero
  - Si no es numérico: `esc_url_raw()` - escapa URLs
- **Efectividad:** ✅ **ALTA** - Previene inyección de código en estos parámetros
- **Análisis:** Correctamente implementado, evita scripts maliciosos

### 2. **Parámetro `offset`**
- **Sanitización:** `floatval()` - convierte a decimal
- **Efectividad:** ✅ **ALTA** - No permite código arbitrario
- **Análisis:** Adecuado para un parámetro numérico

### 3. **Parámetro `orientation`**
- **Sanitización:** Lista blanca (whitelist) - solo permite `'horizontal'` o `'vertical'`
- **Efectividad:** ✅ **ALTA** - Prevención total de inyección
- **Análisis:** Implementación perfecta usando validación de lista blanca

### 4. **Parámetro `align`**
- **Sanitización:** `sanitize_html_class()` - función nativa de WordPress
- **Efectividad:** ✅ **ALTA** - Remueve caracteres peligrosos
- **Análisis:** Adecuado para clases CSS

### 5. **Flags booleanos** (`move_slider_on_hover`, `move_with_handle_only`, `click_to_move`)
- **Sanitización:** Validación estricta - solo permite strings `'true'` o `'false'`
- **Efectividad:** ✅ **ALTA** - No permite valores arbitrarios
- **Análisis:** Bien implementado

---

## ⚠️ PROBLEMA CRÍTICO IDENTIFICADO

### Parámetros NO Sanitizados

```php
// before, after, width → se dejarán escapar por el plugin original
```

**Parámetros vulnerables según la documentación:**
- `before`
- `after`
- `before_label`
- `after_label`
- `width`

### ❌ Análisis del Problema

1. **El parche NO sanitiza estos parámetros**
   - El comentario indica que confía en que el plugin original los escape
   - Si el plugin original los escapara correctamente, **no existiría la vulnerabilidad**

2. **Discrepancia entre documentación y código**
   - El README afirma: "Text fields (before_label, after_label, etc.): Processed with `sanitize_text_field()`"
   - **REALIDAD:** No hay ninguna línea en el código que aplique `sanitize_text_field()` a estos parámetros

3. **Vectores de ataque activos**
   - Los parámetros `before` y `after` son campos de texto libre
   - Pueden contener código JavaScript malicioso
   - Ejemplo de ataque posible:
   ```
   [twenty20 img1="123" img2="456" before="Antes<script>alert('XSS')</script>"]
   ```

---

## 🎯 ¿Soluciona el Problema?

### Respuesta: **PARCIALMENTE ❌**

**Lo que SÍ protege:**
- ✅ Inyección a través de `img1` y `img2`
- ✅ Manipulación de `orientation`
- ✅ Inyección en `align`
- ✅ Valores booleanos maliciosos

**Lo que NO protege:**
- ❌ Parámetros de texto: `before`, `after`
- ❌ Etiquetas: `before_label`, `after_label`
- ❌ Parámetro `width`

---

## 🔧 Solución Recomendada

Para solucionar completamente el problema XSS, el código debería incluir:

```php
// Sanitizar campos de texto
foreach ( ['before', 'after', 'before_label', 'after_label'] as $text_field ) {
    if ( isset( $out[$text_field] ) ) {
        $out[$text_field] = sanitize_text_field( $out[$text_field] );
    }
}

// Sanitizar width
if ( isset( $out['width'] ) ) {
    $out['width'] = sanitize_text_field( $out['width'] );
}
```

---

## 📊 Evaluación Final

| Aspecto | Calificación | Notas |
|---------|--------------|-------|
| **Protección de imágenes** | ✅ Excelente | `img1`/`img2` correctamente sanitizados |
| **Protección de parámetros técnicos** | ✅ Excelente | `offset`, `orientation`, `align` bien protegidos |
| **Protección de texto libre** | ❌ Insuficiente | Campos de texto NO sanitizados |
| **Documentación vs Implementación** | ⚠️ Inconsistente | README no coincide con el código real |
| **Efectividad general** | ⚠️ Parcial | Reduce superficie de ataque pero no la elimina |

---

## 🎓 Conclusión

El parche **reduce significativamente** la superficie de ataque XSS al proteger varios parámetros críticos, pero **NO soluciona completamente** la vulnerabilidad porque:

1. Los parámetros de texto libre (`before`, `after`, `before_label`, `after_label`) permanecen sin sanitizar
2. Estos parámetros son precisamente los vectores de ataque mencionados en la documentación de la vulnerabilidad
3. El parche confía incorrectamente en que el plugin original escape estos valores

**Recomendación:** El parche debe actualizarse para incluir `sanitize_text_field()` en todos los campos de texto antes de considerarse una solución completa.

---

**Analista:** Claude AI
**Fecha:** 2025-11-14
**Versión analizada:** twenty20-xss-patch v1.0.2
