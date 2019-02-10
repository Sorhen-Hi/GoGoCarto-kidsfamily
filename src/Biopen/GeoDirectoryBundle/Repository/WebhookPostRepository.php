<?php

namespace Biopen\GeoDirectoryBundle\Repository;

use Biopen\GeoDirectoryBundle\Document\WebhookPost;
use Doctrine\ODM\MongoDB\DocumentRepository;

class WebhookPostRepository extends DocumentRepository
{
    protected $numAttempts = 5;

    /**
     * Return queued webhook posts which are new or which have previously failed.
     * We try up to 5 times, with an interval of 5m, 25m, 2h, 10h, 2d between each retry.
     * After 5 attempts, the webhook post will stay in the database but will not be retried.
     *
     * @param int $limit
     * @return mixed
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function findPendings($limit = null)
    {
        $qb = $this->createQueryBuilder(WebhookPost::class)
            ->field('numAttempts')->equals(0);

        /*for( $i=1; $i<=$this->numAttempts; $i++ ) {
            // After first try, wait 5m, 25m, 2h, 10h, 2d
            $intervalInMinutes = pow(5, $i);
            $interval = new \DateInterval("P{$intervalInMinutes}M");

            $qb->addOr(
                $qb->expr()
                    ->field('numAttempts')->equals($i)
                    ->field('latestAttemptAt')->lt((new \DateTime())->sub($interval))
            );
        }*/

        return $qb
            ->limit($limit)
            ->getQuery()
            ->execute();
    }
}