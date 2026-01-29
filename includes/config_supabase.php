<?php
// =============================================
// config_supabase.php - Configuración Supabase
// =============================================

// Supabase credentials
define('SUPABASE_URL', 'https://ikajregqluhbqwogtaiq.supabase.co');
define('SUPABASE_KEY', 'sb_publishable_82ZBhRFYW4H1ziu-D08HZg_tgBWMfpM');

// Headers comunes
function supabaseHeaders() {
    return [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ];
}
/*
para la conexiona supabase todo los archivos se deben de cambiar por que todos dependian de get conection por lo tanto 
no iban a poder hacer las funciones ya hechas anteriormente por lo tanto seguiremos el proyecto en mysql que ya esta bastante avaznzado 

*/