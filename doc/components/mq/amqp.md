# AMQP

## 介绍

支持在 imi 框架中使用 支持 AMQP 协议的消息队列，如：RabbitMQ

支持消息发布和消费

Github: <https://github.com/imiphp/imi-amqp>

## Composer

本项目可以使用composer安装，遵循psr-4自动加载规则，在你的 `composer.json` 中加入下面的内容:

```json
{
    "require": {
        "imiphp/imi-amqp": "~2.0.0"
    }
}
```

然后执行 `composer update` 安装。

## 使用说明

可以参考 `example` 目录示例，包括完整的消息发布和消费功能。

在项目 `config/config.php` 中配置：

```php
[
    'components'    =>  [
        // 引入组件
        'AMQP'   =>  'Imi\AMQP',
    ],
]
```

连接池配置：

```php
[
    'pools'    =>    [
        'rabbit'    =>  [
            'sync'    =>    [
                'pool'    =>    [
                    'class'        =>    \Imi\AMQP\Pool\AMQPSyncPool::class,
                    'config'    =>    [
                        'maxResources'    =>    10,
                        'minResources'    =>    0,
                    ],
                ],
                'resource'    =>    [
                    'host'      => '127.0.0.1',
                    'port'      => 5672,
                    'user'      => 'guest',
                    'password'  => 'guest',
                ]
            ],
            'async'    =>    [
                'pool'    =>    [
                    'class'        =>    \Imi\AMQP\Pool\AMQPCoroutinePool::class,
                    'config'    =>    [
                        'maxResources'    =>    10,
                        'minResources'    =>    1,
                    ],
                ],
                'resource'    =>    [
                    'host'      => '127.0.0.1',
                    'port'      => 5672,
                    'user'      => 'guest',
                    'password'  => 'guest',
                ]
            ],
        ],
    ]
]
```

默认连接池：

```php
[
    'beans' =>  [
        'AMQP'  =>  [
            'defaultPoolName'   =>  'rabbit',
        ],
    ],
]
```

### 连接配置项

| 属性名称 | 说明 |
|-|-
| host | 主机 |
| port | 端口 |
| user | 用户名 |
| vhost | vhost，默认`/` |
| insist | insist |
| loginMethod | 默认`AMQPLAIN` |
| loginResponse | loginResponse |
| locale | 默认`en_US` |
| connectionTimeout | 连接超时 |
| readWriteTimeout | 读写超时 |
| keepalive | keepalive，默认`false` |
| heartbeat | 心跳时间，默认`0` |
| channelRpcTimeout | 频道 RPC 超时时间，默认`0.0` |
| sslProtocol | ssl 协议，默认`null` |

### 消息定义

继承 `Imi\AMQP\Message` 类，可在构造方法中对属性修改。

根据需要可以覆盖实现`setBodyData`、`getBodyData`方法，实现自定义的消息结构。

```php
<?php
namespace ImiApp\AMQP\Test2;

use Imi\AMQP\Message;

class TestMessage2 extends Message
{
    /**
     * 用户ID
     *
     * @var int
     */
    private $memberId;

    /**
     * 内容
     *
     * @var string
     */
    private $content;

    public function __construct()
    {
        parent::__construct();
        $this->routingKey = 'imi-2';
        $this->format = \Imi\Util\Format\Json::class;
    }

    /**
     * 设置主体数据
     *
     * @param mixed $data
     * @return self
     */
    public function setBodyData($data)
    {
        foreach($data as $k => $v)
        {
            $this->$k = $v;
        }
    }

    /**
     * 获取主体数据
     *
     * @return mixed
     */
    public function getBodyData()
    {
        return [
            'memberId'  =>  $this->memberId,
            'content'   =>  $this->content,
        ];
    }

    /**
     * Get 用户ID
     *
     * @return int
     */ 
    public function getMemberId()
    {
        return $this->memberId;
    }

    /**
     * Set 用户ID
     *
     * @param int $memberId  用户ID
     *
     * @return self
     */ 
    public function setMemberId(int $memberId)
    {
        $this->memberId = $memberId;

        return $this;
    }

    /**
     * Get 内容
     *
     * @return string
     */ 
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set 内容
     *
     * @param string $content  内容
     *
     * @return self
     */ 
    public function setContent(string $content)
    {
        $this->content = $content;

        return $this;
    }

}
```

**属性列表：**

名称 | 说明 |  默认值
-|-|-
bodyData | 消息主体内容，非字符串 | `null` |
properties | 属性 | `['content_type'  => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,]` |
routingKey | 路由键 | 空字符串 |
format | 如果设置了，发布的消息是编码后的`bodyData`，同理读取时也会解码。实现了`Imi\Util\Format\IFormat`的格式化类。支持`Json`、`PhpSerialize` | `null` |
mandatory | mandatory标志位 | `false` |
immediate | immediate标志位 | `false` |
ticket | ticket | `null` |

### 发布者定义

必选注解：`@Publisher`

可选注解：`@Queue`、`@Exchange`、`@Connection`

不配置 `@Connection` 注解，可以从连接池中获取连接

```php
<?php
namespace ImiApp\AMQP\Test;

use Imi\Bean\Annotation\Bean;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BasePublisher;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Annotation\Publisher;
use Imi\AMQP\Annotation\Connection;

/**
 * @Bean("TestPublisher")
 * @Connection(host="127.0.0.1", port=5672, user="guest", password="guest")
 * @Publisher(tag="tag-imi", queue="queue-imi-1", exchange="exchange-imi", routingKey="imi-1")
 * @Queue(name="queue-imi-1", routingKey="imi-1")
 * @Exchange(name="exchange-imi")
 */
class TestPublisher extends BasePublisher
{

}
```

### 发布消息

```php
// 实例化构建消息
$message = new \ImiApp\AMQP\Test2\TestMessage2;
$message->setMemberId(1);
$message->setContent('imi niubi');

// 发布消息
/** @var \ImiApp\AMQP\Test\TestPublisher $testPublisher */
$testPublisher = App::getBean('TestPublisher');
$testPublisher->publish($message);
```

### 消费者定义

必选注解：`@Consumer`

可选注解：`@Queue`、`@Exchange`、`@Connection`

不配置 `@Connection` 注解，可以从连接池中获取连接

```php
<?php
namespace ImiApp\AMQP\Test;

use Imi\Redis\Redis;
use Imi\Bean\Annotation\Bean;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BaseConsumer;
use Imi\AMQP\Contract\IMessage;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Enum\ConsumerResult;
use Imi\AMQP\Annotation\Connection;

/**
 * 启动一个新连接消费
 * 
 * @Bean("TestConsumer")
 * @Connection(host="127.0.0.1", port=5672, user="guest", password="guest")
 * @Consumer(tag="tag-imi", queue="queue-imi-1", message=\ImiApp\AMQP\Test\TestMessage::class)
 */
class TestConsumer extends BaseConsumer
{
    /**
     * 消费任务
     *
     * @param \ImiApp\AMQP\Test\TestMessage $message
     * @return void
     */
    protected function consume(IMessage $message)
    {
        var_dump(__CLASS__, $message->getBody(), get_class($message));
        Redis::set('imi-amqp:consume:1:' . $message->getMemberId(), $message->getBody());
        return ConsumerResult::ACK;
    }

}

```

### 消费消息

#### 随服务启动的消费进程

只会启动一个进程，适合量少的场景。适合IO密集型场景。

首先定义进程：

```php
<?php
namespace ImiApp\Process;

use Imi\Swoole\Process\BaseProcess;
use Imi\Aop\Annotation\Inject;
use Imi\Swoole\Process\Annotation\Process;

/**
 * @Process(name="TestProcess")
 */
class TestProcess extends BaseProcess
{
    /**
     * @Inject("TestConsumer")
     *
     * @var \ImiApp\AMQP\Test\TestConsumer
     */
    protected $testConsumer;

    /**
     * @Inject("TestConsumer2")
     *
     * @var \ImiApp\AMQP\Test2\TestConsumer2
     */
    protected $testConsumer2;

    public function run(\Swoole\Process $process)
    {
        // 启动消费者
        go(function(){
            do {
                $this->testConsumer->run();
            } while(true);
        });
        go(function(){
            do {
                $this->testConsumer2->run();
            } while(true);
        });
    }

}
```

然后在项目配置`@app.beans`中配置消费进程

```php
[
    'AutoRunProcessManager' =>  [
        'processes' =>  [
            'TestProcess'
        ],
    ],
]
```

#### 启动进程池消费

适合计算密集型场景、消费量非常多的场景。

进程池写法参考：<https://doc.imiphp.com/v2.0/components/process-pool/swoole.html>

启动消费者写法参考上面的即可。

### 注解说明

### @Publisher

发布者注解

| 属性名称 | 说明 |
|-|-
| queue | 队列名称 |
| exchange | 交换机名称 |
| routingKey | 路由键 |

### @Consumer

消费者注解

| 属性名称 | 说明 |
|-|-
| tag | 消费者标签 |
| queue | 队列名称 |
| exchange | 交换机名称 |
| routingKey | 路由键 |
| message | 消息类名，默认：`Imi\AMQP\Message` |
| mandatory | mandatory标志位 |
| immediate | immediate标志位 |
| ticket | ticket |

### @Queue

队列注解

| 属性名称 | 说明 |
|-|-
| name | 队列名称 |
| routingKey | 路由键 |
| passive | 被动模式，默认`false` |
| durable | 消息队列持久化，默认`true` |
| exclusive | 独占，默认`false` |
| autoDelete | 自动删除，默认`false` |
| nowait | 是否非阻塞，默认`false` |
| arguments | 参数 |
| ticket | ticket |

### @Exchange

交换机注解

| 属性名称 | 说明 |
|-|-
| name | 交换机名称 |
| type | 类型可选：`direct`、`fanout`、`topic`、`headers` |
| passive | 被动模式，默认`false` |
| durable | 消息队列持久化，默认`true` |
| autoDelete | 自动删除，默认`false` |
| internal | 设置是否为rabbitmq内部使用, `true`表示是内部使用, `false`表示不是内部使用 |
| nowait | 是否非阻塞，默认`false` |
| arguments | 参数 |
| ticket | ticket |

### @Connection

连接注解

| 属性名称 | 说明 |
|-|-
| poolName | 不为 `null` 时，无视其他属性，直接用该连接池配置。默认为`null`，如果`host`、`port`、`user`、`password`都未设置，则获取默认的连接池。 |
| host | 主机 |
| port | 端口 |
| user | 用户名 |
| vhost | vhost，默认`/` |
| insist | insist |
| loginMethod | 默认`AMQPLAIN` |
| loginResponse | loginResponse |
| locale | 默认`en_US` |
| connectionTimeout | 连接超时 |
| readWriteTimeout | 读写超时 |
| keepalive | keepalive，默认`false` |
| heartbeat | 心跳时间，默认`0` |
| channelRpcTimeout | 频道 RPC 超时时间，默认`0.0` |
| sslProtocol | ssl 协议，默认`null` |
