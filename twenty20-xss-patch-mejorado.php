<?php
/**
 * Plugin Name: Twenty20 XSS Patch - Versión Mejorada
 * Plugin URI:  https://mantenimiento.ayudawp.com
 * Description: Parche completo para la vulnerabilidad XSS del plugin Twenty20 Image Before-After <= 2.0.4. Sanea TODOS los atributos del shortcode incluyendo campos de texto.
 * Version:     2.0.0
 * Author:      Fernando Tellado (Mejorado por Claude AI)
 * Author URI:  https://ayudawp.com
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: twenty20-xss-patch
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Función para sanear completamente los atributos del shortcode Twenty20
 *
 * Esta versión mejorada sanitiza TODOS los parámetros, incluyendo:
 * - Campos de texto libre (before, after, before_label, after_label)
 * - Imágenes (img1, img2)
 * - Parámetros numéricos (offset, width)
 * - Parámetros de configuración (orientation, align)
 * - Flags booleanos
 *
 * @param array $out   Atributos del shortcode procesados
 * @param array $pairs Pares de atributos por defecto
 * @param array $atts  Atributos originales del shortcode
 * @return array Atributos sanitizados
 */
add_filter( 'shortcode_atts_twenty20', function( $out, $pairs, $atts ) {

    // ========================================
    // SANITIZACIÓN DE IMÁGENES (img1, img2)
    // ========================================
    // Pueden ser IDs numéricos o URLs
    foreach ( ['img1', 'img2'] as $key ) {
        if ( isset( $out[$key] ) ) {
            if ( is_numeric( $out[$key] ) ) {
                // Si es numérico, convertir a entero positivo
                $out[$key] = absint( $out[$key] );
            } else {
                // Si es URL, escapar y validar
                $out[$key] = esc_url_raw( $out[$key] );
            }
        }
    }

    // ========================================
    // SANITIZACIÓN DE CAMPOS DE TEXTO
    // ========================================
    // CRÍTICO: Estos son los vectores principales de XSS
    // Sanitizar todos los campos de texto libre
    $text_fields = [
        'before',        // Texto "antes"
        'after',         // Texto "después"
        'before_label',  // Etiqueta "antes"
        'after_label',   // Etiqueta "después"
    ];

    foreach ( $text_fields as $field ) {
        if ( isset( $out[$field] ) ) {
            // sanitize_text_field elimina todas las etiquetas HTML y scripts
            $out[$field] = sanitize_text_field( $out[$field] );
        }
    }

    // ========================================
    // SANITIZACIÓN DE PARÁMETROS NUMÉRICOS
    // ========================================

    // Offset: debe ser un número decimal entre 0 y 1
    if ( isset( $out['offset'] ) ) {
        $out['offset'] = floatval( $out['offset'] );
        // Asegurar que está en el rango válido
        $out['offset'] = max( 0, min( 1, $out['offset'] ) );
    }

    // Width: puede ser numérico o con unidad (px, %, em, etc.)
    if ( isset( $out['width'] ) ) {
        // Sanitizar como texto primero para eliminar scripts
        $width = sanitize_text_field( $out[$width] );

        // Validar formato válido para width (números y unidades CSS válidas)
        if ( preg_match( '/^(\d+\.?\d*)(px|%|em|rem|vw|vh)?$/', $width, $matches ) ) {
            $out['width'] = $width;
        } else {
            // Si no es válido, usar valor por defecto
            $out['width'] = '100%';
        }
    }

    // ========================================
    // SANITIZACIÓN POR LISTA BLANCA
    // ========================================

    // Orientation: solo valores permitidos
    if ( isset( $out['orientation'] ) ) {
        $allowed_orientations = ['horizontal', 'vertical'];
        if ( ! in_array( $out['orientation'], $allowed_orientations, true ) ) {
            $out['orientation'] = 'horizontal'; // Valor por defecto seguro
        }
    }

    // Align: sanitizar como clase HTML
    if ( isset( $out['align'] ) ) {
        $out['align'] = sanitize_html_class( $out['align'] );
    }

    // ========================================
    // SANITIZACIÓN DE FLAGS BOOLEANOS
    // ========================================
    $boolean_flags = [
        'move_slider_on_hover',
        'move_with_handle_only',
        'click_to_move',
        'no_overlay',
    ];

    foreach ( $boolean_flags as $flag ) {
        if ( isset( $out[$flag] ) ) {
            // Normalizar a string 'true' o 'false'
            $out[$flag] = (
                $out[$flag] === 'true' ||
                $out[$flag] === true ||
                $out[$flag] === 1 ||
                $out[$flag] === '1'
            ) ? 'true' : 'false';
        }
    }

    // ========================================
    // SANITIZACIÓN DE OTROS PARÁMETROS
    // ========================================

    // Hover: puede ser numérico (milisegundos)
    if ( isset( $out['hover'] ) ) {
        $out['hover'] = absint( $out['hover'] );
    }

    // Direction: validar valores permitidos
    if ( isset( $out['direction'] ) ) {
        $allowed_directions = ['left', 'right', 'up', 'down'];
        if ( ! in_array( $out['direction'], $allowed_directions, true ) ) {
            $out['direction'] = 'left';
        }
    }

    /**
     * Registrar intento de XSS si se detectan patrones sospechosos
     *
     * Esto ayuda a identificar intentos de ataque en los logs
     */
    foreach ( $atts as $key => $value ) {
        if ( is_string( $value ) ) {
            // Detectar patrones comunes de XSS
            $xss_patterns = [
                '/<script[^>]*>.*?<\/script>/is',
                '/javascript:/i',
                '/on\w+\s*=/i', // onclick, onload, etc.
                '/<iframe/i',
                '/eval\s*\(/i',
            ];

            foreach ( $xss_patterns as $pattern ) {
                if ( preg_match( $pattern, $value ) ) {
                    // Registrar en el log de errores de WordPress
                    if ( function_exists( 'error_log' ) ) {
                        error_log( sprintf(
                            '[Twenty20 XSS Patch] Posible intento XSS detectado en parámetro "%s". Valor sanitizado.',
                            $key
                        ) );
                    }
                    break;
                }
            }
        }
    }

    return $out;

}, 10, 3 );

/**
 * Añadir notificación en el admin si el plugin original no está actualizado
 */
add_action( 'admin_notices', function() {

    // Verificar si el plugin Twenty20 está activo
    if ( ! is_plugin_active( 'twenty20/twenty20.php' ) ) {
        return;
    }

    // Obtener información del plugin
    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/twenty20/twenty20.php' );
    $version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0';

    // Si la versión es vulnerable (2.0.4 o menor)
    if ( version_compare( $version, '2.0.4', '<=' ) ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Twenty20 XSS Patch:</strong>
                El plugin Twenty20 Image Before-After (versión <?php echo esc_html( $version ); ?>)
                tiene una vulnerabilidad XSS conocida. Este parche está activo y protegiendo tu sitio.
                <br>
                <em>Nota: Este es un parche temporal. Contacta con el desarrollador del plugin para
                obtener una versión oficial actualizada.</em>
            </p>
        </div>
        <?php
    } else {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>Twenty20 XSS Patch:</strong>
                Parece que tienes una versión actualizada de Twenty20 (<?php echo esc_html( $version ); ?>).
                Verifica si este parche sigue siendo necesario.
            </p>
        </div>
        <?php
    }
});
