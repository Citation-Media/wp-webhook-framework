<?php
/**
 * Abstract base class for entity handlers.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;

/**
 * Abstract base class for entity handlers.
 *
 * Provides reusable WordPress hook callbacks and data transformation logic
 * for entity-specific webhook events. Does not emit webhooks directly.
 */
abstract class Entity_Handler {

}
