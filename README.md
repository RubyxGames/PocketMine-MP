# RubyxGames PocketMine-MP **1.0.0**

Игровое ядро Minecraft: Bedrock Edition на PHP — форк линии [Axolotl-PM](https://github.com/axolotl-pm/PocketMine-MP) с платформенными доработками Rubyx: мультипротокол (1.20–1.26.60), NetherNet, session record, MultiWorld/WorldEdit в core, DDUI (Bedrock 1.26+), ops/metrics.

| | |
|---|---|
| Версия | **1.0.0** (`VersionInfo::BASE_VERSION`) |
| Changelog | [`changelogs/1.0.0.md`](changelogs/1.0.0.md) |
| PHP | 8.1+ (64-bit), сборка с `ext-webrtc` для NetherNet |
| Лицензия | LGPL-3.0 (наследие PocketMine-MP) |

## Сборка и запуск

См. [BUILDING.md](BUILDING.md) и [CONTRIBUTING.md](CONTRIBUTING.md). Рантайм PHP, миры, плагины и `vendor/` **не** входят в этот репозиторий — `composer install` на машине сборки.

## Совместимость плагинов

Плагины для PMMP 5.x, как правило, совместимы, если не используются нестабильные `pocketmine\network\mcpe` / `pocketmine\data` без ограничения `mcpe-protocol` в `plugin.yml`. Новые возможности ядра объявляются через **capabilities** (`requires-capabilities`).

## Репозиторий

- Исходники: https://github.com/RubyxGames/PocketMine-MP  
- Живой сервер RubyxNet (деплой, Docker, плагины) — отдельный репозиторий, не публикуется здесь.
