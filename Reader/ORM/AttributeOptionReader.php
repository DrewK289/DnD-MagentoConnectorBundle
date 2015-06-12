<?php

namespace DnD\Bundle\MagentoConnectorBundle\Reader\ORM;

use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\Reader;
use Pim\Bundle\CatalogBundle\Entity\Repository\AttributeOptionRepository;
use Pim\Bundle\CatalogBundle\Entity\Repository\AttributeRepository;
use Pim\Bundle\UserBundle\Context\UserContext;

/**
 * ORM Reader for simple entities without query join
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeOptionReader extends Reader
{
    /** @var AttributeOptionRepository */
    protected $attributeOptionRepository;

    /** @var AttributeRepository */
    protected $attributeRepository;

    /** @var UserContext */
    protected $userContext;

    /** @var array */
    protected $excludedAttributes;

    /**
     * @param AttributeOptionRepository $attributeOptionRepository
     * @param AttributeRepository $attributeRepository
     * @param UserContext $userContext
     */
    public function __construct(AttributeOptionRepository $attributeOptionRepository, AttributeRepository $attributeRepository, UserContext $userContext)
    {
        $this->attributeOptionRepository = $attributeOptionRepository;
        $this->attributeRepository = $attributeRepository;
        $this->userContext = $userContext;
    }

    /**
     * Get Excluded attributes
     *
     * @return array
     */
    public function getExcludedAttributes()
    {
        return $this->excludedAttributes;
    }

    /**
     * Set Excluded attributes
     *
     * @param array $excludedAttributes
     * @return AttributeOptionReader
     */
    public function setExcludedAttributes($excludedAttributes)
    {
        $this->excludedAttributes = $excludedAttributes;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        if (!$this->query) {
            $qb = $this->attributeOptionRepository->createQueryBuilder('o');

            if ($this->getExcludedAttributes()) {
                $qb->join('o.attribute', 'a')
                    ->where($qb->expr()->notIn('a.id', $this->getExcludedAttributes()));
            }

            $qb->orderBy('o.sortOrder');

            $this->query = $qb->getQuery();
        }

        return $this->query;
    }

    protected function getAttributeOptions()
    {
        $options = [];

        /** @var \Pim\Bundle\CatalogBundle\Entity\Attribute $attribute */
        foreach ($this->attributeRepository->findAll() as $attribute) {
            $options[$attribute->getId()] = $attribute->setLocale($this->userContext->getCurrentLocaleCode())->getLabel();
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return [
            'excludedAttributes' => [
                'type' => 'choice',
                'options' => [
                    'choices' => $this->getAttributeOptions(),
                    'required' => false,
                    'multiple' => true,
                    'select2' => true,
                    'label' => 'dnd_magento_connector.export.excludedAttributes.label',
                    'help' => 'dnd_magento_connector.export.excludedAttributes.help'
                ]
            ],
        ];
    }
}
