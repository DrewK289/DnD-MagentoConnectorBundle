<?php

namespace DnD\Bundle\MagentoConnectorBundle\Reader\ORM;

use Entity\Repository\CategoryRepository;
use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\Reader;
use Pim\Bundle\UserBundle\Context\UserContext;

/**
 *
 * @author    DnD Mimosa <mimosa@dnd.fr>
 * @copyright Agence Dn'D (http://www.dnd.fr)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryReader extends Reader
{
    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var UserContext
     */
    protected $userContext;

    /** @var array */
    protected $excludedCategories;

    /**
     * @param CategoryRepository $categoryRepository
     * @param UserContext $userContext
     */
    public function __construct(CategoryRepository $categoryRepository, UserContext $userContext)
    {
        $this->categoryRepository = $categoryRepository;
        $this->userContext = $userContext;
    }

    /**
     * Get Excluded categories
     *
     * @return array
     */
    public function getExcludedCategories()
    {
        return $this->excludedCategories;
    }

    /**
     * Set Excluded categories
     *
     * @param array $excludedCategories
     *
     * @return CategoryReader
     */
    public function setExcludedCategories($excludedCategories)
    {
        $this->excludedCategories = $excludedCategories;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        if (!$this->query) {
            $qb = $this->categoryRepository->createQueryBuilder('c');
            if ($this->getExcludedCategories()) {
                $categoryIds = [];

                foreach ($this->getExcludedCategories() as $categoryId) {
                    $categoryIds[] = $categoryId;

                    if ($children = $this->getCategoryChildren($categoryId)) {
                        $categoryIds = array_merge($categoryIds, $children);
                    }
                }

                $qb->where(
                    $qb->expr()->orX(
                        $qb->expr()->notIn('c.id', $categoryIds)
                    )
                );
            }
            $qb->orderBy('c.root')
                ->addOrderBy('c.left');
            $this->query = $qb->getQuery();
        }

        return $this->query;
    }

    /**
     * Get all children of a category by its id
     *
     * @param $categoryId
     * @return array
     */
    protected function getCategoryChildren($categoryId)
    {
        $children = [];

        $qb = $this->categoryRepository->createQueryBuilder('c');
        $qb->select('c.id')
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->eq('c.parent', ':parent')
                )
            )
            ->setParameter('parent', $categoryId);

        $results = $qb->getQuery()->getResult();

        foreach ($results as $result) {
            $children[] = $result['id'];

            if ($subChildren = $this->getCategoryChildren($result['id'])) {
                $children = array_merge($children, $subChildren);
            }
        }

        return $children;
    }

    protected function getRootCategoryChoices()
    {
        $options = [];

        $qb = $this->categoryRepository->createQueryBuilder('c');
        $qb->select()->where($qb->expr()->eq('c.id', 'c.root'));

        /** @var \Pim\Bundle\CatalogBundle\Entity\Category[] $categories */
        $categories = $qb->getQuery()->getResult();

        foreach ($categories as $category) {
            $options[$category->getId()] = $category->setLocale($this->userContext->getCurrentLocaleCode())->getLabel();
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return [
            'excludedCategories' => [
                'type' => 'choice',
                'options' => [
                    'choices' => $this->getRootCategoryChoices(),
                    'required' => false,
                    'multiple' => true,
                    'select2' => true,
                    'label' => 'dnd_magento_connector.export.excludedCategories.label',
                    'help' => 'dnd_magento_connector.export.excludedCategories.help'
                ]
            ],
        ];
    }
}
