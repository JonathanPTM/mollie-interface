<?php
/*
 *  mollie_interface - webhook.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
 *  The software is protected by copyright laws and international copyright treaties,
 *  as well as other intellectual property laws and treaties. The software is licensed, not sold.
 *  You are not allowed to resell, claim ownership of, or modify the software in any way.
 *  You are also prohibited from distributing the software to any other entities, even for educational purposes.
 *  Any violation of this agreement will result in legal action being taken against you. The original developer,
 *  PTMDevelopment, may fine you up to 5000 euros per file and per week until the changes,
 *  claims, or distribution is reversed.
 *  PTMDevelopment reserves the right to add additional compensation at any later date if deemed necessary.
 *
 *  By using or installing the software, you automatically agree to the terms of this agreement.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

use Illuminate\Support\Facades\Route;

Route::prefix('/landing')->name('ptm_mollie.')->group(function (){
    Route::prefix('/redirect')->name('redirect.')->group(function (){
        Route::get('/{id}/subscription', [\PTM\MollieInterface\Http\Controllers\RedirectController::class, 'subscribable'])->name('subscription');
    });
    Route::prefix('/webhook')->name('webhook.')->group(function (){
        Route::prefix('/payment')->name('payment.')->group(function (){
            Route::any('/first', [\PTM\MollieInterface\Http\Controllers\WebHooks\FirstPaymentHookController::class, 'hooked'])->name('first');
            Route::any('/subscription', [\PTM\MollieInterface\Http\Controllers\WebHooks\SubscriptionController::class, 'hooked'])->name('subscription');
            Route::any('/subscription/merged', [\PTM\MollieInterface\Http\Controllers\WebHooks\SubscriptionController::class, 'merged'])->name('subscription.merged');
            Route::any('/merge', [\PTM\MollieInterface\Http\Controllers\WebHooks\MergeController::class, 'hooked'])->name('merge');
            Route::any('/standalone', [\PTM\MollieInterface\Http\Controllers\WebHooks\PaymentController::class, 'hooked'])->name('standalone');
            Route::any('/', [\PTM\MollieInterface\Http\Controllers\WebHooks\AftercareController::class, 'hooked'])->name('after');
        });
    });
});