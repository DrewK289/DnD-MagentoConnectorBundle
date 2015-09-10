<?php

namespace DnD\Bundle\MagentoConnectorBundle\Reader\ORM;

use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\Reader;
use Pim\Bundle\CatalogBundle\Entity\Repository\AttributeRepository;
use Pim\Bundle\UserBundle\Context\UserContext;

/**
 *
 * @author    DnD Mimosa <mimosa@dnd.fr>
 * @copyright Agence Dn'D (http://www.dnd.fr)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeReader extends Reader
{
    /** @var AttributeRepository */
    protected $attributeRepository;

    /** @var UserContext */
    protected $userContext;

    /** @var array */
    protected $excludedAttributes;

    /**
     * @param AttributeRepository $attributeRepository
     * @param UserContext $userContext
     */
    public function __construct(AttributeRepository $attributeRepository, UserContext $userContext)
    {
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
            $qb = $this->attributeRepository->createQueryBuilder('a');

            if ($this->getExcludedAttributes()) {
                $qb->where(
                    $qb->expr()->orX(
                        $qb->expr()->notIn('a.id', $this->getExcludedAttributes())
                    )
                );
            }
            $this->query = $qb->getQuery();
        }
        return $this->query;
    }

    public function setQuery($query)
    {
        if (!is_a($query, 'Doctrine\ORM\AbstractQuery', true) && !is_a($query, 'Doctrine\MongoDB\Query\Query', true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '$query must be either a Doctrine\ORM\AbstractQuery or ' .
                    'a Doctrine\ODM\MongoDB\Query\Query instance, got "%s"',
                    is_object($query) ? get_class($query) : $query
                )
            );
        }
        $this->query = $query;
    }

    protected function getAttributeOptions()
    {
        $options = [];

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
                    'label'    => 'dnd_magento_connector.export.excludedAttributes.label',
                    'help'     => 'dnd_magento_connector.export.excludedAttributes.help'
                ]
            ],
        ];
    }
}
