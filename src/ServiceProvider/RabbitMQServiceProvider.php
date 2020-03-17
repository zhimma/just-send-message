<?php

namespace Zhimma\JustSendMessage\ServiceProvider;

use Zhimma\JustSendMessage\RabbitMQ\RabbitMQClient;
use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class RabbitMQServiceProvider extends ServiceProvider
{

    public function boot()
    {
    }
    public function register()
    {
        if(config("rabbitmq")){
            throw new \Exception("缺少必要配置【rabbitmq.php】");
        }
        //使用singleton绑定单例
        $this->app->singleton('rabbitmq',function(){
            return new RabbitMQClient(config("rabbitmq"));
        });
    }
}
