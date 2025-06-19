<?php

namespace Tweakwise\Magento2Tweakwise\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\PersonalMerchandisingConfig;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Magento\Store\Model\StoreManagerInterface;
use InvalidArgumentException;

class Analytics extends Action
{
    /**
     * Constructor.
     *
     * @param Context                     $context
     * @param JsonFactory                 $resultJsonFactory
     * @param Client                      $client
     * @param PersonalMerchandisingConfig $config
     * @param RequestFactory              $requestFactory
     * @param Helper                      $helper
     * @param StoreManagerInterface       $storeManager
     */
    public function __construct(
        private Context $context,
        private JsonFactory $resultJsonFactory,
        private Client $client,
        private PersonalMerchandisingConfig $config,
        private readonly RequestFactory $requestFactory,
        private readonly Helper $helper,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isAnalyticsEnabled()) {
            return $result->setData(['success' => false, 'message' => 'Analytics is disabled.']);
        }

        $type = $this->getRequest()->getParam('type');
        $value = $this->getRequest()->getParam('value');

        if (empty($type) || empty($value)) {
            return $result->setData(['success' => false, 'message' => 'Missing required parameters.']);
        }

        $profileKey = $this->config->getProfileKey();
        $tweakwiseRequest = $this->requestFactory->create();
        $tweakwiseRequest->setProfileKey($profileKey);
        $storeId = (int)$this->storeManager->getStore()->getId();

        try {
            switch ($type) {
                case 'product':
                    $this->handleProductType($tweakwiseRequest, $value);
                    break;
                case 'search':
                    $this->handleSearchType($tweakwiseRequest, $value);
                    break;
                case 'itemclick':
                    $this->handleItemClickType($tweakwiseRequest, $value, $storeId);
                    break;
                default:
                    return $result->setData(['success' => false, 'message' => 'Invalid type parameter.']);
            }

            $this->client->request($tweakwiseRequest);
            $result->setData(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }

    private function handleProductType($tweakwiseRequest, $value)
    {
        $tweakwiseRequest->setParameter('productKey', $value);
        $tweakwiseRequest->setPath('pageview');
    }

    private function handleSearchType($tweakwiseRequest, $value)
    {
        $tweakwiseRequest->setParameter('searchTerm', $value);
        $tweakwiseRequest->setPath('search');
    }

    private function handleItemClickType($tweakwiseRequest, $value, $storeId)
    {
        $twRequestId = $this->getRequest()->getParam('requestId');
        if (empty($twRequestId)) {
            throw new InvalidArgumentException('Missing requestId for itemclick.');
        }

        if (ctype_digit($value)) {
            $value = $this->helper->getTweakwiseId($storeId, $value);
        }

        $tweakwiseRequest->setParameter('requestId', $twRequestId);
        $tweakwiseRequest->setParameter('itemId', $value);
        $tweakwiseRequest->setPath('itemclick');
    }
}
