# PHP-FPM

imi v2.0 版本开始，支持运行在 PHP-FPM 环境中。

组件引入：`composer require imiphp/imi-fpm`

## 核心特性

| 特性 | 是否支持 |
|-|-
| Http | ✔ |
| Http2 |  |
| WebSocket |  |
| TCP |  |
| UDP |  |
| MQTT |  |

## 性能优化

在生产环境中，我们建议你每次部署都重新生成运行时缓存，以获得性能提升。

生成命令：`vendor/bin/imi-cli imi/buildRuntime --app-namespace "项目命名空间" --runtimeMode=Fpm`

## 命令

启动开发服务: `vendor/bin/imi-cli fpm/start`
