<?php

namespace PTM\MollieInterface\contracts;

use Illuminate\Database\Eloquent\Model;
use PTM\MollieInterface\models\Redirect;

interface PaymentBuilder
{
    /**
     * Build the payment column values
     *
     * @return null
     */
    public function getPaymentPayload();
    public function setOrderID(string $id): void;
    public function setWebhookUrl(string $webhookUrl): void;
    public function setPaymentable(?Model $paymentable): void;
    public function getProcessorPayment(): ?\Mollie\Api\Resources\Payment;
    public function redirect(): ?Redirect;
}