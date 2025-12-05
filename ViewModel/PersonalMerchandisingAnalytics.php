<?php

declare(strict_types=1);

namespace Tweakwise\Magento2Tweakwise\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Class PersonalMerchandisingAnalytics
 *
 * ViewModel for personal merchandising analytics.
 */
class PersonalMerchandisingAnalytics implements ArgumentInterface
{
    /**
     * PersonalMerchandisingAnalytics constructor.
     *
     * @param Config $tweakwiseConfig
     * @param Helper $helper
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly Config $tweakwiseConfig,
        private readonly Helper $helper,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Get the product key.
     *
     * @return string
     */
    public function getProductKey(): string
    {
        $productId = $this->request->getParam('id');
        $storeId = $this->storeManager->getStore()->getId();

        if (!$productId) {
            return '0';
        }

        return $this->helper->getTweakwiseId((int)$storeId, (int)$productId);
    }

    /**
     * Get the API URL.
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return 'https://navigator-analytics.tweakwise.com/api/';
    }

    /**
     * Get the instance key.
     *
     * @return string
     */
    public function getInstanceKey(): string
    {
        return $this->tweakwiseConfig->getGeneralAuthenticationKey();
    }

    /**
     * Get the cookie name.
     *
     * @return string
     */
    public function getCookieName(): string
    {
        return $this->tweakwiseConfig->getPersonalMerchandisingCookieName();
    }

    /**
     * Get the search query.
     *
     * @return string
     */
    public function getSearchQuery(): string
    {
        return $this->request->getParam('q') ?? '';
    }

    /**
     * Get the Tweakwise request ID.
     *
     * @return string
     */
    public function getTwRequestId(): string
    {
        return $this->request->getParam('tw_request_id') ?? '';
    }

    /**
     * Get the value based on the analytics type.
     *
     * @param string $analyticsType
     * @return string
     */
    public function getValue(string $analyticsType): string
    {
        return match ($analyticsType) {
            'product'   => $this->getProductKey(),
            'search'    => $this->getSearchQuery(),
            'itemclick' => $this->getTwRequestId(),
            'session_start' => 'session_start',
            default     => '',
        };
    }
}
