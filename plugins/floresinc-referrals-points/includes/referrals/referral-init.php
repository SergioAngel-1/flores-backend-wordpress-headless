<?php
/**
 * Inicialización del sistema de referidos
 * 
 * Funciones para inicializar el sistema de referidos y registrar hooks.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar sistema de referidos
 */
function floresinc_rp_init_referrals() {
    // Generar código de referido al crear usuario
    add_action('user_register', 'floresinc_rp_generate_referral_code', 10);
    // Procesar la relación de referidos después de generar el código
    add_action('user_register', 'floresinc_rp_process_referral_relationship', 20);
    
    // Añadir shortcode para mostrar enlaces de referido
    add_shortcode('floresinc_referral_link', 'floresinc_rp_referral_link_shortcode');
    
    // Gestionar cookies de referido
    add_action('init', 'floresinc_rp_track_referral');
    
    // Registrar endpoint para obtener información del referidor por código
    add_action('rest_api_init', 'floresinc_rp_register_referrer_endpoint');
}
