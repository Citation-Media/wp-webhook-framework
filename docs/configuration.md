# Configuration

Configure webhook behavior using constants, methods, and filters.

## Configuration Constants

Define these constants in `wp-config.php` to configure global webhook behavior.

### `WP_WEBHOOK_FRAMEWORK_URL`

Sets the webhook endpoint URL for all webhooks.

**Type:** `string`  
**Default:** None (must be set)  
**Priority:** Highest - always takes precedence over filters and webhook-specific URLs

```php
// wp-config.php
define('WP_WEBHOOK_FRAMEWORK_URL', 'https://api.example.com/webhook');
```

**Precedence order:**
1. `WP_WEBHOOK_FRAMEWORK_URL` constant (highest priority)
2. Webhook-specific URL via `webhook_url()` method
3. `wpwf_url` filter
4. No URL set (throws exception)

### Environment-Based Configuration

Use constants for environment-specific configuration:

```php
// wp-config.php
if (WP_ENV === 'production') {
    define('WP_WEBHOOK_FRAMEWORK_URL', 'https://api.example.com/webhook');
} elseif (WP_ENV === 'staging') {
    define('WP_WEBHOOK_FRAMEWORK_URL', 'https://staging-api.example.com/webhook');
} else {
    define('WP_WEBHOOK_FRAMEWORK_URL', 'https://local.test/webhook');
}
```

## Webhook Configuration Methods

Configure individual webhooks using chainable methods.

### `webhook_url()`

Set a custom webhook URL for this specific webhook.

```php
$webhook->webhook_url('https://api.example.com/custom');
```

**Note:** Overridden by `WP_WEBHOOK_FRAMEWORK_URL` constant if defined.

### `max_consecutive_failures()`

Set the number of consecutive failed webhook events before blocking the URL.

**Range:** 0-10  
**Default:** 10

```php
$webhook->max_consecutive_failures(5); // Block after 5 failed events
```

### `max_retries()`

Set the number of retry attempts for a single failed webhook event.

**Range:** 0+
**Default:** 0

```php
$webhook->max_retries(3); // Retry 3 times with exponential backoff
```

### `timeout()`

Set the HTTP request timeout in seconds.

**Range:** 1-300  
**Default:** 10

```php
$webhook->timeout(60); // 60 second timeout
```

### `enabled()`

Enable or disable the webhook.

**Default:** `true`

```php
$webhook->enabled(false); // Disable webhook
```

### `headers()`

Set custom HTTP headers for the webhook.

```php
$webhook->headers([
    'Authorization' => 'Bearer token123',
    'X-Custom-Header' => 'value'
]);
```

### Chainable Configuration

All configuration methods are chainable:

```php
$webhook->webhook_url('https://api.example.com')
        ->max_consecutive_failures(5)
        ->timeout(60)
        ->enabled(true)
        ->headers(['Authorization' => 'Bearer token']);
```

## Registry Configuration

Access and configure webhooks through the registry.

```php
// Get registry instance
$registry = \Citation\WP_Webhook_Framework\Service_Provider::get_registry();

// Configure built-in webhooks
$post_webhook = $registry->get('post');
if ($post_webhook) {
    $post_webhook->webhook_url('https://api.example.com/posts')
                 ->max_consecutive_failures(3)
                 ->timeout(30);
}

$user_webhook = $registry->get('user');
if ($user_webhook) {
    $user_webhook->webhook_url('https://api.example.com/users')
                 ->max_consecutive_failures(5)
                 ->timeout(45);
}
```

## Filter-Based Configuration

Use WordPress filters for dynamic configuration.

### Dynamic URL Routing

```php
add_filter('wpwf_url', function($url, $entity, $id) {
    // Route by entity type
    if ($entity === 'post') {
        return 'https://api.example.com/posts';
    } elseif ($entity === 'user') {
        return 'https://api.example.com/users';
    }
    return $url;
}, 10, 3);
```

### Dynamic Headers

```php
add_filter('wpwf_headers', function($headers, $entity, $id, $webhook_name) {
    // Add authentication
    $headers['Authorization'] = 'Bearer ' . get_option('api_token');
    
    // Add entity-specific headers
    if ($entity === 'post') {
        $headers['X-Post-Type'] = get_post_type($id);
    }
    
    return $headers;
}, 10, 4);
```

See @docs/hooks-and-filters.md for all available filters.

## Options-Based Configuration

Store configuration in WordPress options for admin control.

```php
// Save configuration
update_option('webhook_api_url', 'https://api.example.com');
update_option('webhook_api_token', 'token123');
update_option('webhook_enabled', true);

// Use in webhook configuration
add_action('wpwf_register_webhooks', function($registry) {
    if (!get_option('webhook_enabled')) {
        return;
    }
    
    $webhook = new Custom_Webhook();
    $webhook->webhook_url(get_option('webhook_api_url'))
            ->headers(['Authorization' => 'Bearer ' . get_option('webhook_api_token')]);
    
    $registry->register($webhook);
});
```

## Configuration Priority

Understanding configuration precedence:

### URL Priority
1. `WP_WEBHOOK_FRAMEWORK_URL` constant (always wins)
2. Webhook-specific `webhook_url()` method
3. `wpwf_url` filter
4. Exception thrown if none set

### Headers Priority
1. Webhook-specific `headers()` method
2. `wpwf_headers` filter (merged with webhook headers)

### Payload Priority
1. Original payload from entity handler
2. `wpwf_payload` filter (can modify or prevent)

## Failure Monitoring Configuration

### Default Settings
- **Failure Threshold:** 10 consecutive failures
- **Time Window:** 1 hour
- **Block Duration:** 1 hour (automatic unblock)
- **Email Notification:** Sent on first failure after retries exhausted

### Customize Email Notifications

```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    // Change recipient
    $email_data['recipient'] = 'webhooks@example.com';
    
    // Customize subject
    $email_data['subject'] = 'URGENT: Webhook Failed';
    
    // Customize message
    $email_data['message'] = sprintf(
        "Webhook failed: %s\nError: %s",
        $url,
        $email_data['error_message']
    );
    
    return $email_data;
}, 10, 3);
```

### Disable Email Notifications

```php
add_filter('wpwf_failure_notification_email', '__return_false');
```

See @docs/failure-handling.md for detailed failure monitoring information.

## Meta Configuration

### Exclude Meta Keys

Prevent specific meta keys from triggering webhooks:

```php
add_filter('wpwf_excluded_meta_keys', function($excluded_keys, $meta_key, $meta_type, $object_id) {
    // Exclude custom internal fields
    $excluded_keys[] = '_my_internal_field';
    $excluded_keys[] = '_temp_data';
    
    // Conditionally exclude based on post type
    if ($meta_type === 'post') {
        $post = get_post($object_id);
        if ($post && $post->post_type === 'product') {
            $excluded_keys[] = '_product_internal_cache';
        }
    }
    
    return $excluded_keys;
}, 10, 4);
```

**Default excluded keys:**
- `_edit_lock`
- `_edit_last`
- `_acf_changed`
- `_acf_cache_*` (pattern)

## Configuration Examples

### Development Environment

```php
// wp-config.php
if (WP_ENV === 'development') {
    define('WP_WEBHOOK_FRAMEWORK_URL', 'http://localhost:3000/webhook');
    
    // Disable webhooks in development
    add_filter('wpwf_payload', '__return_empty_array');
}
```

### Multi-Site Configuration

```php
add_filter('wpwf_url', function($url, $entity, $id) {
    // Route by site
    $site_id = get_current_blog_id();
    return sprintf('https://api.example.com/sites/%d/webhook', $site_id);
}, 10, 3);
```

### Conditional Webhook Activation

```php
add_action('wpwf_register_webhooks', function($registry) {
    // Only register post webhook for specific post types
    if (get_option('enable_post_webhooks')) {
        $post_webhook = $registry->get('post');
        if ($post_webhook) {
            $post_webhook->enabled(true);
        }
    }
});

// Filter payloads to only send specific post types
add_filter('wpwf_payload', function($payload, $entity, $id) {
    if ($entity === 'post') {
        $allowed_types = get_option('webhook_post_types', ['post', 'page']);
        $post = get_post($id);
        
        if (!in_array($post->post_type, $allowed_types)) {
            return []; // Prevent webhook
        }
    }
    return $payload;
}, 10, 3);
```

## Security Considerations

### API Key Management

Never hardcode API keys. Use constants or options:

```php
// wp-config.php
define('WEBHOOK_API_KEY', 'your-secret-key');

// In code
$webhook->headers(['Authorization' => 'Bearer ' . WEBHOOK_API_KEY]);
```

### URL Validation

Validate webhook URLs before setting:

```php
add_filter('wpwf_url', function($url, $entity, $id) {
    // Only allow HTTPS in production
    if (WP_ENV === 'production' && !str_starts_with($url, 'https://')) {
        return ''; // Block non-HTTPS URLs
    }
    return $url;
}, 10, 3);
```

### Payload Sanitization

Sanitize sensitive data from payloads:

```php
add_filter('wpwf_payload', function($payload, $entity, $id) {
    // Remove sensitive fields
    unset($payload['password']);
    unset($payload['secret_key']);
    
    return $payload;
}, 10, 3);
```
