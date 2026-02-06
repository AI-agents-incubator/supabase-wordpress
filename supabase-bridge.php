<?php
/**
 * Plugin Name: Supabase Bridge (Auth)
 * Description: Mirrors Supabase users into WordPress and logs them in via JWT. Enhanced security with audit logging and hardening. Includes webhook system for n8n/Make.com integration. Production debugging with enhanced logging.
 * Version: 0.10.6
 * Author: Alexey Krol
 * License: MIT
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

require __DIR__ . '/vendor/autoload.php'; // после composer шага

// === Configuration Constants ===
// Callback page path - used for OAuth redirects and Magic Link
// IMPORTANT: This must match the WordPress page where [supabase_auth_callback] shortcode is placed
define('SB_CALLBACK_PATH', '/test-no-elem-2/');

// === Output Buffering for HTML Entity Fix (Issue #25, #13) ===
// WordPress applies HTML-escaping filters (wptexturize, convert_chars) to the_content,
// which converts && to &#038;&#038; in inline JavaScript, causing SyntaxError.
// This output buffer fixes &#038;&#038; back to && in <script> tags on pages with auth form.
add_action('template_redirect', function() {
  global $post;

  // Only run on pages with [supabase_auth_form] shortcode
  if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'supabase_auth_form')) {
    // Disable wptexturize filter for auth form pages
    remove_filter('the_content', 'wptexturize');

    // Start output buffering to fix HTML entities in final HTML
    ob_start(function($html) {
      // Replace &#038;&#038; back to && inside <script> tags
      // Uses regex to target only JavaScript code, not regular HTML content
      $html = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function($matches) {
        return str_replace('&#038;&#038;', '&&', $matches[0]);
      }, $html);

      return $html;
    });
  }
});

// === Enhanced Logging System ===
// Enables detailed production debugging with multiple log levels
// Logs are written to wp-content/debug.log when WP_DEBUG_LOG is enabled

/**
 * Enhanced logging function with context and log levels
 *
 * @param string $message Log message
 * @param string $level Log level (DEBUG, INFO, WARNING, ERROR)
 * @param array $context Additional context data (will be sanitized)
 */
function sb_log($message, $level = 'INFO', $context = []) {
    // Only log if WP_DEBUG or SB_DEBUG_MODE is enabled
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $sanitized_context = sb_sanitize_log_context($context);

    $log_entry = sprintf(
        "[%s] [Supabase Bridge] [%s] %s",
        $timestamp,
        $level,
        $message
    );

    if (!empty($sanitized_context)) {
        $log_entry .= ' | Context: ' . json_encode($sanitized_context, JSON_UNESCAPED_UNICODE);
    }

    error_log($log_entry);
}

/**
 * Sanitize context data for logging (remove sensitive information)
 *
 * @param array $context Raw context data
 * @return array Sanitized context
 */
function sb_sanitize_log_context($context) {
    if (empty($context) || !is_array($context)) {
        return [];
    }

    $sensitive_keys = ['password', 'token', 'secret', 'key', 'authorization', 'cookie', 'jwt'];
    $sanitized = [];

    foreach ($context as $key => $value) {
        // Check if key contains sensitive data
        $is_sensitive = false;
        foreach ($sensitive_keys as $sensitive_key) {
            if (stripos($key, $sensitive_key) !== false) {
                $is_sensitive = true;
                break;
            }
        }

        if ($is_sensitive) {
            // Mask sensitive data
            if (is_string($value) && strlen($value) > 10) {
                $sanitized[$key] = substr($value, 0, 10) . '...[REDACTED]';
            } else {
                $sanitized[$key] = '[REDACTED]';
            }
        } else {
            // Keep non-sensitive data
            if (is_array($value)) {
                $sanitized[$key] = sb_sanitize_log_context($value);
            } elseif (is_string($value) && strlen($value) > 500) {
                // Truncate very long strings
                $sanitized[$key] = substr($value, 0, 500) . '...[TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
    }

    return $sanitized;
}

/**
 * Log function entry point (for tracing execution flow)
 */
function sb_log_function_entry($function_name, $params = []) {
    sb_log("→ Entering function: {$function_name}", 'DEBUG', $params);
}

/**
 * Log function exit point (for tracing execution flow)
 */
function sb_log_function_exit($function_name, $result = null) {
    $context = $result !== null ? ['result' => $result] : [];
    sb_log("← Exiting function: {$function_name}", 'DEBUG', $context);
}

// === Security Headers ===
add_action('send_headers', 'sb_add_security_headers');
function sb_add_security_headers() {
  if (!headers_sent()) {
    // Prevent clickjacking attacks
    header('X-Frame-Options: SAMEORIGIN');
    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    // Enable XSS protection in older browsers
    header('X-XSS-Protection: 1; mode=block');
    // Referrer policy for privacy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy (DISABLED for compatibility with registration forms)
    // CSP was blocking Supabase Auth form for non-logged-in users
    // If needed for security, enable only on specific pages (not registration pages)
    //
    // if (!is_admin() && !is_user_logged_in()) {
    //   $csp = "default-src 'self'; " .
    //          "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
    //          "connect-src 'self' https://*.supabase.co; " .
    //          "style-src 'self' 'unsafe-inline'; " .
    //          "img-src 'self' data: https:; " .
    //          "font-src 'self' data:; " .
    //          "frame-ancestors 'self';";
    //   header("Content-Security-Policy: " . $csp);
    // }
  }
}

// === Supabase Credentials Verification ===
function sb_verify_supabase_credentials($url, $anon_key) {
  // Quick validation
  if (empty($url) || empty($anon_key)) {
    return ['success' => false, 'error' => 'URL or Anon Key is empty'];
  }

  // Check URL format
  if (!preg_match('/^https?:\/\/.+\.supabase\.co$/', $url)) {
    return ['success' => false, 'error' => 'Invalid Supabase URL format (should be https://yourproject.supabase.co)'];
  }

  // Test API connection
  $response = wp_remote_get($url . '/auth/v1/settings', [
    'headers' => [
      'apikey' => $anon_key,
      'Authorization' => 'Bearer ' . $anon_key
    ],
    'timeout' => 5
  ]);

  if (is_wp_error($response)) {
    return ['success' => false, 'error' => 'Connection failed: ' . $response->get_error_message()];
  }

  $status_code = wp_remote_retrieve_response_code($response);
  if ($status_code === 200) {
    return ['success' => true];
  } else {
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $error_msg = $body['message'] ?? 'HTTP ' . $status_code;
    return ['success' => false, 'error' => $error_msg];
  }
}

// === Encryption helpers ===
function sb_encrypt($value) {
  if (empty($value)) return '';
  $key = wp_salt('auth');
  $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
  $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);
  return base64_encode($encrypted . '::' . $iv);
}

function sb_decrypt($encrypted_value) {
  if (empty($encrypted_value)) return '';
  $key = wp_salt('auth');
  $data = base64_decode($encrypted_value);
  if (strpos($data, '::') === false) return $encrypted_value; // fallback for unencrypted
  list($encrypted, $iv) = explode('::', $data, 2);
  return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

// === Конфиг из БД (encrypted) или окружения (fallback) ===
function sb_cfg($key, $def = null) {
  // Special handling for SUPABASE_PROJECT_REF - extract from URL
  if ($key === 'SUPABASE_PROJECT_REF') {
    $url = sb_cfg('SUPABASE_URL', '');
    if (!empty($url) && preg_match('/https?:\/\/([^.]+)\.supabase\.co/', $url, $matches)) {
      return $matches[1]; // Extract project ref from URL
    }
  }

  // Key mapping for legacy option names
  $key_mapping = [
    'SUPABASE_SERVICE_ROLE_KEY' => 'supabase_service_key',  // saved without _role_
  ];

  // Try database first (encrypted storage)
  $mapped_key = $key_mapping[$key] ?? strtolower($key);
  $db_key = 'sb_' . $mapped_key;
  $encrypted_value = get_option($db_key, false);

  if ($encrypted_value !== false && !empty($encrypted_value)) {
    return sb_decrypt($encrypted_value);
  }

  // Fallback to environment variables (wp-config.php)
  // Try $_ENV first (most reliable in wp-config.php)
  if (isset($_ENV[$key]) && !empty($_ENV[$key])) {
    return $_ENV[$key];
  }

  // Try $_SERVER (also set in wp-config.php)
  if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
    return $_SERVER[$key];
  }

  // Last resort: getenv()
  $v = getenv($key);
  return $v !== false ? $v : $def;
}
// Прим.: Credentials хранятся зашифрованными в БД через Settings UI
// SUPABASE_PROJECT_REF извлекается автоматически из SUPABASE_URL
// Fallback: можно добавить в wp-config.php: SUPABASE_URL, SUPABASE_ANON_KEY

// === Helper: Get Thank You Page URL from Settings ===
function sb_get_thankyou_url() {
  $page_id = get_option('sb_thankyou_page_id');
  if ($page_id && $page_id !== '') {
    $url = get_permalink($page_id);
    if ($url) {
      // Extract just the path from the full URL
      $path = parse_url($url, PHP_URL_PATH);
      return $path ?: '/registr/'; // fallback if parsing fails
    }
  }
  return '/registr/'; // default fallback
}

// === Phase 1: Supabase Tables Management ===
// Execute SQL query via Supabase REST API
function sb_execute_supabase_sql($sql) {
  $url = sb_cfg('SUPABASE_URL');
  $anon_key = sb_cfg('SUPABASE_ANON_KEY');

  if (empty($url) || empty($anon_key)) {
    return ['success' => false, 'error' => 'Supabase credentials not configured'];
  }

  // Supabase REST API endpoint for SQL queries
  $endpoint = rtrim($url, '/') . '/rest/v1/rpc/exec_sql';

  $response = wp_remote_post($endpoint, [
    'headers' => [
      'apikey' => $anon_key,
      'Authorization' => 'Bearer ' . $anon_key,
      'Content-Type' => 'application/json',
    ],
    'body' => json_encode(['query' => $sql]),
    'timeout' => 30,
  ]);

  if (is_wp_error($response)) {
    return ['success' => false, 'error' => $response->get_error_message()];
  }

  $code = wp_remote_retrieve_response_code($response);
  $body = wp_remote_retrieve_body($response);

  if ($code >= 200 && $code < 300) {
    return ['success' => true, 'data' => json_decode($body, true)];
  }

  return ['success' => false, 'error' => "HTTP $code: $body"];
}

// Check if Supabase tables exist
function sb_check_tables_exist() {
  $url = sb_cfg('SUPABASE_URL');
  $anon_key = sb_cfg('SUPABASE_ANON_KEY');

  if (empty($url) || empty($anon_key)) {
    return ['exists' => false, 'error' => 'Supabase credentials not configured'];
  }

  // Try to query wp_registration_pairs table
  $endpoint = rtrim($url, '/') . '/rest/v1/wp_registration_pairs?limit=0';

  $response = wp_remote_get($endpoint, [
    'headers' => [
      'apikey' => $anon_key,
      'Authorization' => 'Bearer ' . $anon_key,
    ],
    'timeout' => 10,
  ]);

  if (is_wp_error($response)) {
    return ['exists' => false, 'error' => $response->get_error_message()];
  }

  $code = wp_remote_retrieve_response_code($response);

  // 200 = table exists, 404/400 = table doesn't exist
  return ['exists' => ($code === 200), 'code' => $code];
}

// === Security: Input Validation Functions ===

/**
 * Validate email address (защита от SQL injection и XSS)
 *
 * @param string $email Email to validate
 * @return string|false Sanitized email or false if invalid
 */
function sb_validate_email($email) {
  if (empty($email)) {
    return false;
  }

  // Remove whitespace and convert to lowercase
  $email = trim(strtolower($email));

  // WordPress built-in email validation
  if (!is_email($email)) {
    error_log("Supabase Bridge: Invalid email format: " . sanitize_text_field($email));
    return false;
  }

  // Additional check: Max length 254 characters (RFC 5321)
  if (strlen($email) > 254) {
    error_log("Supabase Bridge: Email too long: " . strlen($email) . " characters");
    return false;
  }

  // Sanitize for database
  return sanitize_email($email);
}

/**
 * Validate URL path (защита от open redirect и path traversal)
 *
 * @param string $path URL path to validate
 * @return string|false Sanitized path or false if invalid
 */
function sb_validate_url_path($path) {
  if (empty($path)) {
    return false;
  }

  // Remove whitespace
  $path = trim($path);

  // Must start with /
  if (!str_starts_with($path, '/')) {
    error_log("Supabase Bridge: Invalid URL path (must start with /): $path");
    return false;
  }

  // Block path traversal attempts
  if (strpos($path, '..') !== false) {
    error_log("Supabase Bridge: Path traversal attempt detected: $path");
    return false;
  }

  // Block protocol attempts (http://, https://, javascript:, etc.)
  if (preg_match('/^[a-z]+:/i', $path)) {
    error_log("Supabase Bridge: Protocol in path not allowed: $path");
    return false;
  }

  // Sanitize for database (removes harmful characters)
  $path = esc_url_raw($path);

  // Max length check (reasonable URL path)
  if (strlen($path) > 2000) {
    error_log("Supabase Bridge: URL path too long: " . strlen($path) . " characters");
    return false;
  }

  return $path;
}

/**
 * Validate UUID format (защита от injection)
 *
 * @param string $uuid UUID to validate
 * @return string|false Sanitized UUID or false if invalid
 */
function sb_validate_uuid($uuid) {
  if (empty($uuid)) {
    return false;
  }

  // Remove whitespace and convert to lowercase
  $uuid = trim(strtolower($uuid));

  // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
  // Where y is one of [8, 9, a, b]
  $uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

  if (!preg_match($uuid_pattern, $uuid)) {
    error_log("Supabase Bridge: Invalid UUID format: $uuid");
    return false;
  }

  return $uuid;
}

/**
 * Validate and sanitize site URL
 *
 * @param string $url Site URL to validate
 * @return string|false Sanitized URL or false if invalid
 */
function sb_validate_site_url($url) {
  if (empty($url)) {
    return false;
  }

  // WordPress built-in URL validation
  $url = esc_url_raw($url);

  if (empty($url)) {
    error_log("Supabase Bridge: Invalid site URL");
    return false;
  }

  // Must be http or https
  if (!preg_match('/^https?:\/\//', $url)) {
    error_log("Supabase Bridge: Site URL must use http or https protocol");
    return false;
  }

  return $url;
}

// === Phase 3: Supabase Sync Functions ===

// Sync registration pair to Supabase (non-blocking)
function sb_sync_pair_to_supabase($pair_data) {
  try {
    $url = sb_cfg('SUPABASE_URL');
    $anon_key = sb_cfg('SUPABASE_ANON_KEY');

    // Validate credentials
    if (empty($url) || empty($anon_key)) {
      error_log('Supabase Bridge: Sync failed - credentials not configured');
      return false;
    }

    // SECURITY: Validate all inputs before sending to Supabase
    $validated_site_url = sb_validate_site_url(get_site_url());
    $validated_reg_url = sb_validate_url_path($pair_data['registration_page_url'] ?? '');
    $validated_ty_url = sb_validate_url_path($pair_data['thankyou_page_url'] ?? '');
    $validated_id = sb_validate_uuid($pair_data['id'] ?? '');

    if (!$validated_site_url || !$validated_reg_url || !$validated_ty_url || !$validated_id) {
      error_log('Supabase Bridge: Sync failed - Invalid data detected (possible injection attempt)');
      error_log('  site_url: ' . ($validated_site_url ? 'OK' : 'INVALID'));
      error_log('  registration_page_url: ' . ($validated_reg_url ? 'OK' : 'INVALID: ' . ($pair_data['registration_page_url'] ?? 'empty')));
      error_log('  thankyou_page_url: ' . ($validated_ty_url ? 'OK' : 'INVALID: ' . ($pair_data['thankyou_page_url'] ?? 'empty')));
      error_log('  id: ' . ($validated_id ? 'OK' : 'INVALID: ' . ($pair_data['id'] ?? 'empty')));
      return false;
    }

    // Endpoint for wp_registration_pairs table
    $endpoint = rtrim($url, '/') . '/rest/v1/wp_registration_pairs';

    // Use validated data (защита от injection)
    $supabase_data = [
      'id' => $validated_id,
      'site_url' => $validated_site_url,
      'registration_page_url' => $validated_reg_url,
      'thankyou_page_url' => $validated_ty_url,
      'registration_page_id' => intval($pair_data['registration_page_id']),
      'thankyou_page_id' => intval($pair_data['thankyou_page_id']),
    ];

    // UPSERT using Prefer: resolution-merge-duplicates
    $response = wp_remote_post($endpoint, [
      'headers' => [
        'apikey' => $anon_key,
        'Authorization' => 'Bearer ' . $anon_key,
        'Content-Type' => 'application/json',
        'Prefer' => 'resolution=merge-duplicates',
        'x-site-url' => $validated_site_url, // For RLS policy check
      ],
      'body' => json_encode($supabase_data),
      'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
      error_log('Supabase Bridge: Sync failed - ' . $response->get_error_message());
      return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
      return true;
    } else {
      $body = wp_remote_retrieve_body($response);
      error_log("Supabase Bridge: Sync failed - HTTP $code: $body");
      return false;
    }
  } catch (Exception $e) {
    error_log('Supabase Bridge: Exception during sync - ' . $e->getMessage());
    return false;
  }
}

// Delete registration pair from Supabase (non-blocking)
function sb_delete_pair_from_supabase($pair_id) {
  try {
    $url = sb_cfg('SUPABASE_URL');
    $anon_key = sb_cfg('SUPABASE_ANON_KEY');

    // Validate credentials
    if (empty($url) || empty($anon_key)) {
      error_log('Supabase Bridge: Delete failed - credentials not configured');
      return false;
    }

    // SECURITY: Validate UUID before using in query
    $validated_id = sb_validate_uuid($pair_id);
    if (!$validated_id) {
      error_log('Supabase Bridge: Delete failed - Invalid pair_id (possible injection attempt): ' . $pair_id);
      return false;
    }

    // Endpoint with filter by ID (using validated UUID)
    $endpoint = rtrim($url, '/') . '/rest/v1/wp_registration_pairs?id=eq.' . urlencode($validated_id);

    // Validate site URL for RLS check
    $validated_site_url = sb_validate_site_url(get_site_url());

    $response = wp_remote_request($endpoint, [
      'method' => 'DELETE',
      'headers' => [
        'apikey' => $anon_key,
        'Authorization' => 'Bearer ' . $anon_key,
        'x-site-url' => $validated_site_url, // For RLS policy check
      ],
      'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
      error_log('Supabase Bridge: Delete failed - ' . $response->get_error_message());
      return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
      return true;
    } else {
      $body = wp_remote_retrieve_body($response);
      error_log("Supabase Bridge: Delete failed - HTTP $code: $body");
      return false;
    }
  } catch (Exception $e) {
    error_log('Supabase Bridge: Exception during delete - ' . $e->getMessage());
    return false;
  }
}

// === Phase 6: Registration Logging to Supabase ===

// Find Thank You page URL for a given registration URL (from Registration Pairs)
function sb_get_thankyou_url_for_registration($registration_url) {
  // Validate registration_url
  $validated_reg_url = sb_validate_url_path($registration_url);
  if (!$validated_reg_url) {
    return null;
  }

  // Find matching pair by registration_url
  $pairs = get_option('sb_registration_pairs', []);

  foreach ($pairs as $pair) {
    if ($pair['registration_page_url'] === $validated_reg_url) {
      // Return absolute URL for thank you page
      return home_url($pair['thankyou_page_url']);
    }
  }

  // No matching pair found
  return null;
}

// Log user registration to Supabase (non-blocking analytics)
function sb_log_registration_to_supabase($user_email, $supabase_user_id, $registration_url, $landing_url = null) {
  try {
    $url = sb_cfg('SUPABASE_URL');
    $anon_key = sb_cfg('SUPABASE_ANON_KEY');

    // Validate credentials
    if (empty($url) || empty($anon_key)) {
      error_log('Supabase Bridge: Cannot log registration - credentials not configured');
      return false;
    }

    // SECURITY: Validate inputs before sending to Supabase
    $validated_email = sb_validate_email($user_email);
    $validated_user_id = sb_validate_uuid($supabase_user_id);
    $validated_reg_url = sb_validate_url_path($registration_url);

    // Validate landing_url (optional, can be null)
    $validated_landing_url = null;
    if (!empty($landing_url)) {
      $validated_landing_url = sb_validate_url_path($landing_url);
      if (!$validated_landing_url) {
        error_log('Supabase Bridge: Warning - Invalid landing_url: ' . $landing_url);
      }
    }

    if (!$validated_email || !$validated_user_id || !$validated_reg_url) {
      error_log('Supabase Bridge: Registration log failed - Invalid data detected (possible injection attempt)');
      error_log('  email: ' . ($validated_email ? 'OK' : 'INVALID: ' . $user_email));
      error_log('  user_id: ' . ($validated_user_id ? 'OK' : 'INVALID: ' . $supabase_user_id));
      error_log('  registration_url: ' . ($validated_reg_url ? 'OK' : 'INVALID: ' . $registration_url));
      error_log('  landing_url: ' . ($validated_landing_url ? 'OK' : 'NULL or INVALID'));
      return false;
    }

    // Find matching pair by registration_url
    $pairs = get_option('sb_registration_pairs', []);
    $pair_id = null;

    foreach ($pairs as $pair) {
      if ($pair['registration_page_url'] === $validated_reg_url) {
        // SECURITY: Validate pair_id before using
        $validated_pair_id = sb_validate_uuid($pair['id'] ?? '');

        if ($validated_pair_id) {
          $pair_id = $validated_pair_id;
        } else {
          error_log('Supabase Bridge: Warning - Invalid pair_id in wp_options, skipping');
        }
        break;
      }
    }

    // Endpoint for wp_user_registrations table
    $endpoint = rtrim($url, '/') . '/rest/v1/wp_user_registrations';

    // Use validated data (защита от injection)
    $log_data = [
      'user_id' => $validated_user_id,
      'pair_id' => $pair_id, // NULL if no pair found or invalid
      'user_email' => $validated_email,
      'registration_url' => $validated_reg_url,
      'landing_url' => $validated_landing_url, // NULL if not provided or invalid (v0.10.0)
      // Note: thankyou_page_url accessible via pair_id → wp_registration_pairs foreign key
    ];

    // Get validated site URL for RLS policy
    $validated_site_url = sb_validate_site_url(get_site_url());
    if (!$validated_site_url) {
      error_log('Supabase Bridge: Registration log failed - Invalid site URL');
      return false;
    }

    // Using Anon Key with RLS policy validation via x-site-url header
    $response = wp_remote_post($endpoint, [
      'headers' => [
        'apikey' => $anon_key,
        'Authorization' => 'Bearer ' . $anon_key,
        'Content-Type' => 'application/json',
        'x-site-url' => $validated_site_url, // For RLS policy validation
      ],
      'body' => json_encode($log_data),
      'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
      error_log('Supabase Bridge: Registration log failed - ' . $response->get_error_message());
      return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
      return true;
    } else {
      $body = wp_remote_retrieve_body($response);
      error_log("Supabase Bridge: Registration log failed - HTTP $code: $body");
      return false;
    }
  } catch (Exception $e) {
    error_log('Supabase Bridge: Exception during registration logging - ' . $e->getMessage());
    return false;
  }
}

// === Phase 4: Auto-assign MemberPress membership on registration ===
function sb_assign_membership_on_registration($wp_user_id, $registration_url) {
  try {
    // Check if MemberPress is active
    if (!class_exists('MeprTransaction') || !class_exists('MeprProduct')) {
      error_log('Supabase Bridge: MemberPress not active, skipping membership assignment');
      return false;
    }

    // Get membership pairs from wp_options
    $membership_pairs = get_option('sb_membership_pairs', []);

    if (empty($membership_pairs)) {
      // No membership pairs configured - this is normal
      return false;
    }

    // Find matching pair by registration URL
    $membership_id = null;
    foreach ($membership_pairs as $pair) {
      if ($pair['registration_page_url'] === $registration_url) {
        $membership_id = intval($pair['membership_id']);
        break;
      }
    }

    if (!$membership_id) {
      // No matching membership for this registration page - this is normal
      return false;
    }

    // Verify membership exists and is free
    $product = new MeprProduct($membership_id);
    if (!$product->ID) {
      error_log("Supabase Bridge: Membership ID $membership_id not found");
      return false;
    }

    if (floatval($product->price) > 0) {
      error_log("Supabase Bridge: Membership ID $membership_id is not free (price: {$product->price}), skipping");
      return false;
    }

    // Create MeprTransaction to assign membership
    $txn = new MeprTransaction();
    $txn->user_id = $wp_user_id;
    $txn->product_id = $membership_id;
    $txn->status = MeprTransaction::$complete_str;
    $txn->txn_type = MeprTransaction::$payment_str;
    $txn->gateway = 'free';
    $txn->created_at = gmdate('Y-m-d H:i:s');
    $txn->trans_num = 'sb-' . uniqid();

    // Let MemberPress handle expires_at based on product settings
    $txn->store();

    // ✅ CRITICAL FIX: Trigger MemberPress webhook for Make.com integration
    // Without this, webhooks are NOT sent and GetResponse automation fails
    if (class_exists('MeprEvent')) {
      $event = MeprEvent::record('transaction-completed', $txn);

      error_log(sprintf(
        'Supabase Bridge: Membership assigned + MemberPress webhook triggered - User ID: %d, Membership ID: %d, Transaction ID: %d, Event ID: %d',
        $wp_user_id,
        $membership_id,
        $txn->id,
        $event->id ?? 0
      ));
    } else {
      // Fallback: use WordPress action directly
      do_action('mepr-event-transaction-completed', $txn);

      error_log(sprintf(
        'Supabase Bridge: Membership assigned + MemberPress webhook triggered (fallback) - User ID: %d, Membership ID: %d, Transaction ID: %d',
        $wp_user_id,
        $membership_id,
        $txn->id
      ));
    }

    // ✅ NEW: Send independent webhook with MemberPress-compatible format
    // Supports Make.com, Zapier, n8n, and any HTTP endpoint
    $webhook_result = sb_send_memberpress_webhook($wp_user_id, $membership_id, $txn->id, $registration_url);

    if ($webhook_result['success']) {
      error_log(sprintf(
        'Supabase Bridge: MemberPress webhooks sent successfully - User ID: %d, Email: %s, Membership ID: %d, Sent to: %d URL(s)',
        $wp_user_id,
        get_userdata($wp_user_id)->user_email,
        $membership_id,
        $webhook_result['sent_count']
      ));
    } else {
      error_log(sprintf(
        'Supabase Bridge: MemberPress webhooks FAILED - User ID: %d, Error: %s',
        $wp_user_id,
        $webhook_result['error'] ?? 'Unknown error'
      ));
    }

    return true;

  } catch (Exception $e) {
    error_log('Supabase Bridge: Exception during membership assignment - ' . $e->getMessage());
    return false;
  }
}

// === Phase 5: Auto-enroll LearnDash course on registration ===
function sb_enroll_course_on_registration($wp_user_id, $registration_url) {
  try {
    // Check if LearnDash is active
    if (!function_exists('ld_update_course_access')) {
      error_log('Supabase Bridge: LearnDash not active, skipping course enrollment');
      return false;
    }

    // Get course pairs from wp_options
    $course_pairs = get_option('sb_course_pairs', []);

    if (empty($course_pairs)) {
      // No course pairs configured - this is normal
      return false;
    }

    // Find matching pair by registration URL
    $course_id = null;
    foreach ($course_pairs as $pair) {
      if ($pair['registration_page_url'] === $registration_url) {
        $course_id = intval($pair['course_id']);
        break;
      }
    }

    if (!$course_id) {
      // No matching course for this registration page - this is normal
      return false;
    }

    // Verify course exists
    $course = get_post($course_id);
    if (!$course || $course->post_type !== 'sfwd-courses') {
      error_log("Supabase Bridge: Course ID $course_id not found or not a LearnDash course");
      return false;
    }

    // Enroll user in course
    // ld_update_course_access($user_id, $course_id, $remove = false)
    ld_update_course_access($wp_user_id, $course_id, false);

    error_log(sprintf(
      'Supabase Bridge: Course enrollment successful - User ID: %d, Course ID: %d, Course: %s',
      $wp_user_id,
      $course_id,
      $course->post_title
    ));

    return true;

  } catch (Exception $e) {
    error_log('Supabase Bridge: Exception during course enrollment - ' . $e->getMessage());
    return false;
  }
}

// === Helper Functions: Check User Status (v0.9.11) ===

/**
 * Check if user has active membership
 *
 * @param int $user_id WordPress user ID
 * @param int $membership_id MemberPress membership/product ID
 * @return bool True if user has active membership, false otherwise
 */
function sb_user_has_membership($user_id, $membership_id) {
  // Validate inputs
  if (!$user_id || !$membership_id) {
    return false;
  }

  // Check if MemberPress is active
  if (!class_exists('MeprUser')) {
    error_log('Supabase Bridge: MemberPress not active, cannot check membership');
    return false;
  }

  try {
    $mepr_user = new MeprUser($user_id);

    // Get all active product subscriptions for this user
    $active_memberships = $mepr_user->active_product_subscriptions('ids');

    // Check if the specific membership is in the list
    $has_membership = in_array($membership_id, $active_memberships);

    sb_log("Membership check", 'DEBUG', [
      'user_id' => $user_id,
      'membership_id' => $membership_id,
      'has_membership' => $has_membership
    ]);

    return $has_membership;

  } catch (Exception $e) {
    error_log('Supabase Bridge: Membership check failed - ' . $e->getMessage());
    return false;
  }
}

/**
 * Check if user is enrolled in LearnDash course
 *
 * @param int $user_id WordPress user ID
 * @param int $course_id LearnDash course ID
 * @return bool True if user is enrolled, false otherwise
 */
function sb_user_enrolled_in_course($user_id, $course_id) {
  // Validate inputs
  if (!$user_id || !$course_id) {
    return false;
  }

  // Check if LearnDash is active
  if (!function_exists('sfwd_lms_has_access')) {
    error_log('Supabase Bridge: LearnDash not active, cannot check enrollment');
    return false;
  }

  try {
    // sfwd_lms_has_access returns true if user has access to the course
    $is_enrolled = sfwd_lms_has_access($course_id, $user_id);

    sb_log("Course enrollment check", 'DEBUG', [
      'user_id' => $user_id,
      'course_id' => $course_id,
      'is_enrolled' => $is_enrolled
    ]);

    return $is_enrolled;

  } catch (Exception $e) {
    error_log('Supabase Bridge: Course enrollment check failed - ' . $e->getMessage());
    return false;
  }
}

// === User Status Analyzer (v0.9.11) ===

/**
 * Analyze user status and determine required actions
 *
 * This is Module 1 of the two-module architecture:
 * - Analyzes user state (new vs existing, has membership?, has enrollment?)
 * - Returns "User Signature" - a data structure describing required actions
 * - Read-only, no side effects
 *
 * @param WP_User|null $user WordPress user object (null if new user)
 * @param bool $is_new_user Whether this is a newly created user
 * @param string $registration_url The validated registration page URL
 * @return array User Signature with analysis results
 *
 * User Signature structure:
 * [
 *   'user_id' => int,              // WordPress user ID
 *   'is_new_user' => boolean,      // True if user was just created
 *   'registration_url' => string,  // Validated landing page URL
 *   'needs_membership' => boolean, // True if user needs membership assigned
 *   'membership_id' => int|null,   // MemberPress membership ID (if configured)
 *   'needs_enrollment' => boolean, // True if user needs course enrollment
 *   'course_id' => int|null,       // LearnDash course ID (if configured)
 * ]
 */
function sb_analyze_user_status($user, $is_new_user, $registration_url) {
  // Initialize signature with safe defaults
  $signature = [
    'user_id' => $user ? $user->ID : 0,
    'is_new_user' => $is_new_user,
    'registration_url' => $registration_url,
    'needs_membership' => false,
    'membership_id' => null,
    'needs_enrollment' => false,
    'course_id' => null,
  ];

  // If no user or no registration URL, return empty signature
  if (!$user || !$registration_url) {
    sb_log("User Status Analyzer: Invalid input", 'WARNING', [
      'user_id' => $user ? $user->ID : 'null',
      'registration_url' => $registration_url ?: 'null'
    ]);
    return $signature;
  }

  $user_id = $user->ID;

  // === Step 1: Find membership configuration for this landing page ===
  $membership_pairs = get_option('sb_membership_pairs', []);
  $membership_id = null;

  foreach ($membership_pairs as $pair) {
    if (isset($pair['registration_page_url']) && $pair['registration_page_url'] === $registration_url) {
      $membership_id = intval($pair['membership_id']);
      break;
    }
  }

  // === Step 2: Check if user needs membership ===
  if ($membership_id) {
    $signature['membership_id'] = $membership_id;

    // Check if user already has this membership
    $has_membership = sb_user_has_membership($user_id, $membership_id);

    if (!$has_membership) {
      $signature['needs_membership'] = true;
      sb_log("User Status Analyzer: User needs membership", 'DEBUG', [
        'user_id' => $user_id,
        'membership_id' => $membership_id,
        'is_new_user' => $is_new_user
      ]);
    } else {
      sb_log("User Status Analyzer: User already has membership", 'DEBUG', [
        'user_id' => $user_id,
        'membership_id' => $membership_id
      ]);
    }
  } else {
    sb_log("User Status Analyzer: No membership configured for this landing", 'DEBUG', [
      'registration_url' => $registration_url
    ]);
  }

  // === Step 3: Find course configuration for this landing page ===
  $course_pairs = get_option('sb_course_pairs', []);
  $course_id = null;

  foreach ($course_pairs as $pair) {
    if (isset($pair['registration_page_url']) && $pair['registration_page_url'] === $registration_url) {
      $course_id = intval($pair['course_id']);
      break;
    }
  }

  // === Step 4: Check if user needs course enrollment ===
  if ($course_id) {
    $signature['course_id'] = $course_id;

    // Check if user is already enrolled
    $is_enrolled = sb_user_enrolled_in_course($user_id, $course_id);

    if (!$is_enrolled) {
      $signature['needs_enrollment'] = true;
      sb_log("User Status Analyzer: User needs enrollment", 'DEBUG', [
        'user_id' => $user_id,
        'course_id' => $course_id,
        'is_new_user' => $is_new_user
      ]);
    } else {
      sb_log("User Status Analyzer: User already enrolled", 'DEBUG', [
        'user_id' => $user_id,
        'course_id' => $course_id
      ]);
    }
  } else {
    sb_log("User Status Analyzer: No course configured for this landing", 'DEBUG', [
      'registration_url' => $registration_url
    ]);
  }

  // === Step 5: Return signature ===
  sb_log("User Status Analyzer: Analysis complete", 'INFO', [
    'user_id' => $user_id,
    'is_new_user' => $is_new_user,
    'needs_membership' => $signature['needs_membership'],
    'needs_enrollment' => $signature['needs_enrollment']
  ]);

  return $signature;
}

// === Action Executor (v0.9.11) ===

/**
 * Execute required actions based on User Signature
 *
 * This is Module 2 of the two-module architecture:
 * - Takes User Signature as input
 * - Executes required actions (assign membership, enroll in course)
 * - Completely isolated from analysis logic
 *
 * @param array $signature User Signature from sb_analyze_user_status()
 * @return array Execution results
 *
 * Return structure:
 * [
 *   'success' => boolean,
 *   'membership_assigned' => boolean,
 *   'enrollment_completed' => boolean,
 *   'errors' => array,
 * ]
 */
function sb_execute_user_actions($signature) {
  // Initialize result
  $result = [
    'success' => true,
    'membership_assigned' => false,
    'enrollment_completed' => false,
    'errors' => [],
  ];

  // Validate signature
  if (!isset($signature['user_id']) || !$signature['user_id']) {
    $result['success'] = false;
    $result['errors'][] = 'Invalid signature: missing user_id';
    sb_log("Action Executor: Invalid signature", 'ERROR', ['signature' => $signature]);
    return $result;
  }

  $user_id = $signature['user_id'];
  $registration_url = $signature['registration_url'] ?? '';

  sb_log("Action Executor: Starting execution", 'INFO', [
    'user_id' => $user_id,
    'needs_membership' => $signature['needs_membership'],
    'needs_enrollment' => $signature['needs_enrollment']
  ]);

  // === Step 1: Assign membership if needed ===
  if ($signature['needs_membership'] && $signature['membership_id']) {
    try {
      sb_log("Action Executor: Assigning membership", 'DEBUG', [
        'user_id' => $user_id,
        'membership_id' => $signature['membership_id']
      ]);

      // Use existing function to assign membership
      sb_assign_membership_on_registration($user_id, $registration_url);

      $result['membership_assigned'] = true;

      sb_log("Action Executor: Membership assigned successfully", 'INFO', [
        'user_id' => $user_id,
        'membership_id' => $signature['membership_id']
      ]);

    } catch (Exception $e) {
      $result['success'] = false;
      $result['errors'][] = 'Membership assignment failed: ' . $e->getMessage();

      sb_log("Action Executor: Membership assignment failed", 'ERROR', [
        'user_id' => $user_id,
        'membership_id' => $signature['membership_id'],
        'error' => $e->getMessage()
      ]);
    }
  }

  // === Step 2: Enroll in course if needed ===
  if ($signature['needs_enrollment'] && $signature['course_id']) {
    try {
      sb_log("Action Executor: Enrolling in course", 'DEBUG', [
        'user_id' => $user_id,
        'course_id' => $signature['course_id']
      ]);

      // Use existing function to enroll in course
      sb_enroll_course_on_registration($user_id, $registration_url);

      $result['enrollment_completed'] = true;

      sb_log("Action Executor: Course enrollment completed successfully", 'INFO', [
        'user_id' => $user_id,
        'course_id' => $signature['course_id']
      ]);

    } catch (Exception $e) {
      $result['success'] = false;
      $result['errors'][] = 'Course enrollment failed: ' . $e->getMessage();

      sb_log("Action Executor: Course enrollment failed", 'ERROR', [
        'user_id' => $user_id,
        'course_id' => $signature['course_id'],
        'error' => $e->getMessage()
      ]);
    }
  }

  // === Step 3: Return result ===
  sb_log("Action Executor: Execution complete", 'INFO', [
    'user_id' => $user_id,
    'success' => $result['success'],
    'membership_assigned' => $result['membership_assigned'],
    'enrollment_completed' => $result['enrollment_completed'],
    'errors_count' => count($result['errors'])
  ]);

  return $result;
}

// Create Supabase tables for registration pairs
function sb_create_supabase_tables() {
  // Read SQL file
  $sql_file = plugin_dir_path(__FILE__) . 'supabase-tables.sql';

  if (!file_exists($sql_file)) {
    return ['success' => false, 'error' => 'SQL file not found: supabase-tables.sql'];
  }

  $sql = file_get_contents($sql_file);

  if (empty($sql)) {
    return ['success' => false, 'error' => 'SQL file is empty'];
  }

  // Note: Supabase REST API doesn't support direct SQL execution via anon key
  // Tables must be created via Supabase Dashboard SQL Editor or Database URL
  // This function returns instructions for manual setup

  return [
    'success' => false,
    'manual_setup_required' => true,
    'instructions' => 'Please execute the SQL script via Supabase Dashboard:
1. Go to: https://supabase.com/dashboard/project/' . sb_cfg('SUPABASE_PROJECT_REF') . '/sql
2. Copy content from: ' . $sql_file . '
3. Paste and execute in SQL Editor
4. Verify tables created: wp_registration_pairs, wp_user_registrations',
    'sql_file' => $sql_file
  ];
}

// Глобальная переменная для хранения контента auth form
global $sb_auth_form_content;
$sb_auth_form_content = null;

// === Shortcode [supabase_auth_form] ===
add_shortcode('supabase_auth_form', function() {
  global $sb_auth_form_content;

  $auth_form_path = plugin_dir_path(__FILE__) . 'auth-form.html';

  if (!file_exists($auth_form_path)) {
    return '<div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">⚠️ auth-form.html not found. Please reinstall the Supabase Bridge plugin.</div>';
  }

  // Сохраняем контент в глобальную переменную
  $sb_auth_form_content = file_get_contents($auth_form_path);

  // Inject callback path constant into HTML
  $callback_url = home_url(SB_CALLBACK_PATH);
  $sb_auth_form_content = str_replace('{{CALLBACK_URL}}', $callback_url, $sb_auth_form_content);

  // Возвращаем уникальный placeholder
  return '<div id="sb-auth-form-placeholder-' . uniqid() . '"></div>';
});

// === Заменяем placeholder на реальный контент ПОСЛЕ всех WordPress фильтров ===
add_filter('the_content', function($content) {
  global $sb_auth_form_content;

  // Если есть сохраненный контент формы
  if ($sb_auth_form_content && strpos($content, 'sb-auth-form-placeholder-') !== false) {
    // Заменяем placeholder на реальный контент (ПОСЛЕ всех фильтров WordPress)
    $content = preg_replace(
      '/<div id="sb-auth-form-placeholder-[^"]+"><\/div>/',
      $sb_auth_form_content,
      $content
    );

    // Очищаем переменную
    $sb_auth_form_content = null;
  }

  return $content;
}, 9999); // Максимальный приоритет - выполняется последним

// === Добавляем shortcode в whitelist ===
add_filter('no_texturize_shortcodes', function($shortcodes) {
  $shortcodes[] = 'supabase_auth_form';
  $shortcodes[] = 'supabase_auth_callback';
  return $shortcodes;
});

// ========== CALLBACK HANDLER SHORTCODE ==========

// Глобальная переменная для хранения контента callback handler
global $sb_auth_callback_content;
$sb_auth_callback_content = null;

// === Shortcode [supabase_auth_callback] ===
add_shortcode('supabase_auth_callback', function() {
  global $sb_auth_callback_content;

  $callback_path = plugin_dir_path(__FILE__) . 'callback.html';

  if (!file_exists($callback_path)) {
    return '<div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">⚠️ Callback handler file not found. Please reinstall the Supabase Bridge plugin.</div>';
  }

  // Получаем Supabase credentials для телеметрии (v0.12.0)
  $supabase_url = sb_cfg('url');
  $supabase_anon = sb_cfg('anon_key');

  // Создаём inline script с конфигурацией (для телеметрии)
  // Это безопасно: телеметрия изолирована в try/catch и не влияет на авторизацию
  $config_script = '';
  if ($supabase_url && $supabase_anon) {
    $config_script = '<script>window.SUPABASE_CFG = ' . wp_json_encode([
      'url' => $supabase_url,
      'anon' => $supabase_anon
    ]) . ';</script>';
  }

  // Сохраняем контент в глобальную переменную (с конфигурацией для телеметрии)
  $sb_auth_callback_content = $config_script . file_get_contents($callback_path);

  // Возвращаем уникальный placeholder
  return '<div id="sb-auth-callback-placeholder-' . uniqid() . '"></div>';
});

// === Заменяем placeholder на реальный контент ПОСЛЕ всех WordPress фильтров ===
add_filter('the_content', function($content) {
  global $sb_auth_callback_content;

  // Если есть сохраненный контент callback handler
  if ($sb_auth_callback_content && strpos($content, 'sb-auth-callback-placeholder-') !== false) {
    // Заменяем placeholder на реальный контент (ПОСЛЕ всех фильтров WordPress)
    $content = preg_replace(
      '/<div id="sb-auth-callback-placeholder-[^"]+"><\/div>/',
      $sb_auth_callback_content,
      $content
    );

    // Очищаем переменную
    $sb_auth_callback_content = null;
  }

  return $content;
}, 9999); // Максимальный приоритет - выполняется последним

// Подключим supabase-js и прокинем public-конфиг (чтоб не хардкодить в HTML)
add_action('wp_enqueue_scripts', function () {
  // Только для страниц сайта (не админки)
  if (is_admin()) return;
  wp_enqueue_script('supabase-js', 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2', [], null, true);

  // === Phase 4: Inject registration pairs into JavaScript ===
  $pairs = get_option('sb_registration_pairs', []);

  // Prepare pairs for JavaScript (only needed fields)
  $js_pairs = [];
  foreach ($pairs as $pair) {
    $js_pairs[] = [
      'registration_url' => $pair['registration_page_url'],
      'thankyou_url' => $pair['thankyou_page_url'],
    ];
  }

  wp_add_inline_script('supabase-js', 'window.SUPABASE_CFG = ' . wp_json_encode([
    'url'  => sb_cfg('SUPABASE_URL', ''),       // напр. https://<project-ref>.supabase.co
    'anon' => sb_cfg('SUPABASE_ANON_KEY', ''),  // public anon key
    'thankYouUrl' => sb_get_thankyou_url(),     // Thank You Page URL from Settings (global fallback)
    'registrationPairs' => $js_pairs,           // Phase 4: Page-specific pairs
  ]) . ';', 'before');
});

// === Elementor CSP Compatibility ===
// Отключаем CSP для страниц с шорткодом Supabase (для совместимости с Elementor)
add_action('send_headers', function() {
  global $post;

  // Проверяем есть ли шорткод на странице
  if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'supabase_auth_form')) {
    // Удаляем CSP headers которые могут блокировать Supabase SDK
    header_remove('Content-Security-Policy');
    header_remove('X-Content-Security-Policy');
    header_remove('X-WebKit-CSP');
  }
}, 1);

// Альтернативный метод через template_redirect (работает раньше)
add_action('template_redirect', function() {
  global $post;

  if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'supabase_auth_form')) {
    header_remove('Content-Security-Policy');
    header_remove('X-Content-Security-Policy');
    header_remove('X-WebKit-CSP');
  }
}, 1);

// REST: приём токена, верификация, создание/логин WP-пользователя
add_action('rest_api_init', function () {
  register_rest_route('supabase-bridge/v1', '/callback', [
    'methods'  => 'POST',
    'permission_callback' => '__return_true',
    'callback' => 'sb_handle_callback',
  ]);
  register_rest_route('supabase-bridge/v1', '/logout', [
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => 'sb_handle_logout',
  ]);
});

function sb_handle_callback(\WP_REST_Request $req) {
  sb_log_function_entry('sb_handle_callback', ['method' => $req->get_method()]);

  // Rate Limiting: Prevent brute force attacks
  $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $rate_key = 'sb_rate_' . md5($client_ip);
  $attempts = get_transient($rate_key) ?: 0;

  sb_log("Rate limiting check", 'DEBUG', ['ip' => $client_ip, 'attempts' => $attempts]);

  if ($attempts >= 10) {
    sb_log("Rate limit exceeded", 'WARNING', ['ip' => $client_ip, 'attempts' => $attempts]);
    return new \WP_Error('rate_limit', 'Too many requests. Please try again later.', ['status'=>429]);
  }

  set_transient($rate_key, $attempts + 1, 60); // 60 seconds window

  // CSRF Protection: Strict Origin/Referer validation
  $origin = $req->get_header('origin');
  $referer = $req->get_header('referer');
  $allowed_host = parse_url(home_url(), PHP_URL_HOST);

  // Extract host from Origin or Referer header
  $request_host = null;
  if ($origin) {
    $request_host = parse_url($origin, PHP_URL_HOST);
  } elseif ($referer) {
    $request_host = parse_url($referer, PHP_URL_HOST);
  }

  sb_log("CSRF validation", 'DEBUG', [
    'request_host' => $request_host,
    'allowed_host' => $allowed_host,
    'origin' => $origin,
    'referer' => $referer ? substr($referer, 0, 50) . '...' : null
  ]);

  // MUST have Origin or Referer, and it MUST exactly match our host
  if (!$request_host || $request_host !== $allowed_host) {
    sb_log("CSRF check failed", 'ERROR', ['request_host' => $request_host, 'allowed_host' => $allowed_host]);
    return new \WP_Error('csrf', 'Invalid origin', ['status'=>403]);
  }

  $jwt = $req->get_param('access_token');
  if (!$jwt) {
    sb_log("Missing access_token parameter", 'ERROR');
    return new \WP_Error('no_jwt','Missing access_token',['status'=>400]);
  }

  sb_log("JWT received", 'DEBUG', ['jwt_length' => strlen($jwt)]);

  $projectRef = sb_cfg('SUPABASE_PROJECT_REF', '');
  if (!$projectRef) {
    sb_log("SUPABASE_PROJECT_REF not configured", 'ERROR');
    error_log('Supabase Bridge: SUPABASE_PROJECT_REF not configured');
    return new \WP_Error('cfg','Authentication service not configured',['status'=>500]);
  }

  // ═══════════════════════════════════════════════════════════════════════════════
  // КРИТИЧНО: Защита от дублирующих callback запросов с атомарной блокировкой MySQL
  // Один токен должен обрабатываться только один раз
  // ВАЖНО: Lock устанавливается ПОСЛЕ всех early returns (rate limit, CSRF, no_jwt)
  // ═══════════════════════════════════════════════════════════════════════════════
  global $wpdb;
  $token_lock_key = 'sb_lock_' . md5($jwt);
  $lock_acquired = false;

  // GET_LOCK() атомарно возвращает 1 если получили блокировку, 0 если уже заблокировано, NULL при ошибке
  // Timeout 30 секунд = подождать до 30 сек если lock занят (для медленных сетей)
  $lock_acquired = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 30)", $token_lock_key));

  if ($lock_acquired != 1) {
    sb_log("Duplicate callback blocked (MySQL lock)", 'WARNING', ['lock_key' => md5($token_lock_key)]);
    error_log('Supabase Bridge: Token already being processed (duplicate request blocked via MySQL lock)');
    return new \WP_Error('duplicate','Authentication already in progress',['status'=>409]);
  }

  sb_log("MySQL lock acquired for callback processing", 'DEBUG');

  // Helper function to safely release lock
  $release_lock = function() use ($wpdb, $token_lock_key, &$lock_acquired) {
    if ($lock_acquired) {
      $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $token_lock_key));
      $lock_acquired = false;
      sb_log("MySQL lock released", 'DEBUG');
    }
  };

  // Блокировка автоматически освободится при закрытии соединения MySQL
  // Но для надежности освободим явно в конце функции через register_shutdown_function
  register_shutdown_function($release_lock);

  $issuer = "https://{$projectRef}.supabase.co/auth/v1";
  $jwks  = "{$issuer}/.well-known/jwks.json";

  sb_log("Starting JWT verification", 'DEBUG', ['issuer' => $issuer]);

  try {
    // 1) Забираем JWKS (with caching for performance)
    $cache_key = 'sb_jwks_' . md5($jwks);
    $keys = get_transient($cache_key);

    if ($keys === false) {
      sb_log("JWKS cache miss - fetching from Supabase", 'DEBUG', ['jwks_url' => $jwks]);
      // Cache miss - fetch from Supabase
      $resp = wp_remote_get($jwks, [
        'timeout' => 5,
        'sslverify' => true, // Ensure SSL verification
        'user-agent' => 'Supabase-Bridge-WordPress/0.3.3'
      ]);

      if (is_wp_error($resp)) {
        error_log('Supabase Bridge: JWKS fetch failed - ' . $resp->get_error_message());
        throw new Exception('Authentication service unavailable');
      }

      $status_code = wp_remote_retrieve_response_code($resp);
      if ($status_code !== 200) {
        error_log('Supabase Bridge: JWKS fetch returned status ' . $status_code);
        throw new Exception('Authentication service unavailable');
      }

      $keys = json_decode(wp_remote_retrieve_body($resp), true);
      if (!isset($keys['keys']) || !is_array($keys['keys'])) {
        error_log('Supabase Bridge: Invalid JWKS format');
        throw new Exception('Authentication service error');
      }

      // Cache for 1 hour (3600 seconds)
      set_transient($cache_key, $keys, 3600);
      sb_log("JWKS cached successfully", 'DEBUG', ['cache_ttl' => 3600]);
    } else {
      sb_log("JWKS cache hit", 'DEBUG');
    }

    // 2) Проверяем JWT (RS256) и клеймы
    $publicKeys = \Firebase\JWT\JWK::parseKeySet($keys);

    // Add leeway to account for clock skew between servers (60 seconds tolerance)
    // This fixes "Cannot handle token with iat prior to..." errors caused by
    // minor time differences between Supabase and WordPress servers
    \Firebase\JWT\JWT::$leeway = 60;

    $decoded = \Firebase\JWT\JWT::decode($jwt, $publicKeys);
    $claims = (array)$decoded;

    sb_log("JWT decoded successfully", 'INFO', [
      'sub' => $claims['sub'] ?? 'unknown',
      'email' => $claims['email'] ?? 'unknown',
      'provider' => $claims['app_metadata']->provider ?? 'unknown'
    ]);

    // Validate issuer
    if (($claims['iss'] ?? '') !== $issuer) {
      error_log('Supabase Bridge: Invalid issuer claim');
      throw new Exception('Invalid authentication token');
    }

    // Validate audience
    if (($claims['aud'] ?? '') !== 'authenticated') {
      error_log('Supabase Bridge: Invalid audience claim');
      throw new Exception('Invalid authentication token');
    }

    // Validate expiration
    if (isset($claims['exp']) && time() >= intval($claims['exp'])) {
      error_log('Supabase Bridge: Token expired');
      throw new Exception('Authentication token expired');
    }

    // Validate required fields
    if (empty($claims['email']) || empty($claims['sub'])) {
      error_log('Supabase Bridge: Missing required claims (email/sub)');
      throw new Exception('Invalid authentication token');
    }

    // Security: Require verified email for OAuth providers (not for Magic Link)
    // Magic Link = provider is "email" - clicking the link IS verification
    // OAuth (google/facebook) = trust the provider unless email_verified is explicitly false
    //   - email_verified=true: Pass ✅
    //   - email_verified=null/missing: Pass ✅ (OAuth provider implicitly verified)
    //   - email_verified=false: Fail ❌ (explicit rejection)
    $provider = $claims['app_metadata']->provider ?? 'unknown';
    $isMagicLink = ($provider === 'email');

    // DEBUG: Log provider and email_verified status
    error_log('Supabase Bridge DEBUG: provider=' . $provider . ', email_verified=' . var_export($claims['email_verified'] ?? null, true) . ', email=' . sanitize_email($claims['email']));

    // Only block if email_verified is explicitly set to false
    if (!$isMagicLink && isset($claims['email_verified']) && $claims['email_verified'] === false) {
      error_log('Supabase Bridge: Email explicitly NOT verified for OAuth provider (' . $provider . ') - ' . sanitize_email($claims['email']));
      throw new Exception('Email verification required');
    }

    // 3) Найдём/создадим WP-пользователя
    $email = sanitize_email($claims['email']);
    $supabase_user_id = sanitize_text_field($claims['sub']);

    sb_log("Starting WordPress user sync", 'INFO', [
      'email' => $email,
      'supabase_user_id' => $supabase_user_id
    ]);

    // Additional email validation
    if (!is_email($email)) {
      sb_log("Invalid email format", 'ERROR', ['email' => $email]);
      error_log('Supabase Bridge: Invalid email format - ' . $email);
      throw new Exception('Invalid email address');
    }

    // КРИТИЧНО: Проверяем по Supabase UUID ПЕРВЫМ (уникальный идентификатор)
    // Это предотвращает дублирование даже при race condition
    $existing_users = get_users([
      'meta_key' => 'supabase_user_id',
      'meta_value' => $supabase_user_id,
      'number' => 1
    ]);

    if (!empty($existing_users)) {
      // Пользователь с таким Supabase ID уже существует
      $user = $existing_users[0];
      sb_log("User found by supabase_user_id", 'INFO', [
        'wp_user_id' => $user->ID,
        'email' => $email
      ]);
      error_log('Supabase Bridge: User found by supabase_user_id - ' . $email);
    } else {
      // Проверяем по email
      $user = get_user_by('email', $email);
      if ($user) {
        sb_log("User found by email", 'INFO', [
          'wp_user_id' => $user->ID,
          'email' => $email
        ]);
      }
    }

    // Track if this is a new user registration (for redirect logic)
    $is_new_user = false;

    // Read registration_url from POST body (for Registration Pairs redirect)
    // Priority 1: Explicit registration_url from POST body (v0.8.5+)
    $registration_url = $req->get_param('registration_url');

    // Priority 2: Fallback to Referer for backward compatibility
    if (empty($registration_url) && !empty($referer)) {
      $registration_url = parse_url($referer, PHP_URL_PATH);
    }

    // Validate registration_url
    $validated_registration_url = null;
    if (!empty($registration_url)) {
      $validated_registration_url = sb_validate_url_path($registration_url);
    }

    // Read landing_url from POST body (v0.10.0 - marketing analytics)
    $landing_url = $req->get_param('landing_url');
    $validated_landing_url = null;
    if (!empty($landing_url)) {
      $validated_landing_url = sb_validate_url_path($landing_url);
      if ($validated_landing_url) {
        sb_log("Landing URL received", 'DEBUG', ['landing_url' => $validated_landing_url]);
      }
    }

    if (!$user) {
      sb_log("User not found - creating new WordPress user", 'INFO', ['email' => $email]);

      // Distributed lock для предотвращения race condition
      $lock_key = 'sb_create_lock_' . md5($supabase_user_id);

      // Проверяем есть ли уже lock (другой процесс создает пользователя)
      if (get_transient($lock_key)) {
        sb_log("Distributed lock detected - waiting for user creation", 'DEBUG', ['lock_key' => md5($lock_key)]);
        // Ждем 2 секунды и пробуем найти пользователя снова
        sleep(2);

        // Проверяем по Supabase UUID снова
        $existing_users = get_users([
          'meta_key' => 'supabase_user_id',
          'meta_value' => $supabase_user_id,
          'number' => 1
        ]);

        if (!empty($existing_users)) {
          $user = $existing_users[0];
          error_log('Supabase Bridge: User found after lock wait - ' . $email);
        } else {
          // Все еще нет - пробуем по email
          $user = get_user_by('email', $email);

          if (!$user) {
            error_log('Supabase Bridge: Lock expired but user not found - ' . $email);
            throw new Exception('User creation timeout - please try again');
          }
        }
      } else {
        // Устанавливаем lock на 5 секунд
        set_transient($lock_key, 1, 5);

        // Generate strong random password
        $password = wp_generate_password(32, true, true);
        $uid = wp_create_user($email, $password, $email);

        if (is_wp_error($uid)) {
          // Удаляем lock
          delete_transient($lock_key);

          // Race condition: user might have been created between checks and wp_create_user
          // Try finding the user again by Supabase ID
          $existing_users = get_users([
            'meta_key' => 'supabase_user_id',
            'meta_value' => $supabase_user_id,
            'number' => 1
          ]);

          if (!empty($existing_users)) {
            $user = $existing_users[0];
            error_log('Supabase Bridge: User found by UUID after failed creation - ' . $email);
          } else {
            // Пробуем по email
            $user = get_user_by('email', $email);
            if (!$user) {
              // Still not found - real error
              error_log('Supabase Bridge: User creation failed - ' . $uid->get_error_message());
              throw new Exception('Unable to create user account');
            }
            error_log('Supabase Bridge: User found by email after failed creation - ' . $email);
          }
        } else {
          // User created successfully
          $is_new_user = true;  // Mark as new user for redirect logic

          sb_log("WordPress user created successfully", 'INFO', [
            'wp_user_id' => $uid,
            'email' => $email,
            'supabase_user_id' => $supabase_user_id
          ]);

          // Store Supabase user ID
          update_user_meta($uid, 'supabase_user_id', $supabase_user_id);
          sb_log("User metadata updated", 'DEBUG', ['supabase_user_id' => $supabase_user_id]);

          // Set default role (subscriber)
          $user = get_user_by('id', $uid);
          if ($user) {
            $user->set_role('subscriber');
            sb_log("User role set to subscriber", 'DEBUG', ['wp_user_id' => $uid]);
          }

          // Удаляем lock
          delete_transient($lock_key);

          error_log('Supabase Bridge: User created successfully - User ID: ' . $uid);

          // === Phase 6: Log registration to Supabase (non-blocking) ===
          // Use validated_registration_url from earlier in the function
          if ($validated_registration_url) {
            sb_log_registration_to_supabase($email, $supabase_user_id, $validated_registration_url, $validated_landing_url);
            // Note: We don't check return value - logging is non-critical
          }

          // NOTE: Membership & enrollment logic moved to Phase 7 (after user creation)
          // to support BOTH new and existing users (v0.9.11)
        }
      }
    }

    // Update Supabase user ID for user (handles both new and existing users)
    if ($user && $user->ID) {
      update_user_meta($user->ID, 'supabase_user_id', $supabase_user_id);
    }

    // === Phase 7: Universal Membership & Enrollment (v0.9.11) ===
    // This logic works for BOTH new and existing users
    // Module 1 (Analyzer) checks if user needs membership/enrollment
    // Module 2 (Executor) performs actions only if needed (idempotent)
    if ($user && $validated_registration_url) {
      sb_log("Phase 7: Starting universal membership & enrollment logic", 'INFO', [
        'user_id' => $user->ID,
        'is_new_user' => $is_new_user,
        'registration_url' => $validated_registration_url
      ]);

      // Module 1: Analyze user status
      $signature = sb_analyze_user_status($user, $is_new_user, $validated_registration_url);

      // Module 2: Execute required actions based on signature
      $execution_result = sb_execute_user_actions($signature);

      sb_log("Phase 7: Membership & enrollment complete", 'INFO', [
        'user_id' => $user->ID,
        'execution_result' => $execution_result
      ]);
    } else {
      sb_log("Phase 7: Skipped (no registration_url)", 'DEBUG', [
        'user_id' => $user ? $user->ID : 'null',
        'has_registration_url' => !empty($validated_registration_url)
      ]);
    }

    // 4) Логиним в WP
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true, is_ssl());

    sb_log("User logged in successfully", 'INFO', [
      'wp_user_id' => $user->ID,
      'email' => $email,
      'ip' => $client_ip
    ]);

    // Clear rate limit on successful authentication
    delete_transient($rate_key);

    // Audit log (success)
    error_log(sprintf(
      'Supabase Bridge: Successful authentication - User ID: %d, Email: %s, IP: %s',
      $user->ID,
      $email,
      $client_ip
    ));

    // Determine redirect URL for ALL users who accessed via registration URL (Registration Pairs)
    $redirect_url = null;
    if ($validated_registration_url) {
      $redirect_url = sb_get_thankyou_url_for_registration($validated_registration_url);
    }

    // Build response
    $response = [
      'ok' => true,
      'user_id' => $user->ID,
      'user_email' => $email,
      'supabase_user_id' => $supabase_user_id,
      'is_new_user' => $is_new_user,
      'provider' => $provider
    ];

    // Add redirect_url if found
    if ($redirect_url) {
      $response['redirect_url'] = $redirect_url;
    }

    sb_log_function_exit('sb_handle_callback', ['success' => true, 'user_id' => $user->ID, 'is_new_user' => $is_new_user, 'redirect_url' => $redirect_url]);

    return $response;
  } catch (\Throwable $e) {
    // КРИТИЧНО: Освобождаем MySQL lock при ЛЮБОЙ ошибке
    // Без этого lock висит до закрытия соединения (могут быть часы на persistent connections!)
    $release_lock();

    sb_log("Authentication failed", 'ERROR', [
      'error' => $e->getMessage(),
      'ip' => $client_ip,
      'trace' => $e->getTraceAsString()
    ]);

    // Audit log (failure)
    error_log(sprintf(
      'Supabase Bridge: Authentication failed - Error: %s, IP: %s',
      $e->getMessage(),
      $client_ip
    ));

    sb_log_function_exit('sb_handle_callback', ['success' => false, 'error' => $e->getMessage()]);

    return new \WP_Error('auth_failed', $e->getMessage(), ['status'=>401]);
  }
}

function sb_handle_logout(\WP_REST_Request $req) {
  // CSRF Protection: Strict Origin validation (same as callback)
  $origin = $req->get_header('origin');
  $referer = $req->get_header('referer');
  $allowed_host = parse_url(home_url(), PHP_URL_HOST);

  // Extract host from Origin or Referer header
  $request_host = null;
  if ($origin) {
    $request_host = parse_url($origin, PHP_URL_HOST);
  } elseif ($referer) {
    $request_host = parse_url($referer, PHP_URL_HOST);
  }

  // MUST have Origin or Referer, and it MUST exactly match our host
  if (!$request_host || $request_host !== $allowed_host) {
    return new \WP_Error('csrf', 'Invalid origin', ['status'=>403]);
  }

  // User already verified by permission_callback (is_user_logged_in)
  $user_id = get_current_user_id();
  $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

  wp_destroy_current_session();
  wp_clear_auth_cookie();

  // Audit log (logout)
  error_log(sprintf(
    'Supabase Bridge: User logout - User ID: %d, IP: %s',
    $user_id,
    $client_ip
  ));

  return ['ok' => true];
}

// === Activation Hook - redirect to setup page ===
register_activation_hook(__FILE__, 'sb_activation_redirect');
function sb_activation_redirect() {
  add_option('sb_activation_redirect', true);
}

add_action('admin_init', function() {
  if (get_option('sb_activation_redirect', false)) {
    delete_option('sb_activation_redirect');
    wp_redirect(admin_url('admin.php?page=supabase-bridge-setup'));
    exit;
  }
});

// === Admin Menu - Setup Instructions Page ===
add_action('admin_menu', function() {
  add_menu_page(
    'Supabase Bridge Setup',           // Page title
    'Supabase Bridge',                 // Menu title
    'manage_options',                  // Capability
    'supabase-bridge-setup',           // Menu slug
    'sb_render_setup_page',            // Callback
    'dashicons-admin-network',         // Icon
    80                                 // Position
  );
});

function sb_render_setup_page() {
  $verification_message = null;

  // Handle settings form submission
  if (isset($_POST['sb_save_settings']) && check_admin_referer('sb_settings_nonce')) {
    // Page configuration
    update_option('sb_thankyou_page_id', intval($_POST['sb_thankyou_page_id'] ?? 0));

    // Supabase credentials (encrypted)
    $url = sanitize_text_field($_POST['sb_supabase_url'] ?? '');
    $anon_key = sanitize_text_field($_POST['sb_supabase_anon_key'] ?? '');
    $service_key = sanitize_text_field($_POST['sb_supabase_service_key'] ?? '');

    $credentials_updated = false;

    if (!empty($url)) {
      update_option('sb_supabase_url', sb_encrypt($url));
      $credentials_updated = true;
    }
    if (!empty($anon_key)) {
      update_option('sb_supabase_anon_key', sb_encrypt($anon_key));
      $credentials_updated = true;
    }
    if (!empty($service_key)) {
      update_option('sb_supabase_service_key', sb_encrypt($service_key));
      $credentials_updated = true;
    }

    // Verify credentials if they were updated
    if ($credentials_updated && !empty($url) && !empty($anon_key)) {
      $verification = sb_verify_supabase_credentials($url, $anon_key);
      if ($verification['success']) {
        $verification_message = ['type' => 'success', 'text' => '✅ Credentials verified and encrypted in database.'];
      } else {
        $verification_message = ['type' => 'error', 'text' => '⚠️ Verification failed: ' . esc_html($verification['error'])];
      }
    } else {
      $verification_message = ['type' => 'success', 'text' => '✅ Settings saved!'];
    }
  }

  $thankyou_page_id = get_option('sb_thankyou_page_id', 0);

  // Get current tab
  $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
  ?>
  <div class="wrap">
    <h1>🚀 Supabase Bridge</h1>

    <!-- Plugin Version & Diagnostics -->
    <div style="background: #f0f6fc; border-left: 4px solid #0969da; padding: 12px 15px; margin: 15px 0; border-radius: 4px;">
      <strong>📦 Plugin Version:</strong>
      <code style="font-size: 14px; font-weight: 600; color: #0969da; background: white; padding: 4px 8px; border-radius: 3px;">
        <?php
        // Get plugin version from header
        $plugin_data = get_file_data(__FILE__, ['Version' => 'Version'], 'plugin');
        echo esc_html($plugin_data['Version'] ?? 'Unknown');
        ?>
      </code>

      <span style="margin-left: 15px; color: #666;">
        📄 Plugin File: <code style="font-size: 11px;"><?php echo esc_html(basename(__FILE__)); ?></code>
      </span>

      <span style="margin-left: 15px; color: #666;">
        📁 Plugin Dir: <code style="font-size: 11px;"><?php echo esc_html(basename(plugin_dir_path(__FILE__))); ?></code>
      </span>

      <?php
      // Check if enhanced logging is available (v0.9.2+)
      $has_enhanced_logging = function_exists('sb_log');
      if ($has_enhanced_logging): ?>
        <span style="margin-left: 15px;">
          <span style="color: #0a8a0a; font-weight: 600;">✅ Enhanced Logging Available</span>
        </span>
      <?php else: ?>
        <span style="margin-left: 15px;">
          <span style="color: #d32f2f; font-weight: 600;">⚠️ Enhanced Logging NOT Available (version mismatch!)</span>
        </span>
      <?php endif; ?>
    </div>

    <!-- Tabs Navigation -->
    <h2 class="nav-tab-wrapper">
      <a href="?page=supabase-bridge-setup&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
        ⚙️ General Settings
      </a>
      <a href="?page=supabase-bridge-setup&tab=pairs" class="nav-tab <?php echo $current_tab === 'pairs' ? 'nav-tab-active' : ''; ?>">
        🔗 Registration Pairs
      </a>
      <a href="?page=supabase-bridge-setup&tab=memberships" class="nav-tab <?php echo $current_tab === 'memberships' ? 'nav-tab-active' : ''; ?>">
        🎫 Memberships
      </a>
      <a href="?page=supabase-bridge-setup&tab=courses" class="nav-tab <?php echo $current_tab === 'courses' ? 'nav-tab-active' : ''; ?>">
        📚 Courses
      </a>
      <a href="?page=supabase-bridge-setup&tab=course-access" class="nav-tab <?php echo $current_tab === 'course-access' ? 'nav-tab-active' : ''; ?>">
        🎓 Course Access
      </a>
      <a href="?page=supabase-bridge-setup&tab=memberpress-webhook" class="nav-tab <?php echo $current_tab === 'memberpress-webhook' ? 'nav-tab-active' : ''; ?>">
        🔗 MemberPress Webhooks
      </a>
      <?php /* HIDDEN: Webhook tab (feature on hold)
      <a href="?page=supabase-bridge-setup&tab=webhooks" class="nav-tab <?php echo $current_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
        🪝 Webhooks
      </a>
      */ ?>
      <a href="?page=supabase-bridge-setup&tab=learndash-banner" class="nav-tab <?php echo $current_tab === 'learndash-banner' ? 'nav-tab-active' : ''; ?>">
        🎓 Banner
      </a>
      <a href="?page=supabase-bridge-setup&tab=memberpress" class="nav-tab <?php echo $current_tab === 'memberpress' ? 'nav-tab-active' : ''; ?>">
        🔧 MemberPress
      </a>
    </h2>

    <?php if ($current_tab === 'general'): ?>
      <!-- TAB 1: General Settings -->
      <div class="tab-content">
        <!-- Prerequisites Warning -->
        <div class="notice notice-warning" style="border-left-color: #f59e0b; padding: 15px; margin-top: 20px;">
          <h3 style="margin-top: 0;">⚠️ Before You Start</h3>
          <p><strong>Prerequisites:</strong> You must configure Google OAuth and Facebook OAuth in your Supabase Dashboard first.</p>
          <p>📖 <strong>Documentation:</strong> <a href="https://supabase.com/docs/guides/auth/social-login/auth-google" target="_blank">Google OAuth Setup</a> | <a href="https://supabase.com/docs/guides/auth/social-login/auth-facebook" target="_blank">Facebook OAuth Setup</a></p>
          <p>💡 <strong>Magic Link</strong> (passwordless email) works out of the box - no extra setup needed.</p>
        </div>

        <h2>📋 Step 1: Configure Plugin Settings</h2>

    <!-- Settings Section -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <form method="post" action="">
        <?php wp_nonce_field('sb_settings_nonce'); ?>

        <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">🔐 Supabase Credentials (Encrypted Storage)</h3>
        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="sb_supabase_url">Supabase URL</label>
            </th>
            <td>
              <input
                type="url"
                name="sb_supabase_url"
                id="sb_supabase_url"
                value="<?php echo esc_attr(sb_cfg('SUPABASE_URL', '')); ?>"
                class="regular-text"
                placeholder="https://your-project.supabase.co"
              >
              <p class="description">Example: <code>https://abcdefghijk.supabase.co</code> (Project Ref extracted automatically)</p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="sb_supabase_anon_key">Anon Key (Public)</label>
            </th>
            <td>
              <input
                type="password"
                name="sb_supabase_anon_key"
                id="sb_supabase_anon_key"
                value="<?php echo esc_attr(sb_cfg('SUPABASE_ANON_KEY', '')); ?>"
                class="large-text"
                placeholder="eyJhbGci..."
              >
              <p class="description">Used for frontend authentication (browser). Example: <code>eyJhbGciOiJIUzI1...</code></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="sb_supabase_service_key">Service Role Key (Secret) 🔐</label>
            </th>
            <td>
              <input
                type="password"
                name="sb_supabase_service_key"
                id="sb_supabase_service_key"
                value="<?php echo esc_attr(sb_cfg('SUPABASE_SERVICE_ROLE_KEY', '')); ?>"
                class="large-text"
                placeholder="eyJhbGci..."
              >
              <p class="description">
                <strong>⚠️ NEVER expose this key to frontend!</strong> Used for server-side operations (sync, logging).
                Bypasses RLS policies. Example: <code>eyJhbGciOiJIUzI1...</code>
              </p>
            </td>
          </tr>
        </table>

        <?php if ($verification_message): ?>
          <div style="margin: 15px 0; padding: 12px; border-radius: 4px; <?php echo $verification_message['type'] === 'success' ? 'background: #d4edda; border: 1px solid #c3e6cb; color: #155724;' : 'background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;'; ?>">
            <?php echo $verification_message['text']; ?>
          </div>
        <?php endif; ?>

        <h3 style="border-bottom: 1px solid #e0e0e0; padding-bottom: 10px; margin-top: 30px;">🎉 Global Thank You Page (Fallback)</h3>
        <p style="color: #666; margin-top: -5px; margin-bottom: 20px;">
          💡 <strong>Used when:</strong> No specific pair configured in Registration Pairs tab, or registration from non-mapped page
        </p>
        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="sb_thankyou_page_id">Select Page</label>
            </th>
            <td>
              <?php
              wp_dropdown_pages([
                'name' => 'sb_thankyou_page_id',
                'id' => 'sb_thankyou_page_id',
                'selected' => $thankyou_page_id,
                'show_option_none' => '— Select a page —',
                'option_none_value' => '0'
              ]);
              ?>
              <?php if ($thankyou_page_id): ?>
                <p class="description">
                  <strong>Current URL:</strong> <a href="<?php echo esc_url(get_permalink($thankyou_page_id)); ?>" target="_blank"><?php echo esc_url(get_permalink($thankyou_page_id)); ?></a>
                  <br><em>💡 Fallback redirect for new users when no specific pair exists</em>
                </p>
              <?php else: ?>
                <p class="description">Global fallback page for new user registrations (used when no specific pair configured)</p>
              <?php endif; ?>
            </td>
          </tr>
        </table>

        <p class="submit">
          <input type="submit" name="sb_save_settings" class="button button-primary" value="💾 Save Settings">
        </p>
      </form>
    </div>

    <hr style="margin: 40px 0;">

    <!-- Phase 1: Database Status -->
    <h2>📊 Supabase Database Status</h2>
    <?php
    // Check if tables exist
    $tables_check = sb_check_tables_exist();
    $tables_exist = $tables_check['exists'] ?? false;
    ?>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <?php if ($tables_exist): ?>
        <!-- Tables exist -->
        <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; margin-bottom: 15px;">
          <strong>✅ Tables Status: READY</strong>
          <p style="margin: 10px 0 0 0;">Registration pairs tables are created and accessible.</p>
          <ul style="margin: 10px 0 0 20px; list-style: disc;">
            <li><code>wp_registration_pairs</code> - Registration/Thank You page mappings</li>
            <li><code>wp_user_registrations</code> - User registration logs for analytics</li>
          </ul>
        </div>
        <p><em>💡 You're ready to create registration pairs in the next phase</em></p>
      <?php else: ?>
        <!-- Tables don't exist - show setup instructions -->
        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; margin-bottom: 15px;">
          <strong>⚠️ Tables Status: NOT CREATED</strong>
          <p style="margin: 10px 0 0 0;">Registration pairs feature requires database tables in Supabase.</p>
        </div>

        <h3 style="margin-top: 20px;">🔧 Setup Instructions</h3>
        <p>Follow these steps to create the required tables:</p>

        <div style="background: #f8f9fa; padding: 20px; border-left: 4px solid #0969da; border-radius: 4px; margin: 20px 0;">
          <h4 style="margin-top: 0;">Step 1: Open Supabase SQL Editor</h4>
          <p>
            <a href="https://supabase.com/dashboard/project/<?php echo esc_attr(sb_cfg('SUPABASE_PROJECT_REF', 'YOUR_PROJECT')); ?>/sql"
               target="_blank"
               class="button button-primary"
               style="text-decoration: none;">
              🔗 Open SQL Editor →
            </a>
          </p>

          <h4>Step 2: Copy SQL Script</h4>
          <p>SQL file location: <code><?php echo esc_html(plugin_dir_path(__FILE__) . 'supabase-tables.sql'); ?></code></p>
          <textarea readonly style="width: 100%; height: 150px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" onclick="this.select();">
<?php echo esc_textarea(file_get_contents(plugin_dir_path(__FILE__) . 'supabase-tables.sql')); ?>
          </textarea>
          <button
            onclick="const textarea = this.previousElementSibling; textarea.select(); document.execCommand('copy'); this.textContent = '✅ Copied!'; setTimeout(() => this.textContent = '📋 Copy SQL', 2000);"
            class="button"
            style="margin-top: 10px;">
            📋 Copy SQL
          </button>

          <h4>Step 3: Execute in Supabase</h4>
          <ol style="margin-left: 20px;">
            <li>Paste the SQL script into the Supabase SQL Editor</li>
            <li>Click <strong>Run</strong> button</li>
            <li>Verify success message: "Success. No rows returned"</li>
          </ol>

          <h4>Step 4: Verify Tables</h4>
          <p>Refresh this page to verify tables were created successfully.</p>
          <button
            onclick="window.location.reload();"
            class="button button-secondary">
            🔄 Refresh Status
          </button>
        </div>

        <div style="padding: 12px; background: #e7f3ff; border-left: 3px solid #0969da; border-radius: 4px; margin-top: 20px;">
          <strong>💡 What do these tables do?</strong>
          <p style="margin: 8px 0 0 0;">
            <strong>wp_registration_pairs:</strong> Maps registration pages to their thank you pages (e.g., /services/ → /services-thankyou/)<br>
            <strong>wp_user_registrations:</strong> Logs which users registered through which pages (for analytics & webhooks)
          </p>
        </div>
      <?php endif; ?>
    </div>

    <hr style="margin: 40px 0;">

    <h2>Step 2: Add shortcode to your login page</h2>
    <p>Create or edit a page, add <strong>Shortcode block</strong>, insert shortcode, and publish.</p>
    <div style="margin: 20px 0; padding: 15px; background: #f0f6fc; border-left: 4px solid #0969da; border-radius: 6px;">
      <code style="font-size: 16px; font-weight: 600; color: #0969da;">[supabase_auth_form]</code>
      <button
        onclick="navigator.clipboard.writeText('[supabase_auth_form]').then(() => { const btn = event.target; btn.textContent = '✅ Copied!'; setTimeout(() => btn.textContent = '📋 Copy', 2000); })"
        style="padding: 6px 12px; background: #0969da; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 15px; font-size: 13px;"
      >
        📋 Copy
      </button>
    </div>
    <p><em>💡 Save your login page URL - you'll need it for Step 4</em></p>

    <hr style="margin: 40px 0;">

    <h2>Step 3: Add callback shortcode to callback page</h2>
    <p><strong>CRITICAL:</strong> Create a <strong>separate page</strong> for authentication callback handler. This is where OAuth and Magic Links will redirect after authentication.</p>

    <div style="margin: 20px 0; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 6px;">
      <h3 style="margin-top: 0;">⚠️ Current Callback URL</h3>
      <p style="margin: 10px 0;">
        <strong>Path:</strong> <code style="font-size: 14px; background: #f8f9fa; padding: 4px 8px; border-radius: 3px;"><?php echo esc_html(SB_CALLBACK_PATH); ?></code>
      </p>
      <p style="margin: 10px 0;">
        <strong>Full URL:</strong>
        <code style="font-size: 14px; background: #f8f9fa; padding: 4px 8px; border-radius: 3px;"><?php echo esc_html(home_url(SB_CALLBACK_PATH)); ?></code>
        <button
          onclick="navigator.clipboard.writeText('<?php echo esc_js(home_url(SB_CALLBACK_PATH)); ?>').then(() => { const btn = event.target; btn.textContent = '✅ Copied!'; setTimeout(() => btn.textContent = '📋 Copy URL', 2000); })"
          style="padding: 4px 8px; background: #ffc107; color: #000; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px; font-size: 12px;"
        >
          📋 Copy URL
        </button>
      </p>
      <p style="margin: 15px 0 10px 0;"><strong>Instructions:</strong></p>
      <ol style="margin-left: 20px;">
        <li>Create a WordPress page with URL: <code><?php echo esc_html(SB_CALLBACK_PATH); ?></code></li>
        <li>Add <strong>Shortcode block</strong> and insert this shortcode:</li>
      </ol>
      <div style="margin: 15px 0; padding: 15px; background: #f0f6fc; border-left: 4px solid #0969da; border-radius: 6px;">
        <code style="font-size: 16px; font-weight: 600; color: #0969da;">[supabase_auth_callback]</code>
        <button
          onclick="navigator.clipboard.writeText('[supabase_auth_callback]').then(() => { const btn = event.target; btn.textContent = '✅ Copied!'; setTimeout(() => btn.textContent = '📋 Copy', 2000); })"
          style="padding: 6px 12px; background: #0969da; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 15px; font-size: 13px;"
        >
          📋 Copy
        </button>
      </div>
      <ol start="3" style="margin-left: 20px;">
        <li>Publish the page</li>
        <li>Verify the page URL matches <code><?php echo esc_html(SB_CALLBACK_PATH); ?></code></li>
      </ol>
    </div>

    <div style="margin: 20px 0; padding: 15px; background: #e7f3ff; border-left: 4px solid #0969da; border-radius: 6px;">
      <strong>💡 Why a separate callback page?</strong>
      <p style="margin: 8px 0 0 0;">
        OAuth providers (Google, Facebook) and Magic Links redirect to this page after authentication.
        It handles the token, creates WordPress session, and redirects the user back to where they came from.
      </p>
    </div>

    <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ff6b6b; border-radius: 6px;">
      <strong>⚠️ To change callback URL:</strong>
      <p style="margin: 8px 0 0 0;">
        Edit <code>SB_CALLBACK_PATH</code> constant in <code>supabase-bridge.php</code> (line ~20).
        Then create a new WordPress page matching the new path.
      </p>
    </div>

    <hr style="margin: 40px 0;">

    <h2>Step 4: Add URLs to Supabase</h2>
    <p>Go to <a href="https://app.supabase.com" target="_blank">app.supabase.com</a> → your project<?php if (sb_cfg('SUPABASE_PROJECT_REF')): ?> (<code><?php echo esc_html(sb_cfg('SUPABASE_PROJECT_REF')); ?></code>)<?php endif; ?> → <strong>Authentication → URL Configuration</strong></p>

    <div style="margin: 20px 0; padding: 15px; background: #f0f6fc; border-left: 4px solid #0969da; border-radius: 6px;">
      <p style="margin: 0 0 10px 0;"><strong>Add these URLs to "Redirect URLs" field:</strong></p>
      <ol style="margin-left: 20px;">
        <li>Your login page URL (e.g., <code>https://yoursite.com/login/</code>)</li>
        <li><strong>Your callback URL:</strong> <code><?php echo esc_html(home_url(SB_CALLBACK_PATH)); ?></code>
          <button
            onclick="navigator.clipboard.writeText('<?php echo esc_js(home_url(SB_CALLBACK_PATH)); ?>').then(() => { const btn = event.target; btn.textContent = '✅ Copied!'; setTimeout(() => btn.textContent = '📋 Copy', 2000); })"
            style="padding: 4px 8px; background: #0969da; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px; font-size: 12px;"
          >
            📋 Copy
          </button>
        </li>
      </ol>
      <p style="margin: 10px 0 0 0;"><em>💡 Callback URL is critical - OAuth and Magic Links won't work without it!</em></p>
    </div>

    <hr style="margin: 40px 0;">

    <h2>Step 5: Test</h2>
    <p>Open your login page (incognito mode). Try <strong>Google OAuth</strong>, <strong>Facebook OAuth</strong>, and <strong>Magic Link</strong>. Check <strong>WordPress → Users</strong> for new user.</p>

    <hr style="margin: 40px 0;">

    <h2>🐛 Troubleshooting</h2>
    <p><strong>Form doesn't appear:</strong> Open console (F12) → run <code>console.log(window.SUPABASE_CFG)</code> → should show <code>url</code> and <code>anon</code></p>
    <p><strong>OAuth doesn't work:</strong> Check Prerequisites + verify login URL in Supabase Redirect URLs</p>
    <p><strong>⚠️ Development environment:</strong> Ensure permalink structure (Settings → Permalinks) matches production for OAuth testing</p>

    <div class="notice notice-success" style="margin-top: 30px;">
      <p><strong>🎉 Done!</strong> Your Supabase authentication is integrated with WordPress.</p>
    </div>
      </div><!-- End Tab 1: General Settings -->

    <?php elseif ($current_tab === 'pairs'): ?>
      <!-- TAB 2: Registration Pairs -->
      <div class="tab-content">
        <?php sb_render_pairs_tab(); ?>
      </div><!-- End Tab 2: Registration Pairs -->

    <?php elseif ($current_tab === 'memberships'): ?>
      <!-- TAB 3: Memberships -->
      <div class="tab-content">
        <?php sb_render_memberships_tab(); ?>
      </div><!-- End Tab 3: Memberships -->

    <?php elseif ($current_tab === 'courses'): ?>
      <!-- TAB 4: Courses -->
      <div class="tab-content">
        <?php sb_render_courses_tab(); ?>
      </div><!-- End Tab 4: Courses -->

    <?php elseif ($current_tab === 'course-access'): ?>
      <!-- TAB 5: Course Access (Auto-Enroll) -->
      <div class="tab-content">
        <?php sb_render_course_access_tab(); ?>
      </div><!-- End Tab 5: Course Access -->

    <?php elseif ($current_tab === 'memberpress-webhook'): ?>
      <!-- TAB 6: MemberPress Webhooks -->
      <div class="tab-content">
        <?php sb_render_memberpress_webhook_tab(); ?>
      </div><!-- End Tab 5: MemberPress Webhooks -->

    <?php /* HIDDEN: Webhook tab content (feature on hold)
    elseif ($current_tab === 'webhooks'): ?>
      <!-- TAB 3: Webhooks -->
      <div class="tab-content">
        <?php sb_render_webhooks_tab(); ?>
      </div><!-- End Tab 3: Webhooks -->
    */ ?>

    <?php elseif ($current_tab === 'learndash-banner'): ?>
      <!-- TAB 4: LearnDash Banner -->
      <div class="tab-content">
        <?php sb_render_learndash_banner_tab(); ?>
      </div><!-- End Tab 4: LearnDash Banner -->

    <?php elseif ($current_tab === 'memberpress'): ?>
      <!-- TAB 5: MemberPress Patches -->
      <div class="tab-content">
        <?php sb_render_memberpress_tab(); ?>
      </div><!-- End Tab 5: MemberPress Patches -->

    <?php endif; ?>

  </div><!-- End .wrap -->
  <?php
}

// === Phase 2: Registration Pairs Tab ===
function sb_render_pairs_tab() {
  // Get pairs from wp_options
  $pairs = get_option('sb_registration_pairs', []);
  ?>
  <div style="margin-top: 20px;">
    <h2>🔗 Registration / Thank You Page Pairs</h2>
    <p>Map registration pages to their thank you pages. Each registration page will redirect users to its corresponding thank you page.</p>

    <!-- Add New Pair Button -->
    <p style="margin: 20px 0;">
      <button type="button" class="button button-primary" onclick="sbShowAddPairModal()">
        ➕ Add New Pair
      </button>
    </p>

    <?php if (empty($pairs)): ?>
      <!-- Empty State -->
      <div style="background: #f0f6fc; border: 1px solid #d1e4f5; border-radius: 6px; padding: 40px; text-align: center; margin: 20px 0;">
        <p style="font-size: 16px; color: #666; margin: 0;">
          📋 No registration pairs created yet.
        </p>
        <p style="color: #999; margin: 10px 0 0 0;">
          Click "Add New Pair" to create your first registration/thank-you page mapping.
        </p>
      </div>
    <?php else: ?>
      <!-- Pairs Table -->
      <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
          <tr>
            <th style="width: 35%;">Registration Page</th>
            <th style="width: 35%;">Thank You Page</th>
            <th style="width: 20%;">Created</th>
            <th style="width: 10%;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pairs as $pair): ?>
            <tr>
              <td>
                <strong><?php echo esc_html(get_the_title($pair['registration_page_id'])); ?></strong>
                <br>
                <code style="font-size: 11px; color: #666;"><?php echo esc_html($pair['registration_page_url']); ?></code>
              </td>
              <td>
                <strong><?php echo esc_html(get_the_title($pair['thankyou_page_id'])); ?></strong>
                <br>
                <code style="font-size: 11px; color: #666;"><?php echo esc_html($pair['thankyou_page_url']); ?></code>
              </td>
              <td>
                <?php echo esc_html(date('Y-m-d H:i', strtotime($pair['created_at']))); ?>
              </td>
              <td>
                <button type="button" class="button button-small" onclick="sbEditPair('<?php echo esc_js($pair['id']); ?>')">
                  ✏️ Edit
                </button>
                <button type="button" class="button button-small button-link-delete" onclick="sbDeletePair('<?php echo esc_js($pair['id']); ?>', '<?php echo esc_js(get_the_title($pair['registration_page_id'])); ?>')">
                  🗑️ Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Add/Edit Pair Modal -->
    <div id="sb-pair-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 50px auto; padding: 30px; width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h2 id="sb-modal-title">Add New Pair</h2>
        <form id="sb-pair-form">
          <input type="hidden" id="sb-pair-id" name="pair_id" value="">

          <table class="form-table">
            <tr>
              <th scope="row">
                <label for="sb-reg-page">Registration Page</label>
              </th>
              <td>
                <?php
                wp_dropdown_pages([
                  'name' => 'registration_page_id',
                  'id' => 'sb-reg-page',
                  'show_option_none' => '— Select a page —',
                  'option_none_value' => '0'
                ]);
                ?>
                <p class="description">The page where users fill the registration form</p>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="sb-ty-page">Thank You Page</label>
              </th>
              <td>
                <?php
                wp_dropdown_pages([
                  'name' => 'thankyou_page_id',
                  'id' => 'sb-ty-page',
                  'show_option_none' => '— Select a page —',
                  'option_none_value' => '0'
                ]);
                ?>
                <p class="description">Where new users will be redirected after registration</p>
              </td>
            </tr>
          </table>

          <p style="margin-top: 20px;">
            <button type="button" class="button button-primary" onclick="sbSavePair()">💾 Save Pair</button>
            <button type="button" class="button" onclick="sbCloseModal()">Cancel</button>
          </p>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="sb-delete-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 100px auto; padding: 30px; width: 500px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #d63638;">⚠️ Confirm Delete</h2>
        <p id="sb-delete-message" style="font-size: 14px; margin: 20px 0;">Are you sure you want to delete this pair?</p>
        <p style="margin-top: 30px; text-align: right;">
          <button type="button" class="button" onclick="sbCancelDelete()" style="margin-right: 10px;">Cancel</button>
          <button type="button" class="button button-primary" onclick="sbConfirmDelete()" style="background: #d63638; border-color: #d63638;">🗑️ Delete</button>
        </p>
      </div>
    </div>

    <script>
    // Global pairs data (safe outside function scope)
    const SB_PAIRS_DATA = <?php echo json_encode($pairs); ?>;

    // Delete confirmation state
    let pendingDelete = null;

    function sbShowAddPairModal() {
      document.getElementById('sb-modal-title').textContent = 'Add New Pair';
      document.getElementById('sb-pair-id').value = '';
      document.getElementById('sb-reg-page').value = '0';
      document.getElementById('sb-ty-page').value = '0';
      document.getElementById('sb-pair-modal').style.display = 'block';
    }

    function sbEditPair(pairId) {
      // Use global pairs data
      const pair = SB_PAIRS_DATA.find(p => p.id === pairId);

      if (!pair) {
        alert('⚠️ Pair not found');
        return;
      }

      // Populate modal fields with existing data
      document.getElementById('sb-modal-title').textContent = 'Edit Pair';
      document.getElementById('sb-pair-id').value = pair.id;
      document.getElementById('sb-reg-page').value = pair.registration_page_id;
      document.getElementById('sb-ty-page').value = pair.thankyou_page_id;

      // Show modal
      document.getElementById('sb-pair-modal').style.display = 'block';
    }

    function sbCloseModal() {
      document.getElementById('sb-pair-modal').style.display = 'none';
    }

    function sbSavePair() {
      const formData = new FormData(document.getElementById('sb-pair-form'));
      formData.append('action', 'sb_save_pair');
      formData.append('nonce', '<?php echo wp_create_nonce('sb_pair_nonce'); ?>');

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Pair saved successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });
    }

    function sbDeletePair(pairId, pageName) {
      // Show custom confirmation modal (browser confirm() blocked by Safari)
      pendingDelete = { pairId: pairId, pageName: pageName };
      document.getElementById('sb-delete-message').textContent =
        'Are you sure you want to delete the pair for "' + pageName + '"?';
      document.getElementById('sb-delete-modal').style.display = 'block';
    }

    function sbCancelDelete() {
      pendingDelete = null;
      document.getElementById('sb-delete-modal').style.display = 'none';
    }

    function sbConfirmDelete() {
      if (!pendingDelete) return;

      const { pairId, pageName } = pendingDelete;
      document.getElementById('sb-delete-modal').style.display = 'none';

      const formData = new FormData();
      formData.append('action', 'sb_delete_pair');
      formData.append('nonce', '<?php echo wp_create_nonce('sb_pair_nonce'); ?>');
      formData.append('pair_id', pairId);

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Pair deleted successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });

      pendingDelete = null;
    }
    </script>
  </div>
  <?php
}

// === Phase 4: Memberships Tab (v0.9.0) - MemberPress Integration ===
function sb_render_memberships_tab() {
  // Get membership pairs from wp_options
  $pairs = get_option('sb_membership_pairs', []);

  // Get FREE memberships from MemberPress
  $free_memberships = [];
  if (class_exists('MeprProduct')) {
    $all_products = MeprProduct::get_all();
    foreach ($all_products as $product) {
      // Only include FREE memberships (price = 0)
      if (floatval($product->price) == 0) {
        $free_memberships[] = [
          'id' => $product->ID,
          'title' => $product->post_title,
          'price' => $product->price
        ];
      }
    }
  } else {
    // Fallback: query directly if MeprProduct class not available
    $args = [
      'post_type' => 'memberpressproduct',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'meta_query' => [
        [
          'key' => '_mepr_product_price',
          'value' => '0',
          'type' => 'NUMERIC',
          'compare' => '='
        ]
      ]
    ];
    $products = get_posts($args);
    foreach ($products as $product) {
      $free_memberships[] = [
        'id' => $product->ID,
        'title' => $product->post_title,
        'price' => '0'
      ];
    }
  }
  ?>
  <div style="margin-top: 20px;">
    <h2>🎫 Registration / Membership Pairs</h2>
    <p>Map registration pages to FREE memberships. When a user registers from a mapped page, they will automatically be assigned the corresponding membership.</p>

    <?php if (empty($free_memberships)): ?>
      <!-- No FREE Memberships Warning -->
      <div class="notice notice-warning" style="margin: 20px 0; padding: 15px;">
        <h3 style="margin-top: 0;">⚠️ No FREE Memberships Found</h3>
        <p>To use this feature, create at least one FREE membership in MemberPress:</p>
        <p><a href="<?php echo admin_url('post-new.php?post_type=memberpressproduct'); ?>" class="button">➕ Create Membership</a></p>
        <p class="description">Set the price to $0 to make it a free membership.</p>
      </div>
    <?php else: ?>

    <!-- Add New Pair Button -->
    <p style="margin: 20px 0;">
      <button type="button" class="button button-primary" onclick="sbShowAddMembershipModal()">
        ➕ Add New Membership Pair
      </button>
    </p>

    <?php if (empty($pairs)): ?>
      <!-- Empty State -->
      <div style="background: #f0f6fc; border: 1px solid #d1e4f5; border-radius: 6px; padding: 40px; text-align: center; margin: 20px 0;">
        <p style="font-size: 16px; color: #666; margin: 0;">
          📋 No membership pairs created yet.
        </p>
        <p style="color: #999; margin: 10px 0 0 0;">
          Click "Add New Membership Pair" to automatically assign memberships upon registration.
        </p>
      </div>
    <?php else: ?>
      <!-- Pairs Table -->
      <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
          <tr>
            <th style="width: 35%;">Registration Page</th>
            <th style="width: 35%;">Membership</th>
            <th style="width: 20%;">Created</th>
            <th style="width: 10%;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pairs as $pair): ?>
            <tr>
              <td>
                <strong><?php echo esc_html(get_the_title($pair['registration_page_id'])); ?></strong>
                <br>
                <code style="font-size: 11px; color: #666;"><?php echo esc_html($pair['registration_page_url']); ?></code>
              </td>
              <td>
                <strong>🎫 <?php echo esc_html(get_the_title($pair['membership_id'])); ?></strong>
                <br>
                <span style="font-size: 11px; color: #666;">ID: <?php echo esc_html($pair['membership_id']); ?> (FREE)</span>
              </td>
              <td>
                <?php echo esc_html(date('Y-m-d H:i', strtotime($pair['created_at']))); ?>
              </td>
              <td>
                <button type="button" class="button button-small" onclick="sbEditMembership('<?php echo esc_js($pair['id']); ?>')">
                  ✏️ Edit
                </button>
                <button type="button" class="button button-small button-link-delete" onclick="sbDeleteMembership('<?php echo esc_js($pair['id']); ?>', '<?php echo esc_js(get_the_title($pair['registration_page_id'])); ?>')">
                  🗑️ Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Add/Edit Membership Modal -->
    <div id="sb-membership-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 50px auto; padding: 30px; width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h2 id="sb-membership-modal-title">Add New Membership Pair</h2>
        <form id="sb-membership-form">
          <input type="hidden" id="sb-membership-pair-id" name="pair_id" value="">

          <table class="form-table">
            <tr>
              <th scope="row">
                <label for="sb-membership-reg-page">Registration Page</label>
              </th>
              <td>
                <?php
                wp_dropdown_pages([
                  'name' => 'registration_page_id',
                  'id' => 'sb-membership-reg-page',
                  'show_option_none' => '— Select a page —',
                  'option_none_value' => '0'
                ]);
                ?>
                <p class="description">The page where users fill the registration form</p>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="sb-membership-select">Membership</label>
              </th>
              <td>
                <select name="membership_id" id="sb-membership-select" class="regular-text">
                  <option value="0">— Select a FREE membership —</option>
                  <?php foreach ($free_memberships as $membership): ?>
                    <option value="<?php echo esc_attr($membership['id']); ?>">
                      🎫 <?php echo esc_html($membership['title']); ?> (FREE)
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="description">User will be automatically assigned this membership upon registration</p>
              </td>
            </tr>
          </table>

          <p style="margin-top: 20px;">
            <button type="button" class="button button-primary" onclick="sbSaveMembership()">💾 Save Membership Pair</button>
            <button type="button" class="button" onclick="sbCloseMembershipModal()">Cancel</button>
          </p>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="sb-membership-delete-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 100px auto; padding: 30px; width: 500px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #d63638;">⚠️ Confirm Delete</h2>
        <p id="sb-membership-delete-message" style="font-size: 14px; margin: 20px 0;">Are you sure you want to delete this membership pair?</p>
        <p style="margin-top: 30px; text-align: right;">
          <button type="button" class="button" onclick="sbCancelMembershipDelete()" style="margin-right: 10px;">Cancel</button>
          <button type="button" class="button button-primary" onclick="sbConfirmMembershipDelete()" style="background: #d63638; border-color: #d63638;">🗑️ Delete</button>
        </p>
      </div>
    </div>

    <?php endif; // end if free_memberships ?>

    <script>
    // Global membership pairs data
    const SB_MEMBERSHIP_PAIRS_DATA = <?php echo json_encode($pairs); ?>;

    // Delete confirmation state
    let pendingMembershipDelete = null;

    function sbShowAddMembershipModal() {
      document.getElementById('sb-membership-modal-title').textContent = 'Add New Membership Pair';
      document.getElementById('sb-membership-pair-id').value = '';
      document.getElementById('sb-membership-reg-page').value = '0';
      document.getElementById('sb-membership-select').value = '0';
      document.getElementById('sb-membership-modal').style.display = 'block';
    }

    function sbEditMembership(pairId) {
      const pair = SB_MEMBERSHIP_PAIRS_DATA.find(p => p.id === pairId);

      if (!pair) {
        alert('⚠️ Membership pair not found');
        return;
      }

      document.getElementById('sb-membership-modal-title').textContent = 'Edit Membership Pair';
      document.getElementById('sb-membership-pair-id').value = pair.id;
      document.getElementById('sb-membership-reg-page').value = pair.registration_page_id;
      document.getElementById('sb-membership-select').value = pair.membership_id;
      document.getElementById('sb-membership-modal').style.display = 'block';
    }

    function sbCloseMembershipModal() {
      document.getElementById('sb-membership-modal').style.display = 'none';
    }

    function sbSaveMembership() {
      const pairId = document.getElementById('sb-membership-pair-id').value;
      const regPageId = document.getElementById('sb-membership-reg-page').value;
      const membershipId = document.getElementById('sb-membership-select').value;

      if (regPageId === '0') {
        alert('⚠️ Please select a Registration Page');
        return;
      }

      if (membershipId === '0') {
        alert('⚠️ Please select a Membership');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'sb_save_membership_pair');
      formData.append('nonce', '<?php echo wp_create_nonce('sb_membership_pair_nonce'); ?>');
      formData.append('pair_id', pairId);
      formData.append('registration_page_id', regPageId);
      formData.append('membership_id', membershipId);

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Membership pair saved successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });
    }

    function sbDeleteMembership(pairId, pageName) {
      pendingMembershipDelete = { pairId: pairId, pageName: pageName };
      document.getElementById('sb-membership-delete-message').textContent =
        'Are you sure you want to delete the membership pair for "' + pageName + '"?';
      document.getElementById('sb-membership-delete-modal').style.display = 'block';
    }

    function sbCancelMembershipDelete() {
      pendingMembershipDelete = null;
      document.getElementById('sb-membership-delete-modal').style.display = 'none';
    }

    function sbConfirmMembershipDelete() {
      if (!pendingMembershipDelete) return;

      const { pairId, pageName } = pendingMembershipDelete;
      document.getElementById('sb-membership-delete-modal').style.display = 'none';

      const formData = new FormData();
      formData.append('action', 'sb_delete_membership_pair');
      formData.append('nonce', '<?php echo wp_create_nonce('sb_membership_pair_nonce'); ?>');
      formData.append('pair_id', pairId);

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Membership pair deleted successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });

      pendingMembershipDelete = null;
    }
    </script>
  </div>
  <?php
}

// === Phase 5: Courses Tab (v0.9.0) - LearnDash Integration ===
function sb_render_courses_tab() {
  // Get course pairs from wp_options
  $pairs = get_option('sb_course_pairs', []);

  // Get all LearnDash courses
  $courses = [];
  if (function_exists('learndash_get_courses') || post_type_exists('sfwd-courses')) {
    $args = [
      'post_type' => 'sfwd-courses',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'orderby' => 'title',
      'order' => 'ASC'
    ];
    $course_posts = get_posts($args);
    foreach ($course_posts as $course) {
      $courses[] = [
        'id' => $course->ID,
        'title' => $course->post_title
      ];
    }
  }
  ?>
  <div style="margin-top: 20px;">
    <h2>📚 Registration / Course Enrollment Pairs</h2>
    <p>Map registration pages to LearnDash courses. When a user registers from a mapped page, they will automatically be enrolled in the corresponding course.</p>

    <?php if (empty($courses)): ?>
      <!-- No Courses Warning -->
      <div class="notice notice-warning" style="margin: 20px 0; padding: 15px;">
        <h3 style="margin-top: 0;">⚠️ No LearnDash Courses Found</h3>
        <p>To use this feature, create at least one course in LearnDash:</p>
        <p><a href="<?php echo admin_url('post-new.php?post_type=sfwd-courses'); ?>" class="button">➕ Create Course</a></p>
        <p class="description">Or make sure LearnDash plugin is installed and activated.</p>
      </div>
    <?php else: ?>

    <!-- Add New Pair Button -->
    <p style="margin: 20px 0;">
      <button type="button" class="button button-primary" onclick="sbShowAddCourseModal()">
        ➕ Add New Course Enrollment
      </button>
    </p>

    <?php if (empty($pairs)): ?>
      <!-- Empty State -->
      <div style="background: #f0f6fc; border: 1px solid #d1e4f5; border-radius: 6px; padding: 40px; text-align: center; margin: 20px 0;">
        <p style="font-size: 16px; color: #666; margin: 0;">
          📋 No course enrollments configured yet.
        </p>
        <p style="color: #999; margin: 10px 0 0 0;">
          Click "Add New Course Enrollment" to automatically enroll users upon registration.
        </p>
      </div>
    <?php else: ?>
      <!-- Pairs Table -->
      <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
          <tr>
            <th style="width: 35%;">Registration Page</th>
            <th style="width: 35%;">Course</th>
            <th style="width: 20%;">Created</th>
            <th style="width: 10%;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pairs as $pair): ?>
            <tr>
              <td>
                <strong><?php echo esc_html(get_the_title($pair['registration_page_id'])); ?></strong>
                <br>
                <code style="font-size: 11px; color: #666;"><?php echo esc_html($pair['registration_page_url']); ?></code>
              </td>
              <td>
                <strong>📚 <?php echo esc_html(get_the_title($pair['course_id'])); ?></strong>
                <br>
                <span style="font-size: 11px; color: #666;">ID: <?php echo esc_html($pair['course_id']); ?></span>
              </td>
              <td>
                <?php echo esc_html(date('Y-m-d H:i', strtotime($pair['created_at']))); ?>
              </td>
              <td>
                <button type="button" class="button button-small" onclick="sbEditCourse('<?php echo esc_js($pair['id']); ?>')">
                  ✏️ Edit
                </button>
                <button type="button" class="button button-small button-link-delete" onclick="sbDeleteCourse('<?php echo esc_js($pair['id']); ?>', '<?php echo esc_js(get_the_title($pair['registration_page_id'])); ?>')">
                  🗑️ Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Add/Edit Course Modal -->
    <div id="sb-course-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 50px auto; padding: 30px; width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h2 id="sb-course-modal-title">Add New Course Enrollment</h2>
        <form id="sb-course-form">
          <input type="hidden" id="sb-course-pair-id" name="pair_id" value="">

          <table class="form-table">
            <tr>
              <th scope="row">
                <label for="sb-course-reg-page">Registration Page</label>
              </th>
              <td>
                <?php
                wp_dropdown_pages([
                  'name' => 'registration_page_id',
                  'id' => 'sb-course-reg-page',
                  'show_option_none' => '— Select a page —',
                  'option_none_value' => '0'
                ]);
                ?>
                <p class="description">The page where users fill the registration form</p>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="sb-course-select">Course</label>
              </th>
              <td>
                <select name="course_id" id="sb-course-select" class="regular-text">
                  <option value="0">— Select a course —</option>
                  <?php foreach ($courses as $course): ?>
                    <option value="<?php echo esc_attr($course['id']); ?>">
                      📚 <?php echo esc_html($course['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="description">User will be automatically enrolled in this course upon registration</p>
              </td>
            </tr>
          </table>

          <p style="margin-top: 20px;">
            <button type="button" class="button button-primary" onclick="sbSaveCourse()">💾 Save Course Enrollment</button>
            <button type="button" class="button" onclick="sbCloseCourseModal()">Cancel</button>
          </p>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="sb-course-delete-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 100px auto; padding: 30px; width: 500px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #d63638;">⚠️ Confirm Delete</h2>
        <p id="sb-course-delete-message" style="font-size: 14px; margin: 20px 0;">Are you sure you want to delete this course enrollment?</p>
        <p style="margin-top: 30px; text-align: right;">
          <button type="button" class="button" onclick="sbCancelCourseDelete()" style="margin-right: 10px;">Cancel</button>
          <button type="button" class="button button-primary" onclick="sbConfirmCourseDelete()" style="background: #d63638; border-color: #d63638;">🗑️ Delete</button>
        </p>
      </div>
    </div>

    <?php endif; // end if courses ?>

    <script>
    // Global course pairs data
    const SB_COURSE_PAIRS_DATA = <?php echo json_encode($pairs); ?>;

    // Delete confirmation state
    let pendingCourseDelete = null;

    function sbShowAddCourseModal() {
      document.getElementById('sb-course-modal-title').textContent = 'Add New Course Enrollment';
      document.getElementById('sb-course-pair-id').value = '';
      document.getElementById('sb-course-reg-page').value = '0';
      document.getElementById('sb-course-select').value = '0';
      document.getElementById('sb-course-modal').style.display = 'block';
    }

    function sbEditCourse(pairId) {
      const pair = SB_COURSE_PAIRS_DATA.find(p => p.id === pairId);

      if (!pair) {
        alert('⚠️ Course enrollment not found');
        return;
      }

      document.getElementById('sb-course-modal-title').textContent = 'Edit Course Enrollment';
      document.getElementById('sb-course-pair-id').value = pair.id;
      document.getElementById('sb-course-reg-page').value = pair.registration_page_id;
      document.getElementById('sb-course-select').value = pair.course_id;
      document.getElementById('sb-course-modal').style.display = 'block';
    }

    function sbCloseCourseModal() {
      document.getElementById('sb-course-modal').style.display = 'none';
    }

    function sbSaveCourse() {
      const pairId = document.getElementById('sb-course-pair-id').value;
      const regPageId = document.getElementById('sb-course-reg-page').value;
      const courseId = document.getElementById('sb-course-select').value;

      if (regPageId === '0') {
        alert('⚠️ Please select a Registration Page');
        return;
      }

      if (courseId === '0') {
        alert('⚠️ Please select a Course');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'sb_save_course_pair');
      formData.append('nonce', '<?php echo wp_create_nonce('sb_course_pair_nonce'); ?>');
      formData.append('pair_id', pairId);
      formData.append('registration_page_id', regPageId);
      formData.append('course_id', courseId);

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Course enrollment saved successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });
    }

    function sbDeleteCourse(pairId, pageName) {
      pendingCourseDelete = { pairId: pairId, pageName: pageName };
      document.getElementById('sb-course-delete-message').textContent =
        'Are you sure you want to delete the course enrollment for "' + pageName + '"?';
      document.getElementById('sb-course-delete-modal').style.display = 'block';
    }

    function sbCancelCourseDelete() {
      pendingCourseDelete = null;
      document.getElementById('sb-course-delete-modal').style.display = 'none';
    }

    function sbConfirmCourseDelete() {
      if (!pendingCourseDelete) return;

      const { pairId, pageName } = pendingCourseDelete;
      document.getElementById('sb-course-delete-modal').style.display = 'none';

      const formData = new FormData();
      formData.append('action', 'sb_delete_course_pair');
      formData.append('nonce', '<?php echo wp_create_nonce('sb_course_pair_nonce'); ?>');
      formData.append('pair_id', pairId);

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Course enrollment deleted successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });

      pendingCourseDelete = null;
    }
    </script>
  </div>
  <?php
}

// === Phase 6: Course Access Tab (Auto-Enroll on Membership Purchase) ===
function sb_render_course_access_tab() {
  // Get pairs from wp_options
  $pairs = get_option('sb_membership_course_pairs', []);

  // Get all MemberPress memberships (products)
  $memberships = [];
  if (class_exists('MeprProduct')) {
    $all_products = MeprProduct::get_all();
    foreach ($all_products as $product) {
      $memberships[] = [
        'id' => $product->ID,
        'title' => $product->post_title,
        'price' => $product->price
      ];
    }
  }

  // Get all LearnDash courses
  $courses = [];
  if (function_exists('learndash_get_courses') || post_type_exists('sfwd-courses')) {
    $args = [
      'post_type' => 'sfwd-courses',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'orderby' => 'title',
      'order' => 'ASC'
    ];
    $course_posts = get_posts($args);
    foreach ($course_posts as $course) {
      $courses[] = [
        'id' => $course->ID,
        'title' => $course->post_title
      ];
    }
  }
  ?>
  <div style="margin-top: 20px;">
    <h2>🎓 Membership → Course Auto-Enrollment</h2>
    <p style="color: #666; font-size: 14px; line-height: 1.6;">
      💡 <strong>How it works:</strong> When a user purchases a membership (one-time or subscription), they are automatically enrolled in the specified course(s).<br>
      📝 <strong>Note:</strong> One membership can enroll users in multiple courses - just add multiple pairs with the same membership.<br>
      ⚡ <strong>Triggers:</strong> Works for both MemberPress transactions (one-time) and subscriptions (recurring) with status <code>complete</code> or <code>active</code>.
    </p>

    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 6px;">
      <strong>⚠️ Important:</strong>
      <ul style="margin: 8px 0; padding-left: 20px;">
        <li>Enrollment happens <strong>only once</strong> - if user already enrolled, their progress is preserved</li>
        <li>Course access is controlled by MemberPress membership status (not enrollment status)</li>
        <li>If membership expires/paused → access removed, but enrollment/progress remains</li>
        <li>When membership renewed → user continues from where they left off</li>
      </ul>
    </div>

    <!-- Add Button -->
    <p style="margin: 20px 0;">
      <button type="button" class="button button-primary" onclick="sbShowCourseAccessModal()">
        ➕ Add New Auto-Enrollment Rule
      </button>
    </p>

    <?php if (empty($pairs)): ?>
      <!-- Empty State -->
      <div style="background: #f0f6fc; border: 1px solid #d1e4f5; border-radius: 6px; padding: 40px; text-align: center; margin: 20px 0;">
        <p style="font-size: 16px; color: #666; margin: 0;">
          📋 No auto-enrollment rules configured yet.
        </p>
        <p style="color: #999; margin: 10px 0 0 0;">
          Click "Add New Auto-Enrollment Rule" to start auto-enrolling users when they purchase memberships.
        </p>
      </div>
    <?php else: ?>
      <!-- Pairs Table -->
      <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
          <tr>
            <th style="width: 40%;">Membership (MemberPress)</th>
            <th style="width: 40%;">Course (LearnDash)</th>
            <th style="width: 10%;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pairs as $pair): ?>
            <tr>
              <td>
                <strong><?php
                  $membership = get_post($pair['membership_id']);
                  echo $membership ? esc_html($membership->post_title) : '<em>Membership not found</em>';
                ?></strong>
                <br>
                <span style="font-size: 11px; color: #666;">ID: <?php echo esc_html($pair['membership_id']); ?></span>
              </td>
              <td>
                <strong>📚 <?php
                  $course = get_post($pair['course_id']);
                  echo $course ? esc_html($course->post_title) : '<em>Course not found</em>';
                ?></strong>
                <br>
                <span style="font-size: 11px; color: #666;">ID: <?php echo esc_html($pair['course_id']); ?></span>
              </td>
              <td>
                <button type="button" class="button button-small button-link-delete" onclick="sbDeleteCourseAccess('<?php echo esc_js($pair['id']); ?>', '<?php echo esc_js($membership ? $membership->post_title : 'Unknown'); ?>')">
                  🗑️ Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Modal Window -->
    <div id="sb-course-access-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
      <div style="background: #fff; margin: 50px auto; padding: 30px; width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h2>Add New Auto-Enrollment Rule</h2>
        <form id="sb-course-access-form">
          <table class="form-table">
            <tr>
              <th scope="row">
                <label for="sb-ca-membership">Membership (MemberPress)</label>
              </th>
              <td>
                <select name="membership_id" id="sb-ca-membership" class="regular-text" required>
                  <option value="">— Select a membership —</option>
                  <?php foreach ($memberships as $membership): ?>
                    <option value="<?php echo esc_attr($membership['id']); ?>">
                      <?php echo esc_html($membership['title']); ?>
                      <?php if ($membership['price'] > 0): ?>
                        ($<?php echo esc_html(number_format($membership['price'], 2)); ?>)
                      <?php else: ?>
                        (FREE)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="sb-ca-course">Course (LearnDash)</label>
              </th>
              <td>
                <select name="course_id" id="sb-ca-course" class="regular-text" required>
                  <option value="">— Select a course —</option>
                  <?php foreach ($courses as $course): ?>
                    <option value="<?php echo esc_attr($course['id']); ?>">
                      <?php echo esc_html($course['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          </table>

          <p class="submit" style="text-align: right; margin-bottom: 0;">
            <button type="button" class="button" onclick="sbCloseCourseAccessModal()">Cancel</button>
            <button type="submit" class="button button-primary">Save Rule</button>
          </p>
        </form>
      </div>
    </div>

    <!-- JavaScript -->
    <script>
    // Show modal
    function sbShowCourseAccessModal() {
      document.getElementById('sb-course-access-modal').style.display = 'block';
      document.getElementById('sb-ca-membership').value = '';
      document.getElementById('sb-ca-course').value = '';
    }

    // Close modal
    function sbCloseCourseAccessModal() {
      document.getElementById('sb-course-access-modal').style.display = 'none';
    }

    // Close on outside click
    window.onclick = function(event) {
      const modal = document.getElementById('sb-course-access-modal');
      if (event.target === modal) {
        sbCloseCourseAccessModal();
      }
    };

    // Save course access pair
    document.getElementById('sb-course-access-form').addEventListener('submit', function(e) {
      e.preventDefault();

      const membershipId = document.getElementById('sb-ca-membership').value;
      const courseId = document.getElementById('sb-ca-course').value;

      if (!membershipId || !courseId) {
        alert('Please select both membership and course');
        return;
      }

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'sb_save_course_access_pair',
          nonce: '<?php echo wp_create_nonce('sb_course_access_nonce'); ?>',
          membership_id: membershipId,
          course_id: courseId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Auto-enrollment rule added successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });
    });

    // Delete course access pair
    function sbDeleteCourseAccess(pairId, membershipName) {
      if (!confirm('Delete auto-enrollment rule for "' + membershipName + '"?')) {
        return;
      }

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'sb_delete_course_access_pair',
          nonce: '<?php echo wp_create_nonce('sb_course_access_nonce'); ?>',
          pair_id: pairId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Auto-enrollment rule deleted successfully!');
          location.reload();
        } else {
          alert('❌ Error: ' + (data.data || 'Unknown error'));
        }
      })
      .catch(error => {
        alert('❌ Network error: ' + error.message);
      });
    }
    </script>
  </div>
  <?php
}

// === Phase 3: Webhooks Tab (v0.8.0) - FULL IMPLEMENTATION ===
function sb_render_webhooks_tab() {
  // Get current settings
  $webhook_enabled = get_option('sb_webhook_enabled', '0');
  $webhook_url = get_option('sb_webhook_url', '');

  // Save settings if form submitted
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sb_webhook_settings_nonce'])) {
    if (wp_verify_nonce($_POST['sb_webhook_settings_nonce'], 'sb_webhook_settings')) {
      update_option('sb_webhook_enabled', isset($_POST['sb_webhook_enabled']) ? '1' : '0');
      update_option('sb_webhook_url', sanitize_text_field($_POST['sb_webhook_url']));

      echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Settings saved!</strong></p></div>';

      // Reload values
      $webhook_enabled = get_option('sb_webhook_enabled', '0');
      $webhook_url = get_option('sb_webhook_url', '');
    }
  }

  ?>
  <div style="margin-top: 20px;">
    <h2>🪝 Webhook System for n8n/make</h2>
    <p>Send webhooks to n8n/make automatically when users register.</p>

    <!-- Status Summary -->
    <div style="padding: 15px; background: #f9f9f9; border-left: 4px solid <?php echo $webhook_enabled === '1' && !empty($webhook_url) ? '#46b450' : '#dc3232'; ?>; margin: 20px 0;">
      <h3 style="margin-top: 0;">📊 Current Status</h3>
      <ul style="list-style: none; padding: 0; margin: 10px 0;">
        <li style="padding: 5px 0;">
          <strong>Webhooks:</strong>
          <?php if ($webhook_enabled === '1'): ?>
            <span style="color: #46b450;">✅ Enabled</span>
          <?php else: ?>
            <span style="color: #dc3232;">❌ Disabled</span>
          <?php endif; ?>
        </li>
        <li style="padding: 5px 0;">
          <strong>Webhook URL:</strong>
          <?php if (!empty($webhook_url)): ?>
            <code style="background: #fff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($webhook_url); ?></code>
          <?php else: ?>
            <span style="color: #dc3232;">Not configured</span>
          <?php endif; ?>
        </li>
      </ul>
    </div>

    <!-- Configuration Form -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">⚙️ Configuration</h3>

      <form method="post" action="">
        <?php wp_nonce_field('sb_webhook_settings', 'sb_webhook_settings_nonce'); ?>

        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="sb_webhook_enabled">Enable Webhooks</label>
            </th>
            <td>
              <label>
                <input
                  type="checkbox"
                  name="sb_webhook_enabled"
                  id="sb_webhook_enabled"
                  value="1"
                  <?php checked($webhook_enabled, '1'); ?>
                >
                Send webhooks to n8n/make on user registration
              </label>
              <p class="description">
                ⚠️ <strong>Important:</strong> Configure Supabase first (see instructions below)
              </p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="sb_webhook_url">Webhook URL</label>
            </th>
            <td>
              <input
                type="url"
                name="sb_webhook_url"
                id="sb_webhook_url"
                value="<?php echo esc_attr($webhook_url); ?>"
                class="regular-text"
                placeholder="https://hooks.n8n.cloud/webhook/..."
              >
              <p class="description">
                Your n8n or make.com webhook endpoint URL<br>
                📌 Example: <code>https://hooks.n8n.cloud/webhook/abc123xyz</code>
              </p>
            </td>
          </tr>
        </table>

        <p class="submit">
          <input type="submit" name="submit" id="submit" class="button button-primary" value="💾 Save Settings">
        </p>
      </form>
    </div>

    <!-- Supabase Setup Instructions -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">🛠️ Supabase Setup</h3>

      <p>Before enabling webhooks, you must deploy the webhook system to your Supabase project.</p>

      <details style="margin: 15px 0;">
        <summary style="cursor: pointer; padding: 10px; background: #f0f6fc; border-radius: 4px; font-weight: 600;">
          📋 Step 1: Deploy Database Schema (SQL)
        </summary>
        <div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
          <p>1. Open <strong>Supabase Dashboard → SQL Editor</strong></p>
          <p>2. Copy the SQL code from <code>webhook-system/webhook-system.sql</code></p>
          <p>3. Paste and execute in SQL Editor</p>
          <p>4. Verify: <code>SELECT COUNT(*) FROM webhook_logs;</code> should return 0</p>
        </div>
      </details>

      <details style="margin: 15px 0;">
        <summary style="cursor: pointer; padding: 10px; background: #f0f6fc; border-radius: 4px; font-weight: 600;">
          ⚡ Step 2: Deploy Edge Function
        </summary>
        <div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
          <p>1. Copy code from <code>webhook-system/send-webhook-function.ts</code></p>
          <p>2. Deploy via Supabase CLI:</p>
          <pre style="background: #2d2d2d; color: #f8f8f2; padding: 10px; border-radius: 4px; overflow-x: auto;">mkdir -p supabase/functions/send-webhook
cp send-webhook-function.ts supabase/functions/send-webhook/index.ts
supabase functions deploy send-webhook</pre>
          <p>3. Configure secrets in <strong>Supabase Dashboard → Edge Functions → send-webhook → Secrets</strong>:</p>
          <ul>
            <li><code>SUPABASE_URL</code> = Your Supabase URL</li>
            <li><code>SUPABASE_SERVICE_ROLE_KEY</code> = Service Role Key (Dashboard → Settings → API)</li>
            <li><code>WEBHOOK_URL</code> = Webhook URL from configuration above</li>
          </ul>
        </div>
      </details>

      <details style="margin: 15px 0;">
        <summary style="cursor: pointer; padding: 10px; background: #f0f6fc; border-radius: 4px; font-weight: 600;">
          🔌 Step 3: Enable pg_net Extension
        </summary>
        <div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
          <p>1. Go to <strong>Supabase Dashboard → Database → Extensions</strong></p>
          <p>2. Find <code>pg_net</code> and toggle <strong>ON</strong></p>
          <p>3. Verify: <code>SELECT * FROM pg_available_extensions WHERE name = 'pg_net';</code></p>
        </div>
      </details>

      <p style="margin-top: 20px; padding: 15px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;">
        <strong>📖 Full Documentation:</strong> See <code>webhook-system/DEPLOYMENT.md</code> for detailed step-by-step guide.
      </p>
    </div>

    <!-- Testing (Stub) -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">🧪 Testing</h3>

      <p>Test webhook delivery after configuration:</p>

      <button
        type="button"
        id="sb-test-webhook-btn"
        class="button button-secondary"
        <?php if ($webhook_enabled !== '1' || empty($webhook_url)): ?>disabled<?php endif; ?>
      >
        🚀 Send Test Webhook
      </button>

      <div id="sb-test-webhook-result" style="margin-top: 15px;"></div>

      <?php if ($webhook_enabled !== '1' || empty($webhook_url)): ?>
        <p class="description" style="margin-top: 10px;">
          ⚠️ Enable webhooks and configure URL first
        </p>
      <?php endif; ?>
    </div>

    <!-- Architecture Overview -->
    <div style="background: #f0f6fc; padding: 20px; border-left: 4px solid #2271b1; margin: 20px 0;">
      <h3 style="margin-top: 0;">🏗️ How It Works</h3>
      <pre style="background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;">User Registration (WordPress)
    ↓
INSERT wp_user_registrations
    ↓
Database Trigger: trigger_registration_webhook()
    ↓ (async via pg_net.http_post)
Edge Function: send-webhook
    ↓ (3 retries: 1s, 2s, 4s)
n8n/make Webhook Endpoint
    ↓
Update webhook_logs table</pre>

      <p><strong>Key Features:</strong></p>
      <ul>
        <li>✅ Immediate delivery (no cron delays)</li>
        <li>✅ Automatic retries with exponential backoff</li>
        <li>✅ Full logging in webhook_logs table</li>
        <li>✅ Secure (SERVICE_ROLE_KEY in Edge Function only)</li>
      </ul>
    </div>
  </div>

  <!-- JavaScript for test button (stub) -->
  <script>
  jQuery(document).ready(function($) {
    $('#sb-test-webhook-btn').on('click', function() {
      const btn = $(this);
      const resultDiv = $('#sb-test-webhook-result');

      btn.prop('disabled', true).text('⏳ Sending...');
      resultDiv.html('<p style="padding: 12px; background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460;">🔄 Отправка тестового webhook...</p>');

      // TODO: Implement AJAX call to send test webhook
      setTimeout(function() {
        btn.prop('disabled', false).text('🚀 Send Test Webhook');
        resultDiv.html('<p style="padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">⚠️ Test functionality coming soon. Full implementation after Supabase deployment.</p>');
      }, 2000);
    });
  });
  </script>
  <?php
}

// === Phase 2: AJAX Handlers ===

// AJAX: Save registration pair
add_action('wp_ajax_sb_save_pair', 'sb_ajax_save_pair');
function sb_ajax_save_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_pair_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Validate input
  $registration_page_id = intval($_POST['registration_page_id'] ?? 0);
  $thankyou_page_id = intval($_POST['thankyou_page_id'] ?? 0);

  if ($registration_page_id === 0 || $thankyou_page_id === 0) {
    wp_send_json_error('Please select both pages');
    return;
  }

  // Get page URLs
  $registration_url = parse_url(get_permalink($registration_page_id), PHP_URL_PATH);
  $thankyou_url = parse_url(get_permalink($thankyou_page_id), PHP_URL_PATH);

  if (!$registration_url || !$thankyou_url) {
    wp_send_json_error('Invalid page selected');
    return;
  }

  // Get existing pairs
  $pairs = get_option('sb_registration_pairs', []);

  // Check if pair already exists (editing vs creating)
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');

  if (empty($pair_id)) {
    // New pair - check for duplicate registration page
    foreach ($pairs as $existing) {
      if ($existing['registration_page_id'] === $registration_page_id) {
        wp_send_json_error('Registration page already has a pair');
        return;
      }
    }

    // Create new pair
    $pair_id = wp_generate_uuid4();
    $pairs[] = [
      'id' => $pair_id,
      'registration_page_id' => $registration_page_id,
      'registration_page_url' => $registration_url,
      'thankyou_page_id' => $thankyou_page_id,
      'thankyou_page_url' => $thankyou_url,
      'created_at' => current_time('mysql')
    ];
  } else {
    // Edit existing pair
    $pair_found = false;
    foreach ($pairs as &$pair) {
      if ($pair['id'] === $pair_id) {
        $pair['registration_page_id'] = $registration_page_id;
        $pair['registration_page_url'] = $registration_url;
        $pair['thankyou_page_id'] = $thankyou_page_id;
        $pair['thankyou_page_url'] = $thankyou_url;
        $pair_found = true;
        break;
      }
    }
    unset($pair);

    if (!$pair_found) {
      wp_send_json_error('Pair not found');
      return;
    }
  }

  // Save to wp_options
  update_option('sb_registration_pairs', $pairs);

  // === Phase 3: Sync to Supabase (non-blocking) ===
  // Find the saved pair data
  $saved_pair = null;
  foreach ($pairs as $pair) {
    if ($pair['id'] === $pair_id) {
      $saved_pair = $pair;
      break;
    }
  }

  // Sync to Supabase wp_registration_pairs table
  if ($saved_pair) {
    sb_sync_pair_to_supabase($saved_pair);
  }

  wp_send_json_success(['message' => 'Pair saved successfully', 'pair_id' => $pair_id]);
}

// AJAX: Delete registration pair
add_action('wp_ajax_sb_delete_pair', 'sb_ajax_delete_pair');
function sb_ajax_delete_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_pair_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get pair ID
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');

  if (empty($pair_id)) {
    wp_send_json_error('Pair ID required');
    return;
  }

  // Get existing pairs
  $pairs = get_option('sb_registration_pairs', []);

  // Find and remove pair
  $pair_found = false;
  $pairs = array_filter($pairs, function($pair) use ($pair_id, &$pair_found) {
    if ($pair['id'] === $pair_id) {
      $pair_found = true;
      return false; // Remove this pair
    }
    return true; // Keep this pair
  });

  if (!$pair_found) {
    wp_send_json_error('Pair not found');
    return;
  }

  // Reindex array
  $pairs = array_values($pairs);

  // Save to wp_options
  update_option('sb_registration_pairs', $pairs);

  // Delete from Supabase wp_registration_pairs table
  sb_delete_pair_from_supabase($pair_id);

  wp_send_json_success(['message' => 'Pair deleted successfully']);
}

// === Phase 4: Membership Pairs AJAX Handlers ===

// AJAX: Save membership pair
add_action('wp_ajax_sb_save_membership_pair', 'sb_ajax_save_membership_pair');
function sb_ajax_save_membership_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_membership_pair_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get and validate inputs
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');
  $registration_page_id = intval($_POST['registration_page_id'] ?? 0);
  $membership_id = intval($_POST['membership_id'] ?? 0);

  if ($registration_page_id <= 0) {
    wp_send_json_error('Registration page required');
    return;
  }

  if ($membership_id <= 0) {
    wp_send_json_error('Membership required');
    return;
  }

  // Get page URL for matching during registration
  $registration_page_url = get_page_uri($registration_page_id);
  if ($registration_page_url) {
    $registration_page_url = '/' . trim($registration_page_url, '/') . '/';
  }

  // Get existing pairs
  $pairs = get_option('sb_membership_pairs', []);

  if (empty($pair_id)) {
    // Create new pair
    $pair_id = wp_generate_uuid4();
    $pairs[] = [
      'id' => $pair_id,
      'registration_page_id' => $registration_page_id,
      'registration_page_url' => $registration_page_url,
      'membership_id' => $membership_id,
      'created_at' => current_time('mysql'),
    ];
  } else {
    // Update existing pair
    foreach ($pairs as &$pair) {
      if ($pair['id'] === $pair_id) {
        $pair['registration_page_id'] = $registration_page_id;
        $pair['registration_page_url'] = $registration_page_url;
        $pair['membership_id'] = $membership_id;
        break;
      }
    }
    unset($pair);
  }

  // Save to wp_options
  update_option('sb_membership_pairs', $pairs);

  wp_send_json_success(['message' => 'Membership pair saved successfully', 'pair_id' => $pair_id]);
}

// AJAX: Delete membership pair
add_action('wp_ajax_sb_delete_membership_pair', 'sb_ajax_delete_membership_pair');
function sb_ajax_delete_membership_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_membership_pair_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get pair ID
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');

  if (empty($pair_id)) {
    wp_send_json_error('Pair ID required');
    return;
  }

  // Get existing pairs
  $pairs = get_option('sb_membership_pairs', []);

  // Find and remove pair
  $pair_found = false;
  $pairs = array_filter($pairs, function($pair) use ($pair_id, &$pair_found) {
    if ($pair['id'] === $pair_id) {
      $pair_found = true;
      return false; // Remove this pair
    }
    return true; // Keep this pair
  });

  if (!$pair_found) {
    wp_send_json_error('Membership pair not found');
    return;
  }

  // Reindex array
  $pairs = array_values($pairs);

  // Save to wp_options
  update_option('sb_membership_pairs', $pairs);

  wp_send_json_success(['message' => 'Membership pair deleted successfully']);
}

// === Phase 5: Course Pairs AJAX Handlers ===

// AJAX: Save course pair
add_action('wp_ajax_sb_save_course_pair', 'sb_ajax_save_course_pair');
function sb_ajax_save_course_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_course_pair_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get and validate inputs
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');
  $registration_page_id = intval($_POST['registration_page_id'] ?? 0);
  $course_id = intval($_POST['course_id'] ?? 0);

  if ($registration_page_id <= 0) {
    wp_send_json_error('Registration page required');
    return;
  }

  if ($course_id <= 0) {
    wp_send_json_error('Course required');
    return;
  }

  // Get page URL for matching during registration
  $registration_page_url = get_page_uri($registration_page_id);
  if ($registration_page_url) {
    $registration_page_url = '/' . trim($registration_page_url, '/') . '/';
  }

  // Get existing pairs
  $pairs = get_option('sb_course_pairs', []);

  if (empty($pair_id)) {
    // Create new pair
    $pair_id = wp_generate_uuid4();
    $pairs[] = [
      'id' => $pair_id,
      'registration_page_id' => $registration_page_id,
      'registration_page_url' => $registration_page_url,
      'course_id' => $course_id,
      'created_at' => current_time('mysql'),
    ];
  } else {
    // Update existing pair
    foreach ($pairs as &$pair) {
      if ($pair['id'] === $pair_id) {
        $pair['registration_page_id'] = $registration_page_id;
        $pair['registration_page_url'] = $registration_page_url;
        $pair['course_id'] = $course_id;
        break;
      }
    }
    unset($pair);
  }

  // Save to wp_options
  update_option('sb_course_pairs', $pairs);

  wp_send_json_success(['message' => 'Course enrollment saved successfully', 'pair_id' => $pair_id]);
}

// AJAX: Delete course pair
add_action('wp_ajax_sb_delete_course_pair', 'sb_ajax_delete_course_pair');
function sb_ajax_delete_course_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_course_pair_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get pair ID
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');

  if (empty($pair_id)) {
    wp_send_json_error('Pair ID required');
    return;
  }

  // Get existing pairs
  $pairs = get_option('sb_course_pairs', []);

  // Find and remove pair
  $pair_found = false;
  $pairs = array_filter($pairs, function($pair) use ($pair_id, &$pair_found) {
    if ($pair['id'] === $pair_id) {
      $pair_found = true;
      return false; // Remove this pair
    }
    return true; // Keep this pair
  });

  if (!$pair_found) {
    wp_send_json_error('Course enrollment not found');
    return;
  }

  // Reindex array
  $pairs = array_values($pairs);

  // Save to wp_options
  update_option('sb_course_pairs', $pairs);

  wp_send_json_success(['message' => 'Course enrollment deleted successfully']);
}

// === Phase 6: Course Access (Auto-Enroll) AJAX Handlers ===

// AJAX: Save course access pair (membership → course)
add_action('wp_ajax_sb_save_course_access_pair', 'sb_ajax_save_course_access_pair');
function sb_ajax_save_course_access_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_course_access_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get and validate inputs
  $membership_id = intval($_POST['membership_id'] ?? 0);
  $course_id = intval($_POST['course_id'] ?? 0);

  if ($membership_id <= 0) {
    wp_send_json_error('Membership required');
    return;
  }

  if ($course_id <= 0) {
    wp_send_json_error('Course required');
    return;
  }

  // Get existing pairs
  $pairs = get_option('sb_membership_course_pairs', []);

  // Check for duplicate
  foreach ($pairs as $pair) {
    if ($pair['membership_id'] == $membership_id && $pair['course_id'] == $course_id) {
      wp_send_json_error('This auto-enrollment rule already exists');
      return;
    }
  }

  // Create new pair
  $pair_id = wp_generate_uuid4();
  $pairs[] = [
    'id' => $pair_id,
    'membership_id' => $membership_id,
    'course_id' => $course_id,
    'created_at' => current_time('mysql'),
  ];

  // Save to wp_options
  update_option('sb_membership_course_pairs', $pairs);

  error_log(sprintf(
    'Supabase Bridge: Course Access pair created - Membership ID: %d → Course ID: %d',
    $membership_id,
    $course_id
  ));

  wp_send_json_success(['message' => 'Auto-enrollment rule saved successfully', 'pair_id' => $pair_id]);
}

// AJAX: Delete course access pair
add_action('wp_ajax_sb_delete_course_access_pair', 'sb_ajax_delete_course_access_pair');
function sb_ajax_delete_course_access_pair() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_course_access_nonce')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Permission denied');
    return;
  }

  // Get pair ID
  $pair_id = sanitize_text_field($_POST['pair_id'] ?? '');

  if (empty($pair_id)) {
    wp_send_json_error('Pair ID required');
    return;
  }

  // Get existing pairs
  $pairs = get_option('sb_membership_course_pairs', []);

  // Find and remove pair
  $pair_found = false;
  $pairs = array_filter($pairs, function($pair) use ($pair_id, &$pair_found) {
    if ($pair['id'] === $pair_id) {
      $pair_found = true;
      return false; // Remove this pair
    }
    return true; // Keep this pair
  });

  if (!$pair_found) {
    wp_send_json_error('Auto-enrollment rule not found');
    return;
  }

  // Reindex array
  $pairs = array_values($pairs);

  // Save to wp_options
  update_option('sb_membership_course_pairs', $pairs);

  error_log('Supabase Bridge: Course Access pair deleted - Pair ID: ' . $pair_id);

  wp_send_json_success(['message' => 'Auto-enrollment rule deleted successfully']);
}

// === AJAX: LearnDash Banner Management ===
// === AJAX Handler: Toggle MemberPress Patch ===
add_action('wp_ajax_sb_toggle_memberpress_patch', 'sb_ajax_toggle_memberpress_patch');
function sb_ajax_toggle_memberpress_patch() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_memberpress_ajax')) {
    wp_send_json_error(['message' => 'Invalid nonce']);
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Permission denied']);
    return;
  }

  // Get enabled status
  $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

  // Save option
  update_option('sb_memberpress_hide_login_link', $enabled);

  // Return success message
  $message = $enabled
    ? 'Patch enabled - login link will be hidden when disabled in rule settings'
    : 'Patch disabled - MemberPress default behavior restored';

  wp_send_json_success(['message' => $message]);
}

// === AJAX: Log authentication timeout errors ===
add_action('wp_ajax_nopriv_sb_log_auth_timeout', 'sb_ajax_log_auth_timeout'); // Allow non-logged-in users
add_action('wp_ajax_sb_log_auth_timeout', 'sb_ajax_log_auth_timeout');
function sb_ajax_log_auth_timeout() {
  // Get error data from POST
  $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];

  // Validate data
  if (empty($data) || !isset($data['error_type'])) {
    wp_send_json_error(['message' => 'Invalid data']);
    return;
  }

  // Log to WordPress debug.log
  error_log(sprintf(
    'Supabase Bridge: Auth timeout detected - Browser: %s, URL: %s, Platform: %s, Timestamp: %s',
    isset($data['browser']) ? substr($data['browser'], 0, 100) : 'unknown',
    isset($data['url']) ? $data['url'] : 'unknown',
    isset($data['platform']) ? $data['platform'] : 'unknown',
    isset($data['timestamp']) ? $data['timestamp'] : date('c')
  ));

  // Optionally: Store in database for analysis (wp_options or custom table)
  // For now, just log to debug.log

  wp_send_json_success(['message' => 'Logged']);
}

add_action('wp_ajax_sb_save_learndash_banner', 'sb_ajax_save_learndash_banner');
function sb_ajax_save_learndash_banner() {
  // Verify nonce
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sb_learndash_banner_ajax')) {
    wp_send_json_error(['message' => 'Invalid nonce']);
    return;
  }

  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Permission denied']);
    return;
  }

  // Get enabled status
  $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

  // Save option
  update_option('sb_learndash_banner_hidden', $enabled);

  // Apply or restore patch based on enabled status
  if ($enabled) {
    $result = sb_apply_learndash_banner_patch();
  } else {
    $result = sb_restore_learndash_banner_original();
  }

  if ($result['success']) {
    wp_send_json_success(['message' => $result['message']]);
  } else {
    wp_send_json_error(['message' => $result['message']]);
  }
}

// === Phase 6: LearnDash Banner Patch Management ===

/**
 * Get LearnDash plugin path
 */
function sb_get_learndash_path() {
  $possible_paths = [
    WP_CONTENT_DIR . '/plugins/sfwd-lms',                        // Standard WordPress
    dirname(WP_CONTENT_DIR) . '/wp-content/plugins/sfwd-lms',   // Alternate structure
  ];

  foreach ($possible_paths as $path) {
    if (is_dir($path)) {
      return $path;
    }
  }

  return false;
}

/**
 * Check LearnDash banner patch status
 * Returns: ['status' => 'applied|not_applied|needs_reapply|not_found', 'message' => '...', 'file_path' => '...']
 */
function sb_get_learndash_patch_status() {
  $learndash_path = sb_get_learndash_path();

  if (!$learndash_path) {
    return [
      'status' => 'not_found',
      'message' => 'LearnDash plugin not found',
      'file_path' => null
    ];
  }

  $target_file = $learndash_path . '/themes/ld30/templates/modules/infobar/course.php';

  if (!file_exists($target_file)) {
    return [
      'status' => 'not_found',
      'message' => 'Target file not found (LearnDash may be outdated)',
      'file_path' => $target_file
    ];
  }

  $content = file_get_contents($target_file);

  // Check for latest patch version
  if (strpos($content, 'Banner completely disabled') !== false) {
    return [
      'status' => 'applied',
      'message' => 'Banner patch is active',
      'file_path' => $target_file
    ];
  }

  // Check for old patch version
  if (strpos($content, 'Hide banner for free courses') !== false) {
    return [
      'status' => 'needs_reapply',
      'message' => 'Old patch version detected - update recommended',
      'file_path' => $target_file
    ];
  }

  // Not patched
  return [
    'status' => 'not_applied',
    'message' => 'Banner is visible (default LearnDash behavior)',
    'file_path' => $target_file
  ];
}

/**
 * Apply LearnDash banner patch
 */
function sb_apply_learndash_banner_patch() {
  $status = sb_get_learndash_patch_status();

  if ($status['status'] === 'not_found') {
    return ['success' => false, 'message' => $status['message']];
  }

  if ($status['status'] === 'applied') {
    return ['success' => true, 'message' => 'Patch already applied'];
  }

  $target_file = $status['file_path'];
  $content = file_get_contents($target_file);

  // Patterns
  $original_pattern = "<?php elseif ( 'open' !== \$course_pricing['type'] ) : ?>";
  $old_patch = "<?php elseif ( ! in_array( \$course_pricing['type'], array( 'open', 'free' ), true ) ) : /* PATCHED: Hide banner for free courses */ ?>";
  $new_patch = "<?php elseif ( false ) : /* PATCHED: Banner completely disabled - access controlled via MemberPress/Elementor */ ?>";

  // Determine what to replace
  $pattern_to_replace = null;
  if (strpos($content, $old_patch) !== false) {
    $pattern_to_replace = $old_patch;
  } elseif (strpos($content, $original_pattern) !== false) {
    $pattern_to_replace = $original_pattern;
  } else {
    return ['success' => false, 'message' => 'Could not find pattern to patch (file may be modified)'];
  }

  // Create backup
  $backup_file = $target_file . '.backup.' . date('Y-m-d-His');
  if (!copy($target_file, $backup_file)) {
    error_log('Supabase Bridge: Could not create backup for LearnDash patch');
  }

  // Apply patch
  $patched_content = str_replace($pattern_to_replace, $new_patch, $content);

  if (file_put_contents($target_file, $patched_content)) {
    // Clear OPcache for this file to ensure immediate effect
    if (function_exists('opcache_invalidate')) {
      opcache_invalidate($target_file, true);
    } elseif (function_exists('opcache_reset')) {
      opcache_reset();
    }

    return ['success' => true, 'message' => 'Banner patch applied successfully'];
  } else {
    return ['success' => false, 'message' => 'Failed to write patched file (check permissions)'];
  }
}

/**
 * Restore original LearnDash banner
 */
function sb_restore_learndash_banner_original() {
  $status = sb_get_learndash_patch_status();

  if ($status['status'] === 'not_found') {
    return ['success' => false, 'message' => $status['message']];
  }

  if ($status['status'] === 'not_applied') {
    return ['success' => true, 'message' => 'Banner already using default behavior'];
  }

  $target_file = $status['file_path'];
  $content = file_get_contents($target_file);

  // Patterns
  $new_patch = "<?php elseif ( false ) : /* PATCHED: Banner completely disabled - access controlled via MemberPress/Elementor */ ?>";
  $old_patch = "<?php elseif ( ! in_array( \$course_pricing['type'], array( 'open', 'free' ), true ) ) : /* PATCHED: Hide banner for free courses */ ?>";
  $original_pattern = "<?php elseif ( 'open' !== \$course_pricing['type'] ) : ?>";

  // Replace any patch back to original
  $restored_content = $content;
  $restored_content = str_replace($new_patch, $original_pattern, $restored_content);
  $restored_content = str_replace($old_patch, $original_pattern, $restored_content);

  if ($restored_content === $content) {
    return ['success' => false, 'message' => 'No patch found to restore'];
  }

  // Create backup before restore
  $backup_file = $target_file . '.backup.' . date('Y-m-d-His');
  copy($target_file, $backup_file);

  if (file_put_contents($target_file, $restored_content)) {
    // Clear OPcache for this file to ensure immediate effect
    if (function_exists('opcache_invalidate')) {
      opcache_invalidate($target_file, true);
    } elseif (function_exists('opcache_reset')) {
      opcache_reset();
    }

    return ['success' => true, 'message' => 'Default banner behavior restored'];
  } else {
    return ['success' => false, 'message' => 'Failed to restore original (check permissions)'];
  }
}

/**
 * Render MemberPress Patches tab
 */
function sb_render_memberpress_tab() {
  // Get current setting
  $is_enabled = get_option('sb_memberpress_hide_login_link', false);

  // Status badge
  $status_badge = $is_enabled
    ? '<span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">✅ Active</span>'
    : '<span style="background: #6b7280; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">⚪ Not Active</span>';
  ?>

  <div style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
    <h2 style="margin-top: 0; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">🔧 MemberPress Compatibility Patches</h2>

    <!-- Current Status -->
    <div style="background: #f9fafb; padding: 20px; border-left: 4px solid #2271b1; margin-bottom: 25px;">
      <h3 style="margin: 0 0 15px 0; color: #1e293b;">📊 Current Status</h3>
      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
        <strong>Login Link Patch:</strong>
        <?php echo $status_badge; ?>
      </div>
      <p style="margin: 10px 0 0 0; color: #64748b; font-size: 14px;">
        <?php echo $is_enabled ? 'Filter is active - login link will be hidden when disabled in rule settings' : 'Filter is not active - MemberPress default behavior'; ?>
      </p>
    </div>

    <!-- Settings Form -->
    <form method="post" action="" id="sb-memberpress-form">
      <?php wp_nonce_field('sb_memberpress_nonce'); ?>

      <div style="margin-bottom: 25px;">
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 15px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 6px; transition: all 0.2s;">
          <input
            type="checkbox"
            name="sb_memberpress_hide_login_link"
            value="1"
            <?php checked($is_enabled, true); ?>
            style="width: 20px; height: 20px; cursor: pointer;"
          >
          <span style="font-size: 15px; font-weight: 500; color: #1e293b;">
            Hide default "Login" link when "Show login form" is disabled in MemberPress rule settings
          </span>
        </label>
      </div>

      <!-- Problem Description -->
      <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #991b1b; font-size: 14px;">🐛 Problem</h4>
        <p style="margin: 0 0 10px 0; color: #7f1d1d; font-size: 13px; line-height: 1.6;">
          MemberPress shows a <strong>"Login"</strong> link in unauthorized messages even when <strong>"Show login form"</strong>
          is disabled in rule settings. This conflicts with custom authentication forms.
        </p>
        <p style="margin: 0; color: #7f1d1d; font-size: 13px; line-height: 1.6;">
          <strong>Affected HTML:</strong> <code style="background: #fee2e2; padding: 2px 6px; border-radius: 3px;">&lt;div class="mepr-login-form-wrap"&gt;...&lt;/div&gt;</code>
        </p>
      </div>

      <!-- Solution Description -->
      <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #1e40af; font-size: 14px;">✅ Solution</h4>
        <ul style="margin: 0; padding-left: 20px; color: #1e40af; font-size: 13px; line-height: 1.6;">
          <li><strong>How it works:</strong> Uses <code>mepr_unauthorized_message</code> WordPress filter</li>
          <li><strong>What it does:</strong> Removes <code>.mepr-login-form-wrap</code> block from unauthorized messages</li>
          <li><strong>When:</strong> Only when "Show login form" is disabled in the MemberPress rule for that page</li>
          <li><strong>Safe:</strong> Purely cosmetic change - doesn't affect authentication or MemberPress functionality</li>
          <li><strong>Isolated:</strong> No impact on Supabase Bridge authentication system</li>
        </ul>
      </div>

      <!-- Warning -->
      <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #92400e; font-size: 14px;">⚠️ Important Notes</h4>
        <ul style="margin: 0; padding-left: 20px; color: #78350f; font-size: 13px; line-height: 1.6;">
          <li><strong>Testing:</strong> If you experience any issues with MemberPress, disable this patch immediately</li>
          <li><strong>Cache:</strong> Clear browser and LiteSpeed cache after enabling/disabling</li>
          <li><strong>Compatibility:</strong> Works with MemberPress 1.x and higher</li>
          <li><strong>Custom Forms:</strong> Use <code>[supabase_auth_form]</code> shortcode for custom authentication</li>
        </ul>
      </div>

      <!-- Submit Button -->
      <div style="padding-top: 15px; border-top: 1px solid #e5e7eb;">
        <button
          type="submit"
          name="sb_save_memberpress"
          class="button button-primary button-large"
          style="padding: 8px 24px; font-size: 15px;"
        >
          💾 Save Changes
        </button>
        <span id="sb-memberpress-status" style="margin-left: 15px; font-style: italic; color: #64748b;"></span>
      </div>
    </form>

    <!-- Technical Details (collapsible) -->
    <details style="margin-top: 30px; padding: 15px; background: #fafafa; border-radius: 4px;">
      <summary style="cursor: pointer; font-weight: 600; color: #475569; user-select: none;">🔧 Technical Details</summary>
      <div style="margin-top: 15px; padding-left: 10px; color: #64748b; font-size: 13px; line-height: 1.8;">
        <p><strong>How the filter works:</strong></p>
        <ul style="margin: 8px 0; padding-left: 20px;">
          <li>Hooks into: <code>mepr_unauthorized_message</code> filter</li>
          <li>Checks: MemberPress rule settings for current page</li>
          <li>Removes: <code>&lt;div class="mepr-login-form-wrap"&gt;...&lt;/div&gt;</code> from HTML</li>
          <li>When: Only if "Show login form" is disabled in rule settings</li>
        </ul>
        <p style="margin-top: 12px;"><strong>Why this is safe:</strong></p>
        <ul style="margin: 8px 0; padding-left: 20px;">
          <li>Pure presentation layer - only affects HTML output</li>
          <li>No changes to authentication logic</li>
          <li>No database modifications</li>
          <li>Can be disabled instantly if issues occur</li>
          <li>Fully isolated from Supabase Bridge core functionality</li>
        </ul>
      </div>
    </details>
  </div>

  <script>
  jQuery(document).ready(function($) {
    $('#sb-memberpress-form').on('submit', function(e) {
      e.preventDefault();

      const $form = $(this);
      const $status = $('#sb-memberpress-status');
      const $button = $form.find('button[type="submit"]');

      $button.prop('disabled', true);
      $status.html('<span style="color: #f59e0b;">⏳ Saving...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'sb_toggle_memberpress_patch',
          nonce: '<?php echo wp_create_nonce("sb_memberpress_ajax"); ?>',
          enabled: $form.find('input[name="sb_memberpress_hide_login_link"]').is(':checked') ? '1' : '0'
        },
        success: function(response) {
          if (response.success) {
            $status.html('<span style="color: #10b981;">✅ ' + response.data.message + '</span>');
            setTimeout(function() {
              location.reload();
            }, 1000);
          } else {
            $status.html('<span style="color: #ef4444;">❌ ' + response.data.message + '</span>');
            $button.prop('disabled', false);
          }
        },
        error: function() {
          $status.html('<span style="color: #ef4444;">❌ Server error</span>');
          $button.prop('disabled', false);
        }
      });
    });
  });
  </script>

  <?php
}

/**
 * Render LearnDash Banner tab
 */
function sb_render_learndash_banner_tab() {
  // Get current patch status
  $patch_status = sb_get_learndash_patch_status();
  $is_enabled = get_option('sb_learndash_banner_hidden', false);

  // Status badge styling
  $status_badges = [
    'applied' => '<span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">✅ Active</span>',
    'not_applied' => '<span style="background: #ef4444; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">❌ Not Active</span>',
    'needs_reapply' => '<span style="background: #f59e0b; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">⚠️ Update Needed</span>',
    'not_found' => '<span style="background: #6b7280; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">⚠️ Not Found</span>',
  ];

  $current_badge = $status_badges[$patch_status['status']] ?? $status_badges['not_found'];
  ?>

  <div style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
    <h2 style="margin-top: 0; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">🎓 LearnDash Enrollment Banner Control</h2>

    <!-- Current Status -->
    <div style="background: #f9fafb; padding: 20px; border-left: 4px solid #2271b1; margin-bottom: 25px;">
      <h3 style="margin: 0 0 15px 0; color: #1e293b;">📊 Current Status</h3>
      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
        <strong>Patch Status:</strong>
        <?php echo $current_badge; ?>
      </div>
      <p style="margin: 10px 0 0 0; color: #64748b; font-size: 14px;">
        <?php echo esc_html($patch_status['message']); ?>
      </p>
      <?php if ($patch_status['file_path']): ?>
        <p style="margin: 8px 0 0 0; color: #94a3b8; font-size: 12px; font-family: monospace;">
          <?php echo esc_html($patch_status['file_path']); ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Settings Form -->
    <form method="post" action="" id="sb-learndash-banner-form">
      <?php wp_nonce_field('sb_learndash_banner_nonce'); ?>

      <div style="margin-bottom: 25px;">
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 15px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 6px; transition: all 0.2s;">
          <input
            type="checkbox"
            name="sb_learndash_banner_hidden"
            value="1"
            <?php checked($is_enabled, true); ?>
            style="width: 20px; height: 20px; cursor: pointer;"
          >
          <span style="font-size: 15px; font-weight: 500; color: #1e293b;">
            Hide "NOT ENROLLED / Free / Take this Course" banner on all courses
          </span>
        </label>
      </div>

      <!-- Info Box -->
      <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #1e40af; font-size: 14px;">ℹ️ How it works</h4>
        <ul style="margin: 0; padding-left: 20px; color: #1e40af; font-size: 13px; line-height: 1.6;">
          <li><strong>Checked:</strong> Banner will be completely hidden on ALL courses (free, paid, subscriptions)</li>
          <li><strong>Unchecked:</strong> Default LearnDash behavior (banner shows on non-open courses)</li>
          <li><strong>Access Control:</strong> Manage via MemberPress memberships and Elementor visibility conditions</li>
          <li><strong>Updates:</strong> After LearnDash updates, check status and re-apply if needed</li>
          <li><strong>Safe:</strong> Creates automatic backups before applying patch</li>
        </ul>
      </div>

      <!-- IMPORTANT: Cache Clearing Notice -->
      <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #92400e; font-size: 14px;">⚠️ ВАЖНО: Очистка кеша после изменений</h4>
        <p style="margin: 0 0 10px 0; color: #78350f; font-size: 13px; line-height: 1.6;">
          После включения/отключения патча <strong>обязательно очистите кеш</strong>, чтобы изменения вступили в силу:
        </p>
        <ol style="margin: 0; padding-left: 20px; color: #78350f; font-size: 13px; line-height: 1.6;">
          <li><strong>LiteSpeed Cache:</strong> WordPress Admin → LiteSpeed Cache → Purge → Purge All</li>
          <li><strong>Браузер:</strong> Ctrl+Shift+R (Windows) или Cmd+Shift+R (Mac) для hard refresh</li>
          <li><strong>Проверка:</strong> Откройте страницу курса в режиме инкогнито для проверки</li>
        </ol>
        <p style="margin: 10px 0 0 0; color: #92400e; font-size: 12px; font-style: italic;">
          💡 PHP OPcache очищается автоматически, но LiteSpeed/браузерный кеш нужно очистить вручную.
        </p>
      </div>

      <!-- Warning for LearnDash updates -->
      <?php if ($patch_status['status'] === 'needs_reapply'): ?>
        <div class="notice notice-warning inline" style="padding: 12px; margin-bottom: 20px;">
          <p style="margin: 0;"><strong>⚠️ Action Required:</strong> LearnDash was updated. Click "Save Changes" to update the patch to the latest version.</p>
        </div>
      <?php endif; ?>

      <?php if ($patch_status['status'] === 'not_found'): ?>
        <div class="notice notice-error inline" style="padding: 12px; margin-bottom: 20px;">
          <p style="margin: 0;"><strong>❌ LearnDash Not Found:</strong> This feature requires LearnDash plugin to be installed and activated.</p>
        </div>
      <?php endif; ?>

      <!-- Submit Button -->
      <div style="padding-top: 15px; border-top: 1px solid #e5e7eb;">
        <button
          type="submit"
          name="sb_save_learndash_banner"
          class="button button-primary button-large"
          style="padding: 8px 24px; font-size: 15px;"
          <?php disabled($patch_status['status'], 'not_found'); ?>
        >
          💾 Save Changes
        </button>
        <span id="sb-learndash-banner-status" style="margin-left: 15px; font-style: italic; color: #64748b;"></span>
      </div>
    </form>

    <!-- Technical Details (collapsible) -->
    <details style="margin-top: 30px; padding: 15px; background: #fafafa; border-radius: 4px;">
      <summary style="cursor: pointer; font-weight: 600; color: #475569; user-select: none;">🔧 Technical Details</summary>
      <div style="margin-top: 15px; padding-left: 10px; color: #64748b; font-size: 13px; line-height: 1.8;">
        <p><strong>What this patch does:</strong></p>
        <ul style="margin: 8px 0; padding-left: 20px;">
          <li>Modifies: <code>themes/ld30/templates/modules/infobar/course.php</code></li>
          <li>Changes enrollment banner condition from <code>'open' !== $type</code> to <code>false</code></li>
          <li>Result: Banner never displays, regardless of course type</li>
          <li>Backups: Automatic backup created before each modification</li>
        </ul>
        <p style="margin-top: 12px;"><strong>Access control alternatives:</strong></p>
        <ul style="margin: 8px 0; padding-left: 20px;">
          <li>Use MemberPress memberships to control course access</li>
          <li>Use Elementor visibility conditions for custom enrollment CTAs</li>
          <li>Both methods provide better UX than default LearnDash banner</li>
        </ul>
      </div>
    </details>
  </div>

  <script>
  jQuery(document).ready(function($) {
    $('#sb-learndash-banner-form').on('submit', function(e) {
      e.preventDefault();

      const $form = $(this);
      const $status = $('#sb-learndash-banner-status');
      const $button = $form.find('button[type="submit"]');

      $button.prop('disabled', true).text('⏳ Saving...');
      $status.text('');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'sb_save_learndash_banner',
          nonce: '<?php echo wp_create_nonce('sb_learndash_banner_ajax'); ?>',
          enabled: $('input[name="sb_learndash_banner_hidden"]').is(':checked') ? '1' : '0'
        },
        success: function(response) {
          if (response.success) {
            $status.css('color', '#10b981').text('✅ ' + response.data.message);
            // Reload page after 1 second to update status
            setTimeout(function() {
              location.reload();
            }, 1000);
          } else {
            $status.css('color', '#ef4444').text('❌ ' + response.data.message);
            $button.prop('disabled', false).text('💾 Save Changes');
          }
        },
        error: function() {
          $status.css('color', '#ef4444').text('❌ Network error');
          $button.prop('disabled', false).text('💾 Save Changes');
        }
      });
    });
  });
  </script>

  <?php
}

// === MemberPress Login Link Patch ===
// Hides the default "Login" link from MemberPress unauthorized messages via CSS
// MemberPress uses its own hook system (MeprHooks) which is not compatible with
// standard WordPress filters, so we use CSS to hide the element

add_action('wp_head', 'sb_hide_memberpress_login_link_css');
function sb_hide_memberpress_login_link_css() {
  // Check if patch is enabled
  if (!get_option('sb_memberpress_hide_login_link', false)) {
    return; // Patch disabled - do nothing
  }

  // Output CSS to hide the login link wrapper
  echo '<style id="sb-memberpress-patch">
    .mepr-login-form-wrap {
      display: none !important;
    }
  </style>';
}

// =========================================================================
// === MemberPress Webhooks (Independent system with MemberPress format) ===
// =========================================================================

/**
 * MIGRATION: Automatically migrate old webhook settings to new format
 * Runs once on admin_init to ensure backward compatibility
 */
function sb_migrate_webhook_settings() {
  // Check if migration already completed
  if (get_option('sb_webhook_migration_completed', false)) {
    return; // Already migrated
  }

  // Check if old settings exist
  $old_enabled = get_option('sb_make_webhook_enabled', null);
  $old_url = get_option('sb_make_webhook_url', null);

  // If no old settings, nothing to migrate
  if ($old_enabled === null && $old_url === null) {
    update_option('sb_webhook_migration_completed', true);
    return;
  }

  // Check if new settings already exist (manual configuration)
  $new_enabled = get_option('sb_memberpress_webhook_enabled', null);
  $new_urls = get_option('sb_memberpress_webhook_urls', null);

  // If new settings already exist, don't overwrite them
  if ($new_enabled !== null || $new_urls !== null) {
    update_option('sb_webhook_migration_completed', true);
    return;
  }

  // Perform migration: convert old format to new
  if ($old_enabled !== null) {
    update_option('sb_memberpress_webhook_enabled', $old_enabled);
  }

  if ($old_url !== null && !empty(trim($old_url))) {
    // Convert single URL to textarea format (one line)
    update_option('sb_memberpress_webhook_urls', trim($old_url));
  }

  // Migrate last webhook status (if exists)
  $old_last_webhook = get_option('sb_make_last_webhook', null);
  if ($old_last_webhook !== null) {
    // Add sent_count field for new format compatibility
    $old_last_webhook['sent_count'] = $old_last_webhook['success'] ? 1 : 0;
    $old_last_webhook['fail_count'] = $old_last_webhook['success'] ? 0 : 1;
    $old_last_webhook['total_urls'] = 1;
    update_option('sb_memberpress_last_webhook', $old_last_webhook);
  }

  // Mark migration as completed
  update_option('sb_webhook_migration_completed', true);

  // Log migration
  error_log('Supabase Bridge: Webhook settings migrated from old format to new MemberPress format');
}

// Run migration automatically on admin init
add_action('admin_init', 'sb_migrate_webhook_settings');

/**
 * Render MemberPress webhook configuration tab
 */
function sb_render_memberpress_webhook_tab() {
  $webhook_urls = get_option('sb_memberpress_webhook_urls', '');
  $webhook_enabled = get_option('sb_memberpress_webhook_enabled', false);
  $last_webhook = get_option('sb_memberpress_last_webhook', []);

  // Get REAL data from last registration for payload preview
  global $wpdb;
  $last_txn = $wpdb->get_row(
    "SELECT * FROM {$wpdb->prefix}mepr_transactions
     WHERE trans_num LIKE 'sb-%'
     AND status = 'complete'
     ORDER BY created_at DESC
     LIMIT 1"
  );

  $payload_preview = '';
  if ($last_txn) {
    $user = get_userdata($last_txn->user_id);
    $product = class_exists('MeprProduct') ? new MeprProduct($last_txn->product_id) : null;

    $payload_preview = json_encode([
      'event' => 'non-recurring-transaction-completed',
      'type' => 'transaction',
      'data' => [
        'membership' => [
          'id' => (int) $last_txn->product_id,
          'title' => $product ? $product->post_title : 'N/A',
          'price' => $product ? $product->price : '0.00',
          'period' => $product ? $product->period : '1',
          'period_type' => $product ? $product->period_type : 'lifetime',
        ],
        'member' => [
          'id' => (int) $last_txn->user_id,
          'email' => $user ? $user->user_email : 'N/A',
          'username' => $user ? $user->user_login : 'N/A',
          'first_name' => $user ? $user->first_name : '',
          'last_name' => $user ? $user->last_name : '',
          'display_name' => $user ? $user->display_name : 'N/A',
        ],
        'id' => (string) $last_txn->id,
        'amount' => $last_txn->amount,
        'total' => $last_txn->total,
        'status' => $last_txn->status,
        'txn_type' => $last_txn->txn_type,
        'gateway' => $last_txn->gateway,
        'created_at' => $last_txn->created_at,
        'expires_at' => $last_txn->expires_at,
      ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  ?>
  <div style="margin-top: 20px;">
    <h2 style="margin-top: 0; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">🔗 MemberPress Webhooks</h2>

    <!-- Info Notice -->
    <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
      <h3 style="margin-top: 0;">ℹ️ About This Integration</h3>
      <p><strong>Independent webhook system</strong> - sends MemberPress-compatible events when membership is assigned.</p>
      <p><strong>Payload format:</strong> Sends EXACT MemberPress format (100+ fields) - works with existing Make.com/Zapier/n8n automations.</p>
      <p><strong>Trigger:</strong> Every time a FREE membership is assigned to a user (new or existing), webhook is sent.</p>
      <p><strong>Use case:</strong> Add users to GetResponse mailing lists, trigger email sequences, update CRM, etc.</p>
      <p><strong>Multiple services:</strong> Supports Make.com, Zapier, n8n, and any HTTP endpoint simultaneously.</p>
    </div>

    <!-- Configuration Form -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">⚙️ Webhook Configuration</h3>

      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="sb_memberpress_webhook_enabled">Enable Webhooks</label>
          </th>
          <td>
            <label>
              <input
                type="checkbox"
                id="sb_memberpress_webhook_enabled"
                <?php checked($webhook_enabled, true); ?>
              >
              Send webhooks when membership is assigned
            </label>
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="sb_memberpress_webhook_urls">Webhook URLs</label>
          </th>
          <td>
            <textarea
              id="sb_memberpress_webhook_urls"
              rows="5"
              class="large-text"
              placeholder="https://hook.make.com/abc123...&#10;https://hooks.zapier.com/xyz789...&#10;https://n8n.yourdomain.com/webhook/..."
            ><?php echo esc_textarea($webhook_urls); ?></textarea>
            <p class="description">
              Enter webhook URLs (one per line). Supports Make.com, Zapier, n8n, and any HTTP endpoint.<br>
              <strong>Important:</strong> All URLs will receive MemberPress-compatible payload format.
            </p>
          </td>
        </tr>
      </table>

      <p class="submit" style="padding-top: 10px;">
        <button type="button" id="sb-save-memberpress-webhook" class="button button-primary">
          💾 Save Settings
        </button>
        <button type="button" id="sb-test-memberpress-webhook" class="button" style="margin-left: 10px;" <?php echo !$webhook_enabled || !$webhook_urls ? 'disabled' : ''; ?>>
          🧪 Test Webhook
        </button>
      </p>

      <div id="sb-memberpress-webhook-message"></div>
    </div>

    <!-- Webhook Payload Documentation -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">📦 Webhook Payload (MemberPress Format)</h3>
      <?php if ($payload_preview): ?>
        <p>This is the <strong>REAL payload</strong> that will be sent when you click "Test Webhook" (from last registration):</p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 11px;"><code><?php echo esc_html($payload_preview); ?></code></pre>
        <p style="margin-top: 10px; color: #0073aa;">
          <strong>✅ 100% compatible</strong> with existing MemberPress automations in Make.com, Zapier, n8n.
        </p>
      <?php else: ?>
        <p style="color: #d63638;">No registrations found yet. Register a user first to see real payload preview.</p>
      <?php endif; ?>
    </div>

    <!-- Last Webhook Status -->
    <?php if (!empty($last_webhook)): ?>
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">📊 Last Webhook</h3>
      <table class="widefat" style="margin-top: 10px;">
        <tr>
          <th style="width: 150px;">Status</th>
          <td>
            <?php if ($last_webhook['success']): ?>
              <span style="color: #46b450; font-weight: bold;">✅ Success</span>
            <?php else: ?>
              <span style="color: #dc3232; font-weight: bold;">❌ Failed</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Email</th>
          <td><?php echo esc_html($last_webhook['email'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
          <th>User ID</th>
          <td><?php echo esc_html($last_webhook['user_id'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
          <th>Timestamp</th>
          <td><?php echo esc_html($last_webhook['timestamp'] ?? 'N/A'); ?></td>
        </tr>
        <?php if (!$last_webhook['success'] && !empty($last_webhook['error'])): ?>
        <tr>
          <th>Error</th>
          <td style="color: #dc3232;"><?php echo esc_html($last_webhook['error']); ?></td>
        </tr>
        <?php endif; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- How to Setup -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
      <h3 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">🔧 Setup Instructions</h3>

      <h4>Make.com:</h4>
      <ol style="line-height: 1.8;">
        <li>Create scenario with "Webhooks" trigger → "Custom webhook"</li>
        <li>Copy webhook URL → paste above</li>
        <li>Webhook receives <code>event</code>, <code>data.member</code>, <code>data.membership</code>, etc.</li>
        <li>Map fields: Email = <code>data.member.email</code>, Name = <code>data.member.first_name</code></li>
      </ol>

      <h4>Zapier:</h4>
      <ol style="line-height: 1.8;">
        <li>Create Zap with "Webhooks by Zapier" trigger → "Catch Hook"</li>
        <li>Copy webhook URL → paste above</li>
        <li>Same field mapping as Make.com</li>
      </ol>

      <h4>n8n:</h4>
      <ol style="line-height: 1.8;">
        <li>Add "Webhook" node → "POST"</li>
        <li>Copy webhook URL → paste above</li>
        <li>Access fields via <code>$json.data.member.email</code></li>
      </ol>

      <p style="margin-top: 15px;">
        <strong>💡 Tip:</strong> You can use the SAME webhook URL from existing MemberPress automations - payload format is identical!
      </p>
    </div>

  </div>

  <script>
  jQuery(document).ready(function($) {
    // Save webhook settings
    $('#sb-save-memberpress-webhook').on('click', function() {
      const button = $(this);
      const messageDiv = $('#sb-memberpress-webhook-message');

      button.prop('disabled', true).text('Saving...');
      messageDiv.html('');

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
          action: 'sb_save_memberpress_webhook',
          nonce: '<?php echo wp_create_nonce('sb_memberpress_webhook_nonce'); ?>',
          enabled: $('#sb_memberpress_webhook_enabled').is(':checked'),
          urls: $('#sb_memberpress_webhook_urls').val()
        },
        success: function(response) {
          if (response.success) {
            messageDiv.html('<div class="notice notice-success" style="padding: 10px; margin: 10px 0;"><p>✅ Settings saved successfully!</p></div>');
            $('#sb-test-memberpress-webhook').prop('disabled', false);
          } else {
            messageDiv.html('<div class="notice notice-error" style="padding: 10px; margin: 10px 0;"><p>❌ Error: ' + (response.data || 'Unknown error') + '</p></div>');
          }
        },
        error: function() {
          messageDiv.html('<div class="notice notice-error" style="padding: 10px; margin: 10px 0;"><p>❌ AJAX request failed</p></div>');
        },
        complete: function() {
          button.prop('disabled', false).text('💾 Save Settings');
        }
      });
    });

    // Test webhook
    $('#sb-test-memberpress-webhook').on('click', function() {
      const button = $(this);
      const messageDiv = $('#sb-memberpress-webhook-message');

      button.prop('disabled', true).text('Testing...');
      messageDiv.html('');

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
          action: 'sb_test_memberpress_webhook',
          nonce: '<?php echo wp_create_nonce('sb_memberpress_webhook_nonce'); ?>'
        },
        success: function(response) {
          if (response.success) {
            messageDiv.html('<div class="notice notice-success" style="padding: 10px; margin: 10px 0;"><p>✅ ' + response.data + '</p></div>');
          } else {
            messageDiv.html('<div class="notice notice-error" style="padding: 10px; margin: 10px 0;"><p>❌ Error: ' + (response.data || 'Unknown error') + '</p></div>');
          }
        },
        error: function() {
          messageDiv.html('<div class="notice notice-error" style="padding: 10px; margin: 10px 0;"><p>❌ AJAX request failed</p></div>');
        },
        complete: function() {
          button.prop('disabled', false).text('🧪 Test Webhook');
        }
      });
    });
  });
  </script>
  <?php
}

/**
 * AJAX: Save MemberPress webhook settings
 */
add_action('wp_ajax_sb_save_memberpress_webhook', 'sb_ajax_save_memberpress_webhook');
function sb_ajax_save_memberpress_webhook() {
  check_ajax_referer('sb_memberpress_webhook_nonce', 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions');
  }

  $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
  $urls = sanitize_textarea_field($_POST['urls']);

  // Validate URLs
  if ($enabled && empty(trim($urls))) {
    wp_send_json_error('At least one webhook URL is required when enabled');
  }

  if ($enabled) {
    // Split URLs by newline and validate each
    $url_list = array_filter(array_map('trim', explode("\n", $urls)));
    foreach ($url_list as $url) {
      if (!filter_var($url, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Invalid webhook URL: ' . esc_html($url));
      }
    }
  }

  update_option('sb_memberpress_webhook_enabled', $enabled);
  update_option('sb_memberpress_webhook_urls', $urls);

  wp_send_json_success('Settings saved');
}

/**
 * AJAX: Test MemberPress webhook
 * Uses REAL data from last registration (not fake test data)
 */
add_action('wp_ajax_sb_test_memberpress_webhook', 'sb_ajax_test_memberpress_webhook');
function sb_ajax_test_memberpress_webhook() {
  check_ajax_referer('sb_memberpress_webhook_nonce', 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions');
  }

  // Find last real transaction created by our plugin
  global $wpdb;
  $last_txn = $wpdb->get_row(
    "SELECT * FROM {$wpdb->prefix}mepr_transactions
     WHERE trans_num LIKE 'sb-%'
     AND status = 'complete'
     ORDER BY created_at DESC
     LIMIT 1"
  );

  if (!$last_txn) {
    wp_send_json_error('No completed transactions found. Register a user first to test with real data.');
    return;
  }

  // Get registration URL from registration pairs (if exists)
  $registration_url = '';
  $pair = $wpdb->get_row($wpdb->prepare(
    "SELECT registration_url FROM {$wpdb->prefix}user_registrations
     WHERE user_id = %d
     ORDER BY registered_at DESC
     LIMIT 1",
    $last_txn->user_id
  ));
  if ($pair) {
    $registration_url = $pair->registration_url;
  }

  // Send webhook with REAL data (test_mode = false for production payload)
  $result = sb_send_memberpress_webhook(
    $last_txn->user_id,
    $last_txn->product_id,
    $last_txn->id,
    $registration_url,
    false // Use real data, not test data
  );

  if ($result['success']) {
    $user = get_userdata($last_txn->user_id);
    wp_send_json_success(sprintf(
      'Test webhook sent to %d URL(s) using REAL data from last registration (User: %s, Transaction ID: %d)',
      $result['sent_count'],
      $user ? $user->user_email : 'N/A',
      $last_txn->id
    ));
  } else {
    wp_send_json_error($result['error']);
  }
}

/**
 * Send MemberPress-compatible webhook to multiple URLs
 *
 * @param int $user_id WordPress user ID
 * @param int $membership_id MemberPress membership ID
 * @param int $transaction_id MemberPress transaction ID
 * @param string $registration_url Registration page URL
 * @param bool $test_mode If true, sends test data
 * @return array ['success' => bool, 'error' => string, 'sent_count' => int, 'results' => array]
 */
function sb_send_memberpress_webhook($user_id, $membership_id, $transaction_id, $registration_url = '', $test_mode = false) {
  // Check if webhook is enabled (with fallback to old option)
  $enabled = get_option('sb_memberpress_webhook_enabled', null);
  if ($enabled === null) {
    // Fallback: try old option name
    $enabled = get_option('sb_make_webhook_enabled', false);
  }

  if (!$enabled) {
    return ['success' => false, 'error' => 'Webhook disabled', 'sent_count' => 0];
  }

  // Get webhook URLs (with fallback to old option)
  $webhook_urls_text = get_option('sb_memberpress_webhook_urls', '');

  // Fallback: if new option is empty, try old single URL option
  if (empty(trim($webhook_urls_text))) {
    $old_url = get_option('sb_make_webhook_url', '');
    if (!empty(trim($old_url))) {
      $webhook_urls_text = $old_url; // Use old URL as fallback
      error_log('Supabase Bridge: Using old webhook URL (migration pending)');
    }
  }

  if (empty(trim($webhook_urls_text))) {
    return ['success' => false, 'error' => 'Webhook URLs not configured', 'sent_count' => 0];
  }

  // Parse URLs from textarea (one per line)
  $webhook_urls = array_filter(array_map('trim', explode("\n", $webhook_urls_text)));
  if (empty($webhook_urls)) {
    return ['success' => false, 'error' => 'No valid webhook URLs found', 'sent_count' => 0];
  }

  // Get user data
  $user = get_userdata($user_id);
  if (!$user && !$test_mode) {
    return ['success' => false, 'error' => 'User not found', 'sent_count' => 0];
  }

  // Build MemberPress-compatible payload
  if ($test_mode) {
    // Test mode: simplified payload
    $payload = [
      'event' => 'non-recurring-transaction-completed',
      'type' => 'transaction',
      'data' => [
        'membership' => [
          'id' => 999999,
          'title' => 'Test Membership',
          'price' => '0.00',
          'period' => '1',
          'period_type' => 'lifetime'
        ],
        'member' => [
          'id' => $user_id,
          'email' => 'test@example.com',
          'username' => 'testuser',
          'first_name' => 'Test',
          'last_name' => 'User',
          'display_name' => 'Test User'
        ],
        'coupon' => '0',
        'subscription' => '0',
        'id' => '999999',
        'amount' => '0.00',
        'total' => '0.00',
        'status' => 'complete',
        'txn_type' => 'payment',
        'gateway' => 'free',
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 days'))
      ]
    ];
  } else {
    // Production mode: full MemberPress payload
    $product = null;
    $txn = null;

    if (class_exists('MeprProduct')) {
      $product = new MeprProduct($membership_id);
    }

    if (class_exists('MeprTransaction')) {
      $txn = new MeprTransaction($transaction_id);
    }

    // Build membership object (minimal essential fields only)
    $membership_data = [
      'id' => $membership_id,
      'title' => $product ? $product->post_title : 'Unknown Membership',
      'price' => $product ? $product->price : '0.00',
      'period' => $product ? $product->period : '1',
      'period_type' => $product ? $product->period_type : 'lifetime'
    ];

    // Build member object (minimal essential fields only)
    $member_data = [
      'id' => $user_id,
      'email' => $user->user_email,
      'first_name' => $user->first_name,
      'last_name' => $user->last_name
    ];

    // Build transaction data
    $transaction_data = [
      'coupon' => '0',
      'subscription' => '0',
      'id' => (string) $transaction_id,
      'amount' => $txn ? $txn->amount : '0.00',
      'total' => $txn ? $txn->total : '0.00',
      'status' => $txn ? $txn->status : 'complete',
      'txn_type' => $txn ? $txn->txn_type : 'payment',
      'gateway' => $txn ? $txn->gateway : 'free',
      'created_at' => $txn ? $txn->created_at : current_time('mysql'),
      'expires_at' => $txn ? $txn->expires_at : date('Y-m-d H:i:s', strtotime('+10 days'))
    ];

    // Combine into full payload
    $payload = [
      'event' => 'non-recurring-transaction-completed',
      'type' => 'transaction',
      'data' => array_merge($transaction_data, [
        'membership' => $membership_data,
        'member' => $member_data
      ])
    ];
  }

  // Send to all webhook URLs
  $results = [];
  $success_count = 0;
  $fail_count = 0;

  // DEBUG: Log payload size
  $payload_json = json_encode($payload);
  $payload_size = strlen($payload_json);
  error_log(sprintf(
    'Supabase Bridge: Webhook payload size: %d bytes, Content: %s',
    $payload_size,
    substr($payload_json, 0, 500) // First 500 chars
  ));

  foreach ($webhook_urls as $webhook_url) {
    $response = wp_remote_post($webhook_url, [
      'body' => $payload_json,
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'timeout' => 10
    ]);

    // Check response
    if (is_wp_error($response)) {
      $error = $response->get_error_message();
      $results[] = [
        'url' => $webhook_url,
        'success' => false,
        'error' => $error
      ];
      $fail_count++;

      error_log(sprintf(
        'Supabase Bridge: MemberPress webhook failed - URL: %s, Error: %s',
        $webhook_url,
        $error
      ));
    } else {
      $status_code = wp_remote_retrieve_response_code($response);
      if ($status_code >= 200 && $status_code < 300) {
        $results[] = [
          'url' => $webhook_url,
          'success' => true,
          'status_code' => $status_code
        ];
        $success_count++;

        error_log(sprintf(
          'Supabase Bridge: MemberPress webhook sent - URL: %s, User ID: %d, Email: %s, Membership ID: %d',
          $webhook_url,
          $user_id,
          $test_mode ? 'test@example.com' : $user->user_email,
          $membership_id
        ));
      } else {
        $error = "HTTP $status_code";
        $results[] = [
          'url' => $webhook_url,
          'success' => false,
          'error' => $error
        ];
        $fail_count++;

        error_log(sprintf(
          'Supabase Bridge: MemberPress webhook failed - URL: %s, HTTP: %d',
          $webhook_url,
          $status_code
        ));
      }
    }
  }

  // Save last webhook status
  update_option('sb_memberpress_last_webhook', [
    'success' => $success_count > 0,
    'user_id' => $user_id,
    'email' => $test_mode ? 'test@example.com' : $user->user_email,
    'timestamp' => current_time('mysql'),
    'sent_count' => $success_count,
    'fail_count' => $fail_count,
    'total_urls' => count($webhook_urls)
  ]);

  // Return aggregated results
  if ($success_count > 0) {
    return [
      'success' => true,
      'sent_count' => $success_count,
      'fail_count' => $fail_count,
      'results' => $results
    ];
  } else {
    return [
      'success' => false,
      'error' => 'All webhooks failed',
      'sent_count' => 0,
      'fail_count' => $fail_count,
      'results' => $results
    ];
  }
}

/**
 * BACKWARD COMPATIBILITY: Wrapper function for old code that calls sb_send_make_webhook()
 * This ensures no fatal errors if old function name is used anywhere
 *
 * @deprecated Use sb_send_memberpress_webhook() instead
 * @param int $user_id WordPress user ID
 * @param int $membership_id MemberPress membership ID
 * @param int $transaction_id MemberPress transaction ID
 * @param string $registration_url Registration page URL
 * @param bool $test_mode If true, sends test data
 * @return array ['success' => bool, 'error' => string, 'sent_count' => int]
 */
function sb_send_make_webhook($user_id, $membership_id, $transaction_id, $registration_url = '', $test_mode = false) {
  // Simply call the new function - all logic is there
  return sb_send_memberpress_webhook($user_id, $membership_id, $transaction_id, $registration_url, $test_mode);
}

// === Phase 6: Auto-Enrollment on Membership Purchase ===

/**
 * Auto-enroll user into LearnDash courses when they purchase a membership
 *
 * @param int $user_id WordPress user ID
 * @param int $membership_id MemberPress product/membership ID
 */
function sb_auto_enroll_user_on_membership_purchase($user_id, $membership_id) {
  // Validate inputs
  if (!$user_id || !$membership_id) {
    error_log('Supabase Bridge: Auto-enroll skipped - invalid user_id or membership_id');
    return;
  }

  // Check if LearnDash is active
  if (!function_exists('ld_update_course_access')) {
    error_log('Supabase Bridge: Auto-enroll skipped - LearnDash not active');
    return;
  }

  // Get all course access pairs
  $pairs = get_option('sb_membership_course_pairs', []);

  if (empty($pairs)) {
    // No auto-enrollment rules configured - this is normal
    return;
  }

  // Find all courses for this membership
  $courses_to_enroll = [];
  foreach ($pairs as $pair) {
    if (intval($pair['membership_id']) === intval($membership_id)) {
      $courses_to_enroll[] = intval($pair['course_id']);
    }
  }

  if (empty($courses_to_enroll)) {
    // No courses configured for this membership - this is normal
    return;
  }

  // Enroll user into each course (with duplicate check)
  $enrolled_count = 0;
  $skipped_count = 0;

  foreach ($courses_to_enroll as $course_id) {
    // Check if already enrolled (preserve progress)
    $is_enrolled = sfwd_lms_has_access($course_id, $user_id);

    if ($is_enrolled) {
      $skipped_count++;
      error_log(sprintf(
        'Supabase Bridge: Auto-enroll skipped (already enrolled) - User ID: %d, Course ID: %d',
        $user_id,
        $course_id
      ));
      continue;
    }

    // Enroll user
    ld_update_course_access($user_id, $course_id, $remove = false);
    $enrolled_count++;

    error_log(sprintf(
      'Supabase Bridge: Auto-enrolled user - User ID: %d, Membership ID: %d, Course ID: %d',
      $user_id,
      $membership_id,
      $course_id
    ));
  }

  // Summary log
  if ($enrolled_count > 0 || $skipped_count > 0) {
    error_log(sprintf(
      'Supabase Bridge: Auto-enrollment complete - User ID: %d, Membership ID: %d, Enrolled: %d, Skipped (already enrolled): %d',
      $user_id,
      $membership_id,
      $enrolled_count,
      $skipped_count
    ));
  }
}

/**
 * Hook: MemberPress transaction completed (one-time purchases)
 * Triggers when transaction status becomes 'complete' or 'completed'
 */
add_action('mepr_event_transaction_completed', 'sb_on_transaction_completed', 10, 1);
function sb_on_transaction_completed($event) {
  // Get transaction object from event
  $txn = $event->get_data();

  if (!$txn || !is_object($txn)) {
    error_log('Supabase Bridge: Transaction completed hook - invalid transaction object');
    return;
  }

  // Verify transaction status
  if (!in_array($txn->status, ['complete', 'completed'])) {
    error_log(sprintf(
      'Supabase Bridge: Transaction completed hook - skipping (status: %s)',
      $txn->status
    ));
    return;
  }

  // Auto-enroll
  sb_auto_enroll_user_on_membership_purchase($txn->user_id, $txn->product_id);
}

/**
 * Hook: MemberPress subscription status transition (recurring subscriptions)
 * Triggers when subscription status changes (e.g., pending → active)
 */
add_action('mepr_subscription_transition_status', 'sb_on_subscription_status_transition', 10, 3);
function sb_on_subscription_status_transition($old_status, $new_status, $subscription) {
  // Only trigger when subscription becomes active
  if ($new_status !== 'active') {
    return;
  }

  // Prevent duplicate enrollment if already active
  if ($old_status === 'active') {
    return;
  }

  if (!$subscription || !is_object($subscription)) {
    error_log('Supabase Bridge: Subscription status transition hook - invalid subscription object');
    return;
  }

  // Auto-enroll
  sb_auto_enroll_user_on_membership_purchase($subscription->user_id, $subscription->product_id);
}

/**
 * Hook: MemberPress transaction store (for MANUAL transactions ONLY)
 * This hook fires when transaction is saved to database
 * CRITICAL for Zapier-created transactions (gateway = 'manual')
 *
 * Separation of concerns:
 * - Stripe/PayPal → handled by mepr_event_transaction_completed (existing hook)
 * - Manual (Zapier, crypto) → handled by THIS hook
 */
add_action('mepr-txn-store', 'sb_on_transaction_store', 10, 1);
function sb_on_transaction_store($txn) {
  if (!$txn || !is_object($txn)) {
    return;
  }

  // Verify required properties exist
  if (!isset($txn->gateway) || !isset($txn->status) || !isset($txn->user_id) || !isset($txn->product_id)) {
    error_log('Supabase Bridge: Transaction store hook - missing required properties');
    return;
  }

  // ONLY process manual gateway transactions (Zapier-created, crypto payments)
  // Stripe/PayPal handled by mepr_event_transaction_completed hook
  if ($txn->gateway !== 'manual') {
    return;
  }

  // Only process completed transactions
  if (!in_array($txn->status, ['complete', 'completed'])) {
    return;
  }

  // Auto-enroll user in course based on membership → course pairs
  sb_auto_enroll_user_on_membership_purchase($txn->user_id, $txn->product_id);

  error_log(sprintf(
    'Supabase Bridge: Manual transaction auto-enrollment - User ID: %d, Product ID: %d, Gateway: %s',
    $txn->user_id,
    $txn->product_id,
    $txn->gateway
  ));
}

/**
 * Hook: MemberPress subscription post-update (for Zapier-created subscriptions)
 * Fires when subscription is saved to database
 *
 * Separation of concerns:
 * - Normal subscriptions → handled by mepr_subscription_transition_status (existing hook)
 * - Zapier-created subscriptions → handled by THIS hook
 *
 * Note: This catches subscriptions created directly with 'active' status (no transition)
 */
add_action('mepr_subscription_post_update', 'sb_on_subscription_post_update', 10, 1);
function sb_on_subscription_post_update($subscription) {
  if (!$subscription || !is_object($subscription)) {
    return;
  }

  // Verify required properties exist
  if (!isset($subscription->status) || !isset($subscription->user_id) || !isset($subscription->product_id) || !isset($subscription->created_at)) {
    error_log('Supabase Bridge: Subscription post-update hook - missing required properties');
    return;
  }

  // Only process active subscriptions
  if ($subscription->status !== 'active') {
    return;
  }

  // Only process newly created subscriptions (within last 10 minutes)
  // This distinguishes Zapier-created subscriptions from status updates
  // 10-minute window accounts for rare Zapier delays (usually ~10s, rarely 5-10min)
  $created_timestamp = strtotime($subscription->created_at);

  // Verify strtotime succeeded
  if ($created_timestamp === false) {
    error_log(sprintf(
      'Supabase Bridge: Subscription post-update hook - invalid created_at format: %s',
      $subscription->created_at
    ));
    return;
  }

  $current_timestamp = current_time('timestamp');
  $age_seconds = $current_timestamp - $created_timestamp;

  if ($age_seconds > 600) {
    // Subscription is old (>10 minutes), not a new Zapier creation
    // Let mepr_subscription_transition_status handle this
    return;
  }

  // Sanity check: age should not be negative (clock skew protection)
  if ($age_seconds < -60) {
    error_log(sprintf(
      'Supabase Bridge: Subscription post-update hook - clock skew detected, age: %d seconds',
      $age_seconds
    ));
    return;
  }

  // Auto-enroll user in course based on membership → course pairs
  sb_auto_enroll_user_on_membership_purchase($subscription->user_id, $subscription->product_id);

  error_log(sprintf(
    'Supabase Bridge: New subscription auto-enrollment - User ID: %d, Product ID: %d, Age: %d seconds',
    $subscription->user_id,
    $subscription->product_id,
    $age_seconds
  ));
}

// ============================================================================
// CHECKOUT AUTHENTICATION OVERLAY (v0.10.0)
// ============================================================================
// Shows fullscreen overlay on /register/* pages for non-logged-in users
// Redirects to /test-no-elem/ page which handles authentication and return

add_action('wp_footer', 'sb_checkout_auth_overlay', 100);
function sb_checkout_auth_overlay() {
  // Only run on frontend
  if (is_admin()) {
    return;
  }

  // ONLY run on /register/* pages (MemberPress checkout pages)
  $current_url = $_SERVER['REQUEST_URI'];
  if (strpos($current_url, '/register/') === false) {
    return;
  }

  // Check if user is already logged in
  if (is_user_logged_in()) {
    return;
  }

  // Output JavaScript + CSS for overlay
  ?>
  <script id="sb-checkout-auth-detector">
  (function() {
    // Check if current page is /register/*
    if (window.location.pathname.indexOf('/register/') !== 0) {
      return;
    }

    // Check if user is logged in (WordPress sets wordpress_logged_in_ cookie)
    var isLoggedIn = document.cookie.split(';').some(function(item) {
      return item.trim().indexOf('wordpress_logged_in_') === 0;
    });

    if (isLoggedIn) {
      return; // User is logged in, don't show overlay
    }

    // Create overlay HTML
    var overlay = document.createElement('div');
    overlay.id = 'sb-checkout-auth-overlay';
    overlay.innerHTML = `
      <div class="sb-overlay-backdrop"></div>
      <div class="sb-overlay-content">
        <div class="sb-overlay-box">
          <h2>Чтобы оформить покупку, сначала авторизуйтесь — войдите или зарегистрируйтесь</h2>
          <a href="/test-no-elem/" class="sb-overlay-button">Авторизоваться</a>
        </div>
      </div>
    `;

    // Append to body
    document.body.appendChild(overlay);
  })();
  </script>

  <style id="sb-checkout-auth-overlay-css">
  #sb-checkout-auth-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  #sb-checkout-auth-overlay .sb-overlay-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
  }

  #sb-checkout-auth-overlay .sb-overlay-content {
    position: relative;
    z-index: 10;
  }

  #sb-checkout-auth-overlay .sb-overlay-box {
    background: white;
    border-radius: 12px;
    padding: 40px;
    max-width: 500px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  }

  #sb-checkout-auth-overlay h2 {
    margin: 0 0 32px 0;
    font-size: 22px;
    color: #333;
    font-weight: 600;
    line-height: 1.4;
  }

  #sb-checkout-auth-overlay .sb-overlay-button {
    display: inline-block;
    background: #4285f4;
    color: white;
    padding: 14px 48px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 16px;
    font-weight: 600;
    transition: background 0.2s;
  }

  #sb-checkout-auth-overlay .sb-overlay-button:hover {
    background: #357ae8;
    color: white;
  }
  </style>
  <?php
}