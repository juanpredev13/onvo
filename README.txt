=== ONVO Pay ===
Contributors: onvopay
Tags: woocommerce, onvopay, payments, ecommerce, e-commerce
Short Description: ONVO Pay es una solución integrada de pagos en línea que ayuda a los comercios a vender más y mejor, mientras optimiza la experiencia de compra de los clientes.
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.21.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

ONVO Pay es una solución integrada de pagos en línea que ayuda a los comercios a vender más y mejor, mientras optimiza la experiencia de compra de los clientes.

Sus principales características son:

* Habilita pagos por tarjetas de crédito y débito, así como transferencias bancarias (SINPE y SINPE Móvil).
* No genera redireccionamiento ni requiere comprobantes.
* Cuenta con la certificación PCI DSS, la normativa internacional de seguridad que deben cumplir todas las entidades que almacenan, procesan o transmiten datos de tarjetas.
* Los clientes pueden elegir guardar su información de pago para compras futuras de un solo clic.
* Toda la data transaccional se integra en un panel de fácil navegación, diseñado acorde con las mejores prácticas de usabilidad y experiencia de usuario.

== Installation ==

= Modern way =

1. Go to the WordPress Dashboard “Add New Plugin” section.
2. Search For “ONVOpay”.
3. Install, then Activate it.
4. Go to the onvopay.com to get the secret and public keys.
4. Click on onvopay settings or go to WooCommerce settings > payments > click on onvopay.
5. Paste the API Keys in the respective fields.
6. Start collecting payments.


= Old way =

1. Upload onvopay to the /wp-content/plugins/ directory
2. Activate the plugin through the ‘Plugins’ menu in WordPress
3. Go to the onvopay.com to get the secret and public keys.
4. Click on onvopay settings or go to WooCommerce settings > payments > click on onvopay.
5. Paste the API Keys in the respective fields.
6. Start collecting payments.

== Screenshots ==
1. The settings panel used to configure the gateway.
2. Normal checkout with ONVO Pay.
3. Checkout ONVO Pay options.

== Changelog ==

= 0.21.0 - 2025-01-19 =
* Fix: Backwards compatibility with WooCommece 7.9.0
* Fix: Block compatibility with WooCommerce 9.5.2

= 0.20.1 - 2024-12-24 =
* Fix: Compatibility issue with WooCommerce 9.5.0

= 0.20.0 - 2024-12-04 =
* Dev: Update hook to create intent
* Dev: Remove unused conditional

= 0.19.0 - 2024-11-25 =
* Enhancement: Add metadata to ONVO intent
* Enhancement: Update ONVO intent description
* Dev: Update `WC tested up to` to 9.4.2
* Dev: Update `WP Tested up to` to 6.7


= 0.18.0 =
* Enhancement: Hook into `PAYMENT_REQUIRES_ACTION`, `PAYMENT_ACTION_COMPLETED` SDK actions

= 0.17.2 =
* Fix: Set order status based on intent status

= 0.17.1 =
* Enhancement: Add order notes during checkout errors

= 0.17.0 =
* Enhancement: Schedule intent check when it fails during checkout, if intent is completed, complete order
* Dev: Update `WC tested up to` to 9.3.3

= 0.16.0 =
* Enhancement: Handle errors on intent creation, confirmation

= 0.15.0 =
* Fix: Process payment until checkout is valid and order is created

= 0.14.0 =
* Dev: Remove unused code
* Dev: Refactor code
* Enhancement: Update/sync customer data on order payment

= 0.13.2 =
* Fix: Update issue with JS being enqueued before intent is created

= 0.13.1 =
* Fix: Allow payments for guest users

= 0.13.0 =
* Dev: Update `WC tested up to` to 9.1.2
* Dev: Update `Tested up to` to 6.6.1
* Enhancement: Add support for Checkout block

= 0.12.1 =
* Fix: Update issue with SDK widget being removed from the DOM

= 0.12.0 =
* Dev: Update `Tested up to WC` 9.0.1
* Fix: Unhook ONVO listeners on payment method change
* Fix: Don't update intent after payment is completed

= 0.11.0 =
* Dev: Update `Tested up to WC` 8.9.2
* Fix: Clean intent id on cart clear
* Enhancement: Update Intent total on cart update
* Dev: Update property name

= 0.10.3 =
* Dev: Fix typing check on intent response error
* Dev: Update `Tested up to WC` 8.9.1
* Enhancement:

= 0.10.2 =
* Enhancement: Remove need of a false-positive checkout call
* Dev: Update `Tested up to` 8.8.3

= 0.9.3 =
* Dev: Update `Tested up to` 6.5

= 0.9.2 =
* Fix: Remove used of shorthand open tag

= 0.9.1 =
* Enhancement: Declare Compatibility with Woocommerce High-Performance Order Storage (HPOS)

= 0.9.0 =
* Enhancement: Handle \WC_Data_Exception
* Enhancement: Handle errors on renewals
* Enhancement: Update JS event to show/hide spinner
* Enhancement: Use order's intent id if alredy set
* Enhancement: disable #place_order btn during payment processing
* Fix: Avoid creating of multiple intent for the same cart
* Dev: Allow null params
* Dev: Validate if order already has an intent id
* Dev: Extract ONVO metadata functions
* Dev: Define ONVO constants
* Dev: Update debug functions context to include version

= 0.8.0 =
* Enhancement: Add support for `order-pay` checkout page (Deposits, Order payment page)
* Enhancement: Handle `requires_confirmation`, `requires_payment_method`, `refunded`,  and `canceled` intent statuses
* Dev: Save payment intent in Order when payment is completed

= 0.7.0 =
* Dev: extend Intent object, add Builder
* Fix: Fix reference to undefined property in Intent response
* Enhancement: Add order note with ONVO details on processed payment

= 0.6.0 =
* Fix: allow free orders
* Fix: call set_id after $onvo_product_id is validated
* Fix: add more specific selectors to avoid conflicts with other plugins

= 0.5.0 =
* Fix: Create payment-intents only for non-zero orders
* Fix: validate if $price_id is not empty

= 0.4.0 =
* Support multidomain (internal usage)
* Add spinner loading
* Update default copy
* Plugin config: add debug mode option
* Plugin config: add spiner color and opacity fields

= 0.3.1 =
* Adding missing file

= 0.3.0 =
* Updates subscription implementations leaving subs behavior to WC. Instead of creating a sub on ONVO, new intents will be created for the renewals.
* Do not create an Onvo customer during guest checkouts

= 0.2.3 =
* Including unversioned files

= 0.2.2 =
* Fix a general error

= 0.2.1 =
* Add mssing files to fix fatal error

= 0.2.0 =
* ONVO one-click shopper autofill

= 0.1.0 =
* Woocommerce subscriptions enabled

= 0.0.10 =
* Find for `order_ready` mute error by matching text insted of using classes
* Cast package dimensions from string to float
* Do not trigger payment events on `thank you` pge

= 0.0.9 =
* Trigger payment events on checkout page only
* Adding error handling for non supported currencies
* Adding error message when a non supported currency is in place

= 0.0.8 =
* Update descriptions

= 0.0.7 =
* Fix a error when a subscription product is created for first time
* Fix a PHP8 compatibility issue

= 0.0.6 =
* Fix subscription builder class error, missing function param

= 0.0.5 =
* Update payment intent if the cart total changes

= 0.0.4 =
* fix JS issue

= 0.0.3 =
* display errors from js to wp
* set order failure when an error happen

= 0.0.2 =
* error infinite loop fix

= 0.0.1 =
* Initial release.
