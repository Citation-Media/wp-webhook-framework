# WP Webhook Framework

Entity-level webhooks for WordPress using Action Scheduler. Sends non-blocking POSTs for create/update/delete of posts, terms, and users. Meta changes trigger the entity update. ACF updates include field context.

## Features
- Action Scheduleronly dispatch, 5s delay
- Dedupe on action+entity+id
- Payload invariants:
  - Post: post_type
  - Term: taxonomy
  - User: roles[]
- Default post restriction: [] (configurable)
- ACF-aware (adds acf_field_key/name)

## Install
```bash
composer require citation-media/wp-webhook-framework
```
Ensure Action Scheduler is active (dependency is declared).

## Usage
```php
$provider = new \CitationMedia\WpWebhookFramework\ServiceProvider([
  'webhook_url'        => 'https://example.com/webhook',
  'hook_group'         => 'wpwf',
  'process_hook'       => 'wpwf_send_webhook',
  'allowed_post_types' => ['post', 'page'],
]);
$provider->register();
```

Example payload:
```json
{
  "action": "update",
  "entity": "post",
  "id": 123,
  "post_type": "post",
  "acf_field_key": "field_abc",
  "acf_field_name": "some_field"
}
```
