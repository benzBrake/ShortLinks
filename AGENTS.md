# AGENTS.md

## Project Overview

`ShortLinks` 是一个 Typecho 插件，用于把外链转换为站内短链，并提供后台短链管理、跳转页模板、评论者链接转换、referer 白名单和自定义字段转换等功能。

如果仓库根目录存在 `AGENTS.local.md`，以 `AGENTS.local.md` 的说明为准。

## Tech Stack

- PHP
- Typecho 插件接口与后台面板机制
- 原生 HTML、CSS、jQuery
- Typecho 数据库抽象层，兼容 SQLite、MySQL、PostgreSQL

## Key Directories

- `Plugin.php`
  插件主入口，负责激活、禁用、配置项定义，以及正文、评论、自定义字段的链接转换逻辑。
- `Action.php`
  插件动作控制器，负责短链新增、编辑、删除、重定向和访问统计。
- `panel.php`
  Typecho 后台管理页面，提供短链列表、编辑和自定义路由入口。
- `templates`
  跳转页模板目录，模板中可使用 `{{url}}`、`{{delay}}` 以及部分站点字段占位符。
- `README.md`
  面向使用者的功能说明和安装说明。

## Common Commands

- `php -l Plugin.php`
  检查主插件文件语法。
- `php -l Action.php`
  检查动作控制器语法。
- `php -l panel.php`
  检查后台面板文件语法。
- 在 Typecho 插件目录启用或重载 `ShortLinks`
  用于验证路由注册、后台页面和数据库表创建流程。

## Development Conventions

- 保持 Typecho 旧命名空间和新命名空间写法的兼容性，不要随意删除兼容分支。
- 涉及数据库变更时，同时检查 SQLite、MySQL、PostgreSQL 三种适配分支是否一致。
- 不要随意更改插件类名、动作名、面板文件名或路由名；这些名称直接影响 Typecho 的插件加载和后台入口。
- 修改跳转逻辑时，优先保持现有 `referer` 校验、白名单和统计行为不变，除非任务明确要求调整。
- 修改 `templates` 下模板时，保留现有占位符替换约定，避免引入插件端未支持的新模板变量。
- 后台页面继续使用当前的 PHP 模板和 jQuery 风格，除非仓库已经引入新的前端基础设施。

## Testing and Validation

- 这个仓库当前没有现成的自动化测试；每次改动后至少运行相关 PHP 文件的语法检查。
- 涉及链接转换时，手动验证文章正文、评论者链接、自定义字段和禁用转换字段 `noshort` 的行为。
- 涉及跳转或安全逻辑时，手动验证短链访问统计、空 `referer`、白名单 `referer` 和非法来源跳转。
- 涉及后台修改时，手动验证新增、编辑、删除短链，以及自定义短链路由修改功能。

## Database Conventions

- 插件数据表为 `shortlinks`，由 `Plugin.php` 的激活和禁用流程管理。
- 非必要不要改动表结构；如果必须调整，需同时考虑已有安装的升级兼容性。
- 禁用插件时是否删表受插件配置 `isDrop` 控制，相关行为变更应非常谨慎。

## Working with Codex

- 先理解现有 Typecho 插件结构，再动代码，优先做最小范围修改。
- 主动指出潜在的兼容性问题、路由回归、数据库适配风险和缺失的手动验证项。
- 在合适的时候补充必要的错误处理、边界条件检查和小范围注释，但避免无关重构。
- 不要擅自变更对外路由格式、后台交互流程、模板占位符约定或卸载删表语义。
- 不要引入与当前仓库不匹配的构建工具、框架或测试基础设施，除非任务明确要求。
