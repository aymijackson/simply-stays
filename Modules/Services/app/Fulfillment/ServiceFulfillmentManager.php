<?php

namespace Modules\Services\Fulfillment;

use Illuminate\Support\Manager;
use Modules\Services\Contracts\ServiceFulfillmentHandler;

class ServiceFulfillmentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'other';
    }

    public function driver($driver = null): ServiceFulfillmentHandler
    {
        return parent::driver($driver);
    }

    protected function createLaundryDriver(): LaundryFulfillmentHandler
    {
        return new LaundryFulfillmentHandler;
    }

    protected function createLogisticsDriver(): LogisticsFulfillmentHandler
    {
        return new LogisticsFulfillmentHandler;
    }

    protected function createOtherDriver(): OtherFulfillmentHandler
    {
        return new OtherFulfillmentHandler;
    }

    protected function createFoodDriver(): FoodFulfillmentHandler
    {
        return new FoodFulfillmentHandler;
    }
}
