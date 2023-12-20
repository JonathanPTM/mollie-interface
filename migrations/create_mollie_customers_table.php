<?php
/*
 *  mollie_interface - mollie_customers.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePtmMollieCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ptm_customers', function (Blueprint $table) {
            $table->morphs('billable');
            $table->text('interface')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('mandate_id')->nullable();
            $table->boolean('merged')->default(0);
            $table->json('subscriptions')->default('[]');
            $table->dateTime('trial_ends_at')->nullable();
            $table->text('extra_billing_information')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ptm_customers');
    }
}


