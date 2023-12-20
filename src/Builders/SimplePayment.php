<?php

namespace PTM\MollieInterface\Builders;

use Illuminate\Database\Eloquent\Model;
use Mollie\Api\Types\SequenceType;
use PTM\MollieInterface\Builders\Builder;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\traits\PaymentBuilder;

class SimplePayment extends Builder implements \PTM\MollieInterface\contracts\PaymentBuilder
{
    use PaymentBuilder;
    public function __construct(Model $owner, float $total, string $description, array $options = [])
    {
        parent::__construct();
        $this->owner = $owner;
        $this->total = $total;
        $this->options = $options;
        $this->description = $description;
        $this->taxPercentage = config('ptm_subscription.tax', 21);
        $this->sequenceType = SequenceType::SEQUENCETYPE_ONEOFF;
        $this->redirectUrl = url('');
        $urlOptions = $this->options['query'] ?? [];
        $this->webhookUrl = route('ptm_mollie.webhook.payment.standalone', $urlOptions);
    }

    /**
     * @param string $webhookUrl
     */
    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param String $cardToken
     */
    public function setCardToken(string $cardToken)
    {
        $this->cardToken = $cardToken;
        return $this;
    }

    /**
     * @param string $sequenceType
     */
    public function setSequenceType(string $sequenceType): void
    {
        $this->sequenceType = $sequenceType;
    }

    public function create()
    {
        if (!$this->owner->mollieCustomer || !$this->owner->mollieCustomer->mollie_mandate_id) {
            throw new \Exception("Mollie customer doesn't have mandateID!");
        }
        // Create mollie payment
        $this->processorPayment = $this->getInterface()->createPayment($this);
        // Connect payment to the subscription
        $payment = Payment::make($this->getPaymentPayload());
        // Return payment object
        return $payment;
    }

    public function setTax(int $tax)
    {
        $base_price = ($this->total / (100 + $this->taxPercentage)) * 100;
        if ($tax === 0){
            $this->taxPercentage = 0;
            $this->total = $base_price;
            return $this;
        }
        $this->taxPercentage = ($tax > 1 ? $tax : ($tax > 0 ? ($tax * 100) : 0));
        $this->total = $base_price * (1 + ($this->taxPercentage / 100));
        return $this;
    }

    public function setRedirectURL(string $url){
        $this->redirectUrl = $url;
        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }

    public function setOrderID(string $id): void
    {
        $this->order_id = $id;
    }
}