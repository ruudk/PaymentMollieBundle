<?php

namespace Ruudk\Payment\MollieBundle\CacheWarmer;

use AMNL\Mollie\Exception\MollieException;
use AMNL\Mollie\IDeal\IDealGateway;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

class IdealCacheWarmer extends CacheWarmer
{
    /**
     * @var \AMNL\Mollie\IDeal\IDealGateway
     */
    private $api;

    public function __construct(IDealGateway $api)
    {
        $this->api = $api;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        try {
            $list = $this->api->getBankList();

            $banks = array();
            foreach($list AS $bank) {
                $banks[$bank->getId()] = $bank->getName();
            }

            $this->writeCacheFile($cacheDir . '/ruudk_payment_mollie_ideal.php', sprintf('<?php return %s;', var_export($banks, true)));
        } catch(MollieException $exception) {
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