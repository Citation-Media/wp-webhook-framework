# Custom Webhooks

Create and configure custom webhook implementations using the registry pattern.

## Registry Pattern

The framework uses a registry pattern for webhook management, providing:
- Centralized webhook configuration
- Third-party extensibility
- Type-safe webhook instances
- Flexible retry and timeout policies

## Basic Configuration

Access and configure built-in webhooks:

```php
// Get the registry instance
$registry = \Citation\WP_Webhook_Framework\Service_Provider::get_registry();

// Configure existing webhooks
$post_webhook = $registry->get('post');
if ($post_webhook) {
    $post_webhook->max_consecutive_failures(5)
                 ->timeout(60)
                 ->webhook_url('https://api.example.com/posts');
}
```

## Configuration Methods

All webhooks support these chainable configuration methods:

```php
$webhook->max_consecutive_failures(5)         // Set retry attempts (0-10)
        ->timeout(60)                // Set timeout in seconds (1-300)
        ->enabled(true)              // Enable/disable webhook
        ->webhook_url('https://...')  // Custom URL for this webhook
        ->headers(['key' => 'val']); // Additional HTTP headers
```

## Creating Custom Webhooks

### Simple Webhook (No Entity Handlers)

For custom WordPress hooks that don't need entity data transformation:

```php
class Custom_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('my_custom_webhook');
        
        // Configure webhook behavior
        $this->max_consecutive_failures(3)
             ->timeout(30)
             ->webhook_url('https://api.example.com/custom')
             ->headers(['Authorization' => 'Bearer token123']);
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        // Register WordPress hooks
        add_action('my_custom_action', [$this, 'handle_action'], 10, 1);
    }
    
    public function handle_action($data): void {
        $registry = \Citation\WP_Webhook_Framework\Webhook_Registry::instance();
        $dispatcher = $registry->get_dispatcher();
        
        $payload = [
            'custom_data' => $data,
            'timestamp' => time()
        ];
        
        $dispatcher->schedule(
            $this->get_webhook_url(),
            'action_triggered',
            'custom',
            $data['id'],
            $payload,
            $this->get_headers()
        );
    }
}
```

### WooCommerce Order Webhook

Example integrating with WooCommerce:

```php
class WooCommerce_Order_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('woocommerce_orders');
        
        $this->max_consecutive_failures(3)
             ->timeout(45)
             ->webhook_url('https://api.example.com/woocommerce/orders')
             ->headers(['X-WooCommerce-Webhook' => 'order-events']);
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        add_action('woocommerce_new_order', [$this, 'on_new_order']);
        add_action('woocommerce_order_status_changed', [$this, 'on_status_changed'], 10, 4);
    }
    
    public function on_new_order($order_id): void {
        $registry = \Citation\WP_Webhook_Framework\Webhook_Registry::instance();
        $dispatcher = $registry->get_dispatcher();
        
        $order = wc_get_order($order_id);
        $payload = [
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'status' => $order->get_status(),
            'timestamp' => time()
        ];
        
        $dispatcher->schedule(
            $this->get_webhook_url(),
            'created',
            'woocommerce_order',
            $order_id,
            $payload,
            $this->get_headers()
        );
    }
    
    public function on_status_changed($order_id, $old_status, $new_status, $order): void {
        $registry = \Citation\WP_Webhook_Framework\Webhook_Registry::instance();
        $dispatcher = $registry->get_dispatcher();
        
        $payload = [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'timestamp' => time()
        ];
        
        $dispatcher->schedule(
            $this->get_webhook_url(),
            'status_changed',
            'woocommerce_order',
            $order_id,
            $payload,
            $this->get_headers()
        );
    }
}
```

### ACF Field Group Webhook

Example for ACF field group changes:

```php
class ACF_Field_Group_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('acf_field_groups');
        
        $this->max_consecutive_failures(2)
             ->timeout(30)
             ->webhook_url('https://api.example.com/acf/field-groups');
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        add_action('acf/update_field_group', [$this, 'on_field_group_update']);
        add_action('acf/delete_field_group', [$this, 'on_field_group_delete']);
    }
    
    public function on_field_group_update($field_group): void {
        $registry = \Citation\WP_Webhook_Framework\Webhook_Registry::instance();
        $dispatcher = $registry->get_dispatcher();
        
        $payload = [
            'field_group_key' => $field_group['key'],
            'title' => $field_group['title'],
            'fields' => count($field_group['fields'] ?? [])
        ];
        
        $dispatcher->schedule(
            $this->get_webhook_url(),
            'update',
            'acf_field_group',
            $field_group['key'],
            $payload,
            $this->get_headers()
        );
    }
    
    public function on_field_group_delete($field_group): void {
        $registry = \Citation\WP_Webhook_Framework\Webhook_Registry::instance();
        $dispatcher = $registry->get_dispatcher();
        
        $payload = [
            'field_group_key' => $field_group['key'],
            'title' => $field_group['title']
        ];
        
        $dispatcher->schedule(
            $this->get_webhook_url(),
            'delete',
            'acf_field_group',
            $field_group['key'],
            $payload,
            $this->get_headers()
        );
    }
}
```

## Registering Custom Webhooks

Register webhooks using the `wpwf_register_webhooks` action:

```php
function register_my_webhooks($registry) {
    $registry->register(new Custom_Webhook());
    $registry->register(new WooCommerce_Order_Webhook());
    $registry->register(new ACF_Field_Group_Webhook());
}
add_action('wpwf_register_webhooks', 'register_my_webhooks');
```

## Webhook Statefulness

Webhook instances are singletons and **must remain stateless**. Never store per-emission data as instance properties.

**Correct (stateless configuration):**
```php
public function __construct() {
    parent::__construct('my_webhook');
    
    // Configuration is set once during initialization
    $this->webhook_url('https://api.example.com')
         ->headers(['Authorization' => 'Bearer token']);
}
```

**Incorrect (stateful data):**
```php
private $current_payload; // NEVER DO THIS

public function handle_action($data): void {
    $this->current_payload = $data; // WRONG - creates race conditions
}
```

**Why?** Multiple WordPress hooks can fire rapidly. Storing emission-specific data on the instance creates race conditions. Pass dynamic data as parameters to `schedule()` instead.

See @docs/webhook-statefulness.md for detailed explanation.

## Singleton Pattern

The Service_Provider and Webhook_Registry use singleton pattern to ensure consistent configuration:

```php
// Get the singleton instance
$provider = \Citation\WP_Webhook_Framework\Service_Provider::get_instance();
$registry = \Citation\WP_Webhook_Framework\Webhook_Registry::instance();

// Same instance returned on subsequent calls
$sameProvider = \Citation\WP_Webhook_Framework\Service_Provider::get_instance();
```

## Registry Methods

### Register Webhook
```php
$registry->register($webhook); // Registers and initializes if enabled
```

### Get Webhook
```php
$webhook = $registry->get('webhook_name'); // Returns Webhook|null
```

### Check Registration
```php
$exists = $registry->has('webhook_name'); // Returns bool
```

### Get All Webhooks
```php
$all = $registry->get_all(); // Returns array<string,Webhook>
$enabled = $registry->get_enabled(); // Returns enabled webhooks only
```

### Unregister Webhook
```php
$registry->unregister('webhook_name'); // Returns bool
```

## Best Practices

1. **Always check `is_enabled()`** in `init()` method before registering hooks
2. **Use chainable configuration** in constructor for cleaner code
3. **Pass dispatcher instance** from registry instead of creating new ones
4. **Keep webhooks stateless** - no per-emission data on instance
5. **Use meaningful webhook names** - lowercase with underscores
6. **Set appropriate timeouts** - balance between reliability and performance
7. **Configure retry policies** - consider endpoint reliability
8. **Document webhook behavior** - explain action types and payloads
