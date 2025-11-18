# Third-Party Integration

Integrate the webhook framework with third-party plugins and custom functionality.

## Integration Hook

Use the `wpwf_register_webhooks` action to register custom webhooks with the framework:

```php
/**
 * Register custom webhooks with the framework.
 *
 * @param \Citation\WP_Webhook_Framework\Webhook_Registry $registry
 */
function my_plugin_register_webhooks($registry) {
    // Register custom webhooks here
    $registry->register(new My_Custom_Webhook());
}
add_action('wpwf_register_webhooks', 'my_plugin_register_webhooks');
```

## Plugin Integration Examples

### WooCommerce Integration

Track order lifecycle events:

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
        add_action('woocommerce_payment_complete', [$this, 'on_payment_complete']);
    }
    
    public function on_new_order($order_id): void {
        $order = wc_get_order($order_id);
        $payload = [
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'status' => $order->get_status(),
            'customer_email' => $order->get_billing_email(),
            'items_count' => $order->get_item_count()
        ];
        
        $this->emit('created', 'woocommerce_order', $order_id, $payload);
    }
    
    public function on_status_changed($order_id, $old_status, $new_status, $order): void {
        $payload = [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ];
        
        $this->emit('status_changed', 'woocommerce_order', $order_id, $payload);
    }
    
    public function on_payment_complete($order_id): void {
        $order = wc_get_order($order_id);
        $payload = [
            'order_id' => $order_id,
            'payment_method' => $order->get_payment_method(),
            'transaction_id' => $order->get_transaction_id()
        ];
        
        $this->emit('payment_complete', 'woocommerce_order', $order_id, $payload);
    }
}

// Register the webhook
add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new WooCommerce_Order_Webhook());
});
```

### Advanced Custom Fields (ACF) Integration

Track field group and field changes:

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
        $payload = [
            'field_group_key' => $field_group['key'],
            'title' => $field_group['title'],
            'fields' => $this->extract_field_info($field_group['fields'] ?? [])
        ];
        
        $this->emit('update', 'acf_field_group', $field_group['key'], $payload);
    }
    
    public function on_field_group_delete($field_group): void {
        $payload = [
            'field_group_key' => $field_group['key'],
            'title' => $field_group['title']
        ];
        
        $this->emit('delete', 'acf_field_group', $field_group['key'], $payload);
    }
    
    private function extract_field_info(array $fields): array {
        return array_map(function($field) {
            return [
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type']
            ];
        }, $fields);
    }
}

add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new ACF_Field_Group_Webhook());
});
```

### Contact Form 7 Integration

Track form submissions:

```php
class CF7_Submission_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('cf7_submissions');
        
        $this->max_consecutive_failures(3)
             ->timeout(20)
             ->webhook_url('https://api.example.com/forms/submissions');
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        add_action('wpcf7_mail_sent', [$this, 'on_form_submit']);
    }
    
    public function on_form_submit($contact_form): void {
        $submission = \WPCF7_Submission::get_instance();
        
        if (!$submission) {
            return;
        }
        
        $payload = [
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'data' => $submission->get_posted_data(),
            'submitted_at' => current_time('mysql')
        ];
        
        $this->emit('submitted', 'cf7_form', $contact_form->id(), $payload);
    }
}

add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new CF7_Submission_Webhook());
});
```

### Gravity Forms Integration

Track form entries:

```php
class Gravity_Forms_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('gravity_forms');
        
        $this->max_consecutive_failures(3)
             ->timeout(30)
             ->webhook_url('https://api.example.com/gravity-forms/entries');
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        add_action('gform_after_submission', [$this, 'on_form_submit'], 10, 2);
    }
    
    public function on_form_submit($entry, $form): void {
        $payload = [
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'entry_id' => $entry['id'],
            'entry_data' => $this->extract_entry_data($entry, $form),
            'submitted_at' => $entry['date_created']
        ];
        
        $this->emit('submitted', 'gravity_form', $form['id'], $payload);
    }
    
    private function extract_entry_data(array $entry, array $form): array {
        $data = [];
        foreach ($form['fields'] as $field) {
            $data[$field->label] = rgar($entry, $field->id);
        }
        return $data;
    }
}

add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new Gravity_Forms_Webhook());
});
```

### Easy Digital Downloads (EDD) Integration

Track download purchases:

```php
class EDD_Purchase_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('edd_purchases');
        
        $this->max_consecutive_failures(3)
             ->timeout(30)
             ->webhook_url('https://api.example.com/edd/purchases');
    }
    
    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }
        
        add_action('edd_complete_purchase', [$this, 'on_purchase_complete']);
    }
    
    public function on_purchase_complete($payment_id): void {
        $payment = edd_get_payment($payment_id);
        
        $payload = [
            'payment_id' => $payment_id,
            'customer_email' => $payment->email,
            'total' => $payment->total,
            'downloads' => $this->get_download_info($payment_id)
        ];
        
        $this->emit('purchase_complete', 'edd_payment', $payment_id, $payload);
    }
    
    private function get_download_info($payment_id): array {
        $downloads = edd_get_payment_meta_downloads($payment_id);
        return array_map(function($download) {
            return [
                'id' => $download['id'],
                'name' => get_the_title($download['id']),
                'price' => $download['price']
            ];
        }, $downloads);
    }
}

add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new EDD_Purchase_Webhook());
});
```

## Best Practices

### Plugin Detection

Check if third-party plugins are active before registering webhooks:

```php
add_action('wpwf_register_webhooks', function($registry) {
    // WooCommerce
    if (class_exists('WooCommerce')) {
        $registry->register(new WooCommerce_Order_Webhook());
    }
    
    // ACF
    if (function_exists('acf')) {
        $registry->register(new ACF_Field_Group_Webhook());
    }
    
    // Contact Form 7
    if (defined('WPCF7_VERSION')) {
        $registry->register(new CF7_Submission_Webhook());
    }
    
    // Gravity Forms
    if (class_exists('GFForms')) {
        $registry->register(new Gravity_Forms_Webhook());
    }
});
```

### Conditional Registration

Enable webhooks based on configuration:

```php
add_action('wpwf_register_webhooks', function($registry) {
    // Only register if enabled in options
    if (get_option('enable_woocommerce_webhooks')) {
        $webhook = new WooCommerce_Order_Webhook();
        $webhook->webhook_url(get_option('woocommerce_webhook_url'));
        $registry->register($webhook);
    }
});
```

### Environment-Specific URLs

Configure different webhook URLs per environment:

```php
class My_Plugin_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    
    public function __construct() {
        parent::__construct('my_plugin');
        
        // Set URL based on environment
        $url = WP_ENV === 'production'
            ? 'https://api.example.com/webhooks'
            : 'https://staging-api.example.com/webhooks';
        
        $this->webhook_url($url)
             ->max_consecutive_failures(3)
             ->timeout(30);
    }
    
    public function init(): void {
        // Implementation
    }
}
```

### Using Filters for Dynamic Configuration

Combine webhooks with filters for maximum flexibility:

```php
add_filter('wpwf_url', function($url, $entity, $id) {
    // Route WooCommerce orders to different endpoint
    if ($entity === 'woocommerce_order') {
        return 'https://api.example.com/orders';
    }
    return $url;
}, 10, 3);

add_filter('wpwf_headers', function($headers, $entity, $id, $webhook_name) {
    // Add authentication for WooCommerce webhooks
    if ($webhook_name === 'woocommerce_orders') {
        $headers['X-WooCommerce-Key'] = get_option('woocommerce_api_key');
    }
    return $headers;
}, 10, 4);
```

## Distributing Webhooks

### In a Plugin

```php
// my-plugin/webhooks/class-my-webhook.php
class My_Plugin_Webhook extends \Citation\WP_Webhook_Framework\Webhook {
    // Implementation
}

// my-plugin/my-plugin.php
add_action('wpwf_register_webhooks', function($registry) {
    require_once plugin_dir_path(__FILE__) . 'webhooks/class-my-webhook.php';
    $registry->register(new My_Plugin_Webhook());
});
```

### In a Theme

```php
// functions.php
require_once get_template_directory() . '/inc/webhooks.php';

// inc/webhooks.php
add_action('wpwf_register_webhooks', function($registry) {
    $registry->register(new Theme_Custom_Webhook());
});
```

See @docs/custom-webhooks.md for detailed webhook implementation examples.
