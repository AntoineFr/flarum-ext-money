<?php namespace AntoineFr\Money\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Api\Event\Serializing;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;

class LoadSettingsFromDatabase
{
    protected $settings;
    
    public function __construct(SettingsRepositoryInterface $settings) {
        $this->settings = $settings;
    }
    
    public function subscribe(Dispatcher $events) {
        $events->listen(Serializing::class, [$this, 'prepareApiAttributes']);
    }
    
    public function prepareApiAttributes(Serializing $event) {
        if ($event->isSerializer(ForumSerializer::class)) {
            $event->attributes['antoinefr-money.moneyname'] = $this->settings->get('antoinefr-money.moneyname');
            $event->attributes['antoinefr-money.moneyforpost'] = $this->settings->get('antoinefr-money.moneyforpost');
            $event->attributes['antoinefr-money.moneyfordiscussion'] = $this->settings->get('antoinefr-money.moneyfordiscussion');
            $event->attributes['antoinefr-money.postminimumlength'] = $this->settings->get('antoinefr-money.postminimumlength');
            $event->attributes['antoinefr-money.noshowzero'] = (bool) $this->settings->get('antoinefr-money.noshowzero');
        }
        if ($event->isSerializer(UserSerializer::class)) {
            $canEditMoney = $event->actor->can('edit_money', $event->model);
            $event->attributes['money'] = $event->model->money;
            $event->attributes['canEditMoney'] = $canEditMoney;
        }
    }
}
