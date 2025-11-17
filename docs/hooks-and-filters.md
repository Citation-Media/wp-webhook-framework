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

Filter failure notification email data before sending. Allows customizing recipients, subject, message, and headers.

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

**Example - Disable notifications:**
```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    // Return false to prevent email from being sent
    return false;
}, 10, 3);
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
