<?php

namespace PTM\MollieInterface\models;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PTM\MollieInterface\Builders\OrderBuilder;

class Order extends Model
{
    use HasFactory;
    protected $table = 'ptm_orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'actions',
        'executed'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->id = Str::uuid();
    }
    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }
    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }

    protected $casts = [
        'actions' => 'array',
    ];

    /**
     * The parent model of this order.
     * Most of the time this will be the billable like a use or company.
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function billable(){
        return $this->morphTo('billable');
    }

    /**
     * The model that triggered this order to be completed, might be empty.
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function confirmatable(){
        return $this->morphTo('confirmatable');
    }

    /**
     * Add an executable laravel job that will be run after the order is completed.
     * @param ShouldQueue $job
     * @return void
     */
    public function addAction(ShouldQueue $job){
        $this->actions[] = ['class'=>$job->getMorphClass(),'context'=>$job->__serialize()];
    }

    /**
     * Set the array of executable laravel jobs that are serializable.
     * @param array|null $actions
     * @return void
     */
    public function setActions(?array $actions){
        $this->actions = $actions;
    }

    /**
     * Get a OrderBuilder instance.
     * @return OrderBuilder
     */
    public static function Builder(){
        return new OrderBuilder();
    }
}