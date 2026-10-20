# AGENTS.md — Tkila SinergyPay Gateway

## Mission
Build a production-oriented WooCommerce payment gateway plugin for SinergyPay.

The immediate deliverable is SPEI in SinergyPay Sandbox. Card payments and tokenization are future capabilities and MUST remain disabled until PCI DSS requirements and the exact SinergyPay card/tokenization flow are confirmed.

## Priorities
1. Get the complete SPEI Sandbox flow working end-to-end.
2. Keep the plugin modular so cards/tokenization can be added without rewriting SPEI.
3. Favor WooCommerce APIs and WordPress conventions over custom infrastructure.
4. Security and idempotency are mandatory for payment state changes.

## Architecture
Use the `TKILA_SinergyPay` namespace.

Suggested responsibilities:
- `Plugin`: bootstrap and dependency wiring.
- `Api_Client`: authenticated HTTP communication with SinergyPay.
- `Spei_Gateway`: WooCommerce payment gateway and checkout flow.
- `Webhook_Handler`: receives, validates and processes notifications.
- `Logger`: WooCommerce logger wrapper with secret redaction.
- `Card_Gateway`: placeholder only until PCI/API requirements are confirmed.
- `Tokenization`: placeholder only until PCI/API requirements are confirmed.

## Coding rules
- PHP 8.0+ compatible.
- Follow WordPress/WooCommerce escaping, sanitization, nonce and capability practices.
- Prefix option/meta keys with `_tkila_sinergypay_` or `tkila_sinergypay_`.
- Use `wp_remote_request()` / WordPress HTTP API rather than raw cURL.
- Use WooCommerce CRUD APIs for orders.
- Use `wc_get_logger()` for logs.
- Do not log API keys, authorization headers, PAN, CVV or other sensitive payment data.
- Treat webhook delivery as repeatable: processing must be idempotent.
- Do not mark an order paid until the webhook/event has been authenticated and the amount/order mapping has been validated.
- Never commit real Sandbox or Production credentials.

## API implementation rule
Do NOT invent SinergyPay endpoint paths, authentication headers, payload fields, webhook signatures, statuses, or tokenization behavior. Implement them only from the supplied official SinergyPay documentation. If a required detail is missing, leave a clear TODO and fail safely.

## Current milestone
Implement SPEI Sandbox:
- WooCommerce gateway registration.
- Admin settings: enabled, title, description, environment, sandbox credentials, production credentials (stored only in WP options), debug toggle.
- Create SinergyPay operation/order when checkout is submitted.
- Obtain SPEI payment data/CLABE using the documented API.
- Persist SinergyPay identifiers in WooCommerce order metadata.
- Show payment instructions on thank-you/order pages.
- Webhook endpoint using WordPress REST API.
- Validate webhook according to official documentation.
- Update order status only after validated payment confirmation.
- Useful sanitized logging and customer-facing error handling.

## Cards/tokenization
Create architectural placeholders only. Do not expose card fields and do not process card data yet. Future implementation should use SinergyPay tokenization/hosted mechanisms where documented and WooCommerce payment-token APIs where appropriate.

## Definition of done for SPEI
A Sandbox order can be created from WooCommerce, payment instructions are returned and displayed, the SinergyPay transaction can be correlated to the WooCommerce order, and a valid webhook updates the correct order without duplicate side effects.
