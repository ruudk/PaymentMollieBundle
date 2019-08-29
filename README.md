RuudkPaymentMollieBundle
========================

A Symfony2 Bundle that provides access to the Mollie API. Based on JMSPaymentCoreBundle.

## Installation

### Step1: Require the package with Composer

````
composer require ruudk/payment-mollie-bundle
````

### Step2: Enable the bundle

Enable the bundle in the kernel:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...

        new Ruudk\Payment\MollieBundle\RuudkPaymentMollieBundle(),
    );
}
```

### Step3: Configure

Add the following to your routing.yml:
```yaml
ruudk_payment_mollie_notifications:
    pattern:  /webhook/mollie
    defaults: { _controller: ruudk_payment_mollie.controller.notification:processNotification }
    methods:  [GET, POST]
```

Add the following to your config.yml:
```yaml
ruudk_payment_mollie:
    api_key:  Your API key
    logger:   true/false   # Default true
    methods:
      - ideal
      - mistercash
      - creditcard
      - sofort
      - banktransfer
      - belfius
      - kbc
      - inghomepay
      - bitcoin
      - paypal
      - paysafecard
      - ...
```
See the [Mollie API documentation](https://www.mollie.nl/files/documentatie/payments-api.html) for all available methods.

Make sure you set the `return_url` in the `predefined_data` for every payment method you enable:
````php
$form = $this->getFormFactory()->create('jms_choose_payment_method', null, array(
    'amount'   => $order->getAmount(),
    'currency' => 'EUR',
    'predefined_data' => array(
        'mollie_ideal' => array(
            'return_url' => $this->generateUrl('order_complete', array(), true),
        ),
    ),
));
````
It's also possible to set a `description` for the transaction in the `predefined_data`.

To use the Mollie Webhook you should also set the `notify_url` for every transaction. You can use the default 
processNotification route `ruudk_payment_mollie_notifications` for this url.

See [JMSPaymentCoreBundle documentation](http://jmsyst.com/bundles/JMSPaymentCoreBundle/master/usage) for more info.
