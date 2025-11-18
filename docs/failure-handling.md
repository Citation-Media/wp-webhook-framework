# Failure Handling

Automatic failure monitoring, retry mechanism, and blocking system that tracks failed webhook events to prevent spam to unreliable endpoints.

## Critical Distinction: Retries vs. Blocking

**Retry Attempt**: The framework automatically retrying a **single specific webhook event** that failed (e.g., "Post #123 update").
- Configured via `$webhook->max_retries()`
- Uses exponential backoff (1m, 2m, 4m...)

**Blocking**: Stopping **all** webhooks to a specific URL after **multiple distinct webhook events** have failed.
- Configured via `$webhook->max_consecutive_failures()`
- Prevents system resource waste on dead endpoints

## Retry Mechanism

The framework implements a custom retry mechanism hooking into Action Scheduler's failure events.

### Configuration

```php
$webhook->max_retries(3)        // Retry this webhook 3 times before giving up (default: 0)
        ->timeout(30);          // 30 second timeout per attempt
```

### Retry Logic (Exponential Backoff)

If a webhook fails, the framework schedules a new attempt with a delay:
- **Retry 1**: 1 minute after failure
- **Retry 2**: 2 minutes after failure
- **Retry 3**: 4 minutes after failure
- **Retry 4**: 8 minutes after failure
- ...

The retry count is tracked in the webhook headers (`wpwf-retry-count`).

### Customizing Retry Delays

You can customize the retry timing using filters:

**Change Base Time (Default: 60s)**
```php
add_filter('wpwf_retry_base_time', function($base_time, $webhook_name, $retry_count) {
    return 10; // Start with 10s (10s, 20s, 40s...)
}, 10, 3);
```

**Override Delay Completely**
```php
add_filter('wpwf_retry_delay', function($delay, $retry_count, $webhook_name) {
    return 300; // Static 5 minute delay for all retries
}, 10, 3);
```

## Failure Tracking & Blocking

Tracks **failed webhook events** (after all retries are exhausted) per URL.

1. **Monitoring**: Each webhook delivery is checked.
2. **Retry**: If failed, retries are attempted according to `max_retries()`.
3. **Counting**: If all retries fail, the "consecutive failure count" for that URL is incremented.
4. **Notification**: First failed webhook event emails site admin.
5. **Blocking**: After `max_consecutive_failures()` is reached, the URL is blocked.
6. **Auto-Reset**: A successful delivery resets the counter and unblocks the URL.

### Blocking Configuration

```php
$webhook->max_consecutive_failures(10); // Block URL after 10 distinct events fail
```

## Failure Data Structure

The `Failure` DTO stores per-URL state in a single transient:

```php
array(
    'failed_webhook_count' => 5,          // Count of failed webhook EVENTS (not retry attempts)
    'first_failure_at'     => 1234567890, // First failure timestamp (current window)
    'blocked'              => true,       // Whether URL is blocked
    'blocked_time'         => 1234567890  // When URL was blocked
)
```

**Transient key**: `wpwf_failures_{md5($url)}`
**Expiration**: 1 hour (HOUR_IN_SECONDS)

## Email Notifications

Sent to site admin (`admin_email` option) when a URL is blocked due to reaching the consecutive failure threshold.

Notifications are handled by the `Failure_Notifier` class, which listens to the `wpwf_webhook_blocked` action.

### Customizing Notifications

Use the `wpwf_failure_notification_email` filter to customize email content:

```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    $email_data['subject'] = "[Alert] Webhook Failed: {$url}";
    return $email_data;
}, 10, 3);
```

### Disabling Notifications

Return `false` from the filter to prevent email notifications:

```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    return false; // Disable notifications
}, 10, 3);
```

### Custom Notification Handlers

Hook into `wpwf_webhook_blocked` to implement custom notification systems:

```php
add_action('wpwf_webhook_blocked', function($url, $response, $max_failures) {
    // Send to Slack, log to monitoring service, etc.
}, 10, 3);
```

## Webhook Status Actions

The framework fires actions for webhook success and failure events, allowing custom monitoring and logging.

### `wpwf_webhook_success`

Fired when a webhook is successfully delivered.

**Parameters:**
- `$url` (string) - The webhook URL

```php
add_action('wpwf_webhook_success', function($url) {
    error_log("Webhook succeeded: {$url}");
}, 10, 1);
```

### `wpwf_webhook_failed`

Fired when a webhook fails (after retries exhausted), before blocking decision.

**Parameters:**
- `$url` (string) - The webhook URL
- `$response` (mixed) - The response from wp_remote_post
- `$failure_count` (int) - Current consecutive failure count
- `$max_failures` (int) - Maximum failures before blocking

```php
add_action('wpwf_webhook_failed', function($url, $response, $failure_count, $max_failures) {
    error_log("Webhook failed ({$failure_count}/{$max_failures}): {$url}");
}, 10, 4);
```

### `wpwf_webhook_blocked`

Fired when a webhook URL is blocked due to reaching the failure threshold.

**Parameters:**
- `$url` (string) - The webhook URL
- `$response` (mixed) - The response from wp_remote_post
- `$max_failures` (int) - Maximum failures threshold

```php
add_action('wpwf_webhook_blocked', function($url, $response, $max_failures) {
    // Custom notification logic
    send_slack_alert("Webhook blocked: {$url}");
}, 10, 3);
```

## Implementation Details

**Location**: `src/Dispatcher.php`, `src/Failure.php`, `src/Notifications/Failure_Notifier.php`, `src/Webhook.php`

**Key methods**:
- `Dispatcher::handle_action_failure()` - Hooks into `action_scheduler_failed_action` to schedule retries.
- `Webhook::calculate_retry_delay()` - Calculates exponential backoff.
- `Dispatcher::trigger_webhook_failure()` - Tracks final failures for blocking and fires actions.
- `Failure_Notifier::send_failure_notification()` - Handles email notifications on block.
