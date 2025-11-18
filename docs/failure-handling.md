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

Sent to site admin (`admin_email` option) on **first failed webhook event** (after retries are exhausted).

### Customizing Notifications

Use the `wpwf_failure_notification_email` filter to customize email content:

```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    $email_data['subject'] = "[Alert] Webhook Failed: {$url}";
    return $email_data;
}, 10, 3);
```

## Implementation Details

**Location**: `src/Dispatcher.php`, `src/Failure.php`, `src/Webhook.php`

**Key methods**:
- `Dispatcher::handle_action_failure()` - Hooks into `action_scheduler_failed_action` to schedule retries.
- `Webhook::calculate_retry_delay()` - Calculates exponential backoff.
- `Dispatcher::trigger_webhook_failure()` - Tracks final failures for blocking.
