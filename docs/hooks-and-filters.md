# Hooks and Filters

Control webhook behavior, payloads, and delivery using WordPress hooks and filters.

## Actions

### `wpwf_register_webhooks`

Register custom webhooks with the framework registry.

**Parameters:**
- `$registry` (Webhook_Registry) - The registry instance

**Example:**
```php
function my_plugin_register_webhooks($registry) {
    $registry->register(new My_Custom_Webhook());
}
add_action('wpwf_register_webhooks', 'my_plugin_register_webhooks');
```

See @docs/custom-webhooks.md for detailed webhook creation examples.

### `wpwf_webhook_success`

Fired when a webhook is successfully delivered.

**Parameters:**
- `$url` (string) - The webhook URL

**Example:**
```php
add_action('wpwf_webhook_success', function($url) {
    error_log("Webhook delivered successfully: {$url}");
});
```

### `wpwf_webhook_failed`

Fired when a webhook fails after all retries are exhausted, before blocking decision is made.

**Parameters:**
- `$url` (string) - The webhook URL
- `$response` (mixed) - The response from wp_remote_post
- `$failure_count` (int) - Current consecutive failure count
- `$max_failures` (int) - Maximum failures before blocking

**Example:**
```php
add_action('wpwf_webhook_failed', function($url, $response, $failure_count, $max_failures) {
    error_log("Webhook failed ({$failure_count}/{$max_failures}): {$url}");
}, 10, 4);
```

### `wpwf_webhook_blocked`

Fired when a webhook URL is blocked due to reaching the consecutive failure threshold. This is when email notifications are sent by default.

**Parameters:**
- `$url` (string) - The webhook URL
- `$response` (mixed) - The response from wp_remote_post
- `$max_failures` (int) - Maximum failures threshold

**Example - Send Slack notification:**
```php
add_action('wpwf_webhook_blocked', function($url, $response, $max_failures) {
    wp_remote_post('https://hooks.slack.com/services/YOUR/WEBHOOK/URL', array(
        'body' => json_encode(array(
            'text' => "Webhook blocked after {$max_failures} failures: {$url}"
        ))
    ));
}, 10, 3);
```

## Filters

### `wpwf_payload`

Filter webhook payloads before scheduling. Return empty array to prevent webhook emission.

**Parameters:**
- `$payload` (array) - The webhook payload data
- `$entity` (string) - The entity type (post, term, user, meta)
- `$id` (int|string) - The entity ID

**Example - Prevent delete webhooks:**
```php
add_filter('wpwf_payload', function($payload, $entity, $id) {
    if ($payload['action'] === 'delete') {
        return array(); // Prevent webhook
    }
    return $payload;
}, 10, 3);
```

**Example - Add custom data to user webhooks:**
```php
add_filter('wpwf_payload', function($payload, $entity, $id) {
    if ($entity === 'user') {
        $user = get_userdata($id);
        $payload['email'] = $user->user_email;
        $payload['display_name'] = $user->display_name;
    }
    return $payload;
}, 10, 3);
```

### `wpwf_url`

Filter the webhook URL before scheduling. Allows dynamic URL routing based on entity type or payload.

**Parameters:**
- `$url` (string) - The webhook URL
- `$entity` (string) - The entity type
- `$id` (int|string) - The entity ID

**Note:** The `WP_WEBHOOK_FRAMEWORK_URL` constant always takes precedence over this filter when defined.

**Example - Route by entity type:**
```php
add_filter('wpwf_url', function($url, $entity, $id) {
    if ($entity === 'post') {
        return 'https://api.example.com/webhooks/posts';
    } elseif ($entity === 'user') {
        return 'https://api.example.com/webhooks/users';
    }
    return $url;
}, 10, 3);
```

**Example - Route by post type:**
```php
add_filter('wpwf_url', function($url, $entity, $id) {
    if ($entity === 'post') {
        $post = get_post($id);
        if ($post && $post->post_type === 'product') {
            return 'https://api.example.com/webhooks/products';
        }
    }
    return $url;
}, 10, 3);
```

### `wpwf_headers`

Filter HTTP headers before sending webhook requests. Allows adding authentication, custom headers, or modifying existing ones.

**Parameters:**
- `$headers` (array) - The HTTP headers array
- `$entity` (string) - The entity type
- `$id` (int|string) - The entity ID
- `$webhook_name` (string|null) - The webhook name (if applicable)

**Example - Add authentication:**
```php
add_filter('wpwf_headers', function($headers, $entity, $id, $webhook_name) {
    $headers['Authorization'] = 'Bearer ' . get_option('api_token');
    return $headers;
}, 10, 4);
```

**Example - Custom headers per entity:**
```php
add_filter('wpwf_headers', function($headers, $entity, $id, $webhook_name) {
    if ($entity === 'post') {
        $headers['X-Post-Type'] = get_post_type($id);
    }
    return $headers;
}, 10, 4);
```

### `wpwf_excluded_meta_keys`

Exclude specific meta keys from triggering webhooks. Useful for preventing webhooks on internal or transient meta fields.

**Parameters:**
- `$excluded_keys` (array) - Array of meta keys to exclude
- `$meta_key` (string) - The current meta key being processed
- `$meta_type` (string) - The meta type (post, term, user)
- `$object_id` (int) - The object ID

**Default excluded keys:**
- WordPress internal: `_edit_lock`, `_edit_last`
- ACF cache: `_acf_changed`, `_acf_cache_*`

**Example - Exclude custom meta keys:**
```php
add_filter('wpwf_excluded_meta_keys', function($excluded_keys, $meta_key, $meta_type, $object_id) {
    $excluded_keys[] = '_my_internal_field';
    $excluded_keys[] = '_temp_data';
    return $excluded_keys;
}, 10, 4);
```

**Example - Conditional exclusion by post type:**
```php
add_filter('wpwf_excluded_meta_keys', function($excluded_keys, $meta_key, $meta_type, $object_id) {
    if ($meta_type === 'post' && str_starts_with($meta_key, '_acf_cache_')) {
        $post = get_post($object_id);
        if ($post && $post->post_type === 'product') {
            $excluded_keys[] = $meta_key;
        }
    }
    return $excluded_keys;
}, 10, 4);
```

### `wpwf_failure_notification_email`

Filter failure notification email data before sending. Allows customizing recipients, subject, message, and headers. Return `false` to prevent email from being sent.

**Parameters:**
- `$email_data` (array) - Email data containing:
  - `recipient` (string) - Email recipient
  - `subject` (string) - Email subject
  - `message` (string) - Email message
  - `headers` (array) - Email headers
  - `url` (string) - The failing webhook URL
  - `error_message` (string) - Error description
  - `response` (mixed) - The response from wp_remote_post
- `$url` (string) - The webhook URL that failed
- `$response` (mixed) - The response from wp_remote_post

**Example - Change recipient:**
```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    $email_data['recipient'] = 'webhooks@example.com';
    return $email_data;
}, 10, 3);
```

**Example - Custom message format:**
```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    $email_data['message'] = sprintf(
        "Webhook failed!\n\nURL: %s\nError: %s\n\nCheck logs for details.",
        $email_data['url'],
        $email_data['error_message']
    );
    return $email_data;
}, 10, 3);
```

**Example - Disable email notifications (use actions instead):**
```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    return false; // Disable email, use wpwf_webhook_blocked action instead
}, 10, 3);
```

### `wpwf_max_consecutive_failures`

Filter the maximum consecutive failures threshold before URL blocking. Allows dynamic threshold per webhook.

**Parameters:**
- `$max_failures` (int) - The maximum consecutive failures threshold
- `$webhook_name` (string) - The webhook name/identifier

**Example - Different thresholds per webhook:**
```php
add_filter('wpwf_max_consecutive_failures', function($max_failures, $webhook_name) {
    if ($webhook_name === 'critical_webhook') {
        return 3; // Block after 3 failures for critical webhooks
    }
    return $max_failures; // Use default for others
}, 10, 2);
```

**Example - Environment-based thresholds:**
```php
add_filter('wpwf_max_consecutive_failures', function($max_failures, $webhook_name) {
    if (wp_get_environment_type() === 'development') {
        return 100; // Very lenient in development
    }
    return $max_failures;
}, 10, 2);
```

### `wpwf_timeout`

Filter webhook request timeout in seconds. Allows dynamic timeout configuration per webhook.

**Parameters:**
- `$timeout` (int) - The timeout in seconds (1-300)
- `$webhook_name` (string) - The webhook name/identifier

**Example - Longer timeout for specific webhooks:**
```php
add_filter('wpwf_timeout', function($timeout, $webhook_name) {
    if ($webhook_name === 'bulk_sync_webhook') {
        return 120; // 2 minutes for bulk operations
    }
    return $timeout;
}, 10, 2);
```

**Example - Adjust timeout based on server load:**
```php
add_filter('wpwf_timeout', function($timeout, $webhook_name) {
    $server_load = sys_getloadavg()[0];
    if ($server_load > 5.0) {
        return min(300, $timeout * 2); // Double timeout under high load
    }
    return $timeout;
}, 10, 2);
```

### `wpwf_retry_base_time`

Filter the base time (in seconds) for exponential backoff calculation. Default is 60 seconds.

**Parameters:**
- `$base_time` (int) - The base time in seconds
- `$webhook_name` (string) - The webhook name/identifier
- `$retry_count` (int) - The current retry attempt number

**Example - Faster retries for critical webhooks:**
```php
add_filter('wpwf_retry_base_time', function($base_time, $webhook_name, $retry_count) {
    if ($webhook_name === 'critical_webhook') {
        return 10; // Start with 10s base (10s, 20s, 40s...)
    }
    return $base_time;
}, 10, 3);
```

### `wpwf_retry_delay`

Filter the final calculated retry delay. Allows overriding the exponential backoff with custom logic or static values.

**Parameters:**
- `$delay` (int) - The calculated delay in seconds
- `$retry_count` (int) - The current retry attempt number
- `$webhook_name` (string) - The webhook name/identifier

**Example - Static retry delay:**
```php
add_filter('wpwf_retry_delay', function($delay, $retry_count, $webhook_name) {
    return 300; // Always retry after 5 minutes, regardless of attempt count
}, 10, 3);
```

### `wpwf_webhook_enabled`

Filter whether a webhook is enabled. Allows dynamic enabling/disabling based on conditions.

**Parameters:**
- `$enabled` (bool) - Whether the webhook is enabled
- `$webhook_name` (string) - The webhook name/identifier

**Example - Disable webhooks in maintenance mode:**
```php
add_filter('wpwf_webhook_enabled', function($enabled, $webhook_name) {
    if (defined('WP_MAINTENANCE_MODE') && WP_MAINTENANCE_MODE) {
        return false; // Disable all webhooks during maintenance
    }
    return $enabled;
}, 10, 2);
```

**Example - Conditional webhook enabling:**
```php
add_filter('wpwf_webhook_enabled', function($enabled, $webhook_name) {
    // Only enable user webhooks if sync is active
    if (str_contains($webhook_name, 'user') && !get_option('user_sync_enabled')) {
        return false;
    }
    return $enabled;
}, 10, 2);
```

**Example - Time-based webhook control:**
```php
add_filter('wpwf_webhook_enabled', function($enabled, $webhook_name) {
    // Disable heavy webhooks during business hours (9am-5pm)
    if ($webhook_name === 'heavy_sync_webhook') {
        $hour = (int) current_time('H');
        if ($hour >= 9 && $hour < 17) {
            return false;
        }
    }
    return $enabled;
}, 10, 2);
```


## Payload Structure

Standard payload sent with all webhooks:

```json
{
  "action": "create|update|delete",
  "entity": "post|term|user|meta",
  "id": 123,
  "post_type": "post"
}
```

**Entity-specific fields:**
- **Post**: `post_type`
- **Term**: `taxonomy`
- **User**: `roles` (array)
- **Meta**: `acf_field_key`, `acf_field_name` (if ACF field)

## Filter Priority

Filters are applied in this order:

1. `wpwf_payload` - Modify or prevent payload
2. `wpwf_url` - Customize webhook URL
3. `WP_WEBHOOK_FRAMEWORK_URL` constant - Overrides filtered URL
4. `wpwf_headers` - Customize HTTP headers
5. `wpwf_excluded_meta_keys` - Filter meta keys (meta webhooks only)
