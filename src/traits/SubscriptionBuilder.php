<?php

namespace PTM\MollieInterface\traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use PTM\MollieInterface\models\SubscriptionInterval;

trait SubscriptionBuilder
{
    use SerializesModels;
    /**
     * The billable model.
     *
     * @var Model
     */
    protected $owner;
    /**
     * @var integer|null
     */
    protected $subscriptionID;
    /**
     * @var string
     */
    protected $webhookUrl;
    /**
     * Force confirmation payment or just create.
     * Only works with subscriptionBuiolders
     * @var bool
     */
    protected $forceConfirmationPayment = false;
    /**
     * @var SubscriptionInterval
     */
    protected $interval;
}