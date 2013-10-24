RuudkPaymentMollieBundle
========================

A Symfony2 Bundle that provides access to the Mollie API. Based on JMSPaymentCoreBundle.

## Installation

### Step1: Require the package with Composer

````
php composer.phar require ruudk/payment-mollie-bundle
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
    partner_id:     Your partner id
    profile_key:    ~
    test:           true/false
    report_url:     http://host/webhook/mollie
    methods:
      - ideal
```

For now, iDEAL is the only supported method.

See [JMSPaymentCoreBundle documentation](http://jmsyst.com/bundles/JMSPaymentCoreBundle/master/usage) for more info.
