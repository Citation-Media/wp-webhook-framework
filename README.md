# WP Webhook Framework

Entity-level webhooks for WordPress using Action Scheduler. Sends non-blocking POSTs for create/update/delete of posts, terms, and users. Meta changes trigger the entity update. ACF updates include field context. Features intelligent failure monitoring with email notifications, automatic blocking, and comprehensive filtering options.

## Features
- Action Scheduler only dispatch, 5s delay
- Dedupe on action+entity+id
- Payload invariants:
  - Post: post_type
  - Term: taxonomy
  - User: roles[]
- ACF-aware (adds acf_field_key/name)
- Payload filtering system for custom webhook control
- **Failure monitoring and blocking:**
  - Email notifications for failed deliveries (non-200 responses)
  - Automatic blocking after 10 consecutive failures within 1 hour
  - Transient-based failure tracking with automatic reset
  - Filterable email notifications with i18n support
  - Clean DTO architecture for maintainable code

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
// Webhook endpoint URL (always takes precedence over filters when defined)
define('WP_WEBHOOK_FRAMEWORK_URL', 'https://example.com/webhook');
```

**Note:** The `WP_WEBHOOK_FRAMEWORK_URL` constant always takes precedence over the `wpwf_url` filter when defined. This ensures that configuration via code remains authoritative and cannot be overridden by filters.

## Payload Filtering

Control webhook payloads and delivery using WordPress filters. Each entity type has its own filter:

### Available Filters
- `wpwf_payload` - Filter webhook payloads
- `wpwf_url` - Filter webhook url
- `wpwf_excluded_meta_keys` - Exclude specific meta keys from webhook emission

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

add_filter('wpwf_excluded_meta_keys', function($excluded_keys, $meta_key, $meta_type, $object_id) {
    // $excluded_keys: array - Array of meta keys to exclude from webhooks
    // $meta_key: string - The current meta key being processed
    // $meta_type: string - The meta type (post, term, user)
    // $object_id: int - The object ID

    return $excluded_keys; // Return modified array of excluded keys
}, 10, 4);
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

**Exclude additional meta keys from webhooks:**
```php
add_filter('wpwf_excluded_meta_keys', function($excluded_keys, $meta_key, $meta_type, $object_id) {
    // Add custom meta keys to exclude
    $excluded_keys[] = '_my_internal_field';
    $excluded_keys[] = '_temp_data';
    
    return $excluded_keys;
}, 10, 4);
```

**Conditionally exclude meta keys based on post type:**
```php
add_filter('wpwf_excluded_meta_keys', function($excluded_keys, $meta_key, $meta_type, $object_id) {
    // Only exclude ACF cache for specific post types
    if ($meta_type === 'post' && str_starts_with($meta_key, '_acf_cache_')) {
        $post = get_post($object_id);
        if ($post && $post->post_type === 'product') {
            $excluded_keys[] = $meta_key;
        }
    }
    
    return $excluded_keys;
}, 10, 4);
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

## Failure Monitoring & Blocking

The framework includes built-in failure monitoring and automatic blocking to prevent spam to failing endpoints:

### Features
- **Email Notifications**: Sends email to site admin on first failure
- **Failure Tracking**: Uses WordPress transients to track consecutive failures per URL
- **Automatic Blocking**: Blocks URLs after 10 consecutive failures within 1 hour
- **Auto-Reset**: Failure counts reset after successful delivery or 1-hour timeout

### How It Works
1. Each webhook delivery is monitored for HTTP response codes
2. Non-200 responses are tracked using a single WordPress transient per URL
3. First failure triggers an email notification to the admin
4. After 10 consecutive failures within 1 hour, the URL is blocked
5. Blocked URLs are automatically unblocked after 1 hour
6. Successful deliveries reset both failure count and blocked status
7. All data automatically expires after 1 hour via transient expiration

### Transient Structure
Each URL uses a single transient containing failure data managed by the `Failure` DTO:
```php
array(
    'count'             => 5,           // Number of consecutive failures
    'first_failure_at'  => 1234567890,  // Timestamp of first failure in current window
    'blocked'           => true,        // Whether URL is currently blocked
    'blocked_time'      => 1234567890   // When URL was blocked
)
```

### Email Notifications
When a webhook fails for the first time, an email is sent to the site administrator containing:
- The failing webhook URL
- Error details (WP_Error or HTTP status code)
- Timestamp of the failure
- Warning about upcoming blocking

### Filterable Email Notifications
The failure notification emails are fully filterable, allowing you to customize recipients, content, headers, and more.

#### Available Filter
- `wpwf_failure_notification_email` - Filter email data before sending

#### Filter Signature
```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    // $email_data: array - Contains recipient, subject, message, headers, and more
    // $url: string - The webhook URL that failed
    // $response: mixed - The response from wp_remote_post

    return $email_data; // Return modified email data
}, 10, 3);
```

### Configuration
The failure monitoring is automatic and doesn't require additional configuration. The thresholds are:
- **Failure Threshold**: 10 consecutive failures
- **Time Window**: 1 hour
- **Block Duration**: 1 hour (automatic unblocking)

## Code Quality
This framework follows WordPress coding standards and modern PHP practices:
- **PHPCS Compliant**: All code passes PHP CodeSniffer validation
- **Type Safe**: Full PHPStan static analysis support
- **i18n Ready**: All user-facing strings are internationalized
- **Filterable**: Extensive WordPress filter integration
- **Well Documented**: Comprehensive inline documentation and examples
