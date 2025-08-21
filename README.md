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
// Webhook endpoint URL (optional - can be overridden by wpwf_url filter)
define('WP_WEBHOOK_FRAMEWORK_URL', 'https://example.com/webhook');
```

**Note:** The `WP_WEBHOOK_FRAMEWORK_URL` constant provides a default webhook endpoint, but can be overridden using the `wpwf_url` filter for more granular control.

## Payload Filtering

Control webhook payloads and delivery using WordPress filters. Each entity type has its own filter:

### Available Filters
- `wpwf_payload` - Filter webhook payloads
- `wpwf_url` - Filter webhook url

### Filter Signature
```php
add_filter('wpwf_payload', function($payload, $entity, $action) {
    // $payload: array - The webhook payload data
    // $entity: string - The entity type (post, term, user, meta)
    // $action: string - The action (create/update/delete)

    return $payload; // Return modified payload or empty array to prevent webhook
}, 10, 3);

add_filter('wpwf_url', function($url, $entity, $id, $action, $payload) {
    // $url: string - The webhook URL
    // $entity: string - The entity type
    // $id: int|string - The entity ID
    // $action: string - The action (create/update/delete)
    // $payload: array - The webhook payload data

    return $url; // Return modified URL
}, 10, 5);
```

### Filter Examples

**Prevent delete webhooks:**
```php
add_filter('wpwf_payload', function($payload, $entity, $action) {
    if ($action === 'delete') {
        return array(); // Return empty array to prevent webhook
    }
    return $payload;
}, 10, 3);
```

**Add custom data to user webhooks:**
```php
add_filter('wpwf_payload', function($payload, $entity, $action) {
    if ($entity === 'user') {
        $user = get_userdata($payload['id']);
        $payload['email'] = $user->user_email;
        $payload['display_name'] = $user->display_name;
    }
    return $payload;
}, 10, 3);
```

**Set custom webhook URL based on entity type:**
```php
add_filter('wpwf_url', function($url, $entity, $id, $action, $payload) {
    if ($entity === 'post') {
        return 'https://api.example.com/webhooks/posts';
    } elseif ($entity === 'user') {
        return 'https://api.example.com/webhooks/users';
    }
    return $url;
}, 10, 5);
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

The payload includes the entity data plus the action, entity type, and ID added by the framework.
