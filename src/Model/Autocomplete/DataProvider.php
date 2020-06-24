<?php
/**
 * Tweakwise & Emico (https://www.tweakwise.com/ & https://www.emico.nl/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2017 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   Proprietary and confidential, Unauthorized copying of this file, via any medium is strictly prohibited
 */

namespace Emico\Tweakwise\Model\Autocomplete;

use Emico\Tweakwise\Model\Autocomplete\DataProvider\ProductItemFactory;
use Emico\Tweakwise\Model\Autocomplete\DataProvider\SuggestionItemFactory;
use Emico\Tweakwise\Model\Autocomplete\DataProvider\SuggestionGroupItemFactory;
use Emico\Tweakwise\Model\Client;
use Emico\Tweakwise\Model\Client\Request\AutocompleteRequest;
use Emico\Tweakwise\Model\Client\RequestFactory;
use Emico\Tweakwise\Model\Client\Response\AutocompleteResponse;
use Emico\Tweakwise\Model\Client\Response\AutocompleteProductResponseInterface;
use Emico\Tweakwise\Model\Config;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Layer\Category\CollectionFilter;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\ItemInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;

use function GuzzleHttp\Promise\unwrap;

class DataProvider implements DataProviderInterface
{
    /**
     * @var ProductItemFactory
     */
    protected $productItemFactory;

    /**
     * @var SuggestionItemFactory
     */
    protected $suggestionItemFactory;

    /**
     * @var SuggestionGroupItemFactory
     */
    protected $suggestionGroupItemFactory;

    /**
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var RequestFactory
     */
    protected $autocompleteRequestFactory;

    /**
     * @var RequestFactory
     */
    protected $suggestionRequestFactory;

    /**
     * @var RequestFactory
     */
    protected $productSuggestionRequestFactory;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CollectionFilter
     */
    protected $collectionFilter;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var int|null
     */
    protected $categoryId;

    /**
     * @var string|null
     */
    protected $queryText;

    /**
     * @var bool
     */
    protected $addMediaGalleryData = false;

    /**
     * DataProvider constructor.
     *
     * @param ProductItemFactory $productItemFactory
     * @param SuggestionItemFactory $suggestionItemFactory
     * @param SuggestionGroupItemFactory $suggestionGroupItemFactory
     * @param QueryFactory $queryFactory
     * @param RequestFactory $autocompleteRequestFactory
     * @param RequestFactory $suggestionRequestFactory
     * @param RequestFactory $productSuggestionRequestFactory
     * @param Client $client
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CollectionFilter $collectionFilter
     * @param CategoryRepository $categoryRepository
     * @param Config $config
     * @param HttpRequest $request
     */
    public function __construct(
        ProductItemFactory $productItemFactory,
        SuggestionItemFactory $suggestionItemFactory,
        SuggestionGroupItemFactory $suggestionGroupItemFactory,
        QueryFactory $queryFactory,
        RequestFactory $autocompleteRequestFactory,
        RequestFactory $suggestionRequestFactory,
        RequestFactory $productSuggestionRequestFactory,
        Client $client,
        ProductCollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        CollectionFilter $collectionFilter,
        CategoryRepository $categoryRepository,
        Config $config,
        HttpRequest $request
    ) {
        $this->productItemFactory = $productItemFactory;
        $this->suggestionItemFactory = $suggestionItemFactory;
        $this->queryFactory = $queryFactory;
        $this->autocompleteRequestFactory = $autocompleteRequestFactory;
        $this->suggestionRequestFactory = $suggestionRequestFactory;
        $this->productSuggestionRequestFactory = $productSuggestionRequestFactory;
        $this->client = $client;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->collectionFilter = $collectionFilter;
        $this->categoryRepository = $categoryRepository;
        $this->config = $config;
        $this->request = $request;
        $this->suggestionGroupItemFactory = $suggestionGroupItemFactory;
    }

    /**
     * @param string|null $text
     */
    public function setQueryText(string $text = null)
    {
        $this->queryText = $text;
    }

    /**
     * @param int|null $categoryId
     */
    public function setCategoryId(int $categoryId = null)
    {
        $this->categoryId = $categoryId;
    }

    /**
     * @param bool $addMediaGalleryData
     */
    public function setAddMediaGalleryData(bool $addMediaGalleryData = true)
    {
        $this->addMediaGalleryData = $addMediaGalleryData;
    }

    /**
     * @return ItemInterface[]
     */
    public function getItems()
    {
        if (!$this->config->isSuggestionsAutocomplete()) {
            return $this->getAutocompleteItems();
        }

        return $this->getSuggestionItems();
    }

    /**
     * @return ItemInterface[]
     */
    protected function getAutocompleteItems()
    {
        $query = $this->getQuery();
        $config = $this->config;

        /** @var AutocompleteRequest $request */
        $request = $this->autocompleteRequestFactory->create();
        $request->addCategoryFilter($this->getCategory());
        $request->setGetProducts($config->isAutocompleteProductsEnabled());
        $request->setGetSuggestions($config->isAutocompleteSuggestionsEnabled());
        $request->setMaxResult($config->getAutocompleteMaxResults());
        $request->setSearch($query);

        /** @var AutocompleteResponse $response */
        $response = $this->client->request($request);

        $productResult = $this->getProductItems($response);
        $suggestionResult = $this->getSuggestionResult($response);

        return array_merge($suggestionResult, $productResult);
    }

    /**
     * @return ItemInterface[]
     */
    protected function getSuggestionItems()
    {
        $query = $this->getQuery();
        $category = $this->getCategory();

        $promises = [];
        if ($this->config->isAutocompleteProductsEnabled()) {
            /** @var Client\Request\Suggestions\ProductSuggestionsRequest $productSuggestionRequest */
            $productSuggestionsRequest = $this->productSuggestionRequestFactory->create();
            $productSuggestionsRequest->setSearch($query);
            $productSuggestionsRequest->addCategoryFilter($category);
            $promises['product_suggestions'] = $this->client->request(
                $productSuggestionsRequest,
                true
            );
        }

        if ($this->config->isAutocompleteSuggestionsEnabled()) {
            $suggestionsRequest = $this->suggestionRequestFactory->create();
            $suggestionsRequest->setSearch($query);
            $suggestionsRequest->addCategoryFilter($category);
            $promises['suggestions'] = $this->client->request(
                $suggestionsRequest,
                true
            );
        }

        if (empty($promises)) {
            return [];
        }

        $results = [];
        $responses = unwrap($promises);
        foreach ($responses as $key => $response) {
            if ($response instanceof AutocompleteProductResponseInterface) {
                $results[] = $this->getProductItems($response);
            }
            if ($response instanceof Client\Response\Suggestions\SuggestionsResponse) {
                $results[] = $this->getSuggestionGroups($response);
            }
        }

        return (!empty($results)) ? array_merge(...$results) : [];
    }

    /**
     * @return Query|mixed|string|null
     */
    protected function getQuery()
    {
        /** @var Query $query */
        $query = $this->queryFactory->get();
        $query = $this->queryText ?? $query->getQueryText();

        return $query;
    }

    /**
     * @return Category
     */
    protected function getCategory()
    {
        $categoryId = (int)($this->categoryId ?? $this->request->getParam('cid'));
        if ($categoryId && $this->config->isAutocompleteStayInCategory()) {
            try {
                return $this->categoryRepository->get($categoryId);
            } catch (NoSuchEntityException $e) {

            }
        }

        $store = $this->storeManager->getStore();
        $categoryId = $store->getRootCategoryId();
        return $this->categoryRepository->get($categoryId);
    }

    /**
     * @param AutocompleteProductResponseInterface $response
     * @return ItemInterface[]
     * @throws LocalizedException
     */
    protected function getProductItems(AutocompleteProductResponseInterface $response)
    {
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->setStore($this->storeManager->getStore());
        $productCollection->addAttributeToFilter('entity_id', ['in' => $response->getProductIds()]);
        $this->collectionFilter->filter($productCollection, $this->getCategory());

        if ($this->addMediaGalleryData) {
            $productCollection->addMediaGalleryData();
        }

        $result = [];
        foreach ($response->getProductIds() as $productId) {
            $product = $productCollection->getItemById($productId);
            if (!$product) {
                continue;
            }

            $result[] = $this->productItemFactory->create(['product' => $product]);
        }

        return $result;
    }

    /**
     * @param AutocompleteResponse $response
     * @return ItemInterface[]
     */
    protected function getSuggestionResult(AutocompleteResponse $response)
    {
        $result = [];
        foreach ($response->getSuggestions() as $suggestion) {
            $result[] = $this->suggestionItemFactory->create(['suggestion' => $suggestion]);
        }
        return $result;
    }

    /**
     * @param Client\Response\Suggestions\SuggestionsResponse $response
     * @return ItemInterface[]
     */
    protected function getSuggestionGroups(Client\Response\Suggestions\SuggestionsResponse $response)
    {
        $results = [];
        $groups = $response->getGroups() ?: [];
        foreach ($groups as $suggestionGroup) {
            $results[] = $this->suggestionGroupItemFactory->create(['group' => $suggestionGroup]);
        }

        return $results;
    }
}
