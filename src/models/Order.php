<?php

namespace PTM\MollieInterface\models;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PTM\MollieInterface\Builders\Builder;
use PTM\MollieInterface\Builders\OrderBuilder;
use PTM\MollieInterface\Events\OrderConfirmed;
use ReflectionClass;

class Order extends Model
{
    use HasFactory;
    protected $table = 'ptm_orders';

    private Builder $processor;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'interface',
        'actions',
        'executed'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->id = Str::uuid();
        $this->processor = new Builder();
        if ($this->interface !== null){
            $this->processor->setInterface($this->interface);
        }
    }

    /**
     * @return \PTM\MollieInterface\contracts\PaymentProcessor
     */
    public function getInterface(){
        return $this->processor->getInterface();
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
        $actions = (array)$this->actions;
        $actions[] = ['class'=>$job::class,'context'=>serialize($job)];
        $this->setActions($actions);
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
     * Get an array of the actions.
     * @return array
     * @throws \ReflectionException
     */
    public function getActions(){
        $actions = (array)$this->actions;
        if (count($actions) < 1) return [];
        $unserialized = [];
        foreach ($actions as $action){
            $job = unserialize($action['context']);
            $unserialized[] = $job;
        }
        return $unserialized;
    }

    /**
     * @return bool
     * @throws \ReflectionException
     */
    public function executeActions($force=false): bool
    {
        if ($this->executed && !$force) return false;
        foreach ($this->getActions() as $job){
            $job->handle($this);
        }
        $this->update([
            'executed'=>true
        ]);
        return true;
    }

    /**
     * @param Model $confirmedBy
     * @return void
     */
    public function confirm(Model $confirmedBy){
        $this->confirmatable()->associate($confirmedBy);
        $this->save();
        $this->executeActions();
        // Trigger logic.
        Event::dispatch(new OrderConfirmed($this));
    }

    /**
     * Get a OrderBuilder instance.
     * @return OrderBuilder
     */
    public static function Builder(){
        return new OrderBuilder();
    }
}