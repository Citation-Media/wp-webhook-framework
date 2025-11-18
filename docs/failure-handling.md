# Failure Handling

Automatic failure monitoring and blocking system that tracks failed webhook events (not individual retry attempts) to prevent spam to unreliable endpoints.

## Critical Distinction: Webhook Events vs. Retry Attempts

**Failed Webhook Event**: A unique webhook (entity change) that failed delivery after all Action Scheduler retry attempts exhausted.

**Retry Attempt**: Action Scheduler's automatic retry of the same webhook event (default: 3 retries with exponential backoff).

### Example Scenario

A post is updated, triggering a webhook:
1. **First attempt**: Fails with 500 error
2. **Retry 1** (by Action Scheduler): Fails again
3. **Retry 2** (by Action Scheduler): Fails again  
4. **Retry 3** (by Action Scheduler): Fails again
5. **Result**: This counts as **1 failed webhook event** for blocking purposes

If 10 different post updates each fail (after their retries), that's 10 failed webhook events → URL blocked.

## Action Scheduler Retry Configuration

The `max_consecutive_failures()` method on webhooks configures Action Scheduler's retry behavior:

```php
$webhook->max_consecutive_failures(5)  // Action Scheduler will retry up to 5 times
        ->timeout(30);          // 30 second timeout per attempt
```

**Retry timing**: Exponential backoff (managed by Action Scheduler)
- Retry 1: ~1 minute after failure
- Retry 2: ~2 minutes after failure
- Retry 3: ~4 minutes after failure
- etc.

**When retries = 0**: No retries, immediate failure after first attempt

## Failure Tracking

Tracks **failed webhook events** (not retry attempts) per URL:

1. **Monitoring**: Each webhook delivery checked (only 200 = success)
2. **Retry**: Action Scheduler retries based on `max_consecutive_failures()` configuration
3. **Counting**: After all retries exhausted, increment failed webhook event counter by 1
4. **Notification**: First failed webhook event emails site admin
5. **Blocking**: After 10 consecutive failed webhook events within 1 hour, URL blocked
6. **Auto-Reset**: Success resets counter and unblocks; blocks expire after 1 hour

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

**Important**: `failed_webhook_count` increments by 1 per webhook event, regardless of how many retry attempts occurred.

## Thresholds

- **Blocking threshold**: 10 consecutive failed webhook events
- **Time window**: 1 hour
- **Block duration**: 1 hour (automatic unblock after expiration)
- **Max retries**: 0-10 per webhook (configurable via `max_consecutive_failures()`)

## Email Notifications

Sent to site admin (`admin_email` option) on **first failed webhook event**. Includes:
- Webhook URL
- Error message (WP_Error message or HTTP status code)
- Timestamp  
- Number of retries configured for the webhook
- Warning about blocking threshold (10 failed webhook events)

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

## Configuration Examples

### High-Reliability Endpoint

```php
$webhook->max_consecutive_failures(5)   // Retry up to 5 times per webhook event
        ->timeout(60);          // Allow longer response time
```

Timeline for one webhook event that keeps failing:
- Attempt 1: Immediate
- Retry 1: +~1 min
- Retry 2: +~2 min
- Retry 3: +~4 min
- Retry 4: +~8 min
- Retry 5: +~16 min
- **Result**: 1 failed webhook event counted

### Low-Latency Endpoint

```php
$webhook->max_consecutive_failures(1)   // Quick retry only
        ->timeout(10);          // Fail fast
```

### No Retries (Fail Immediately)

```php
$webhook->max_consecutive_failures(0)   // No retries, immediate failure
        ->timeout(30);
```

## How Blocking Works

### Blocking Logic

1. Webhook event fails after all Action Scheduler retries → failed webhook count increments by 1
2. After 10 consecutive failed webhook events → URL blocked
3. Blocked URL rejects new webhook scheduling (throws exception)
4. Block expires after 1 hour OR success resets counter

### Example Blocking Scenario

URL with `max_consecutive_failures(3)`:
1. **Post update 1**: Fails (after 3 retries) → count = 1
2. **Post update 2**: Fails (after 3 retries) → count = 2
3. **Term update 1**: Fails (after 3 retries) → count = 3
4. ... (7 more webhook events fail)
10. **User update 1**: Fails (after 3 retries) → count = 10
11. **Post update 3**: **Blocked!** Can't schedule, exception thrown

**Total attempts**: 10 events × 4 attempts each (1 initial + 3 retries) = 40 HTTP requests  
**Failed webhook count**: 10 (only counts unique webhook events)

### Checking Block Status

```php
// Internal method - checks and auto-expires blocks
$is_blocked = $this->is_url_blocked($url);
```

### Manual Unblock

Delete the transient:

```php
delete_transient('wpwf_failures_' . md5($url));
```

## Implementation Details

**Location**: `src/Dispatcher.php`, `src/Failure.php`

**Key methods**:
- `Dispatcher::schedule()` - Schedules webhook via Action Scheduler
- `Dispatcher::process_scheduled_webhook()` - Processes delivery, calls failure handler
- `Dispatcher::trigger_webhook_failure()` - Tracks failed webhook events (not retries)
- `Dispatcher::handle_webhook_success()` - Resets failed webhook count
- `Dispatcher::is_url_blocked()` - Checks block status with auto-expiry
- `Failure::increment_failed_webhook_count()` - Increments by 1 per webhook event
- `Failure::get_failed_webhook_count()` - Returns count of failed webhook events
- `Failure::is_block_expired()` - Checks if 1 hour elapsed since block
- `Failure::from_transient()` - Loads failure data from transient
- `Failure::save()` - Persists failure data to transient

## Action Scheduler Integration

Action Scheduler handles retries **per webhook event**:
- Each retry is Action Scheduler re-executing the same scheduled action
- Framework doesn't manually re-schedule; Action Scheduler does it automatically
- On final failure (after all retries), framework increments failed webhook count by 1
- Exception thrown on failure signals Action Scheduler to retry

**Benefits**:
- Action Scheduler's battle-tested retry logic with exponential backoff
- Framework only tracks unique webhook events for blocking decisions
- Prevents aggressive blocking due to temporary endpoint issues
- Clear separation: Action Scheduler handles retries, framework handles blocking
