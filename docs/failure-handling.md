# Failure Handling

Automatic failure monitoring and blocking system prevents spam to failing webhook endpoints.

## Retries vs. Failures

**Important distinction**:
- **Retries**: Handled by Action Scheduler automatically when actions fail (throws exception). Default: 3 retries with exponential backoff
- **Failures**: Tracked by this framework when webhook delivery returns non-200 HTTP response (no exception thrown)

The `allowed_retries` configuration on `Webhook` instances is **not currently implemented**. Action Scheduler uses its own retry logic. Framework failure tracking is separate and counts delivery attempts that complete but fail (non-200 responses).

## How It Works

1. **Monitoring**: Each webhook delivery response is checked (only 200 = success)
2. **Tracking**: Failures tracked per URL via WordPress transients (1-hour expiration)
3. **Notification**: First failure emails site admin with error details
4. **Blocking**: After 10 consecutive failures within 1 hour, URL is blocked
5. **Auto-Reset**: Successful delivery resets count and unblocks; blocks auto-expire after 1 hour

## Failure Data Structure

The `Failure` DTO stores per-URL state in a single transient:

```php
array(
    'count'            => 5,          // Consecutive failure count
    'first_failure_at' => 1234567890, // First failure timestamp (current window)
    'blocked'          => true,       // Whether URL is blocked
    'blocked_time'     => 1234567890  // When URL was blocked
)
```

**Transient key**: `wpwf_failures_{md5($url)}`  
**Expiration**: 1 hour (HOUR_IN_SECONDS)

## Thresholds (Hardcoded)

- **Failure threshold**: 10 consecutive failures
- **Time window**: 1 hour
- **Block duration**: 1 hour (automatic unblock)

## Email Notifications

Sent to site admin (`admin_email` option) on **first failure only**. Includes:
- Webhook URL
- Error message (WP_Error message or HTTP status code)
- Timestamp
- Warning about blocking threshold

### Customizing Notifications

Use the `wpwf_failure_notification_email` filter to customize email content:

```php
add_filter('wpwf_failure_notification_email', function($email_data, $url, $response) {
    // Modify recipient
    $email_data['recipient'] = 'devops@example.com';
    
    // Customize subject
    $email_data['subject'] = "[CRITICAL] Webhook Failed: {$url}";
    
    // Add custom headers
    $email_data['headers'][] = 'Cc: admin@example.com';
    
    // Append to message
    $email_data['message'] .= "\n\nEnvironment: " . wp_get_environment_type();
    
    return $email_data;
}, 10, 3);
```

**Available data**:
- `recipient` (string) - Email recipient
- `subject` (string) - Email subject
- `message` (string) - Email body
- `headers` (array) - Email headers
- `url` (string) - Failed webhook URL
- `error_message` (string) - Error details
- `response` (mixed) - Full wp_remote_post response

### Disabling Notifications

Return `false` for the recipient to prevent sending:

```php
add_filter('wpwf_failure_notification_email', function($email_data) {
    $email_data['recipient'] = false;
    return $email_data;
}, 10, 3);
```

## Configuration

No configuration required—failure handling is automatic. To modify thresholds, edit `Dispatcher::handle_webhook_failure()` and `Failure::is_block_expired()` source code.

**Note**: The `allowed_retries()` method on `Webhook` instances is defined but not implemented. Retries are handled by Action Scheduler's built-in retry mechanism.

## Implementation Details

**Location**: `src/Dispatcher.php`, `src/Failure.php`

**Key methods**:
- `Dispatcher::handle_webhook_failure()` - Increments count, blocks at threshold
- `Dispatcher::handle_webhook_success()` - Resets failure data
- `Dispatcher::is_url_blocked()` - Checks block status with auto-expiry
- `Failure::is_block_expired()` - Checks if 1 hour elapsed since block
- `Failure::from_transient()` - Loads failure data from transient
- `Failure::save()` - Persists failure data to transient
