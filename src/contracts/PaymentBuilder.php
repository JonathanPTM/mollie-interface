<?php

namespace PTM\MollieInterface\contracts;

interface PaymentBuilder
{
    /**
     * Build the Mollie Payment Payload
     *
     * @return array
     */
    public function getMolliePayload(): array;
    /**
     * Build the payment column values
     *
     * @return null
     */
    public function getPaymentPayload();

    public function setWebhookUrl(string $webhookUrl): void;
    public function getMolliePayment(): ?\Mollie\Api\Resources\Payment;
    public function redirect(): ?string;
}