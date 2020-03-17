<?php
/**
 *
 */

namespace Zhimma\JustSendMessage\RabbitMQ;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQClient
{
    /**
     * @var object $instance 单例对象
     */
    private static $instance = null;

    /**
     * @var object $connection 队列连接对象
     */
    private $connection = null;

    /**
     * @var object $channel 队列通道对象
     */
    private $channel = null;

    /**
     * @var object $message 队列消息对象
     */
    private $message = null;


    public function __construct($config)
    {
        $this->connection = new AMQPStreamConnection($config['host'], $config['port'], $config['user'],
            $config['password'], $config['vhost']);
        $this->channel = $this->connection->channel();
        $this->message = new AMQPMessage('', [
            'content_type'  => 'json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
    }

    /**
     * 消息入队列
     *
     * @param string $strExchange 交换机名称
     * @param string $strQueue    队列名称
     * @param array  $aBody       消息内容
     * @param string $strType     路由类型
     *
     * @return array $aRes 标准输出
     */
    public function send($strExchange, $strQueue, $aBody, $strType = 'topic')
    {
        $result = ['status' => true, "message" => "ok"];
        try {
            // 参数校验
            $objValidator = \Illuminate\Support\Facades\Validator::make([
                'exchange' => $strExchange,
                'queue'    => $strQueue,
                'body'     => $aBody,
                'type'     => $strType,
            ], [
                'exchange' => 'required|string',
                'queue'    => 'required|string',
                'body'     => 'required|array',
                'type'     => 'required|string',
            ]);

            if ($objValidator->fails()) {
                throw new \Exception($objValidator->errors()->first());
            }
            // 声明交换机
            // 默认精准推送，不检测同名交换机，持久化，不自动删除交换机
            $this->channel->exchange_declare($strExchange, $strType, false, true, false);
            // 声明队列
            // 不检测同名队列，持久化，不允许其他队列访问，不自动删除队列
            $this->channel->queue_declare($strQueue, false, true, false, false);
            // 绑定队列和交换机，用队列名作routingKey
            $this->channel->queue_bind($strQueue, $strExchange, $strQueue);
            // 设备消息体
            $this->message->setBody(json_encode($aBody));
            // 推送信息
            $this->channel->basic_publish($this->message, $strExchange, $strQueue);
        } catch (\Exception $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();

            return $result;
        }

        return $result;
    }

    /**
     * 从队列读取消息
     *
     * @param string  $strQueue     队列名称
     * @param boolean $bForceDelete 是否取后即删
     *
     * @return array $aRes 标准输出
     * $aRes['data'] => message object
     */
    public function read($strQueue, $bForceDelete = false)
    {
        $result = ['status' => true, "message" => "ok"];

        try {

            // 参数校验
            $objValidator = \Illuminate\Support\Facades\Validator::make([
                'queue' => $strQueue,
            ], [
                'queue' => 'required|string',
            ]);
            if ($objValidator->fails()) {
                throw new \Exception($objValidator->errors()->first());
            }
            // 声明队列
            // 不检测同名队列，持久化，不允许其他队列访问，不自动删除队列
            $this->channel->queue_declare($strQueue, false, true, false, false);
            $objMessage = $this->channel->basic_get($strQueue);
            if ($objMessage && $bForceDelete) {
                // 回复确认信息
                $this->channel->basic_ack($objMessage->delivery_info['delivery_tag']);
            }
        } catch (\Exception $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();

            return $result;
        }

        return $result;
    }

    /**
     * 回复响应消息
     *
     * @param int $nTag 消息传递标签
     *
     * @return array $aRes  标准输出
     */
    public function ack($nTag)
    {
        $result = ['status' => true, "message" => "ok"];

        try {
            $this->channel->basic_ack($nTag);
        } catch (\Exception $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();

            return $result;
        }

        return $result;
    }
}
