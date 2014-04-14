<?php

namespace Ruudk\Payment\MollieBundle\CacheWarmer;

use Omnipay\Mollie\Gateway;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

class IssuersCacheWarmer extends CacheWarmer
{
    /**
     * @var \Omnipay\Mollie\Gateway
     */
    protected $gateway;

    /**
     * @param Gateway $gateway
     */
    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        try {
            $issuers = $this->gateway->fetchIssuers()->send()->getIssuers();

            $cacheFile = "<?php return array(" . PHP_EOL;
            foreach($issuers AS $issuer) {
                $cacheFile .= sprintf('new \Omnipay\Common\Issuer(%s, %s, %s),' . PHP_EOL,
                    var_export($issuer->getId(), true),
                    var_export($issuer->getName(), true),
                    var_export($issuer->getPaymentMethod(), true)
                );
            }
            $cacheFile .= ");";

            $this->writeCacheFile($cacheDir . '/ruudk_payment_mollie_issuers.php', $cacheFile);
        } catch(\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return Boolean true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return false;
    }
}
