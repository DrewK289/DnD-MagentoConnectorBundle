<?php

namespace DnD\Bundle\MagentoConnectorBundle\Reader\Doctrine;

use Pim\Bundle\CatalogBundle\Version;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Item\AbstractConfigurableStepElement;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\BaseConnectorBundle\Reader\ProductReaderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;
use Pim\Bundle\BaseConnectorBundle\Validator\Constraints\Channel as ChannelConstraint;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;

/**
 *
 * @author    DnD Mimosa <mimosa@dnd.fr>
 * @copyright Agence Dn'D (http://www.dnd.fr)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ORMProductReader extends AbstractConfigurableStepElement implements ProductReaderInterface
{
    /**
     * @var integer
     */
    protected $limit = 10;

    /**
     * @var string
     *
     * @Assert\NotBlank(groups={"Execution"})
     * @ChannelConstraint
     */
    protected $channel;

    /**
     * @var ChannelManager
     */
    protected $channelManager;

    /**
     * @var integer
     */
    protected $offset = 0;

    /**
     * @var null|integer[]
     */
    protected $ids = null;

    /**
     * @var ArrayIterator
     */
    protected $products;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ProductRepositoryInterface
     */
    protected $repository;

    /**
     * @var CompletenessManager
     */
    protected $completenessManager;

    /**
     * @var MetricConverter
     */
    protected $metricConverter;

    /**
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * @var boolean
     */
    protected $missingCompleteness;

    /**
     * @var date
     */
    protected $exportFrom = "1970-01-01 01:00:00";

    /**
     * @var boolean
     */
    protected $isEnabled = true;

    /**
     * @var boolean
     */
    protected $isComplete = true;

    /**
     * get exportFrom
     *
     * @return string exportFrom
     */
    public function getExportFrom()
    {
        return $this->exportFrom;
    }

    /**
     * Set exportFrom
     *
     * @param string $exportFrom exportFrom
     *
     * @return AbstractProcessor
     */
    public function setExportFrom($exportFrom)
    {
        $this->exportFrom = $exportFrom;

        return $this;
    }

    /**
     * get isEnabled
     *
     * @return boolean isEnabled
     */
    public function getIsEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Set isEnabled
     *
     * @param string isEnabled $isEnabled
     *
     * @return AbstractProcessor
     */
    public function setIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    /**
     * get isComplete
     *
     * @return boolean isComplete
     */
    public function getIsComplete()
    {
        return $this->isComplete;
    }

    /**
     * Set isComplete
     *
     * @param string isComplete $isComplete
     *
     * @return AbstractProcessor
     */
    public function setIsComplete($isComplete)
    {
        $this->isComplete = $isComplete;

        return $this;
    }

    /**
     * @param ProductRepositoryInterface $repository
     * @param ChannelManager             $channelManager
     * @param CompletenessManager        $completenessManager
     * @param MetricConverter            $metricConverter
     * @param EntityManager              $entityManager
     * @param boolean                    $missingCompleteness
     */
    public function __construct(
        ProductRepositoryInterface $repository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        EntityManager $entityManager,
        $missingCompleteness = true
    ) {
        $this->entityManager       = $entityManager;
        $this->repository          = $repository;
        $this->channelManager      = $channelManager;
        $this->completenessManager = $completenessManager;
        $this->metricConverter     = $metricConverter;
        $this->products            = new \ArrayIterator();
        $this->missingCompleteness = $missingCompleteness;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        $product = null;

        if (!$this->products->valid()) {
            $this->products = $this->getNextProducts();
        }

        if (null !== $this->products) {
            $product = $this->products->current();
            $this->products->next();
            $this->stepExecution->incrementSummaryInfo('read');
        }

        if (null !== $product) {
            $this->metricConverter->convert($product, $this->channel);
        }

        return $product;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array(
            'channel' => array(
                'type'    => 'choice',
                'options' => array(
                    'choices'  => $this->channelManager->getChannelChoices(),
                    'required' => true,
                    'select2'  => true,
                    'label'    => 'pim_base_connector.export.channel.label',
                    'help'     => 'pim_base_connector.export.channel.help'
                )
            ),
            'exportFrom' => array(
                'required' => false,
                'options' => array(
                    'help'    => 'dnd_magento_connector.export.exportFrom.help',
                    'label'   => 'dnd_magento_connector.export.exportFrom.label',
                )
            ),
            'isEnabled' => array(
                'type'    => 'switch',
                'required' => false,
                'options' => array(
                    'help'    => 'dnd_magento_connector.export.isEnabled.help',
                    'label'   => 'dnd_magento_connector.export.isEnabled.label',
                )
            ),
            'isComplete' => array(
                'type'    => 'switch',
                'required' => false,
                'options' => array(
                    'help'    => 'dnd_magento_connector.export.isComplete.help',
                    'label'   => 'dnd_magento_connector.export.isComplete.label',
                )
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->entityManager->clear();
        $this->ids = null;
        $this->offset = 0;
        $this->products = new \ArrayIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * @param integer $limit
     *
     * @return ORMProductReader
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get ids of products which are completes and in channel
     *
     * @return array
     */
    protected function getIds()
    {
        if (!is_object($this->channel)) {
            $this->channel = $this->channelManager->getChannelByCode($this->channel);
        }

        if ($this->missingCompleteness) {
            $this->completenessManager->generateMissingForChannel($this->channel);
        }

        $qb = $this->DnDBuildByChannelAndCompleteness($this->channel, $this->getIsComplete());

        $rootAlias = current($qb->getRootAliases());
        $rootIdExpr = sprintf('%s.id', $rootAlias);

        $from = current($qb->getDQLPart('from'));

        $qb->select($rootIdExpr)
            ->resetDQLPart('from')
            ->from($from->getFrom(), $from->getAlias(), $rootIdExpr)
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->gte($from->getAlias() . '.updated', ':updated')
                )
            )
            ->setParameter('updated', $this->getDateFilter())
            ->setParameter('enabled', $this->getIsEnabled())
            ->groupBy($rootIdExpr);

        return array_keys($qb->getQuery()->getResult());
    }

    /**
     * Get product collection by channel and completness
     */
    protected function DnDBuildByChannelAndCompleteness($channel, $isComplete){
        $scope = $channel->getCode();

        if (version_compare(Version::VERSION, '1.3.0', '<')) {
            $qb = $this->repository->buildByScope($scope);

            $rootAlias = $qb->getRootAlias();

            $complete = ($isComplete) ? $qb->expr()->eq('pCompleteness.ratio', '100') : $qb->expr()->lt('pCompleteness.ratio', '100');
            $expression =
                'pCompleteness.product = '.$rootAlias.' AND '.
                $complete.' AND '.
                $qb->expr()->eq('pCompleteness.channel', $channel->getId());

            $rootEntity          = current($qb->getRootEntities());
            $completenessMapping = $this->entityManager->getClassMetadata($rootEntity)
                ->getAssociationMapping('completenesses');
            $completenessClass   = $completenessMapping['targetEntity'];
            $qb->innerJoin(
                $completenessClass,
                'pCompleteness',
                'WITH',
                $expression
            );

            $treeId = $channel->getCategory()->getId();
            $expression = $qb->expr()->eq('pCategory.root', $treeId);
            $qb->innerJoin(
                $rootAlias.'.categories',
                'pCategory',
                'WITH',
                $expression
            );
        } else {
            $qb = $this->repository->buildByChannelAndCompleteness($channel);
            // TODO Fix with isComplete at false
        }


        return $qb;
    }

    /**
     * Get the date use to filter the product collection
     *
     * @return \DateTime
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getDateFilter()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from('Akeneo\Bundle\BatchBundle\Entity\JobExecution', 'e')
            ->join('e.jobInstance', 'j')
            ->where(
                $qb->expr()->eq('j.id', ':id'),
                $qb->expr()->eq('e.exitCode', ':exitCode')
            )
            ->addOrderBy('j.id', 'desc')
            ->setMaxResults(1)
            ->setParameter('id', $this->stepExecution->getJobExecution()->getJobInstance()->getId())
            ->setParameter('exitCode', 'COMPLETED');

        /** @var \Akeneo\Bundle\BatchBundle\Entity\JobExecution $lastJobExecution */
        $lastJobExecution = $qb->getQuery()->getOneOrNullResult();

        $date = $lastJobExecution ? $lastJobExecution->getStartTime() : new \DateTime('1970-01-01 01:00:00');

        return $this->getExportFrom() ? new \DateTime($this->getExportFrom()) : $date;
    }

    /**
     * Get next products batch from DB
     *
     * @return \ArrayIterator
     */
    protected function getNextProducts()
    {
        $this->entityManager->clear();
        $products = null;

        if (null === $this->ids) {
            $this->ids = $this->getIds();
        }

        $currentIds = array_slice($this->ids, $this->offset, $this->limit);

        if (!empty($currentIds)) {
            $items = $this->repository->findByIds($currentIds);
            $products = new \ArrayIterator($items);
            $this->offset += $this->limit;
        }

        return $products;
    }
}
