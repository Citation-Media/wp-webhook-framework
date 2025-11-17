# WP Webhook Framework

Entity-level webhooks for WordPress using Action Scheduler. Sends non-blocking POSTs for create/update/delete of posts, terms, and users. Meta changes trigger the entity update. ACF updates include field context. Features intelligent failure monitoring with email notifications, automatic blocking, and comprehensive filtering options.

## Features

- **Action Scheduler dispatch** - 5s delay, non-blocking async delivery
- **Automatic deduplication** - Based on action+entity+id
- **Entity-aware payloads** - Includes post_type, taxonomy, or roles
- **ACF integration** - Adds field key/name context to meta changes
- **Registry pattern** - Extensible webhook system with custom configurations
- **Failure monitoring** - Email notifications, automatic blocking after 10 failures
- **Comprehensive filtering** - Control payloads, URLs, headers, and meta keys

## Quick Start

### Installation

```bash
composer require citation-media/wp-webhook-framework
```

Ensure Action Scheduler is active (dependency is declared).

### Basic Setup

```php
// Initialize the framework
\Citation\WP_Webhook_Framework\Service_Provider::register();
```

### Configuration

```php
// wp-config.php
define('WP_WEBHOOK_FRAMEWORK_URL', 'https://api.example.com/webhook');
```

See @docs/configuration.md for detailed configuration options.

## Usage Examples

### Configure Built-in Webhooks

```php
$registry = \Citation\WP_Webhook_Framework\Service_Provider::get_registry();

$post_webhook = $registry->get('post');
if ($post_webhook) {
    $post_webhook->webhook_url('https://api.example.com/posts')
                 ->allowed_retries(5)
                 ->timeout(60);
}
```

### Create Custom Webhook

```php
class Custom_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('my_custom_webhook');
        
        $this->allowed_retries(3)
             ->timeout(30)
             ->webhook_url('https://api.example.com/custom')
             ->headers(['Authorization' => 'Bearer token123']);
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        add_action('my_custom_action', [$this, 'handle_action'], 10, 1);
    }
    
    public function handle_action($data): void {
        $payload = ['custom_data' => $data, 'timestamp' => time()];
        $this->emit('action_triggered', 'custom', $data['id'], $payload);
    }
}

// Register the webhook
add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new Custom_Webhook());
});
```

See @docs/custom-webhooks.md for detailed webhook creation guide.

### Filter Payloads

```php
// Prevent delete webhooks
add_filter('wpwf_payload', function($payload, $entity, $id) {
    if ($payload['action'] === 'delete') {
        return []; // Return empty array to prevent webhook
    }
    return $payload;
}, 10, 3);

// Add custom data to user webhooks
add_filter('wpwf_payload', function($payload, $entity, $id) {
    if ($entity === 'user') {
        $user = get_userdata($id);
        $payload['email'] = $user->user_email;
    }
    return $payload;
}, 10, 3);
```

See @docs/hooks-and-filters.md for all available hooks and filters.

## Architecture Concepts

### Webhook Statefulness

**Important:** Webhook instances are singletons and must remain stateless. Never store per-emission data as instance properties.

**Stateless (configuration):**
```php
// Set once during __construct()
$this->webhook_url('https://api.example.com')
     ->allowed_retries(5);
```

**Stateful (emission data):**
```php
// Pass directly to emit() - NEVER store on instance
$this->emit('update', 'post', $post_id, $payload);
```

See @docs/webhook-statefulness.md for detailed explanation.

### Core Components

- **Service_Provider** - Singleton that bootstraps the framework
- **Webhook_Registry** - Manages webhook instances and initialization
- **Webhook** (abstract) - Base class for all webhooks
- **Dispatcher** - Schedules and sends HTTP requests via Action Scheduler
- **Entity Handlers** - Prepare payloads for posts, terms, users, and meta

**Data Flow:**
```
WordPress hook → Webhook → Handler::prepare_payload() → Webhook::emit() → Dispatcher → Action Scheduler → HTTP POST
```

## Payload Structure

Example webhook payload:

```json
{
  "action": "update",
  "entity": "post",
  "id": 123,
  "post_type": "post"
}
```

**Entity-specific invariants:**
- Post: `post_type`
- Term: `taxonomy`
- User: `roles[]`
- Meta: `acf_field_key`, `acf_field_name` (if ACF field)

## Failure Monitoring

- **Email notifications** on first failure after retries exhausted
- **Automatic blocking** after 10 consecutive failures within 1 hour
- **Auto-unblock** after 1 hour
- **Success resets** failure count and blocked status

See @docs/failure-handling.md for configuration and customization.

## Code Quality

- **PHPCS Compliant** - WordPress coding standards (WPCS 3.1)
- **Type Safe** - PHPStan level 6 static analysis
- **i18n Ready** - All user-facing strings internationalized
- **Filterable** - Extensive WordPress filter integration
- **Well Documented** - Comprehensive inline documentation

## Commands

```bash
composer install              # Install dependencies
composer run-script phpstan   # Run static analysis
composer run-script phpcs     # Lint code
composer run-script phpcbf    # Auto-fix code style
```
