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

    public function billable(){
        return $this->morphTo('billable');
    }

    public function confirmatable(){
        return $this->morphTo('confirmatable');
    }

    /**
     * @param ShouldQueue $job
     * @return void
     */
    public function addAction(ShouldQueue $job){
        $this->actions[] = ['class'=>$job->getMorphClass(),'context'=>$job->__serialize()];
    }

    /**
     * @param array|null $actions
     * @return void
     */
    public function setActions(?array $actions){
        $this->actions = $actions;
    }

    public static function Builder(){
        return new OrderBuilder();
    }
}