<p class="rubyx-pixel-hero-wrap align-center" align="center">
	<a href="https://rubyxgames.github.io/website/docs/">
		<img src=".github/readme/rubyx-pixel-hero.png" alt="RubyxGames" width="554" loading="eager" />
	</a>
	<br>
	<b>PocketMine-MP · rubyx.net</b>
</p>

<p align="center">
	<a href="https://github.com/RubyxGames/PocketMine-MP/actions/workflows/main.yml"><img src="https://github.com/RubyxGames/PocketMine-MP/actions/workflows/main.yml/badge.svg" alt="CI" /></a>
	<a href="https://github.com/RubyxGames/PocketMine-MP/releases/latest"><img alt="GitHub release (latest SemVer)" src="https://img.shields.io/github/v/release/RubyxGames/PocketMine-MP?label=release&sort=semver&color=red"></a>
	<a href="https://github.com/RubyxGames/PocketMine-MP/releases"><img alt="GitHub all releases" src="https://img.shields.io/github/downloads/RubyxGames/PocketMine-MP/total?label=downloads"></a>
	<br>
	<a href="https://github.com/RubyxGames/PocketMine-MP/releases">Скачать релиз</a> ·
	<a href="https://rubyxgames.github.io/website/docs/rubyx/">Документация Rubyx</a> ·
	<a href="changelogs/1.0.0.md">Changelog 1.0.0</a>
</p>

## Что это такое?

**RubyxGames PocketMine-MP** — настраиваемое серверное ПО для Minecraft: Bedrock Edition на PHP.  
Форк линии [Axolotl-PM](https://github.com/axolotl-pm/PocketMine-MP) с платформенными доработками Rubyx: мультипротокол (1.20–1.26.60), NetherNet, запись сессий, MultiWorld/WorldEdit в ядре, DDUI (Bedrock 1.26+), метрики и ops-инструменты.

Если вам нужен сервер с **своей логикой** (лобби, мини-игры, кастомные режимы), а не ванильный survival — это ваш стек.

- 🧩 **Мощный API плагинов** — capabilities, формы, DDUI, камера, movement-guard
- 🌐 **Несколько версий клиента** на одном процессе
- 🗺️ **Мультимиры и WorldEdit** встроены в ядро
- ⤴️ **Непрерывные обновления** — ориентир на актуальный Bedrock

| | |
|---|---|
| Версия | **1.0.0** (`VersionInfo::BASE_VERSION`) |
| PHP | 8.1+ (64-bit), `ext-webrtc` для NetherNet |
| Лицензия | LGPL-3.0 (наследие PocketMine-MP) |

## :x: Это НЕ ванильный survival-сервер

**Плохо подходит для «как в одиночной игре».** Нет многих ванильных систем: полной генерации, redstone, ИИ мобов и т.д.

Для ванильного multiplayer лучше [официальный Bedrock Dedicated Server](https://minecraft.net/download/server/bedrock).  
Остальное часто закрывается плагинами или доработками ядра — см. [Poggit](https://poggit.pmmp.io/plugins).

## Начало работы

- [Документация на сайте](https://rubyxgames.github.io/website/docs/)
- [Установка RubyxGames](https://rubyxgames.github.io/website/docs/rubyx/installation)
- [Эксплуатация](https://rubyxgames.github.io/website/docs/rubyx/) — NetherNet, миры, WorldEdit, метрики
- [Сборка из исходников](BUILDING.md) · [Участие](CONTRIBUTING.md)

Рантайм PHP, миры, плагины и `vendor/` **не** входят в репозиторий — `composer install` на машине сборки.

## Разработка плагинов

Плагины PMMP 5.x обычно совместимы, если не лезут в нестабильные `pocketmine\network\mcpe` без `mcpe-protocol` в `plugin.yml`. Новые фичи ядра — через **`requires-capabilities`**.

- [Plugin API Rubyx](https://rubyxgames.github.io/website/docs/rubyx/plugin-api)
- [DDUI](https://rubyxgames.github.io/website/docs/rubyx/plugin-api-ddui)
- [Документация плагинов PMMP](https://rubyxgames.github.io/website/docs/developers/plugin-docs-index)
- [DevTools](https://github.com/pmmp/DevTools/) · [ExamplePlugin](https://github.com/pmmp/ExamplePlugin/)

## Репозиторий и поддержка

- Исходники: https://github.com/RubyxGames/PocketMine-MP  
- Релизы: https://github.com/RubyxGames/PocketMine-MP/releases  
- Живой сервер RubyxNet (деплой, Docker, плагины) — **отдельный** приватный репозиторий, здесь не публикуется.

## Лицензия

Проект под **LGPL-3.0** — см. [LICENSE](/LICENSE).

RubyxGames и PocketMine-MP **не** связаны с Mojang. PocketMine-MP не одобрен Mojang.
