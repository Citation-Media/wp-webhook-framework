# WP Webhook Framework

Entity-level webhooks for WordPress using Action Scheduler. Sends non-blocking POSTs for create/update/delete of posts, terms, and users. Meta changes trigger the entity update. ACF updates include field context.

## Features
- Action Scheduler only dispatch, 5s delay
- Dedupe on action+entity+id
- Payload invariants:
  - Post: post_type
  - Term: taxonomy
  - User: roles[]
- ACF-aware (adds acf_field_key/name)
- Payload filtering system for custom webhook control

## Install
```bash
composer require citation-media/wp-webhook-framework
```
Ensure Action Scheduler is active (dependency is declared).

## Usage

### Basic Setup
```php
// Initialize the webhook framework
\Citation\WP_Webhook_Framework\ServiceProvider::register();
```

### Singleton Pattern

The ServiceProvider uses the singleton pattern to ensure only one instance is created and shared across your application. This prevents duplicate hook registrations and ensures consistent configuration.

```php
// Get the singleton instance
$provider = \Citation\WP_Webhook_Framework\ServiceProvider::get_instance();

// The same instance is returned on subsequent calls
$sameProvider = \Citation\WP_Webhook_Framework\ServiceProvider::get_instance();
```

### Configuration Constants

Define these constants in your `wp-config.php` to configure webhook behavior:

```php
// Webhook endpoint URL (required for basic setup)
define('WP_WEBHOOK_FRAMEWORK_URL', 'https://example.com/webhook');
```

**Note:** The `WP_WEBHOOK_FRAMEWORK_URL` constant is used as the default webhook endpoint when no URL is provided in the configuration.

## Payload Filtering

Control webhook payloads and delivery using WordPress filters. Each entity type has its own filter:

### Available Filters
- `wp_webhook_framework_post_payload` - Filter post webhook payloads
- `wp_webhook_framework_term_payload` - Filter term webhook payloads
- `wp_webhook_framework_user_payload` - Filter user webhook payloads
- `wp_webhook_framework_meta_payload` - Filter meta webhook payloads

### Filter Signature
```php
add_filter('wp_webhook_framework_post_payload', function($payload, $entity_id, $action) {
    // $payload: array - The webhook payload data
    // $entity_id: int|string - The entity ID
    // $action: string - The action (create/update/delete)

    return $payload; // Return modified payload or empty array to prevent webhook
}, 10, 3);
```

### Filter Examples

**Prevent delete webhooks:**
```php
add_filter('wp_webhook_framework_post_payload', function($payload, $post_id, $action) {
    if ($action === 'delete') {
        return array(); // Return empty array to prevent webhook
    }
    return $payload;
}, 10, 3);
```

**Add custom data to user webhooks:**
```php
add_filter('wp_webhook_framework_user_payload', function($payload, $user_id, $action) {
    $user = get_userdata($user_id);
    $payload['email'] = $user->user_email;
    $payload['display_name'] = $user->display_name;
    return $payload;
}, 10, 3);
```

**Filter by post type:**
```php
add_filter('wp_webhook_framework_post_payload', function($payload, $post_id, $action) {
    $post_type = get_post_type($post_id);
    if ($post_type !== 'post') {
        return array(); // Only send webhooks for 'post' type
    }
    return $payload;
}, 10, 3);
```

Example payload:
```json
{
  "action": "update",
  "entity": "post",
  "id": 123,
  "post_type": "post"
}
```
