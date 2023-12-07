<?php

namespace PTM\MollieInterface\Http\Controllers\WebHooks\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PTM\MollieInterface\Http\Controllers\WebHooks\WebhookController;

class OrderPaymentController  extends WebhookController
{
    public function hooked(Request $request)
    {
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment) {
            return new Response(null, 404);
        }
    }
}