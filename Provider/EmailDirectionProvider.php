<?php

namespace OroCRM\Bundle\ActivityContactBundle\Provider;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\ActivityBundle\EntityConfig\ActivityScope;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Model\EmailHolderInterface;

use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use OroCRM\Bundle\ActivityContactBundle\Direction\DirectionProviderInterface;

class EmailDirectionProvider implements DirectionProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getSupportedClass()
    {
        return 'Oro\Bundle\EmailBundle\Entity\Email';
    }

    /**
     * {@inheritdoc}
     */
    public function getDirection($activity, $target)
    {
        /** @var $activity Email */
        /** @var $target EmailHolderInterface */
        if ($activity->getFromEmailAddress()->getEmail() === $target->getEmail()) {
            return DirectionProviderInterface::DIRECTION_OUTGOING;
        }

        return DirectionProviderInterface::DIRECTION_INCOMING;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate($activity)
    {
        /** @var $activity Email */
        return $activity->getSentAt() ?: new \DateTime('now', new \DateTimeZone('UTC'));
    }

    public function getLastActivitiesDateForTarget(EntityManager $em, $target, $skipId, $direction)
    {
        $result = [];
        $resultActivity = $this->getLastActivity($em, $target, $skipId);
        if ($resultActivity) {
            $result['all'] = $this->getDate($resultActivity);
            if ($this->getDirection($resultActivity, $target) !== $direction) {
                $resultActivity = $this->getLastActivity($em, $target, $skipId, $direction);
                if ($resultActivity) {
                    $result['direction'] = $this->getDate($resultActivity);
                } else {
                    $result['direction'] = null;
                }
            } else {
                $result['direction'] = $result['all'];
            }
        }

        return $result;
    }

    protected function getLastActivity(EntityManager $em, $target, $skipId, $direction = null)
    {
        $qb = $em->getRepository('Oro\Bundle\EmailBundle\Entity\Email')
            ->createQueryBuilder('email')
            ->select('email')
            ->innerJoin(
                sprintf(
                    'email.%s',
                    ExtendHelper::buildAssociationName(ClassUtils::getClass($target), ActivityScope::ASSOCIATION_KIND)
                ),
                'target'
            )
            ->andWhere('target = :target')
            ->andWhere('email.id <> :skipId')
            ->orderBy('email.sentAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('target', $target)
            ->setParameter('skipId', $skipId);

        if ($direction) {
            $operator = '!=';
            if ($direction === DirectionProviderInterface::DIRECTION_OUTGOING) {
                $operator = '=';
            }
            $qb->join('email.fromEmailAddress', 'fromEmailAddress')
                ->andWhere('fromEmailAddress.email ' . $operator . ':email')
                ->setParameter('email', $target->getEmail());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
