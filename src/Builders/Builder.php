<?php

namespace PTM\MollieInterface\Builders;

use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\PTMFacade;
use ReflectionClass;

class Builder
{
    private PaymentProcessor $interface;

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function __construct($loadDefault=false)
    {
        if (!$loadDefault) {
            $this->interface = PTMFacade::getInterface();
            return;
        }
        $defaultClass = config("ptm_subscription.default_processor");
        $this->interface = $this->importInterface($defaultClass);
    }

    /**
     * @throws \ReflectionException
     */
    public function importInterface($class): object
    {
        $ref = new ReflectionClass($class);
        if (!$ref->implementsInterface(PaymentProcessor::class))
            throw new \Exception("Expected PaymentProcessor, but was provided a different class as default processor.");
        return $ref->newInstance();
    }

    /**
     * @return PaymentProcessor
     */
    public function getProcessor(): PaymentProcessor
    {
        return $this->getInterface();
    }

    /**
     * @return PaymentProcessor
     */
    public function getInterface(): PaymentProcessor
    {
        return $this->interface;
    }

    /**
     * @return string
     */
    public function exportInterface(){
        return $this->interface::class;
    }

    /**
     * @param string|PaymentProcessor $processor
     * @return void
     * @throws \ReflectionException
     */
    public function setInterface(string|PaymentProcessor $processor): void
    {
        if (is_string($processor)){
            $this->interface = $this->importInterface($processor);
        } else {
            $this->interface = $processor;
        }
    }
}