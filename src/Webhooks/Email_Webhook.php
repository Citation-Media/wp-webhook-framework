<?php
/**
 * Email webhook implementation.
 *
 * @package juvo\WP_Webhook_Framework\Webhooks
 */

declare(strict_types=1);

namespace juvo\WP_Webhook_Framework\Webhooks;

use juvo\WP_Webhook_Framework\Webhook;

/**
 * Captures `wp_mail()` calls as structured webhook events.
 *
 * Hooks into `pre_wp_mail` so webhook delivery can optionally replace the
 * original mail transport before PHPMailer or SMTP plugins send the message.
 */
class Email_Webhook extends Webhook {

	/**
	 * Controls whether the original `wp_mail()` call is short-circuited.
	 *
	 * @var bool
	 */
	private bool $abort_wp_mail = false;

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 */
	public function __construct( string $name = '' ) {
		if ( '' === $name ) {
			$name = 'email';
		}

		parent::__construct( $name );
	}

	/**
	 * Configure whether webhook delivery replaces the actual email send.
	 *
	 * @param bool $abort Whether to short-circuit `wp_mail()` after emission.
	 * @return static
	 */
	public function abort_wp_mail( bool $abort = true ): static {
		$this->abort_wp_mail = $abort;
		return $this;
	}

	/**
	 * Register the mail interception hook.
	 */
	public function init(): void {
		\add_filter( 'pre_wp_mail', array( $this, 'on_pre_wp_mail' ), 10, 2 );
	}

	/**
	 * Emit a structured webhook payload for each `wp_mail()` call.
	 *
	 * Returning `true` short-circuits WordPress mail delivery, which prevents
	 * PHPMailer and SMTP transport plugins from sending the original email.
	 *
	 * @param null|bool           $pre_wp_mail Short-circuit value from earlier filters.
	 * @param array<string,mixed> $atts   Filtered `wp_mail()` arguments.
	 * @return null|bool
	 */
	public function on_pre_wp_mail( null|bool $pre_wp_mail, array $atts ): null|bool {
		if ( null !== $pre_wp_mail ) {
			return $pre_wp_mail;
		}

		$email_data = $this->build_email_data( $atts );

		$email_data = $this->filter_email_payload( $email_data, $atts );

		if ( false === $email_data ) {
			return null;
		}

		$did_emit = $this->emit(
			'send',
			'email',
			\wp_generate_uuid4(),
			array(
				'email' => $email_data,
			)
		);

		if ( ! $this->should_abort_wp_mail( $email_data ) || ! $did_emit ) {
			return null;
		}

		return true;
	}

	/**
	 * Resolve whether this email should replace the original `wp_mail()` call.
	 *
	 * @param array<string,mixed> $email_data The structured email payload.
	 * @return bool
	 */
	private function should_abort_wp_mail( array $email_data ): bool {
		/**
		 * Filter whether an email webhook should short-circuit `wp_mail()`.
		 *
		 * Returning `true` tells WordPress the email was handled successfully and
		 * skips PHPMailer setup as well as SMTP plugin transports.
		 *
		 * @param bool          $abort      Whether to abort the original `wp_mail()` call.
		 * @param array<string,mixed> $email_data Structured email payload.
		 * @param Email_Webhook $webhook    The email webhook instance.
		 */
		return (bool) \apply_filters( 'wpwf_email_abort_send', $this->abort_wp_mail, $email_data, $this );
	}

	/**
	 * Build the structured email payload that downstream systems can replay.
	 *
	 * @param array<string,mixed> $atts Filtered `wp_mail()` arguments.
	 * @return array<string,mixed>
	 */
	private function build_email_data( array $atts ): array {
		$to           = $this->parse_address_list( $atts['to'] ?? array() );
		$header_lines = $this->normalize_header_lines( $atts['headers'] ?? array() );
		$header_data  = $this->parse_headers( $header_lines );

		$subject = $atts['subject'] ?? '';
		$message = $atts['message'] ?? '';

		return array(
			'recipient'    => $to,
			'to'           => $to,
			'cc'           => $header_data['cc'],
			'bcc'          => $header_data['bcc'],
			'reply_to'     => $header_data['reply_to'],
			'recipients'   => array(
				'to'       => $this->parse_address_list( $atts['to'] ?? array() ),
				'cc'       => $header_data['cc'],
				'bcc'      => $header_data['bcc'],
				'reply_to' => $header_data['reply_to'],
			),
			'sender'       => $header_data['from_email'],
			'sender_name'  => $header_data['from_name'],
			'from'         => array(
				'email' => $header_data['from_email'],
				'name'  => $header_data['from_name'],
			),
			'subject'      => is_scalar( $subject ) ? (string) $subject : '',
			'content'      => is_scalar( $message ) ? (string) $message : '',
			'content_type' => $header_data['content_type'],
			'charset'      => $header_data['charset'],
			'headers'      => $header_data['custom_headers'],
			'header_lines' => $header_lines,
			'attachments'  => $this->normalize_string_array( $atts['attachments'] ?? array() ),
			'embeds'       => $this->normalize_string_array( $atts['embeds'] ?? array() ),
		);
	}

	/**
	 * Filter the structured email payload with runtime validation.
	 *
	 * @param array<string,mixed> $email_data Structured email payload.
	 * @param array<string,mixed> $atts       Filtered `wp_mail()` arguments.
	 * @return array<string,mixed>|false
	 */
	private function filter_email_payload( array $email_data, array $atts ): array|false {
		/**
		 * Filter the structured email webhook payload before emission.
		 *
		 * Return `false` to skip webhook delivery for the current email while
		 * allowing WordPress to continue its normal mail workflow.
		 */
		return $this->validate_filtered_email_payload( \apply_filters( 'wpwf_email_payload', $email_data, $atts, $this ) );
	}

	/**
	 * Validate the email payload returned by runtime filters.
	 *
	 * @param mixed $filtered_email_data Filter output from `wpwf_email_payload`.
	 * @return array<string,mixed>|false
	 */
	private function validate_filtered_email_payload( mixed $filtered_email_data ): array|false {
		if ( false === $filtered_email_data ) {
			return false;
		}

		if ( ! is_array( $filtered_email_data ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Error handling context, no escaping needed.
			trigger_error( 'Failed to emit webhook "' . $this->get_name() . '": email webhook payload must be an array.', E_USER_WARNING );
			return false;
		}

		return $filtered_email_data;
	}

	/**
	 * Normalize raw header input into the line format used by core.
	 *
	 * @param mixed $headers Raw `wp_mail()` headers value.
	 * @return array<int,string>
	 */
	private function normalize_header_lines( mixed $headers ): array {
		if ( is_string( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		if ( ! is_array( $headers ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $headers as $header_line ) {
			if ( ! is_scalar( $header_line ) ) {
				continue;
			}

			$header_line = trim( (string) $header_line );
			if ( '' === $header_line ) {
				continue;
			}

			$normalized[] = $header_line;
		}

		return $normalized;
	}

	/**
	 * Parse headers into the structured fields used by the webhook payload.
	 *
	 * Mirrors the relevant `wp_mail()` parsing rules so the webhook contains the
	 * same sender and header context that WordPress would use for delivery.
	 *
	 * @param array<int,string> $header_lines Normalized header lines.
	 * @return array{
	 *     from_email: string,
	 *     from_name: string,
	 *     cc: array<int,array{email: string, name: string}>,
	 *     bcc: array<int,array{email: string, name: string}>,
	 *     reply_to: array<int,array{email: string, name: string}>,
	 *     content_type: string,
	 *     charset: string,
	 *     custom_headers: array<string,string>
	 * }
	 */
	private function parse_headers( array $header_lines ): array {
		$from_email     = '';
		$from_name      = '';
		$content_type   = '';
		$charset        = '';
		$custom_headers = array();
		$cc             = array();
		$bcc            = array();
		$reply_to       = array();

		foreach ( $header_lines as $header_line ) {
			if ( ! str_contains( $header_line, ':' ) ) {
				continue;
			}

			list( $name, $content ) = explode( ':', $header_line, 2 );

			$name    = trim( $name );
			$content = trim( $content );

			switch ( strtolower( $name ) ) {
				case 'from':
					$from       = $this->parse_single_address( $content );
					$from_email = $from['email'];
					$from_name  = $from['name'];
					break;
				case 'cc':
					$cc = array_merge( $cc, $this->parse_address_list( $content ) );
					break;
				case 'bcc':
					$bcc = array_merge( $bcc, $this->parse_address_list( $content ) );
					break;
				case 'reply-to':
					$reply_to = array_merge( $reply_to, $this->parse_address_list( $content ) );
					break;
				case 'content-type':
					list( $content_type, $charset ) = $this->parse_content_type_header( $content );
					break;
				default:
					$custom_headers[ strtolower( $name ) ] = $content;
			}
		}

		if ( '' === $from_name ) {
			$from_name = 'WordPress';
		}

		if ( '' === $from_email ) {
			$from_email = $this->get_default_from_email();
		}

		$from_email = (string) \apply_filters( 'wp_mail_from', $from_email );
		$from_name  = (string) \apply_filters( 'wp_mail_from_name', $from_name );

		if ( '' === $content_type ) {
			$content_type = 'text/plain';
		}

		$content_type = (string) \apply_filters( 'wp_mail_content_type', $content_type );

		if ( '' === $charset ) {
			$charset = \get_bloginfo( 'charset' );
		}

		$charset = (string) \apply_filters( 'wp_mail_charset', $charset );

		return array(
			'from_email'     => $from_email,
			'from_name'      => $from_name,
			'cc'             => $cc,
			'bcc'            => $bcc,
			'reply_to'       => $reply_to,
			'content_type'   => $content_type,
			'charset'        => $charset,
			'custom_headers' => $custom_headers,
		);
	}

	/**
	 * Parse the Content-Type header into MIME type and charset.
	 *
	 * @param string $content The raw Content-Type header value.
	 * @return array{0: string, 1: string}
	 */
	private function parse_content_type_header( string $content ): array {
		$content_type = trim( $content );
		$charset      = '';

		if ( ! str_contains( $content, ';' ) ) {
			return array( $content_type, $charset );
		}

		$parts        = array_map( 'trim', explode( ';', $content ) );
		$content_type = array_shift( $parts ) ?: '';

		foreach ( $parts as $part ) {
			if ( ! str_contains( strtolower( $part ), 'charset=' ) ) {
				continue;
			}

			$charset = trim( str_replace( array( 'charset=', 'CHARSET=', '"' ), '', $part ) );
			break;
		}

		return array( $content_type, $charset );
	}

	/**
	 * Parse one or more address values into a structured list.
	 *
	 * @param mixed $addresses Raw `wp_mail()` recipient value.
	 * @return array<int,array{email: string, name: string}>
	 */
	private function parse_address_list( mixed $addresses ): array {
		$values = $this->normalize_string_array( $addresses, true );
		$parsed = array();

		foreach ( $values as $address ) {
			$parsed_address = $this->parse_single_address( $address );
			if ( '' === $parsed_address['email'] ) {
				continue;
			}

			$parsed[] = $parsed_address;
		}

		return $parsed;
	}

	/**
	 * Parse a single mailbox string into email and display name.
	 *
	 * @param string $address Raw mailbox string.
	 * @return array{email: string, name: string}
	 */
	private function parse_single_address( string $address ): array {
		$address = trim( $address );

		if ( '' === $address ) {
			return array(
				'email' => '',
				'name'  => '',
			);
		}

		if ( preg_match( '/^(.*)<([^>]+)>$/', $address, $matches ) ) {
			return array(
				'email' => trim( $matches[2] ),
				'name'  => trim( trim( $matches[1] ), '" ' ),
			);
		}

		return array(
			'email' => $address,
			'name'  => '',
		);
	}

	/**
	 * Normalize mixed scalar-or-array input into string values.
	 *
	 * `wp_mail()` accepts strings, newline-delimited strings, arrays, and
	 * comma-separated address lists. This helper keeps webhook payloads stable.
	 *
	 * @param mixed $values          Raw value from `wp_mail()` arguments.
	 * @param bool  $split_on_commas Whether comma-separated values should be split.
	 * @return array<int,string>
	 */
	private function normalize_string_array( mixed $values, bool $split_on_commas = false ): array {
		if ( is_string( $values ) ) {
			$values = str_replace( "\r\n", "\n", $values );

			if ( $split_on_commas ) {
				$values = str_getcsv( $values, ',' );
			} else {
				$values = explode( "\n", $values );
			}
		}

		if ( ! is_array( $values ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$normalized[] = $value;
		}

		return $normalized;
	}

	/**
	 * Reproduce WordPress' default sender email fallback.
	 *
	 * @return string
	 */
	private function get_default_from_email(): string {
		$sitename   = \wp_parse_url( \network_home_url(), PHP_URL_HOST );
		$from_email = 'wordpress@';

		if ( ! is_string( $sitename ) || '' === $sitename ) {
			return $from_email;
		}

		if ( str_starts_with( $sitename, 'www.' ) ) {
			$sitename = substr( $sitename, 4 );
		}

		return $from_email . $sitename;
	}
}
