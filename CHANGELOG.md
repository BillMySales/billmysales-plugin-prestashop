# Changelog

All notable changes to this plugin. Versions follow [Semantic Versioning](https://semver.org).

## [2.0.0] - 2026-09-27

Rewritten:

- Deliveries are queued when an order reaches a selected order state (or the
  merchant asks to send it again), and sent from the module's own cron
  controller, from PrestaShop's `actionCronJob` hook when the `cronjobs`
  module is active, or opportunistically from the back office: the checkout
  and the admin never wait for BillMySales. Failed deliveries are retried on
  network errors, timeouts, rate limits and server errors (after 1 min,
  5 min, 30 min, 2 h and 12 h); other HTTP errors are logged, not retried.
- Two payload formats: "Standard" (the order data BillMySales has parsed
  since the 1.x module) and "Webservice" (PrestaShop's own webservice
  representation of the order, built without an HTTP call or a webservice
  key), selectable in the settings.
- Standard `X-BillMySales-*` headers, plus the legacy
  `X-PRESTASHOPBMS-HMAC-SHA256` signature header the 1.x module sent.
- The order detail page shows the last known delivery status, and offers
  "Send to BillMySales" to send the order again.
- Custom billing fields for the checkout's address form (and "My
  addresses"), also editable from the order detail page and the "Add new
  order" form.
- English source strings, with a Spanish translation catalog.
- Settings stored in new `BILLMYSALES_*` Configuration values (reconfigure
  after the update).
- License changed to the European Union Public Licence v. 1.2 (EUPL-1.2).

## [1.0.2] - 2020-05-24

- Webhook to a configurable URL when an order reaches the selected order
  states, signed with HMAC-SHA256.
- Custom billing fields (e.g. tax id, business activity) for the checkout's
  address form, also editable from the order detail page and the "Add new
  order" form.
