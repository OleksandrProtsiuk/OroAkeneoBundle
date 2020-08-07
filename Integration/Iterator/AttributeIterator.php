<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoPimExtendableClientInterface;
use Psr\Log\LoggerInterface;

class AttributeIterator extends AbstractIterator
{
    /**
     * @var array
     */
    private $attributesFilter = [];

    /**
     * AttributeIterator constructor.
     *
     * @param array $attributesFilter
     */
    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimExtendableClientInterface $client,
        LoggerInterface $logger,
        $attributesFilter = []
    ) {
        $this->attributesFilter = $attributesFilter;

        parent::__construct($resourceCursor, $client, $logger);
    }

    const OPTION_TYPES = [
        'pim_catalog_simpleselect',
        'pim_catalog_multiselect',
    ];

    const REFERENCE_ENTITY_TYPES = [
        'akeneo_reference_entity',
        'akeneo_reference_entity_collection',
    ];

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $attribute = $this->filter();

        if (null === $attribute) {
            return null;
        }

        $this->setOptions($attribute);
        $this->setReferenceEntityRecords($attribute);

        return $attribute;
    }

    /**
     * Get attribute options from API.
     */
    private function setOptions(array &$attribute)
    {
        if (!in_array($attribute['type'], self::OPTION_TYPES)) {
            return;
        }

        $attribute['options'] = [];

        foreach ($this->client->getAttributeOptionApi()->all($attribute['code'], self::PAGE_SIZE) as $option) {
            $attribute['options'][] = $option;
        }

        usort(
            $attribute['options'],
            function ($a, $b) {
                if ($a['sort_order'] == $b['sort_order']) {
                    return 0;
                }

                return ($a['sort_order'] < $b['sort_order']) ? -1 : 1;
            }
        );
    }

    /**
     * Get Reference Entity records as options from API.
     */
    private function setReferenceEntityRecords(array &$attribute): void
    {
        if (!in_array($attribute['type'], self::REFERENCE_ENTITY_TYPES)) {
            return;
        }

        $attribute['options'] = [];

        $records = $this->client->getReferenceEntityRecordApi()->all($attribute['reference_data_name']);

        foreach ($records as $record) {
            $labels = [];
            foreach (($record['values']['label'] ?? []) as $label) {
                $labels[$label['locale']] = $label['data'];
            }

            if (!$labels) {
                continue;
            }

            $attribute['options'][] = [
                'code' => $record['code'],
                'labels' => $labels,
            ];
        }
    }

    /**
     * @return array|null
     */
    private function filter()
    {
        do {
            $attribute = $this->resourceCursor->current();

            if (!empty($this->attributesFilter) && !in_array($attribute['code'], $this->attributesFilter)) {
                $this->next();

                if (!$this->valid()) {
                    return null;
                }
            } else {
                break;
            }
        } while ($this->valid());

        return $attribute;
    }
}
